"""Prepare, but never install or release, replacements for legacy EF scripts."""

import argparse
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

from deadline_render_ef import asset_base_script, valid_job_id

PHASES = [
    ("PreLoadScript", "flow_asset_base_pre.ms"),
    ("PostLoadScript", "flow_asset_base_post.ms"),
]


def prepare(plan, job_id, source, target):
    source, target = Path(source).resolve(strict=True), Path(target).resolve()
    if not valid_job_id(job_id) or source.name.lower() != job_id.lower():
        raise ValueError("Pasta auxiliar não corresponde ao Job ID informado.")
    if target == source or source in target.parents or target in source.parents:
        raise ValueError("A pasta de preparação deve ficar fora da árvore do Job.")
    if plan.get("origin") != "NAS":
        raise ValueError("Somente planos NAS são aceitos.")
    records = []
    for phase, name in PHASES:
        path = source / name
        if path.is_symlink() or not path.is_file():
            raise ValueError("Script ausente ou link não permitido: " + name)
        old = path.read_bytes()
        if old.decode("utf-8-sig").replace("\r\n", "\n") != asset_base_script(
            plan, phase, legacy=True
        ):
            raise ValueError(
                "Script não corresponde exatamente ao legado do plano: " + name
            )
        new = asset_base_script(plan, phase).encode("utf-8-sig")
        records.append((name, old, new))
    target.mkdir(parents=False, exist_ok=False)
    manifest = {
        "job_id": job_id,
        "created_utc": datetime.now(timezone.utc).isoformat(),
        "snapshot_id": plan["snapshot_id"],
        "scene_sha256": plan["scene_fingerprint"]["sha256"],
        "scripts": [],
    }
    for name, old, new in records:
        (target / (name + ".original")).write_bytes(old)
        (target / name).write_bytes(new)
        manifest["scripts"].append(
            {
                "name": name,
                "before_sha256": hashlib.sha256(old).hexdigest(),
                "after_sha256": hashlib.sha256(new).hexdigest(),
            }
        )
    (target / "manifest.json").write_text(
        json.dumps(manifest, indent=2, ensure_ascii=False) + "\n", encoding="utf-8"
    )
    return manifest


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--plan", required=True, help="Exportação restrita de plano_json da solicitação"
    )
    parser.add_argument("--job", required=True)
    parser.add_argument(
        "--source", required=True, help="Pasta auxiliar do Job; somente leitura"
    )
    parser.add_argument(
        "--staging",
        required=True,
        help="Nova pasta privada, fora do repositório Deadline",
    )
    args = parser.parse_args()
    manifest = prepare(
        json.loads(Path(args.plan).read_text(encoding="utf-8-sig")),
        args.job,
        args.source,
        args.staging,
    )
    print(json.dumps(manifest, indent=2, ensure_ascii=False))
