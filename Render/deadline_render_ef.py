"""Manual EF requests, consumed by the existing continuous Deadline worker.

The source scene is never opened or saved. A new, initially suspended Job owns
its own auxiliary copy; only that new Job receives the EF output and resolution.
"""

from __future__ import annotations

import hashlib
import json
import logging
import ntpath
import os
import re
import subprocess
import tempfile
import uuid
from pathlib import Path

from deadline_client import parse_output
from deadline_domain import state_from_deadline, valid_job_id


def ensure_smb_destination_ready(destination: str, logger=None) -> None:
    """Create the UNC destination and verify write, rename, and delete access."""
    log = logger or logging.getLogger(__name__)
    probe_id = uuid.uuid4().hex
    probe_path = os.path.join(destination, f".flow-ef-write-{probe_id}.tmp")
    renamed_path = os.path.join(destination, f".flow-ef-write-{probe_id}.renamed")
    operation = "criar a pasta"
    failed_with: OSError | None = None

    log.info("EF SMB directory creation started", extra={"destination": destination})
    try:
        # exist_ok handles a concurrent creator without a check-then-create race.
        os.makedirs(destination, exist_ok=True)
        log.info(
            "EF SMB directory created or found",
            extra={"destination": destination},
        )

        operation = "criar e gravar o arquivo temporário de validação"
        with open(probe_path, "xb") as probe:
            probe.write(b"Flow EF SMB write check\n")

        operation = "renomear o arquivo temporário de validação"
        os.rename(probe_path, renamed_path)

        operation = "excluir o arquivo temporário de validação"
        os.unlink(renamed_path)
        log.info(
            "EF SMB write validation completed",
            extra={"destination": destination},
        )
    except OSError as exc:
        failed_with = exc
        log.exception(
            "EF SMB destination operation failed",
            extra={"destination": destination, "operation": operation},
        )
        legacy_hint = (
            " A pasta EF existente pode ter uma ACL legada criada via SFTP."
            if isinstance(exc, PermissionError)
            else ""
        )
        raise RuntimeError(
            f"Falha ao {operation} no destino SMB '{destination}'.{legacy_hint} "
            "O Job não será submetido."
        ) from exc
    finally:
        # If creation, writing, or rename failed, remove whichever UUID probe
        # path exists. Never mask the original failure with a cleanup failure.
        for path in (probe_path, renamed_path):
            try:
                os.unlink(path)
            except FileNotFoundError:
                pass
            except OSError:
                log.exception(
                    "EF SMB validation file cleanup failed",
                    extra={"destination": destination, "probe_path": path},
                )
                if failed_with is None:
                    raise


def plugin_info(data: dict) -> dict[str, str]:
    raw = data.get("PluginInfoDictionary", "")
    if isinstance(raw, list):
        raw = ",".join(raw)
    return dict(
        part.split("=", 1)
        for part in re.split(r",(?=[A-Za-z_][A-Za-z0-9_]*=)", str(raw))
        if "=" in part
    )


def ef_resolution(width: int, height: int) -> tuple[int, int]:
    if width <= 0 or height <= 0:
        raise RuntimeError("A resolução original é inválida.")
    edge = max(width, height)
    # Nearest integer pixel, with .5 rounded up, for both orientations.
    return max(1, (width * 5000 + edge // 2) // edge), max(
        1, (height * 5000 + edge // 2) // edge
    )


def snapshot(info: dict) -> str:
    return hashlib.sha256(
        json.dumps(info, sort_keys=True, ensure_ascii=False).encode("utf-8")
    ).hexdigest()


def read_info(path: Path) -> dict[str, str]:
    raw = path.read_bytes()
    if raw.startswith((b"\xff\xfe", b"\xfe\xff")):
        text = raw.decode("utf-16")
    else:
        try:
            text = raw.decode("utf-8-sig")
        except UnicodeDecodeError:
            text = raw.decode("cp1252")
    return dict(line.split("=", 1) for line in text.splitlines() if "=" in line)


def write_info(path: Path, values: dict) -> None:
    path.write_text(
        "".join(f"{key}={value}\n" for key, value in values.items()), encoding="utf-8"
    )


class RenderEF:
    def __init__(self, worker):
        self.worker = worker
        self.db = worker.database
        self.client = worker.client
        self.enabled = None

    def command(self, args: list[str]) -> str:
        result = self.client.run(args)
        if not result.success or re.search(
            r"^(?:Error:|An Unexpected Error)", result.output, re.M
        ):
            self.worker.logger.error(
                "EF Deadline command failed",
                extra={"command": args[0], "result": result.output[-1500:]},
            )
            raise RuntimeError(
                f"O Deadline não concluiu a operação {args[0]}. Consulte o log do worker."
            )
        return result.output

    def update(
        self, request: dict, stage: str, message: str, status="PROCESSANDO", **fields
    ):
        allowed = {"plano_json", "novo_job_id", "render_ef_id"}
        if set(fields) - allowed:
            raise ValueError("Unsupported EF request field")
        sets = ["etapa = %s", "mensagem = %s", "status = %s"] + [
            f"{k} = %s" for k in fields
        ]
        with self.db.transaction() as cursor:
            cursor.execute(
                f"UPDATE render_ef_solicitacoes SET {', '.join(sets)} WHERE id = %s AND worker_id = %s",
                (
                    stage,
                    message,
                    status,
                    *fields.values(),
                    request["id"],
                    self.worker.settings.worker_id,
                ),
            )
            if cursor.rowcount != 1:
                raise RuntimeError(
                    "A solicitação perdeu a reserva do worker. Nenhum novo envio será executado."
                )
        request.update(etapa=stage, mensagem=message, status=status, **fields)
        self.worker.business.publish_render_update(
            "render.ef_progress",
            {
                "imagem_id": request["imagem_id"],
                "request_id": request["id"],
                "etapa": stage,
            },
        )

    def context(self, request: dict) -> dict:
        with self.db.transaction() as cursor:
            cursor.execute(
                """SELECT r.job_folder, r.imagem_id, r.excluido_em,
                i.imagem_nome, i.status_id, s.nome_status
                FROM render_alta r JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = r.imagem_id
                JOIN status_imagem s ON s.idstatus = i.status_id WHERE r.idrender_alta = %s""",
                (request["render_origem_id"],),
            )
            context = cursor.fetchone()
            if (
                not context
                or int(context["imagem_id"]) != int(request["imagem_id"])
                or context["excluido_em"]
            ):
                raise RuntimeError("O render de origem mudou ou foi arquivado.")
            if str(context["nome_status"]).strip().upper() != "EF":
                raise RuntimeError(
                    "A imagem não está mais na etapa EF. O envio foi interrompido."
                )
            cursor.execute(
                """SELECT id FROM deadline_comandos WHERE deadline_job_id = %s
                AND tipo = 'DELETE_JOB' AND status IN ('PENDENTE', 'PROCESSANDO') LIMIT 1""",
                (request["origem_job_id"],),
            )
            if cursor.fetchone():
                raise RuntimeError(
                    "A exclusão anterior desse Job ainda está em andamento. Aguarde sua conclusão e tente novamente."
                )
            cursor.execute(
                """SELECT r.idrender_alta, rt.id, rt.deadline_job_id, rt.status AS tentativa_status
                FROM render_alta r LEFT JOIN render_tentativas rt ON rt.render_id = r.idrender_alta AND rt.ativa = 1
                WHERE r.imagem_id = %s AND r.status_id = %s""",
                (request["imagem_id"], context["status_id"]),
            )
            existing = cursor.fetchone()
            if (
                existing
                and existing["deadline_job_id"]
                and existing["deadline_job_id"] != request.get("novo_job_id")
            ):
                raise RuntimeError(
                    "Já existe um render EF com Job vinculado para esta imagem. Nenhum Job duplicado foi criado."
                )
            if (
                existing
                and not existing["deadline_job_id"]
                and (
                    not existing["id"]
                    or existing["tentativa_status"] != "AGUARDANDO_JOB"
                )
            ):
                raise RuntimeError(
                    "Já existe um render EF que não está aguardando um novo Job. Verifique esse registro antes de continuar."
                )
        return context

    def storage(self, context: dict, source: str, action: str) -> dict:
        php = os.getenv(
            "DEADLINE_PHP", r"C:\xampp\php\php.exe" if os.name == "nt" else "php"
        )
        result = subprocess.run(
            [php, str(Path(__file__).with_name("render_ef_storage.php"))],
            input=json.dumps(
                {
                    "source": source,
                    "image_name": context["imagem_nome"],
                    "action": action,
                }
            ),
            capture_output=True,
            text=True,
            encoding="utf-8",
            timeout=90,
            check=False,
        )
        try:
            data = json.loads(result.stdout)
        except (ValueError, TypeError):
            raise RuntimeError(
                "O armazenamento não retornou uma resposta válida. Verifique o PHP e o SFTP do worker."
            )
        if not data.get("ok") or result.returncode:
            raise RuntimeError(
                data.get("message") or "Não foi possível acessar a pasta dos renders."
            )
        return data

    def source(self, request: dict) -> tuple[dict, dict, list[str]]:
        data = parse_output(
            self.command(["-GetDeletedJob", request["origem_job_id"], "True"])
        )
        if (
            not data
            or (data.get("ID") or data.get("JobId")) != request["origem_job_id"]
        ):
            raise RuntimeError(
                "O Job original não foi encontrado entre os Jobs deletados do Deadline. Nada foi recuperado ou submetido."
            )
        if str(data.get("PluginName") or data.get("JobPlugin", "")).lower() != "3dsmax":
            raise RuntimeError("O Job de origem não usa o plugin 3ds Max.")
        if str(data.get("IsBeingPurged", "")).lower() == "true":
            raise RuntimeError(
                "O Job original está sendo removido definitivamente pelo Deadline. O envio foi interrompido."
            )
        with tempfile.TemporaryDirectory(prefix="flow_ef_source_") as temp:
            job_path, plugin_path, meta_path = (
                Path(temp) / name for name in ("job.job", "plugin.job", "metadata.json")
            )
            self.command(
                [
                    "-ExecuteScriptNoGui",
                    str(Path(__file__).with_name("deadline_ef_export.py")),
                    request["origem_job_id"],
                    str(job_path),
                    str(plugin_path),
                    str(meta_path),
                ]
            )
            data["_job_info"] = read_info(job_path)
            info = read_info(plugin_path)
            metadata = json.loads(meta_path.read_text(encoding="utf-8"))
            folder = Path(metadata["auxiliary_path"])
            names = metadata["auxiliary_names"]
        keys = {k.lower(): k for k in info}
        if "renderwidth" not in keys or "renderheight" not in keys:
            raise RuntimeError(
                "As Plugin Info Keys de resolução não foram encontradas neste Job. Nenhuma chave será presumida ou criada."
            )
        # Only Jobs whose primary scene was actually submitted as an auxiliary.
        if info.get("SceneFile", "").strip():
            raise RuntimeError(
                "Este Job referencia uma cena externa em SceneFile. O envio foi bloqueado para garantir o uso do .max auxiliar original."
            )
        scene = str(data.get("PluginDataFileName", ""))
        if not scene.lower().endswith(".max") or ntpath.basename(scene) != scene:
            raise RuntimeError(
                "O arquivo .max auxiliar principal não foi identificado."
            )
        if scene not in names:
            raise RuntimeError(
                "O .max principal não está registrado entre os arquivos auxiliares."
            )
        files = []
        for name in names:
            if not name or ntpath.basename(name) != name:
                raise RuntimeError(
                    "A lista de arquivos auxiliares contém um caminho inválido."
                )
            path = folder / name
            if not path.is_file() or not os.access(path, os.R_OK):
                raise RuntimeError(
                    f"O arquivo auxiliar {name} não está disponível. O envio foi interrompido."
                )
            files.append(str(path))
        if (folder / scene).stat().st_size != int(data.get("PluginDataFileSize", 0)):
            raise RuntimeError(
                "O tamanho do .max não corresponde ao registrado no Job original."
            )
        return data, info, files

    def inspect(self, request: dict):
        self.update(
            request, "INSPECAO", "Buscando o Job deletado e conferindo a cena original."
        )
        context = self.context(request)
        data, info, files = self.source(request)
        keys = {k.lower(): k for k in info}
        wkey, hkey = keys["renderwidth"], keys["renderheight"]
        width, height = int(info[wkey]), int(info[hkey])
        new_w, new_h = ef_resolution(width, height)
        output = self.worker.business.extract_render_output(data, "")
        output_folder = self.worker.business.convert_deadline_render_path(output)
        source_folder = ntpath.normpath(context["job_folder"] or "")
        output_folder_norm = ntpath.normcase(ntpath.normpath(output_folder or ""))
        flow_folder_norm = ntpath.normcase(source_folder)
        flow_parent_norm = ntpath.normcase(ntpath.dirname(source_folder))
        if (
            not output_folder
            or not source_folder
            or output_folder_norm not in {flow_folder_norm, flow_parent_norm}
        ):
            raise RuntimeError(
                "A pasta registrada no Flow não corresponde à saída do Job original. Confira a origem antes de continuar."
            )
        self.update(
            request,
            "PASTA",
            "Conferindo a pasta anterior e o destino EF no armazenamento.",
        )
        destination = self.storage(context, source_folder, "inspect")["destination"]
        outputs = {
            key: ntpath.join(destination, ntpath.basename(value))
            for key, value in info.items()
            if value
            and (
                key == "RenderOutput"
                or re.fullmatch(r"RenderElementOutputFilename\d+", key)
            )
        }
        if "RenderOutput" not in outputs:
            raise RuntimeError(
                "A chave de saída principal não foi encontrada no Job original."
            )
        plan = {
            "old_width": width,
            "old_height": height,
            "width": new_w,
            "height": new_h,
            "width_key": wkey,
            "height_key": hkey,
            "scene": data["PluginDataFileName"],
            "scene_size": int(data["PluginDataFileSize"]),
            "files": files,
            "source_folder": source_folder,
            "destination": destination,
            "job_name": re.sub(r"_EF$", "", context["imagem_nome"], flags=re.I) + "_EF",
            "camera": info.get("Camera", ""),
            "frames": str(data.get("Frames") or data.get("JobFrames", "")),
            "renderer": info.get("SubmittedRendererName", ""),
            "outputs": outputs,
            "source_info": info,
            "snapshot": snapshot(info),
            "job_snapshot": snapshot(data["_job_info"]),
            "generic": {
                key: data[key]
                for key in ("Frames", "ChunkSize", "Pool", "Group", "Priority")
                if key in data
            },
            "rounding": width * 5000 % max(width, height) != 0
            or height * 5000 % max(width, height) != 0,
        }
        self.update(
            request,
            "REVISAO",
            "Inspeção concluída. Confira a resolução e o destino antes de confirmar o envio.",
            status="PRONTO",
            plano_json=json.dumps(plan, ensure_ascii=False),
        )

    def submit(self, request: dict, plan: dict, context: dict) -> str:
        source_data, info, files = self.source(request)
        if (
            snapshot(info) != plan["snapshot"]
            or files != plan["files"]
            or snapshot(source_data["_job_info"]) != plan["job_snapshot"]
        ):
            raise RuntimeError(
                "As configurações ou os arquivos da origem mudaram desde a inspeção. Faça uma nova conferência."
            )
        self.update(
            request, "CRIANDO_PASTA", "Criando a pasta EF e a subpasta desta imagem."
        )
        storage_result = self.storage(context, plan["source_folder"], "inspect")
        if storage_result["destination"] != plan["destination"]:
            raise RuntimeError("O destino do armazenamento mudou desde a inspeção.")
        self.worker.logger.info(
            "EF SFTP storage inspection completed",
            extra={"destination": plan["destination"]},
        )
        ensure_smb_destination_ready(plan["destination"], self.worker.logger)
        self.update(
            request,
            "CONFIGURACOES",
            "Copiando câmera, frame, renderer e configurações; ajustando resolução e saídas do novo EF.",
        )
        with tempfile.TemporaryDirectory(prefix="flow_render_ef_") as temp:
            job_path, plugin_path = (
                Path(temp) / "job_info.job",
                Path(temp) / "plugin_info.job",
            )
            job, plugin = dict(source_data["_job_info"]), dict(info)
            if any(plugin.get(k) != v for k, v in plan["source_info"].items()):
                raise RuntimeError(
                    "A exportação do Plugin Info não preservou todas as configurações da origem."
                )
            plugin.update(plan["outputs"])
            plugin[plan["width_key"]], plugin[plan["height_key"]] = str(
                plan["width"]
            ), str(plan["height"])
            job.update(Name=plan["job_name"], InitialStatus="Suspended")
            for key in list(job):
                if re.fullmatch(r"OutputDirectory\d+", key):
                    job[key] = plan["destination"]
                elif re.fullmatch(r"OutputFilename\d+", key) and ntpath.isabs(job[key]):
                    job[key] = ntpath.join(
                        plan["destination"], ntpath.basename(job[key])
                    )
            extra = 0
            while f"ExtraInfoKeyValue{extra}" in job:
                extra += 1
            job[f"ExtraInfoKeyValue{extra}"] = f"FlowRenderEFRequest={request['id']}"
            write_info(job_path, job)
            write_info(plugin_path, plugin)
            self.context(request)
            # Commit the intent before calling the external command. A timeout or
            # lost response must never trigger an automatic second submission.
            self.update(
                request,
                "SUBMETENDO",
                "Criando um novo Job suspenso com o .max auxiliar original.",
            )
            self.worker.logger.info(
                "EF Deadline submission started",
                extra={
                    "request_id": request["id"],
                    "destination": plan["destination"],
                },
            )
            result = self.client.run([str(job_path), str(plugin_path), *files])
            found = re.search(r"\bJobID=([a-fA-F0-9]{24})\b", result.output)
            if not found:
                self.update(
                    request,
                    "SUBMISSAO_INCERTA",
                    "O Deadline não confirmou o novo Job ID. Verifique o Monitor antes de qualquer novo envio. A solicitação não será reenviada automaticamente.",
                    status="ATENCAO",
                )
                return ""
            new_id = found.group(1)
            if not valid_job_id(new_id) or new_id == request["origem_job_id"]:
                raise RuntimeError("O Deadline não retornou um novo Job ID válido.")
            request["novo_job_id"] = new_id
            self.update(
                request,
                "VALIDANDO",
                "Novo Job criado e suspenso. Conferindo cena, resolução e todos os caminhos de saída.",
                novo_job_id=new_id,
            )
            return new_id

    def validate(self, request: dict, plan: dict) -> tuple:
        result, data = self.client.get_job(request["novo_job_id"])
        if not result.success:
            raise RuntimeError(
                "O novo Job não pôde ser consultado. Ele não será submetido novamente."
            )
        actual = plugin_info(data)
        if any(
            data.get(key) != value for key, value in plan.get("generic", {}).items()
        ):
            raise RuntimeError(
                "Frames, divisão de tarefas, pool, grupo ou prioridade do novo Job não correspondem à origem."
            )
        expected = {
            **plan["source_info"],
            **plan["outputs"],
            plan["width_key"]: str(plan["width"]),
            plan["height_key"]: str(plan["height"]),
        }
        mismatches = {
            key: {
                "expected": repr(value)[:500],
                "actual": repr(actual.get(key))[:500],
            }
            for key, value in expected.items()
            if actual.get(key) != value
        }
        if mismatches:
            self.worker.logger.error(
                "EF plugin configuration mismatch",
                extra={
                    "job_id": request["novo_job_id"],
                    "reason": json.dumps(mismatches, ensure_ascii=True),
                },
            )
            raise RuntimeError(
                "As configurações do novo Job não correspondem ao plano confirmado. "
                f"Chaves divergentes: {', '.join(mismatches)}. "
                "Verifique o Job no Monitor."
            )
        if (
            data.get("PluginDataFileName") != plan["scene"]
            or int(data.get("PluginDataFileSize", 0)) != plan["scene_size"]
        ):
            raise RuntimeError(
                "A cena auxiliar do novo Job não corresponde ao .max original."
            )
        folder = self.command(["-GetJobAuxiliaryPath", request["novo_job_id"]]).strip()
        scene = Path(folder) / plan["scene"]
        if not scene.is_file() or scene.stat().st_size != plan["scene_size"]:
            raise RuntimeError("O .max auxiliar não foi encontrado no novo Job.")
        return result, data

    def execute(self, request: dict):
        plan = json.loads(request["plano_json"])
        context = self.context(request)
        fresh_submission = not request.get("novo_job_id")
        if fresh_submission:
            if not self.submit(request, plan, context):
                return
        job_result, data = self.validate(request, plan)
        if fresh_submission and str(data.get("Status", "")).lower() != "suspended":
            raise RuntimeError(
                "O novo Job não permaneceu suspenso durante a conferência. Verifique-o no Deadline Monitor antes de retomar."
            )
        self.update(
            request,
            "VINCULANDO",
            "Registrando o render EF e sua tentativa no fluxo padrão do worker.",
        )
        candidate = {
            "imagem_id": request["imagem_id"],
            "status_id": context["status_id"],
        }
        attempt = self.worker.repository.create_first_render(
            candidate, request["novo_job_id"], plan["job_name"], "VINCULADA"
        )
        if attempt["outcome"] in {"JOB_ALREADY_KNOWN", "RACE_RENDER_EXISTS"}:
            with self.db.transaction() as cursor:
                cursor.execute(
                    """SELECT rt.* FROM render_tentativas rt JOIN render_alta r ON r.idrender_alta = rt.render_id
                    WHERE rt.imagem_id = %s AND rt.status_id = %s AND rt.ativa = 1 AND r.excluido_em IS NULL""",
                    (request["imagem_id"], context["status_id"]),
                )
                candidates = cursor.fetchall()
            if len(candidates) != 1:
                raise RuntimeError(
                    "O registro EF não tem uma tentativa única para receber o Job. O novo Job permanecerá suspenso."
                )
            attempt = candidates[0]
            if not attempt["deadline_job_id"]:
                if not self.worker.repository.bind_job(
                    attempt["id"], request["novo_job_id"], plan["job_name"]
                ):
                    raise RuntimeError(
                        "Não foi possível vincular o novo Job à tentativa EF."
                    )
                attempt.update(
                    deadline_job_id=request["novo_job_id"], status="VINCULADA"
                )
            if attempt["deadline_job_id"] != request["novo_job_id"]:
                raise RuntimeError(
                    "A tentativa EF foi vinculada a outro Job. O novo Job permanecerá suspenso."
                )
        elif attempt["outcome"] != "FIRST_RENDER_CREATED":
            raise RuntimeError(
                "A criação do registro EF foi recusada pelo fluxo padrão."
            )
        self.update(
            request,
            "LIBERANDO",
            "Registro EF confirmado. Liberando o mesmo novo Job para renderização.",
            render_ef_id=attempt["render_id"],
        )
        with self.db.transaction() as cursor:
            cursor.execute(
                "UPDATE render_alta SET job_folder = %s WHERE idrender_alta = %s AND deadline_job_id = %s",
                (plan["destination"], attempt["render_id"], request["novo_job_id"]),
            )
        self.context(request)
        _, data = self.validate(request, plan)
        if str(data.get("Status", "")).lower() == "suspended":
            self.command(["-ResumeJob", request["novo_job_id"]])
        job_result, data = self.client.get_job(request["novo_job_id"])
        if not job_result.success or str(data.get("Status", "")).lower() not in {
            "active",
            "queued",
            "rendering",
            "pending",
            "completed",
        }:
            raise RuntimeError(
                "O Job EF foi registrado, mas o Deadline ainda não confirmou sua entrada na fila. Use Retomar para conferir o mesmo Job."
            )
        tasks_result, tasks = self.client.get_tasks(request["novo_job_id"])
        self.worker.process_first_render(
            attempt,
            job_result,
            data,
            tasks if tasks_result.success else {},
            state_from_deadline(data, tasks),
        )
        self.update(
            request,
            "CONCLUIDO",
            f"Render EF enviado. Novo Job {request['novo_job_id']}; status Deadline: {data.get('Status')}.",
            status="CONCLUIDO",
        )

    def process_one(self) -> bool:
        if self.enabled is None:
            with self.db.transaction() as cursor:
                cursor.execute(
                    "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'render_ef_solicitacoes'"
                )
                self.enabled = bool(cursor.fetchone())
        if not self.enabled:
            return False
        with self.db.transaction() as cursor:
            # Stale executions stop for review, never blindly resubmit.
            cursor.execute(
                """UPDATE render_ef_solicitacoes SET status = 'ATENCAO',
                mensagem = 'O processamento foi interrompido. Confira o Job no Monitor antes de retomar; nenhum novo envio automático será feito.'
                WHERE status = 'PROCESSANDO' AND atualizado_em < NOW() - INTERVAL 30 MINUTE"""
            )
            cursor.execute(
                "SELECT * FROM render_ef_solicitacoes WHERE status IN ('PENDENTE', 'EXECUTAR') ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED"
            )
            request = cursor.fetchone()
            if not request:
                return False
            mode = request["status"]
            cursor.execute(
                "UPDATE render_ef_solicitacoes SET status = 'PROCESSANDO', worker_id = %s WHERE id = %s",
                (self.worker.settings.worker_id, request["id"]),
            )
        try:
            self.inspect(request) if mode == "PENDENTE" else self.execute(request)
        except Exception as exc:
            self.worker.logger.exception(
                "manual EF request failed",
                extra={
                    "request_id": request["id"],
                    "job_id": request.get("novo_job_id"),
                },
            )
            message = str(exc)
            if request.get("novo_job_id"):
                message += f" Job EF: {request['novo_job_id']}. Não será criado outro Job ao retomar."
            uncertain = request.get("etapa") == "SUBMETENDO" and not request.get(
                "novo_job_id"
            )
            if uncertain:
                message += " A submissão pode ter sido recebida pelo Deadline. Confira o Monitor antes de qualquer novo envio."
            self.update(
                request,
                request.get("etapa", "INSPECAO"),
                message,
                status="ATENCAO" if uncertain else "ERRO",
                novo_job_id=request.get("novo_job_id"),
            )
        return True
