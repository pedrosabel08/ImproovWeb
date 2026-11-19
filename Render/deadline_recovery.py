from __future__ import annotations

import json
from contextlib import nullcontext
from pathlib import Path
from tempfile import TemporaryDirectory

from deadline_client import parse_output
from deadline_domain import valid_job_id
from deadline_farm import WORKER_NAME, collect_workers


class Rejected(ValueError):
    pass


def eligible(state, workers):
    def matches(name, rule):
        return (name in {n.lower() for n in rule["listed"]}) == rule["allow"]

    result, low_ram = [], []
    pools = {state["pool"].lower()}
    if state["secondary"].lower() not in ("none", ""):
        pools.add(state["secondary"].lower())
    for worker in workers:
        name = worker["name"].lower()
        if worker["state"] not in ("idle", "rendering"):
            continue
        if (
            state["pool"].lower() not in ("none", "")
            and state["secondary"].lower() != "none"
            and not pools.intersection(p.lower() for p in worker["pools"])
        ):
            continue
        if state["group"].lower() not in ("none", "") and state[
            "group"
        ].lower() not in {g.lower() for g in worker["groups"]}:
            continue
        if not matches(name, state) or any(
            name not in {n.lower() for n in limit["excluded"]}
            and (not matches(name, limit) or not limit.get("available", True))
            for limit in state["limits"]
        ):
            continue
        if (
            state["required_ram"]
            and worker["memory"]
            and worker["memory"] < state["required_ram"]
        ):
            low_ram.append(worker["name"])
        elif not state["required_ram"] or worker["memory"]:
            result.append(worker["name"])
    return result, low_ram


def failed(state):
    return {t["id"] for t in state["tasks"] if t["status"] == "failed"}


class Recovery:
    def __init__(self, database, client, logger, discovery_lock=None):
        self.db, self.client, self.logger = database, client, logger
        self.discovery_lock = discovery_lock

    def run(self, stop):
        unavailable = False
        try:
            while not stop.is_set():
                try:
                    self.tick()
                    unavailable = False
                except Exception as exc:
                    if not unavailable and not (exc.args and exc.args[0] == 1146):
                        self.logger.exception("Deadline recovery queue unavailable")
                    unavailable = True
                stop.wait(2)
        finally:
            self.db.close()

    def bridge(self, job, mode, **params):
        with TemporaryDirectory(prefix="flow-recovery-") as directory:
            source, target = (
                Path(directory) / "input.json",
                Path(directory) / "output.json",
            )
            source.write_text(json.dumps({"mode": mode, **params}), encoding="utf-8")
            result = self.client.run(
                [
                    "-ExecuteScriptNoGui",
                    str(Path(__file__).with_name("deadline_recovery_sdk.py")),
                    job,
                    str(source),
                    str(target),
                ]
            )
            try:
                response = json.loads(target.read_text(encoding="utf-8-sig"))
                if result.success and response["job_id"] == job:
                    return response["data"], result
            except (OSError, ValueError, KeyError):
                pass
            raise RuntimeError(
                result.output[-4000:] or "Resposta do Deadline indisponível."
            )

    def inspect(self, job, reports=False):
        state, _ = self.bridge(job, "inspect", reports=reports)
        quality, workers = collect_workers(self.client)
        worker_fields = (
            "name",
            "state",
            "pools",
            "groups",
            "memory",
            "job_id",
            "unavailable",
        )
        state.update(
            workers=[{k: w[k] for k in worker_fields if k in w} for w in workers],
            workers_complete=quality == "ok",
        )
        state["eligible"], state["low_ram"] = eligible(
            state, [w for w in workers if not w.get("unavailable")]
        )
        return state

    def save(self, request, result, status="running", before=None):
        with self.db.transaction() as cur:
            cur.execute(
                """UPDATE deadline_recovery_requests SET status=%s, result_json=%s,
                before_json=COALESCE(%s,before_json),
                finished_at=IF(%s='running',NULL,UTC_TIMESTAMP()) WHERE id=%s AND status='running'""",
                (
                    status,
                    json.dumps(result),
                    json.dumps(before) if before is not None else None,
                    status,
                    request,
                ),
            )

    @staticmethod
    def linked(cur, row):
        cur.execute(
            """SELECT 1 FROM render_alta r WHERE r.idrender_alta=%s AND r.excluido_em IS NULL
            AND (LOWER(r.deadline_job_id)=%s OR EXISTS (
                SELECT 1 FROM render_tentativas t WHERE t.render_id=r.idrender_alta
                AND LOWER(t.deadline_job_id)=%s))""",
            (row["render_id"], row["job_id"], row["job_id"]),
        )
        return cur.fetchone() is not None

    def tick(self):
        with self.db.transaction() as cur:
            cur.execute(
                """SELECT *, TIMESTAMPDIFF(SECOND,created_at,UTC_TIMESTAMP()) AS age
                FROM deadline_recovery_requests WHERE status='queued'
                ORDER BY id LIMIT 1 FOR UPDATE"""
            )
            row = cur.fetchone()
            if not row:
                return
            cur.execute(
                """UPDATE deadline_recovery_requests SET status='running',started_at=UTC_TIMESTAMP()
                WHERE id=%s""",
                (row["id"],),
            )
            linked = self.linked(cur, row)
        request, job, action = row["id"], row["job_id"], row["action"]
        result = {"commands": []}
        try:
            if row["age"] > 120:
                raise Rejected(
                    "A solicitação expirou antes de o executor atendê-la. Atualize a consulta."
                )
            if not linked or not valid_job_id(job):
                raise Rejected("O job não pertence mais ao render selecionado.")
            if action == "inspect":
                result["state"] = self.inspect(job, reports=True)
            else:
                self.change(row, result)
            self.save(request, result, "succeeded")
        except Rejected as exc:
            result["message"] = str(exc)
            self.save(request, result, "rejected")
        except Exception as exc:
            result["message"] = (
                "O Deadline não confirmou toda a operação. Consulte o estado atual antes de tentar novamente."
                if result["commands"]
                else "Não foi possível consultar o Deadline."
            )
            result["error"] = str(exc)[-4000:]
            if action == "resubmit" and result["commands"]:
                new_job = result.get("new_job_id")
                result["message"] = (
                    f"Reenvio incompleto. Confira o novo job {new_job} no Deadline antes de continuar."
                    if new_job
                    else "O Deadline não confirmou se criou o novo job. Confira o Monitor antes de reenviar."
                )
            self.save(request, result, "unverified" if result["commands"] else "failed")

    def change(self, row, result):
        request, job, action = row["id"], row["job_id"], row["action"]
        params = json.loads(row["params_json"])
        with self.db.transaction() as cur:
            cur.execute(
                """SELECT result_json FROM deadline_recovery_requests WHERE id=%s AND
                user_id=%s AND job_id=%s AND render_id=%s AND action='inspect' AND status='succeeded'
                AND finished_at >= UTC_TIMESTAMP() - INTERVAL 5 MINUTE
                AND TIMESTAMPDIFF(SECOND,%s,UTC_TIMESTAMP()) <= 120""",
                (
                    row["source_id"],
                    row["user_id"],
                    job,
                    row["render_id"],
                    row["created_at"],
                ),
            )
            source = cur.fetchone()
        if not source:
            raise Rejected(
                "A consulta expirou. Atualize o diagnóstico e confirme novamente."
            )
        old = json.loads(source["result_json"])["state"]
        before = (
            self.inspect(job)
            if action in ("deny", "route")
            else self.bridge(job, "state")[0]
        )
        result["observed"] = None
        self.save(request, result, before=before)
        if action == "resubmit":
            if not before.get("resubmit") or before["resubmit"] != old.get("resubmit"):
                raise Rejected(
                    "O job precisa estar concluído e disponível para reenvio. Atualize a consulta."
                )
            self.resubmit(row, params, result)
            return

        def run(*args, routing=None):
            with self.db.transaction() as cur:
                still_linked = self.linked(cur, row)
                cur.execute(
                    """SELECT 1 FROM deadline_comandos WHERE deadline_job_id=%s
                    AND tipo='DELETE_JOB' AND status IN ('PENDENTE','PROCESSANDO') LIMIT 1""",
                    (job,),
                )
                deletion = cur.fetchone()
            if not still_linked or deletion:
                error = "O vínculo do job mudou ou existe uma exclusão pendente."
                if result["commands"]:
                    raise RuntimeError(error)
                raise Rejected(error)
            entry = {
                "operation": args[0] if args else "RepositoryUtils.SaveJob",
                "arguments": list(args[1:]) if args else routing,
                "returned": False,
            }
            result["commands"].append(entry)
            self.save(request, result)
            if routing is not None:
                response, response_command = self.bridge(job, "route", **routing)
                if response != {"saved": True}:
                    raise RuntimeError("A alteração de Pool/Group não foi confirmada.")
            else:
                response_command = self.client.run([args[0], job, *args[1:]])
            entry.update(
                returned=True,
                output=response_command.output[-4000:],
                code=response_command.returncode,
                timed_out=response_command.timed_out,
            )
            self.save(request, result)
            if not response_command.success:
                raise RuntimeError(response_command.output[-4000:])

        if action in ("suspend", "resume"):
            expected = "active" if action == "suspend" else "suspended"
            if before["status"] != expected or old["status"] != expected:
                raise Rejected("O estado atual não permite esta ação.")
            run("-SuspendJobNonRenderingTasks" if action == "suspend" else "-ResumeJob")
            after = self.bridge(job, "state")[0]
            verified = after["status"] == (
                "suspended" if action == "suspend" else "active"
            )
        elif action == "retry":
            ids = params["tasks"]
            if (
                not ids
                or not all(type(i) is int and i >= 0 for i in ids)
                or not set(ids) <= failed(old) & failed(before)
            ):
                raise Rejected(
                    "A seleção contém tarefas que já não estão em falha. Atualize a lista."
                )
            if (
                before["status"] not in ("active", "failed")
                or before["status"] != old["status"]
            ):
                raise Rejected(
                    "O estado atual do job não permite retomar tarefas com erro."
                )
            ordered = sorted(set(ids))
            # Keep explicit task IDs even for "all", and bound the Windows command line.
            for start in range(0, len(ordered), 500):
                run(
                    "-ResumeFailedJobTasks",
                    ",".join(map(str, ordered[start : start + 500])),
                )
            after = self.bridge(job, "state")[0]
            states = {t["id"]: t["status"] for t in after["tasks"]}
            verified = all(
                states.get(i) in ("queued", "rendering", "completed") for i in ids
            )
        elif action in ("deny", "route"):
            keys = (
                "status",
                "listed",
                "allow",
                "maximum",
                "release",
                "pool",
                "secondary",
                "group",
                "required_ram",
            )
            if any(before[k] != old[k] for k in keys) or before["status"] not in (
                "active",
                "suspended",
                "failed",
                "pending",
            ):
                raise Rejected(
                    "As propriedades do job mudaram. Atualize o diagnóstico."
                )
            proposed = dict(before)
            if action == "deny":
                name = params["worker"]
                blocked = params["blocked"]
                listed = {n.lower(): n for n in before["listed"]}
                known = {w["name"].lower(): w["name"] for w in before["workers"]}
                key = name.lower()
                if (
                    before["allow"]
                    or not WORKER_NAME.fullmatch(name)
                    or type(blocked) is not bool
                ):
                    raise Rejected("Este job não pode ter a Deny List editada.")
                if (key in listed) == blocked or (blocked and key not in known):
                    raise Rejected(
                        "O Worker já está nesse estado ou não existe na fazenda."
                    )
                canonical = known[key] if blocked else listed[key]
                proposed["listed"] = sorted(
                    (list(listed.values()) + [canonical])
                    if blocked
                    else [n for n in listed.values() if n.lower() != key]
                )
            else:
                for key in ("pool", "group"):
                    value = params[key]
                    if value != before[key] and value not in before[key + "s"]:
                        raise Rejected("Pool ou Group não existe no Deadline.")
                    proposed[key] = value
                if all(proposed[k] == before[k] for k in ("pool", "group")):
                    raise Rejected(
                        "Nenhuma alteração de Pool ou Group foi selecionada."
                    )
            allowed, low_ram = eligible(
                proposed, [w for w in before["workers"] if not w.get("unavailable")]
            )
            if not before["workers_complete"] or not allowed:
                raise Rejected(
                    "A configuração não tem Worker elegível confirmado."
                    + (" Há Workers abaixo da RAM mínima declarada." if low_ram else "")
                )
            result["incompatible_ram"] = low_ram
            if action == "deny":
                run(
                    (
                        "-AddSlavesToJobMachineLimitList"
                        if blocked
                        else "-RemoveSlavesFromJobMachineLimitList"
                    ),
                    canonical,
                )
            else:
                run(
                    routing={
                        "before": {k: before[k] for k in keys},
                        "pool": proposed["pool"],
                        "group": proposed["group"],
                    }
                )
            after = self.bridge(job, "state")[0]
            verified = all(after[k] == proposed[k] for k in keys if k != "status")
        else:
            raise Rejected("Operação de recuperação desconhecida.")

        result["observed"] = after
        if not verified:
            raise RuntimeError(
                "The observed Deadline state does not match the requested change"
            )

    @staticmethod
    def resubmit_origin(cur, row):
        cur.execute(
            """SELECT r.imagem_id, r.status_id, r.status, r.previa_jpg, r.deadline_job_id,
            i.status_id AS current_stage
            FROM render_alta r JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=r.imagem_id
            WHERE r.idrender_alta=%s AND r.excluido_em IS NULL FOR UPDATE""",
            (row["render_id"],),
        )
        render = cur.fetchone()
        if (
            not render
            or render["status_id"] != render["current_stage"]
            or render["status"]
            not in (
                "Em aprovação",
                "Aprovado",
                "Finalizado",
            )
        ):
            raise Rejected(
                "A imagem precisa estar na etapa atual com o render finalizado."
            )
        cur.execute(
            """SELECT id, numero_tentativa, deadline_job_id, ativa FROM render_tentativas
            WHERE render_id=%s ORDER BY numero_tentativa DESC LIMIT 1 FOR UPDATE""",
            (row["render_id"],),
        )
        attempt = cur.fetchone()
        source = attempt["deadline_job_id"] if attempt else render["deadline_job_id"]
        if str(source or "").lower() != row["job_id"]:
            raise Rejected(
                "Somente o job da tentativa mais recente pode ser reenviado."
            )
        cur.execute(
            """SELECT 1 FROM deadline_comandos WHERE deadline_job_id=%s
            AND tipo='DELETE_JOB' AND status='PROCESSANDO' LIMIT 1""",
            (row["job_id"],),
        )
        if cur.fetchone():
            raise Rejected("O job original está sendo excluído no Deadline.")
        return render, attempt

    def resubmit(self, row, params, result):
        request, source = row["id"], row["job_id"]
        name = params["name"].strip()
        frames = "".join(params["frames"].split())
        args = ["-ResubmitJob", source, frames, str(params["chunk"]), "True"]
        # Discovery must not claim the copy by name before it has an explicit link.
        with self.discovery_lock if self.discovery_lock is not None else nullcontext():
            with self.db.transaction() as cur:
                self.resubmit_origin(cur, row)
                cur.execute(
                    """SELECT 1 FROM deadline_recovery_requests WHERE render_id=%s AND job_id=%s
                    AND id<>%s AND action='resubmit' AND status='unverified' LIMIT 1""",
                    (row["render_id"], source, request),
                )
                if cur.fetchone():
                    raise Rejected(
                        "Há um reenvio anterior sem confirmação. Confira o histórico antes de criar outra cópia."
                    )
            command = self.resubmit_command(request, result, args)
            receipt = parse_output(command.output)
            new_job = receipt.get("JobID")
            if (
                not isinstance(new_job, str)
                or not valid_job_id(new_job)
                or new_job.lower() == source
            ):
                raise RuntimeError("Deadline did not return a distinct job ID")
            new_job = new_job.lower()
            result["new_job_id"] = new_job
            self.save(request, result)
            if not command.success or receipt.get("Result") != "Success":
                raise RuntimeError("Deadline did not confirm the resubmission")

            entry = {
                "operation": "prepare_resubmit",
                "job_id": new_job,
                "returned": False,
            }
            result["commands"].append(entry)
            self.save(request, result)
            _, prepared = self.bridge(
                new_job, "prepare_resubmit", name=name, request_id=request
            )
            entry.update(
                returned=True, output=prepared.output[-4000:], code=prepared.returncode
            )
            self.save(request, result)
            with self.db.transaction() as cur:
                try:
                    render, previous = self.resubmit_origin(cur, row)
                except Rejected as exc:
                    raise RuntimeError(str(exc)) from exc
                attempt = self.bind_resubmitted_job(
                    cur, row, render, previous, new_job, name
                )
            result["attempt"] = attempt
            self.save(request, result)

        if not params["suspended"]:
            resumed = self.resubmit_command(request, result, ["-ResumeJob", new_job])
            if not resumed.success:
                raise RuntimeError("Deadline did not confirm resume")
        after = self.bridge(new_job, "state")[0]
        result["observed"] = after
        expected = (
            {"suspended"} if params["suspended"] else {"active", "pending", "completed"}
        )
        if after["status"] not in expected:
            raise RuntimeError("The new job did not reach the requested state")
        suffix = " O job foi enviado suspenso." if params["suspended"] else ""
        result["message"] = f"Job reenviado na tentativa {attempt}.{suffix}"

    def resubmit_command(self, request, result, args):
        entry = {"operation": args[0], "arguments": args[1:], "returned": False}
        result["commands"].append(entry)
        self.save(request, result)
        command = self.client.run(args)
        entry.update(
            returned=True,
            output=command.output[-4000:],
            code=command.returncode,
            timed_out=command.timed_out,
        )
        self.save(request, result)
        return command

    @staticmethod
    def bind_resubmitted_job(cur, row, render, previous, new_job, name):
        render_id = row["render_id"]
        attempt = int(previous["numero_tentativa"]) + 1 if previous else 1
        if previous and previous["ativa"]:
            cur.execute(
                """UPDATE render_tentativas SET ativa=0, encerrado_em=COALESCE(encerrado_em,NOW())
                WHERE id=%s""",
                (previous["id"],),
            )
        cur.execute(
            """INSERT INTO render_tentativas
            (render_id,imagem_id,status_id,numero_tentativa,deadline_job_id,deadline_job_name,status,ativa,vinculado_em)
            VALUES (%s,%s,%s,%s,%s,%s,'VINCULADA',1,NOW())""",
            (
                render_id,
                render["imagem_id"],
                render["status_id"],
                attempt,
                new_job,
                name,
            ),
        )
        attempt_id = cur.lastrowid
        cur.execute(
            "SELECT filename FROM render_previews WHERE render_id=%s", (render_id,)
        )
        old_previews = [preview["filename"] for preview in cur.fetchall()]
        if old_previews:
            cur.execute("DELETE FROM render_previews WHERE render_id=%s", (render_id,))
        cur.execute(
            """UPDATE render_alta SET deadline_job_id=%s,status='Não iniciado',data=NOW(),
            previa_jpg=NULL,has_error=0,errors=NULL WHERE idrender_alta=%s""",
            (new_job, render_id),
        )
        cur.execute(
            "UPDATE pos_producao SET status_pos=1 WHERE render_id=%s", (render_id,)
        )
        event = {
            "origem": "RESUBMIT_JOB",
            "solicitacao_id": row["id"],
            "job_original": row["job_id"],
            "previa_anterior": render["previa_jpg"],
            "previews_anteriores": old_previews,
        }
        cur.execute(
            """INSERT INTO render_tentativa_eventos (tentativa_id,tipo,chave,dados_json)
            VALUES (%s,'VINCULO',%s,%s)""",
            (attempt_id, new_job, json.dumps(event)),
        )
        return attempt
