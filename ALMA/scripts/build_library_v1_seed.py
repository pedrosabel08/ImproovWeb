"""Generate the immutable ALMA v1.0 SQL seed from the official PDF.

The generated SQL is committed so production does not need Python or the PDF.
This script exists to make the provenance and regeneration process auditable.
"""

from __future__ import annotations

import argparse
import hashlib
import re
import unicodedata
from dataclasses import dataclass
from pathlib import Path

from pypdf import PdfReader


@dataclass(frozen=True)
class ItemSpec:
    dimension: str
    title: str
    first_page: int
    last_page: int
    start: str
    end: str | None = None


ITEMS = [
    ItemSpec("atmosfera", "Contemplação", 10, 11, "CONTEMPLAÇÃO", "BEM-ESTAR"),
    ItemSpec("atmosfera", "Bem-Estar", 11, 12, "BEM-ESTAR", "SOFISTICAÇÃO"),
    ItemSpec("atmosfera", "Sofisticação", 12, 14, "SOFISTICAÇÃO", "EXCLUSIVIDADE"),
    ItemSpec("atmosfera", "Exclusividade", 14, 15, "EXCLUSIVIDADE", "ACOLHIMENTO"),
    ItemSpec(
        "atmosfera", "Acolhimento", 15, 17, "ACOLHIMENTO", "CONEXÃO COM A NATUREZA"
    ),
    ItemSpec(
        "atmosfera",
        "Conexão com a Natureza",
        17,
        18,
        "CONEXÃO COM A NATUREZA",
        "LEVEZA",
    ),
    ItemSpec("atmosfera", "Leveza", 18, 20, "LEVEZA", "VITALIDADE URBANA"),
    ItemSpec(
        "atmosfera", "Vitalidade Urbana", 20, 21, "VITALIDADE URBANA", "CONVIVÊNCIA"
    ),
    ItemSpec("atmosfera", "Convivência", 21, 23, "CONVIVÊNCIA"),
    ItemSpec("luz_linguagem", "Luz Difusa", 26, 28, "Luz Difusa", "Luz Direcional"),
    ItemSpec(
        "luz_linguagem", "Luz Direcional", 28, 30, "Luz Direcional", "Luz Contrastada"
    ),
    ItemSpec(
        "luz_linguagem", "Luz Contrastada", 30, 32, "Luz Contrastada", "Luz Filtrada"
    ),
    ItemSpec("luz_linguagem", "Luz Filtrada", 32, 35, "Luz Filtrada", "Luz Uniforme"),
    ItemSpec("luz_linguagem", "Luz Uniforme", 35, 37, "Luz Uniforme", "Luz Narrativa"),
    ItemSpec("luz_linguagem", "Luz Narrativa", 37, 39, "Luz Narrativa"),
    ItemSpec("arquitetura", "Contemporâneo", 41, 43, "Contemporâneo", "Japandi"),
    ItemSpec("arquitetura", "Japandi", 43, 45, "Japandi", "Tropical"),
    ItemSpec("arquitetura", "Tropical", 45, 47, "Tropical", "Biofílico"),
    ItemSpec("arquitetura", "Biofílico", 47, 50, "Biofílico", "Minimalista"),
    ItemSpec("arquitetura", "Minimalista", 50, 52, "Minimalista", "Escandinavo"),
    ItemSpec("arquitetura", "Escandinavo", 52, 54, "Escandinavo"),
    ItemSpec("arquitetura", "Neoclássico", 55, 57, "Neoclássico"),
    ItemSpec("materialidade", "Contemporâneo", 59, 63, "Contemporâneo", "Japandi"),
    ItemSpec("materialidade", "Japandi", 63, 68, "Japandi", "Tropical"),
    ItemSpec("materialidade", "Tropical", 68, 72, "Tropical"),
    ItemSpec("materialidade", "Biofílico", 73, 77, "Biofílico", "Minimalista"),
    ItemSpec("materialidade", "Minimalista", 77, 82, "Minimalista", "Escandinavo"),
    ItemSpec("materialidade", "Escandinavo", 82, 87, "Escandinavo", "Neoclássico"),
    ItemSpec("materialidade", "Neoclássico", 87, 92, "Neoclássico"),
    ItemSpec("lifestyle", "Ritual", 94, 97, "Ritual", "Convivência"),
    ItemSpec("lifestyle", "Convivência", 97, 101, "Convivência", "Bem-Estar"),
    ItemSpec(
        "lifestyle", "Bem-Estar", 101, 105, "Bem-Estar", "Movimento Versão Resumida"
    ),
    ItemSpec("lifestyle", "Movimento", 105, 108, "Movimento"),
    ItemSpec(
        "lifestyle",
        "Celebração",
        109,
        112,
        "Celebração",
        "Versão Resumida Experiência Principal Momentos de exploração",
    ),
    ItemSpec("lifestyle", "Descoberta", 112, 116, "Versão Resumida"),
    ItemSpec("composicao", "Focada", 118, 122, "Focada", "Equilibrada"),
    ItemSpec("composicao", "Equilibrada", 122, 125, "Equilibrada"),
    ItemSpec("composicao", "Narrativa", 126, 129, "Narrativa", "Monumental"),
    ItemSpec("composicao", "Monumental", 129, 133, "Monumental"),
    ItemSpec("composicao", "Imersiva", 134, 137, "Imersiva"),
]

MOMENTOS_LUZ = [
    "Amanhecer",
    "Manhã",
    "Meio-dia",
    "Tarde",
    "Golden Hour",
    "Blue Hour",
    "Dusk",
    "Noite",
]

DIMENSIONS = [
    (
        "atmosfera",
        None,
        "SENTIR",
        "Sentir",
        "atmosfera",
        "Atmosfera",
        "Atmosfera",
        "PILAR",
        1,
        0,
        0,
        1,
    ),
    (
        "arquitetura",
        None,
        "CONSTRUIR",
        "Construir",
        "arquitetura",
        "Arquitetura",
        "Arquitetura",
        "PILAR",
        2,
        0,
        0,
        1,
    ),
    (
        "materialidade",
        None,
        "MATERIALIZAR",
        "Materializar",
        "materialidade",
        "Materialidade",
        "Materialidade",
        "PILAR",
        3,
        0,
        0,
        1,
    ),
    ("luz", None, "ILUMINAR", "Iluminar", "luz", "Luz", "Luz", "PILAR", 4, 0, 0, 0),
    (
        "luz_momento",
        "luz",
        "ILUMINAR",
        "Iluminar",
        "luz",
        "Luz",
        "Momento da Luz",
        "DIMENSAO",
        4,
        1,
        0,
        1,
    ),
    (
        "luz_linguagem",
        "luz",
        "ILUMINAR",
        "Iluminar",
        "luz",
        "Luz",
        "Linguagem da Luz",
        "DIMENSAO",
        4,
        2,
        0,
        1,
    ),
    (
        "lifestyle",
        None,
        "VIVER",
        "Viver",
        "lifestyle",
        "Lifestyle",
        "Lifestyle",
        "PILAR",
        5,
        0,
        0,
        1,
    ),
    (
        "fotografia",
        None,
        "OBSERVAR",
        "Observar",
        "fotografia",
        "Fotografia",
        "Fotografia",
        "PILAR",
        6,
        0,
        0,
        0,
    ),
    (
        "fotografia_direcao",
        "fotografia",
        "OBSERVAR",
        "Observar",
        "fotografia",
        "Fotografia",
        "Direção Fotográfica",
        "DIMENSAO",
        6,
        1,
        0,
        0,
    ),
    (
        "fotografia_teste_angulos",
        "fotografia",
        "OBSERVAR",
        "Observar",
        "fotografia",
        "Fotografia",
        "Teste de Ângulos",
        "DIMENSAO",
        6,
        2,
        0,
        0,
    ),
    (
        "fotografia_enquadramento",
        "fotografia",
        "OBSERVAR",
        "Observar",
        "fotografia",
        "Fotografia",
        "Enquadramento",
        "DIMENSAO",
        6,
        3,
        0,
        0,
    ),
    (
        "fotografia_referencias_sire",
        "fotografia",
        "OBSERVAR",
        "Observar",
        "fotografia",
        "Fotografia",
        "Referências SIRE",
        "DIMENSAO",
        6,
        4,
        1,
        0,
    ),
    (
        "composicao",
        None,
        "CONTAR",
        "Contar",
        "composicao",
        "Composição",
        "Composição",
        "PILAR",
        7,
        0,
        0,
        1,
    ),
]


def compact(text: str) -> str:
    return re.sub(r"\s+", " ", text.replace("\u2028", " ")).strip()


def slug(text: str) -> str:
    normalized = (
        unicodedata.normalize("NFKD", text).encode("ascii", "ignore").decode("ascii")
    )
    return re.sub(r"[^a-z0-9]+", "_", normalized.lower()).strip("_")


def sql(value: str | None) -> str:
    if value is None or value == "":
        return "NULL"
    escaped = value.replace("\\", "\\\\").replace("'", "''")
    return "'" + escaped + "'"


def between(text: str, starts: list[str], ends: list[str]) -> str | None:
    found = None
    for marker in starts:
        match = re.search(re.escape(marker), text, flags=re.IGNORECASE)
        if match and (
            found is None
            or match.start() < found.start()
            or (match.start() == found.start() and match.end() > found.end())
        ):
            found = match
    if not found:
        return None
    end_pos = len(text)
    for marker in ends:
        match = re.search(re.escape(marker), text[found.end() :], flags=re.IGNORECASE)
        if match:
            end_pos = min(end_pos, found.end() + match.start())
    return compact(text[found.end() : end_pos].strip(" :-")) or None


def list_values(text: str | None) -> list[str]:
    if not text:
        return []
    parts = re.split(r"\s*[✓✕●]\s*", text)
    return [compact(part) for part in parts if compact(part)]


def extract_item(reader: PdfReader, spec: ItemSpec) -> dict[str, object]:
    raw = " ".join(
        (reader.pages[i - 1].extract_text() or "")
        for i in range(spec.first_page, spec.last_page + 1)
    )
    text = compact(raw)
    start = text.lower().find(compact(spec.start).lower())
    if start < 0:
        raise RuntimeError(
            f"Start marker not found for {spec.dimension}/{spec.title}: {spec.start}"
        )
    text = text[start + len(compact(spec.start)) :]
    if spec.end:
        end = text.lower().find(compact(spec.end).lower())
        if end >= 0:
            text = text[:end]
    text = compact(text)

    description = between(
        text, ["Descrição"], ["Características", "Evitar", "Diretriz Completa"]
    )
    summary = (
        between(
            text,
            [
                "Essência Arquitetônica",
                "Essência Material",
                "Experiência Principal",
                "Hierarquia Principal",
            ],
            ["Diferença Principal", "Características", "Evitar", "Diretriz Completa"],
        )
        or description
    )
    difference = between(
        text,
        ["Diferença Principal"],
        ["Descrição", "Características", "Evitar", "Diretriz Completa"],
    )
    characteristics_text = between(
        text,
        ["Características"],
        ["Evitar", "Princípio Fundamental", "Diretriz Completa"],
    )
    avoid_text = between(
        text, ["Evitar"], ["Princípio Fundamental", "Diretriz Completa"]
    )
    directive = between(
        text,
        ["Diretriz Completa (Ver Mais)", "Diretriz Completa"],
        ["Camada Operacional", "Estrutura Técnica (Interna)", "Aplicação na IMPROOV"],
    )
    principle = between(
        text,
        ["Princípio Fundamental"],
        [
            "Diretriz Completa",
            "Camada Operacional",
            "Estrutura Técnica (Interna)",
            "Aplicação na IMPROOV",
        ],
    )
    reinforce = between(
        directive or "",
        [
            "O que reforça essa atmosfera?",
            "O que reforça essa linguagem luminosa?",
            "O que reforça essa linguagem?",
            "Quais elementos reforçam essa linguagem?",
            "O que reforça essa materialidade?",
            "O que reforça essa experiência?",
            "O que reforça essa composição?",
        ],
        ["O que enfraquece", "Quais elementos enfraquecem"],
    )
    weaken = between(
        directive or "",
        [
            "O que enfraquece essa atmosfera?",
            "O que enfraquece essa linguagem luminosa?",
            "O que enfraquece essa linguagem?",
            "Quais elementos enfraquecem essa linguagem?",
            "O que enfraquece essa materialidade?",
            "O que enfraquece essa experiência?",
            "O que enfraquece essa composição?",
        ],
        [
            "Princípio Fundamental",
            "Camada Operacional",
            "Estrutura Técnica (Interna)",
            "Aplicação na IMPROOV",
        ],
    )
    complementary = between(
        text,
        ["Camada Operacional", "Estrutura Técnica (Interna)", "Aplicação na IMPROOV"],
        [],
    )

    return {
        "title": spec.title,
        "code": slug(spec.title),
        "summary": summary,
        "difference": difference,
        "description": description,
        "principle": principle,
        "directive": directive,
        "source": text,
        "sections": [
            (
                "caracteristicas",
                "Características",
                None,
                "CARACTERISTICA",
                list_values(characteristics_text),
            ),
            ("reforca", "Reforça", None, "REFORCA", list_values(reinforce)),
            (
                "evitar",
                "Evitar / enfraquece",
                None,
                "EVITAR",
                list_values(avoid_text or weaken),
            ),
            ("complementar", "Conteúdo complementar", complementary, "ITEM", []),
            ("fonte_oficial", "Conteúdo oficial integral", text, "ITEM", []),
        ],
    }


def generate(pdf: Path) -> str:
    reader = PdfReader(str(pdf))
    if len(reader.pages) != 137:
        raise RuntimeError(f"Expected 137 pages, found {len(reader.pages)}")
    checksum = hashlib.sha256(pdf.read_bytes()).hexdigest()
    out = [
        "-- Biblioteca Oficial ALMA v1.0 - seed imutavel.",
        "-- Gerado de Biblioteca Oficial ALMA v1.0.pdf por ALMA/scripts/build_library_v1_seed.py.",
        "-- O conteudo oficial existe uma unica vez na Biblioteca; revisoes guardam somente vinculos e contexto.",
        "START TRANSACTION;",
        "INSERT INTO alma_biblioteca_versao (codigo, nome, estado, origem_documento, checksum_origem, criada_em, publicada_em)",
        f"VALUES ('1.0', 'ALMA Library v1.0', 'PUBLICADA', 'Biblioteca Oficial ALMA v1.0.pdf', '{checksum}', NOW(), NOW())",
        "ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);",
        "SET @alma_v1 = (SELECT id FROM alma_biblioteca_versao WHERE codigo = '1.0' LIMIT 1);",
    ]
    for (
        code,
        parent,
        stage_code,
        stage_name,
        pillar_code,
        pillar_name,
        name,
        kind,
        journey,
        inner_order,
        multiple,
        requires_item,
    ) in DIMENSIONS:
        parent_sql = "NULL" if parent is None else "@alma_parent_dim"
        if parent is not None:
            out.append(
                "SET @alma_parent_dim = (SELECT id FROM alma_biblioteca_dimensao "
                f"WHERE versao_id=@alma_v1 AND codigo={sql(parent)} LIMIT 1);"
            )
        out.extend(
            [
                "INSERT IGNORE INTO alma_biblioteca_dimensao",
                "(versao_id, dimensao_pai_id, codigo, etapa_codigo, etapa_nome, pilar_codigo, pilar_nome, nome, tipo_conteudo, ordem_jornada, ordem_no_pilar, permite_multiplas, exige_item_biblioteca)",
                f"VALUES (@alma_v1, {parent_sql}, {sql(code)}, {sql(stage_code)}, {sql(stage_name)}, {sql(pillar_code)}, {sql(pillar_name)}, {sql(name)}, {sql(kind)}, {journey}, {inner_order}, {multiple}, {requires_item});",
            ]
        )

    for order, title in enumerate(MOMENTOS_LUZ, 1):
        out.extend(
            [
                "SET @alma_dim = (SELECT id FROM alma_biblioteca_dimensao WHERE versao_id=@alma_v1 AND codigo='luz_momento' LIMIT 1);",
                "INSERT IGNORE INTO alma_biblioteca_item (dimensao_id, codigo, titulo, resumo, ordem)",
                f"VALUES (@alma_dim, {sql(slug(title))}, {sql(title)}, {sql('Momento da Luz: ' + title + '.')}, {order});",
            ]
        )

    per_dimension_order: dict[str, int] = {}
    for spec in ITEMS:
        item = extract_item(reader, spec)
        per_dimension_order[spec.dimension] = (
            per_dimension_order.get(spec.dimension, 0) + 1
        )
        out.extend(
            [
                f"SET @alma_dim = (SELECT id FROM alma_biblioteca_dimensao WHERE versao_id=@alma_v1 AND codigo={sql(spec.dimension)} LIMIT 1);",
                "INSERT IGNORE INTO alma_biblioteca_item",
                "(dimensao_id, codigo, titulo, resumo, diferenca_principal, descricao, principio_fundamental, diretriz_completa, ordem)",
                f"VALUES (@alma_dim, {sql(item['code'])}, {sql(item['title'])}, {sql(item['summary'])}, {sql(item['difference'])}, {sql(item['description'])}, {sql(item['principle'])}, {sql(item['directive'])}, {per_dimension_order[spec.dimension]});",
                f"SET @alma_item = (SELECT id FROM alma_biblioteca_item WHERE dimensao_id=@alma_dim AND codigo={sql(item['code'])} LIMIT 1);",
            ]
        )
        section_order = 0
        for section_code, section_title, content, entry_type, entries in item[
            "sections"
        ]:
            if not content and not entries:
                continue
            section_order += 1
            out.extend(
                [
                    "INSERT IGNORE INTO alma_biblioteca_item_secao (item_id, codigo, titulo, conteudo, ordem)",
                    f"VALUES (@alma_item, {sql(section_code)}, {sql(section_title)}, {sql(content)}, {section_order});",
                    f"SET @alma_secao = (SELECT id FROM alma_biblioteca_item_secao WHERE item_id=@alma_item AND codigo={sql(section_code)} LIMIT 1);",
                ]
            )
            for entry_order, entry in enumerate(entries, 1):
                out.append(
                    "INSERT INTO alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem) "
                    f"SELECT @alma_secao, {sql(entry_type)}, {sql(entry)}, {entry_order} "
                    "WHERE NOT EXISTS (SELECT 1 FROM alma_biblioteca_secao_entrada WHERE secao_id=@alma_secao AND ordem="
                    f"{entry_order});"
                )
    out.extend(["COMMIT;", ""])
    return "\n".join(out)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("pdf", type=Path)
    parser.add_argument("output", type=Path)
    args = parser.parse_args()
    args.output.write_text(generate(args.pdf), encoding="utf-8", newline="\n")


if __name__ == "__main__":
    main()
