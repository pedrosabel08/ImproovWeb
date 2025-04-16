<?php
include '../conexao.php'; // Conexão com o banco

$sqlImagens = "SELECT img.tipo_imagem, img.imagem_nome
FROM imagens_cliente_obra img
JOIN obra o ON img.obra_id = o.idobra
WHERE o.idobra = 55
  AND EXISTS (
      SELECT 1
      FROM arquivos a
      WHERE a.obra_id = o.idobra
        AND a.tipo = img.tipo_imagem
  )
ORDER BY img.tipo_imagem, img.imagem_nome";
$resultImagens = $conn->query($sqlImagens);

$sqlEtapas = "SELECT etapa, tipo_imagem, data_inicio, data_fim 
              FROM gantt_prazos 
              WHERE obra_id = 55";
$resultEtapas = $conn->query($sqlEtapas);

// Determinar intervalo de datas
$sqlDatas = "SELECT MIN(data_inicio) as primeira_data, MAX(data_fim) as ultima_data FROM gantt_prazos WHERE obra_id = 55 AND data_inicio <> '0000-00-00' AND data_fim <> '0000-00-00'";
$resultDatas = $conn->query($sqlDatas);
$rowDatas = $resultDatas->fetch_assoc();
$primeiraData = new DateTime($rowDatas['primeira_data']);
$ultimaData = new DateTime($rowDatas['ultima_data']);

date_default_timezone_set('America/Sao_Paulo');
$periodo = new DatePeriod($primeiraData, new DateInterval('P1D'), $ultimaData->modify('+1 day'));
$datas = [];
foreach ($periodo as $data) {
    $datas[] = $data->format('d/m');
}

// Organizar os dados
$imagens = [];
while ($row = $resultImagens->fetch_assoc()) {
    $imagens[$row['tipo_imagem']][] = $row['imagem_nome'];
}

$etapas = [];
while ($row = $resultEtapas->fetch_assoc()) {
    $etapas[$row['tipo_imagem']][] = $row;
}

// Criar a tabela
$html = '<table border="1">';
$html .= '<tr><th>Nome da Imagem</th>';
foreach ($datas as $data) {
    $html .= "<th>$data</th>";
}
$html .= '</tr>';

foreach ($imagens as $tipoImagem => $nomesImagens) {
    $rowspan = count($nomesImagens);
    $firstRow = true;

    foreach ($nomesImagens as $imagemNome) {
        $html .= "<tr><td>$imagemNome</td>";

        if ($firstRow && isset($etapas[$tipoImagem])) {
            $firstRow = false;
            $diasUsados = 0;
            foreach ($etapas[$tipoImagem] as $etapa) {
                $dataInicio = new DateTime($etapa['data_inicio']);
                $dataFim = new DateTime($etapa['data_fim']);
                $colspan = $dataInicio->diff($dataFim)->days + 1;
                $html .= "<td rowspan='$rowspan' colspan='$colspan' class='" . strtolower(str_replace(['ç', 'ã', 'á', 'é', 'í', 'ó', 'ú'], ['c', 'a', 'a', 'e', 'i', 'o', 'u'], $etapa['etapa'])) . "'>{$etapa['etapa']}</td>";
                $diasUsados += $colspan;
            }

            $diasRestantes = count($datas) - $diasUsados;
            if ($diasRestantes > 0) {
                $html .= "<td rowspan='$rowspan' colspan='$diasRestantes'></td>";
            }
        }
        $html .= "</tr>";
    }
}

$html .= '</table>';
$conn->close();

echo $html;
