"""Create an idempotent EF recovery request. Does not submit or resume a Job."""

from __future__ import annotations

import argparse
import copy
import json
import ntpath
import uuid
import getpass

from deadline_render_ef import snapshot
from deadline_nas_submission import destination
from deadline_snapshots import SnapshotStore, approved_attempt, decode_snapshot
from deadline_scene import assert_scene_access


def recovery_token(request_id, attempt_id, job_id):
    return str(
        uuid.uuid5(
            uuid.NAMESPACE_URL, f"flow:ef-recovery:{request_id}:{attempt_id}:{job_id}"
        )
    )


def make_plan(old, token, request_id, attempt_id, job_id, context):
    plan = copy.deepcopy(old)
    previous_destination = plan["destination"]
    new_destination = destination(context, token)
    for key, path in plan["outputs"].items():
        if ntpath.normcase(ntpath.dirname(path)) != ntpath.normcase(
            previous_destination
        ):
            raise RuntimeError("Saída original fora da pasta exclusiva.")
        plan["outputs"][key] = ntpath.join(new_destination, ntpath.basename(path))
    for key in list(plan["job_info"]):
        if (
            key.startswith("OutputDirectory")
            and key[len("OutputDirectory") :].isdigit()
        ):
            plan["job_info"][key] = new_destination
    plan["destination"] = new_destination
    plan["source_info"].pop("SceneFile", None)
    plan["snapshot"] = snapshot(plan["source_info"])
    plan["job_snapshot"] = snapshot(plan["job_info"])
    plan["recovery"] = {
        "request_id": request_id,
        "previous_attempt_id": attempt_id,
        "previous_job_id": job_id,
    }
    return plan


def reserve(worker, request_id, attempt_id, job_id):
    """Reserve exactly one recovery. The existing worker performs external I/O."""
    token = recovery_token(request_id, attempt_id, job_id)
    with worker.database.transaction() as cur:
        cur.execute("SELECT * FROM render_ef_solicitacoes WHERE id=%s", (request_id,))
        prior = cur.fetchone()
    if (
        not prior
        or prior["origem"] != "NAS"
        or prior["novo_job_id"] != job_id
        or not prior["render_ef_id"]
    ):
        raise RuntimeError("Solicitação anterior ou vínculo do Job divergente.")
    reply, job = worker.client.get_job(job_id)
    if (
        not reply.success
        or str(job.get("Status", "")).lower() != "suspended"
        or str(job.get("RenderingChunks", "")) != "0"
    ):
        raise RuntimeError("Job anterior não está suspenso e sem renderização.")
    approved = approved_attempt(worker.database, prior)
    row = SnapshotStore(worker.database).get(prior["origem_job_id"])
    old = json.loads(prior["plano_json"])
    if (
        not row
        or row["id"] != prior["snapshot_id"]
        or row["id"] != old["snapshot_id"]
        or row["tentativa_id"] != approved["id"]
        or row["conteudo_sha256"] != old["snapshot_sha256"]
    ):
        raise RuntimeError("Snapshot aprovado divergente.")
    decode_snapshot(row)
    if assert_scene_access(old["scene_path"]) != old["scene_fingerprint"]:
        raise RuntimeError("Cena original mudou desde a confirmação.")
    context = {
        "imagem_id": prior["imagem_id"],
        "nomenclatura": ntpath.basename(
            ntpath.dirname(ntpath.dirname(old["destination"]))
        ),
    }
    plan = make_plan(old, token, request_id, attempt_id, job_id, context)
    with worker.database.transaction() as cur:
        cur.execute(
            "SELECT status_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra=%s FOR UPDATE",
            (prior["imagem_id"],),
        )
        image = cur.fetchone()
        cur.execute(
            "SELECT * FROM render_ef_solicitacoes WHERE submissao_uuid=%s FOR UPDATE",
            (token,),
        )
        existing = cur.fetchone()
        if existing:
            if (
                existing["status"] == "ERRO"
                and not existing["submissao_iniciada_em"]
                and not existing["novo_job_id"]
            ):
                cur.execute(
                    "UPDATE render_ef_solicitacoes SET status='EXECUTAR_V2',etapa='FILA',mensagem='Retomada da recuperação antes da submissão.' WHERE id=%s",
                    (existing["id"],),
                )
                cur.execute(
                    "INSERT INTO render_ef_eventos (solicitacao_id,etapa,status,mensagem,origem) VALUES (%s,'RECUPERACAO','EXECUTAR_V2',%s,'OPERADOR')",
                    (existing["id"], "Retomada por " + getpass.getuser()),
                )
            return existing["id"], token
        cur.execute(
            "SELECT * FROM render_ef_solicitacoes WHERE id=%s FOR UPDATE", (request_id,)
        )
        locked = cur.fetchone()
        if (
            not locked
            or locked["status"] != "CONCLUIDO"
            or locked["plano_json"] != prior["plano_json"]
            or locked["novo_job_id"] != job_id
            or locked["render_ef_id"] != prior["render_ef_id"]
        ):
            raise RuntimeError("Solicitação original mudou durante a recuperação.")
        cur.execute(
            "SELECT * FROM render_tentativas WHERE imagem_id=%s AND status_id=%s AND ativa=1 FOR UPDATE",
            (prior["imagem_id"], image["status_id"]),
        )
        active = list(cur.fetchall())
        if (
            len(active) != 1
            or active[0]["id"] != attempt_id
            or active[0]["render_id"] != prior["render_ef_id"]
            or active[0]["deadline_job_id"] != job_id
            or active[0]["status"] != "ERRO"
            or image["status_id"] != active[0]["status_id"]
        ):
            raise RuntimeError(
                "Tentativa anterior não é a única tentativa ativa em erro na etapa atual."
            )
        cur.execute(
            "SELECT id FROM render_ef_solicitacoes WHERE imagem_id=%s AND operacao_ativa_imagem IS NOT NULL FOR UPDATE",
            (prior["imagem_id"],),
        )
        if cur.fetchone():
            raise RuntimeError("Outra operação EF já está ativa.")
        cur.execute(
            """INSERT INTO render_ef_solicitacoes
            (render_origem_id,imagem_id,criado_por,origem_job_id,origem,arquivo_log_id,submissao_uuid,status,etapa,mensagem,plano_json,configuracao_json,snapshot_id,origem_tentativa_id,arquivo_confirmado_por,arquivo_confirmado_em,confirmado_por,confirmado_em)
            VALUES (%s,%s,%s,%s,'NAS',%s,%s,'EXECUTAR_V2','FILA',%s,%s,%s,%s,%s,%s,%s,%s,NOW())""",
            (
                prior["render_origem_id"],
                prior["imagem_id"],
                prior["criado_por"],
                prior["origem_job_id"],
                prior["arquivo_log_id"],
                token,
                "Recuperação autorizada da tentativa " + str(attempt_id),
                json.dumps(plan, ensure_ascii=False),
                prior["configuracao_json"],
                prior["snapshot_id"],
                prior["origem_tentativa_id"],
                prior["arquivo_confirmado_por"],
                prior["arquivo_confirmado_em"],
                prior["confirmado_por"],
            ),
        )
        new_id = cur.lastrowid
        cur.execute(
            "INSERT INTO render_ef_eventos (solicitacao_id,etapa,status,mensagem,origem) VALUES (%s,'RECUPERACAO','EXECUTAR_V2',%s,'OPERADOR')",
            (
                new_id,
                "Recuperação da solicitação "
                + str(request_id)
                + ", tentativa "
                + str(attempt_id)
                + ", Job "
                + job_id
                + ", operador "
                + getpass.getuser(),
            ),
        )
    return new_id, token


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--request", type=int, required=True)
    parser.add_argument("--attempt", type=int, required=True)
    parser.add_argument("--job", required=True)
    args = parser.parse_args()
    from deadline_worker import DeadlineWorker

    worker = DeadlineWorker()
    new_id, token = reserve(worker, args.request, args.attempt, args.job)
    print(json.dumps({"request_id": new_id, "submission_uuid": token}))
