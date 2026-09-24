import fs from "node:fs/promises";
import { FileBlob, SpreadsheetFile } from "@oai/artifact-tool";

const outputDir = "outputs/relatorio_producao_funcoes_2025-01_a_2026-08";
const path = `${outputDir}/relatorio_producao_funcoes.xlsx`;
const workbook = await SpreadsheetFile.importXlsx(await FileBlob.load(path));
const sheet = workbook.worksheets.getItem("Produção");
const existing = sheet.getRange("A1:C145").values;
const years = new Map([[2025, []], [2026, []]]);
let activeYear = null;
for (const [yearCell, month, func] of existing) {
  if (yearCell === 2025 || yearCell === 2026) { activeYear = yearCell; continue; }
  if (!activeYear || !yearCell || !month || typeof func !== "number") continue;
  if (["Finalização Completa", "Finalização de Planta Humanizada"].includes(month)) continue;
  years.get(activeYear).push([yearCell, month, func]);
}
if (years.get(2025).length !== 60 || years.get(2026).length !== 40) {
  throw new Error(`Filtered row counts unexpected: 2025=${years.get(2025).length}, 2026=${years.get(2026).length}`);
}
console.log(`Native tables before: ${sheet.tables.items.length}`);
for (const table of [...sheet.tables.items]) table.delete();
console.log(`Native tables after deletion: ${sheet.tables.items.length}`);
sheet.getUsedRange()?.clear({applyTo:"all"});
sheet.showGridLines = false;
sheet.getRange("A1:C145").format.rowHeight = 20;

const header = ["Mês", "Função", "Quantidade"];
const section = (titleRow, headerRow, startRow, year, rows) => {
  sheet.getRange(`A${titleRow}`).values = [[year]];
  sheet.getRange(`A${titleRow}:C${titleRow}`).format = {
    fill: "#1F4E78",
    font: {name:"Arial", size:14, bold:true, color:"#FFFFFF"},
    verticalAlignment:"center",
  };
  sheet.getRange(`A${titleRow}:C${titleRow}`).format.rowHeight = 27;
  sheet.getRange(`A${headerRow}:C${headerRow}`).values = [header];
  sheet.getRange(`A${headerRow}:C${headerRow}`).format = {
    fill: "#156082",
    font: {name:"Arial", size:10, bold:true, color:"#FFFFFF"},
    horizontalAlignment:"center",
    verticalAlignment:"center",
    rowHeight: 23,
    borders: {bottom:{style:"thin", color:"#FFFFFF"}},
  };
  const endRow = startRow + rows.length - 1;
  sheet.getRange(`A${startRow}:C${endRow}`).values = rows;
  sheet.getRange(`A${startRow}:C${endRow}`).format = {
    font: {name:"Arial", size:10, color:"#202124"},
    verticalAlignment:"center",
    borders: {insideHorizontal:{style:"thin", color:"#D9E2F3"}},
  };
  sheet.getRange(`A${startRow}:A${endRow}`).format.horizontalAlignment = "left";
  sheet.getRange(`C${startRow}:C${endRow}`).format.numberFormat = "#,##0";
  sheet.getRange(`C${startRow}:C${endRow}`).format.horizontalAlignment = "right";
  for (let i = 0; i < rows.length; i += 2) {
    const row = startRow + i;
    sheet.getRange(`A${row}:C${row}`).format.fill = "#DDEBF2";
  }
  return endRow;
};

const end2025 = section(1, 2, 3, 2025, years.get(2025));
const dividerRow = end2025 + 1;
sheet.getRange(`A${dividerRow}:C${dividerRow}`).format = {
  borders: {top:{style:"medium", color:"#1F4E78"}},
  rowHeight: 8,
};
const title2026 = dividerRow + 1;
const end2026 = section(title2026, title2026 + 1, title2026 + 2, 2026, years.get(2026));
sheet.getRange("A:A").format.columnWidth = 18;
sheet.getRange("B:B").format.columnWidth = 40;
sheet.getRange("C:C").format.columnWidth = 16;
workbook.recalculate();
const summary = await workbook.inspect({kind:"workbook,sheet,table", maxChars:2200, tableMaxRows:4, tableMaxCols:3});
console.log(summary.ndjson);
const finalCheck = await workbook.inspect({kind:"table", range:`Produção!A1:C${end2026}`, include:"values", tableMaxRows:4, tableMaxCols:3, maxChars:2200});
console.log(finalCheck.ndjson);
const preview = await workbook.render({sheetName:"Produção", range:`A1:C${end2026}`, scale:1, format:"png"});
await fs.writeFile(`${outputDir}/preview_updated.png`, new Uint8Array(await preview.arrayBuffer()));
const file = await SpreadsheetFile.exportXlsx(workbook);
await file.save(path);
console.log(`Saved ${path}: 2025=${years.get(2025).length}, 2026=${years.get(2026).length}, no Excel tables.`);




