"""Deterministic rules over evidence from one Deadline error report."""
from __future__ import annotations

import hashlib
import re
from dataclasses import dataclass

CATALOG_VERSION = 1
UNKNOWN = "Causa não identificada — análise manual necessária."
MAX_EXIT = r'monitored managed process ["\x27]?3dsmaxprocess["\x27]? has exited or been terminated|\b3ds\s*max(?:\.exe)? (?:crashed|exited unexpectedly)'
CORONA_MEMORY = r'\bcorona(?: renderer)?\b.{0,100}\b(?:not enough|insufficient|low(?: on)?) (?:physical )?(?:ram|memory)|\b(?:not enough|insufficient) (?:physical )?(?:ram|memory)\b.{0,100}\bcorona\b'
MEMORY_USAGE = r'\b(?:ram|memory)(?: usage| utilization| used)?\s*[:=]?\s*(?:[\d.,]+\s*[KMGT]i?B[^%]{0,40}\()?\s*(\d{1,3}(?:[.,]\d+)?)\s*%'
ERROR_LINE = r'\b[A-Z][A-Za-z]+Exception\s*:|\b(?:fatal error|error|warning|warn)\s*:'
SECRET = re.compile(
    r'(?i)\b(?:authorization|cookie|(?:[\w-]*[_-])?(?:password|passwd|pwd|token|secret|api[_-]?key))["\x27]?\s*[:=].*'
    r'|\b(?:bearer|basic)\s+[\w+/=.-]+|\bxox[baprs]-[\w-]+|https?://[^\s<>"\x27]+'
    r'|\b(?:AKIA|ASIA)[A-Z0-9]{16}\b|\b[\w.+-]+@[a-z0-9.-]+\.[a-z]{2,}\b'
    r'|-----BEGIN [^-]+PRIVATE KEY-----[\s\S]*?(?:-----END [^-]+PRIVATE KEY-----|\Z)',
)
PATH = re.compile(r'(?i)(?:[a-z]:[\\/]|\\\\|(?<!\w)/(?:mnt|home|tmp|var|Users)/)[^\r\n"\x27|<>]+')
LOG_PREFIX = re.compile(r'^(?:\d{4}-\d\d-\d\d[ T])?\d\d:\d\d:\d\d(?:[.,]\d+)?(?:\s*:\s*|\s+)(?:\d+:\s*)?')


@dataclass(frozen=True)
class Rule:
    id: str
    category: str
    required: tuple[str, ...]
    supplemental: tuple[str, ...]
    priority: int
    confidence: str
    technical: str
    explanation: str
    recommendation: str
    role: str = "cause"
    severity: str = "ACAO"
    version: int = 1


RULES = (
    Rule("ram_allocation", "memory", (r'\b(?:System\.)?OutOfMemoryException\b\s*[:.]|\bstd::bad_alloc\b|\b(?:cannot|failed to|unable to) allocate (?:\d+\s*\w+ (?:of )?)?memory\b|\b(?:system|os) (?:ran )?out of memory\b',),
         (MEMORY_USAGE, MAX_EXIT), 120, "confirmado", "O relatório registra falha explícita de alocação de memória.",
         "Memória insuficiente durante a execução.", "Verificar o pico de memória da cena e a capacidade física do Worker."),
    Rule("deadline_repository", "deadline_communication", (r'\b(?:failed|unable|could not) to (?:connect to|communicate with) (?:the )?(?:deadline repository|remote connection server|deadline database)\b',),
         (), 113, "confirmado", "O Deadline registrou falha de comunicação com um componente do repositório.",
         "Falha na comunicação com o Deadline.", "Verificar o repositório, o RCS e a conectividade do Worker.", severity="CRITICO"),
    Rule("worker_stall", "worker_stalled", (r'\b(?:worker|slave)(?:\s+[\w.-]+)? (?:is stalled|has stalled|has stopped responding|missed (?:its )?heartbeat)\b',),
         (), 112, "confirmado", "O relatório registra parada de resposta ou perda de heartbeat do Worker.",
         "O Worker parou de responder.", "Investigar conectividade, heartbeat e logs do Worker.", role="symptom", severity="CRITICO"),
    Rule("license_service", "license", (r'\blicen[cs]e server (?:is )?(?:unreachable|not responding|unavailable)\b|\bcannot connect to (?:the )?licen[cs]e server\b',),
         (), 111, "confirmado", "O relatório indica indisponibilidade do servidor de licenças.",
         "O Worker não alcançou o servidor de licenças.", "Verificar o serviço de licenças, a rede e a disponibilidade do servidor.", severity="CRITICO"),
    Rule("license_checkout", "license", (r'\b(?:licen[cs]e checkout (?:failed|denied|timed out)|(?:failed|unable) to (?:obtain|acquire|check out) (?:an? |a valid )?licen[cs]e|no (?:valid |available )licen[cs]es? (?:found|available)|licen[cs]e (?:has )?expired)\b',),
         (), 105, "confirmado", "A licença foi recusada, está indisponível ou expirou.",
         "O aplicativo não obteve licença.", "Verificar as licenças do aplicativo e do renderer no Worker."),
    Rule("corona_memory_pressure", "memory", (CORONA_MEMORY, MAX_EXIT, "high_ram"),
         (MEMORY_USAGE,), 104, "provável", "Aviso de memória do Corona, RAM alta e encerramento do processo aparecem no mesmo relatório.",
         "Provável insuficiência de memória durante a execução.", "Verificar o consumo da cena e a capacidade física do Worker."),
    Rule("network_path", "network", (r'\b(?:the )?network (?:path|name) (?:was not found|is no longer available|is unreachable)\b|\b(?:system error|winerror)\s*(?:53|64|67)\b|\b(?:access denied|permission denied|could not connect)\b.{0,40}\\\\',),
         (), 101, "confirmado", "O relatório registra falha de acesso a um caminho de rede.",
         "O Worker não conseguiu acessar um caminho de rede.", "Verificar disponibilidade do NAS, credenciais do serviço e o caminho a partir do Worker."),
    Rule("plugin_load", "plugin", (r'\b(?:missing (?:dlls?|plug-?ins?)|plug-?in (?:was )?not found|incompatible plug-?in|(?:failed|unable) to load (?:the )?(?:plug-?in|dll)\b|plug-?in .{1,80} (?:is missing|is incompatible))',),
         (), 98, "confirmado", "Carregamento, presença ou compatibilidade de plugin falhou.",
         "Um plugin está ausente ou não pôde ser carregado.", "Comparar versões e dependências do plugin com o ambiente de submissão."),
    Rule("asset_missing", "missing_asset", (r'\b(?:FileNotFoundException|DirectoryNotFoundException)\s*:|\b(?:file|asset|texture|scene) (?:was )?not found\b|\bno such file or directory\b|\b(?:could not|cannot) find (?:the )?(?:file|asset|texture|scene)\b',),
         (), 96, "confirmado", "O relatório identifica um arquivo, diretório ou asset ausente.",
         "Um arquivo necessário não foi encontrado.", "Verificar os assets publicados e os caminhos da cena no Worker."),
    Rule("deadline_task_timeout", "timeout", (r'\b(?:render(?:ing)?|task|execution) (?:has )?timed out\b|\b(?:exceeded|reached) (?:the )?(?:maximum |configured )?(?:task|render) (?:time limit|timeout)\b',),
         (), 94, "confirmado", "O relatório indica que o limite de execução foi atingido.",
         "A execução excedeu o limite de tempo.", "Comparar o limite do job com os tempos reais por frame."),
    Rule("max_startup", "max_initialization", (r'\b(?:failed|unable|could not) to (?:start|launch|initialize) (?:the )?3ds\s*max(?:\.exe)?\b|\b3ds\s*max (?:startup|initialization) failed\b',),
         (), 92, "confirmado", "O 3ds Max falhou durante a inicialização.",
         "O 3ds Max não iniciou.", "Verificar o executável, versão, dependências e permissões do Worker.", role="symptom"),
    Rule("file_io", "io", (r'\b(?:no space left on device|disk full|not enough space on the disk|I/O device error|sharing violation|read-only file system)\b|\b(?:failed|unable) to (?:read|write|save) (?:the )?(?:file|output|image)\b',),
         (), 90, "confirmado", "O relatório registra falha de leitura ou gravação.",
         "Uma operação de leitura ou gravação falhou.", "Verificar espaço, permissões e bloqueios no destino."),
    Rule("worker_configuration", "worker_configuration", (r'\bincompatible (?:worker|slave) version\b|\b(?:worker|slave) (?:does not meet|doesn\x27t meet) (?:the )?(?:job |minimum )?requirements\b|\bunsupported (?:operating system|cpu architecture)\b',),
         (), 89, "confirmado", "O relatório indica incompatibilidade explícita de configuração.",
         "A configuração do Worker é incompatível.", "Comparar os requisitos declarados com o hardware e a configuração do Worker."),
    Rule("corona_failure", "corona", (r'\bcorona(?: renderer)? (?:internal error|fatal error|crashed|initialization failed|failed to initialize)\b',),
         (), 88, "confirmado", "O renderer Corona reportou falha própria.",
         "O Corona reportou uma falha.", "Revisar os avisos do Corona e comparar a versão instalada com a da cena.", role="symptom"),
    Rule("corona_memory_warning", "memory", (CORONA_MEMORY,),
         (MEMORY_USAGE, MAX_EXIT), 70, "possível", "Há aviso do Corona sobre memória insuficiente.",
         "Memória insuficiente é uma hipótese.", "Comparar picos de RAM e avisos do Corona em outras tentativas."),
    Rule("max_process_exit", "max_exit", (MAX_EXIT,),
         (), 60, "indeterminado", "O processo monitorado do 3ds Max terminou inesperadamente.",
         UNKNOWN, "Revisar as mensagens anteriores ao encerramento e os logs locais do Worker.", role="symptom"),
)

CATEGORIES = {
    "memory": "Memória RAM insuficiente", "max_exit": "Encerramento do 3ds Max",
    "corona": "Falha do Corona", "missing_asset": "Arquivo ou asset ausente",
    "network": "Acesso à rede ou NAS", "license": "Licença", "plugin": "Plugin",
    "timeout": "Tempo de execução excedido", "max_initialization": "Inicialização do 3ds Max",
    "deadline_communication": "Comunicação com o Deadline", "worker_stalled": "Worker sem resposta",
    "io": "Leitura ou gravação", "worker_configuration": "Configuração do Worker",
    "unknown": "Falha desconhecida",
}


def digest(value: str) -> str:
    return hashlib.sha256(value.encode("utf-8")).hexdigest()


def redact(value: str) -> str:
    value = SECRET.sub("[suprimido]", value)
    return PATH.sub(" [caminho]", value)


def normalize(line: str) -> str:
    line = LOG_PREFIX.sub("", line.lower().strip())
    line = re.sub(r'\b(?:error|warning|stdout|info)\s*:\s*', "", line)
    line = re.sub(r'\b[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\b|\b(?:0x[0-9a-f]+|[0-9a-f]{24,})\b', "<id>", line)
    line = re.sub(r'\b(frame|task|pid|process id|timestamp)\s*[:=#]?\s*\d+(?:-\d+)?\b', r'\1 <n>', line)
    return " ".join(line.split())[:350]


def extract(body: str, report: dict) -> dict:
    # Keep diagnostically relevant excerpts and bounded context. Never persist the full log.
    body = re.sub(r'-----BEGIN [^-]+PRIVATE KEY-----[\s\S]*?(?:-----END [^-]+PRIVATE KEY-----|\Z)', "[suprimido]", body)
    lines = [line.strip() for line in body.splitlines() if line.strip()]
    messages = [line.strip() for line in str(report.get("message") or "").splitlines() if line.strip()]
    candidates = list(dict.fromkeys(messages + lines))
    patterns = {pattern for rule in RULES for pattern in (*rule.required, *rule.supplemental)} - {"high_ram"}
    matched = [line for line in candidates if any(re.search(pattern, line, re.I) for pattern in patterns)]
    errors = [line for line in candidates if re.search(ERROR_LINE, line)]
    main = next(iter(messages or errors or matched or lines[-1:]), "")
    percent = [float(value.replace(",", ".")) for line in matched for value in re.findall(MEMORY_USAGE, line, re.I)]
    renderer = next((name for name, pattern in (("Corona", r'\bCorona(?: Renderer)?\b'),
                    ("V-Ray", r'\bV-?Ray\b'), ("Arnold", r'\bArnold\b'), ("Redshift", r'\bRedshift\b'))
                     if any(re.search(pattern, line, re.I) for line in candidates)), None)
    max_version = next((value for line in candidates
                        if (value := re.search(r'\b3ds\s*max(?:\.exe)?[ \t]+(20\d{2})\b', line, re.I))), None)
    flags = {}
    for pattern in sorted(patterns):
        hit = next((line for line in matched if re.search(pattern, line, re.I)), None)
        if hit:
            flags[pattern] = redact(hit)[:500]
    previous = lines[max(0, lines.index(main) - 5):lines.index(main)] if main in lines else lines[-5:]
    excerpts = list(dict.fromkeys(redact(line)[:500] for line in [main, *previous, *matched[:24], *errors[-8:]] if line))
    unavailable = [key for key in ("worker", "task_id", "frame", "occurred_at", "plugin", "peak_ram") if report.get(key) is None]
    if not renderer:
        unavailable.append("renderer")
    if not any((report.get("capacity") or {}).get(name) is not None for name in ("physical_bytes", "groups", "pools")):
        unavailable.append("worker_capacity")
    return {
        "main": redact(main)[:500], "excerpts": excerpts, "hits": flags,
        "renderer": renderer, "ram_percent": max((value for value in percent if value <= 100), default=None),
        "exception": (re.search(r'\b([\w.]+Exception)\b', main) or [None, None])[1],
        "process": "3dsmaxProcess" if re.search(MAX_EXIT, "\n".join(matched), re.I) else None,
        "application_version": f"3ds Max {max_version[1]}" if max_version else None,
        "unavailable": unavailable,
    }


def classify(evidence: dict, report: dict) -> dict:
    hits = dict(evidence["hits"])
    # Reapply the current signatures to the preserved excerpts on a rule upgrade.
    patterns = {pattern for rule in RULES for pattern in (*rule.required, *rule.supplemental)} - {"high_ram"}
    for pattern in patterns - hits.keys():
        hit = next((line for line in evidence["excerpts"] if re.search(pattern, line, re.I)), None)
        if hit:
            hits[pattern] = hit
    if (evidence["ram_percent"] or 0) >= 95:
        hits["high_ram"] = f'RAM reportada em {evidence["ram_percent"]:g}%.'
    hypotheses = []
    for rule in sorted(RULES, key=lambda item: -item.priority):
        if not all(pattern in hits for pattern in rule.required):
            continue
        hypotheses.append({
            "rule": rule.id, "version": rule.version, "category": rule.category, "role": rule.role,
            "confidence": rule.confidence, "severity": rule.severity, "technical": rule.technical,
            "diagnosis": rule.explanation, "recommendation": rule.recommendation,
            "evidence": list(dict.fromkeys(hits[pattern] for pattern in (*rule.required, *rule.supplemental) if pattern in hits)),
        })
    primary = hypotheses[0] if hypotheses else {
        "rule": "unknown", "version": 1, "category": "unknown", "role": "cause",
        "confidence": "indeterminado", "severity": "ACAO", "technical": "Nenhuma assinatura conhecida corresponde às evidências.",
        "diagnosis": UNKNOWN, "recommendation": "Consultar o relatório no Deadline Monitor.", "evidence": [],
    }
    capacity = report.get("capacity") or {}
    factors = []
    if capacity.get("required_bytes") and capacity.get("physical_bytes") and capacity["required_bytes"] > capacity["physical_bytes"]:
        factors.append("A RAM física informada pelo Worker é inferior ao requisito de RAM declarado no job.")
    if capacity.get("configuration_changed"):
        factors.append("Grupo, pool ou memória física informados mudaram desde a última coleta deste Worker.")
    # Specific rules already encode a failure mechanism. Keep message detail for unknown errors.
    detail = normalize(evidence["main"])
    if primary["rule"] == "unknown" and not re.search(r'\b\w+\b.*\b\w+\b', re.sub(r'[\w.]*exception|\b(?:unknown|error|failure|render|failed)\b', "", detail)):
        detail = f'{detail}:{report["job_id"]}:{report.get("report_id")}'
    specific_detail = ""
    if primary["category"] in {"plugin", "worker_configuration", "corona", "io", "license", "network", "missing_asset", "unknown"}:
        specific_detail = normalize(primary["evidence"][0]) if primary["evidence"] else detail
    signature = digest(f'{primary["rule"]}:{(evidence["renderer"] or "").lower()}:{specific_detail}')
    return {
        **primary, "catalog_version": CATALOG_VERSION, "signature": signature, "symptom": evidence["main"],
        "category_label": CATEGORIES[primary["category"]],
        "severity": "CRITICO" if any(item["severity"] == "CRITICO" for item in hypotheses) else primary["severity"],
        "alternatives": [item for item in hypotheses[1:] if item["category"] != primary["category"]],
        "factors": factors,
    }
