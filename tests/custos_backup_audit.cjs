// Read a local mysqldump without executing SQL or connecting to any database.
// Usage: node tests/custos_backup_audit.cjs backup.sql [output.json]
const fs = require("fs");
function parseDump(path) {
  const sql = fs.readFileSync(path, "utf8"),
    tables = {};
  const names = [
    "obra",
    "imagens_cliente_obra",
    "imagem_comercial",
    "servico_foto",
    "funcao_imagem",
    "funcao",
    "pagamento_itens",
    "pagamentos",
    "pagamento_eventos",
    "acompanhamento",
    "animacao",
    "funcao_animacao",
  ];
  for (const name of names) {
    const start = sql.indexOf("CREATE TABLE `" + name + "`");
    const ddl = sql.slice(start, sql.indexOf(";", start));
    const cols = [...ddl.matchAll(/^  `([^`]+)`/gm)].map((m) => m[1]);
    const rows = [];
    for (const match of sql.matchAll(
      new RegExp("INSERT INTO `" + name + "` VALUES ([\\s\\S]*?);\\r?\\n", "g"),
    )) {
      let str = match[1],
        values = [],
        token = "",
        quoted = false,
        wasQuoted = false,
        escape = false;
      const value = () => {
        const v = wasQuoted ? token : token === "NULL" ? null : Number(token);
        token = "";
        wasQuoted = false;
        return v;
      };
      for (let p = 0; p < str.length; p++) {
        const c = str[p];
        if (quoted) {
          if (escape) {
            token += { n: "\n", r: "\r", t: "\t", 0: "\0" }[c] ?? c;
            escape = false;
          } else if (c === "\\") escape = true;
          else if (c === "'") {
            if (str[p + 1] === "'") {
              token += "'";
              p++;
            } else quoted = false;
          } else token += c;
        } else if (c === "'") {
          quoted = true;
          wasQuoted = true;
        } else if (c === "(") {
          values = [];
        } else if (c === ",") {
          if (str[p - 1] !== ")") values.push(value());
        } else if (c === ")") {
          values.push(value());
          if (values.length !== cols.length)
            throw Error(
              name + ": column mismatch " + values.length + "/" + cols.length,
            );
          rows.push(Object.fromEntries(cols.map((k, i) => [k, values[i]])));
        } else token += c;
      }
    }
    tables[name] = rows;
  }
  return tables;
}
function project(t, id) {
  const images = t.imagens_cliente_obra.filter((i) => i.obra_id === id),
    ids = new Set(images.map((i) => i.idimagens_cliente_obra));
  const functions = Object.fromEntries(
    t.funcao.map((f) => [f.idfuncao, f.nome_funcao]),
  );
  const animation = Object.fromEntries(
    t.animacao.filter((a) => a.obra_id === id).map((a) => [a.idanimacao, a]),
  );
  const tasks = [
    ...t.funcao_imagem
      .filter((f) => ids.has(f.imagem_id))
      .map((f) => ({
        ...f,
        origem: "funcao_imagem",
        origem_id: f.idfuncao_imagem,
        nome_funcao: functions[f.funcao_id],
      })),
    ...t.acompanhamento
      .filter((a) => a.obra_id === id)
      .map((a) => ({
        ...a,
        origem: "acompanhamento",
        origem_id: a.idacompanhamento,
        nome_funcao: "Acompanhamento",
      })),
    ...t.funcao_animacao
      .filter((f) => animation[f.animacao_id])
      .map((f) => ({
        ...f,
        origem: "funcao_animacao",
        origem_id: f.id,
        imagem_id: animation[f.animacao_id].imagem_id,
        nome_funcao: "Animação",
      })),
  ];
  const keys = new Set(tasks.map((f) => f.origem + ":" + f.origem_id));
  const payments = Object.fromEntries(
    t.pagamentos.map((p) => [p.idpagamento, p]),
  );
  return {
    obra: t.obra.find((o) => o.idobra === id),
    imagens: images.map((i) => ({
      id: i.idimagens_cliente_obra,
      nome: i.imagem_nome,
      tipo: i.tipo_imagem,
      subtipo: i.subtipo_imagem,
    })),
    comercial: [
      ...t.imagem_comercial
        .filter((c) => c.obra_id === id)
        .map((c) => ({ ...c, categoria: "imagem" })),
      ...t.servico_foto
        .filter((c) => c.obra_id === id)
        .map((c) => ({ ...c, categoria: "foto", imagem_id: null })),
    ],
    tarefas: tasks,
    itens: t.pagamento_itens
      .filter((p) => keys.has(p.origem + ":" + p.origem_id))
      .map((p) => ({
        ...p,
        mes_ref: payments[p.pagamento_id]?.mes_ref,
        colaborador_id: payments[p.pagamento_id]?.colaborador_id,
      })),
  };
}
if (require.main === module) {
  const t = parseDump(process.argv[2]);
  const origins = {};
  for (const i of t.pagamento_itens) {
    const k = i.origem + " / " + (i.observacao || "sem observação");
    origins[k] = (origins[k] || 0) + 1;
  }
  const grouped = {};
  for (const i of t.pagamento_itens)
    (grouped[i.origem + ":" + i.origem_id] ??= []).push(i);
  const duplicates = Object.entries(grouped)
    .filter(([, v]) => v.length > 1)
    .map(([origem, rows]) => ({
      origem,
      rows: rows.map((r) => ({
        id: r.idpagamento_item,
        valor: r.valor,
        observacao: r.observacao,
        mes: t.pagamentos.find((p) => p.idpagamento === r.pagamento_id)
          ?.mes_ref,
      })),
    }));
  const candidates = t.obra
    .map((o) => {
      const p = project(t, o.idobra);
      return {
        id: o.idobra,
        nome: o.nomenclatura,
        receitas: p.comercial.length,
        itens: p.itens.length,
      };
    })
    .filter((o) => o.receitas && o.itens)
    .sort((a, b) => b.itens - a.itens);
  console.log(
    JSON.stringify(
      {
        counts: Object.fromEntries(
          Object.entries(t).map(([k, v]) => [k, v.length]),
        ),
        origins,
        duplicates,
        candidates,
      },
      null,
      2,
    ),
  );
  if (process.argv[3])
    fs.writeFileSync(
      process.argv[3],
      JSON.stringify(project(t, candidates[0].id), null, 2),
    );
}
module.exports = { parseDump, project };
