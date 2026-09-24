import fs from "node:fs/promises";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const sourcePath = "TelaGerencial/relatorio_producao_funcoes_2025-01_a_2026-08.md";
const outputDir = "outputs/relatorio_producao_funcoes_2025-01_a_2026-08";
const source = await fs.readFile(sourcePath, "utf8");
const monthNames = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
const rows = source.split(/\r?\n/).flatMap((line) => {
  const match = line.match(/^\|\s*(\d{4})-(\d{2})\s*\|\s*(.*?)\s*\|\s*(\d+)\s*\|$/);
  if (!match || match[3].trim().toLocaleLowerCase("pt-BR") === "alteração") return [];
  const year = Number(match[1]);
  const month = Number(match[2]);
  return [[year, monthNames[month - 1], match[3].trim(), Number(match[4])]];
});
if (rows.length !== 100 || rows.some((r) => r[2].toLocaleLowerCase("pt-BR") === "alteração")) {
  throw new Error(`Unexpected extracted row count or excluded function. Found ${rows.length} rows.`);
}

const workbook = Workbook.create();
const sheet = workbook.worksheets.add("Produção");
sheet.showGridLines = false;
sheet.getRange(`A1:D${rows.length + 1}`).values = [["Ano", "Mês", "Função", "Quantidade"], ...rows];
sheet.getRange("A1:D1").format = {
  fill: "#1F4E78",
  font: { name: "Arial", size: 10, bold: true, color: "#FFFFFF" },
  horizontalAlignment: "center",
  verticalAlignment: "center",
};
sheet.getRange(`A2:D${rows.length + 1}`).format = {
  font: { name: "Arial", size: 10, color: "#202124" },
  verticalAlignment: "center",
};
sheet.getRange(`A2:B${rows.length + 1}`).format.horizontalAlignment = "center";
sheet.getRange(`D2:D${rows.length + 1}`).format.horizontalAlignment = "right";
sheet.getRange(`A2:A${rows.length + 1}`).format.numberFormat = "0";
sheet.getRange(`D2:D${rows.length + 1}`).format.numberFormat = "#,##0";
sheet.getRange(`A1:D${rows.length + 1}`).format.borders = {
  insideHorizontal: { style: "thin", color: "#D9E2F3" },
  bottom: { style: "thin", color: "#A6A6A6" },
};
sheet.getRange("A:A").format.columnWidth = 12;
sheet.getRange("B:B").format.columnWidth = 16;
sheet.getRange("C:C").format.columnWidth = 24;
sheet.getRange("D:D").format.columnWidth = 16;
sheet.getRange("A1:D1").format.rowHeight = 24;
sheet.freezePanes.freezeRows(1);
const table = sheet.tables.add(`A1:D${rows.length + 1}`, true, "ProducaoFuncoes");
table.style = "TableStyleMedium2";
table.showFilterButton = true;

workbook.recalculate();
const check = await workbook.inspect({
  kind: "table",
  range: `Produção!A1:D${rows.length + 1}`,
  include: "values",
  tableMaxRows: 8,
  tableMaxCols: 4,
  maxChars: 2500,
});
console.log(check.ndjson);
const render = await workbook.render({ sheetName: "Produção", range: "A1:D20", scale: 1, format: "png" });
await fs.writeFile(`${outputDir}/preview.png`, new Uint8Array(await render.arrayBuffer()));
const xlsx = await SpreadsheetFile.exportXlsx(workbook);
await xlsx.save(`${outputDir}/relatorio_producao_funcoes.xlsx`);
console.log(`Saved ${outputDir}/relatorio_producao_funcoes.xlsx with ${rows.length} data rows.`);

