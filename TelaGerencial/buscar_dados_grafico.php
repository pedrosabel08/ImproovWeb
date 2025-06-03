<?php
include '../conexao.php';

$mes = $_GET['mes'] ?? date('m');
$ano = date('Y');

// 1. Total de produção por função no mês atual
$sqlTotal = "SELECT 
    f.nome_funcao, 
    COUNT(*) AS total_mes
FROM funcao_imagem fi
JOIN funcao f ON f.idfuncao = fi.funcao_id
WHERE MONTH(fi.prazo) = 05 AND YEAR(fi.prazo) = ? AND fi.valor > 1 AND fi.funcao_id = 4
GROUP BY f.nome_funcao";
$stmtTotal = $conn->prepare($sqlTotal);
$stmtTotal->bind_param("i",  $ano);
$stmtTotal->execute();
$resTotal = $stmtTotal->get_result();
$dadosTotais = $resTotal->fetch_all(MYSQLI_ASSOC);

// 2. Recorde de produção por função (maior mês da história)
$sqlRecorde = "SELECT 
    nome_funcao, 
    MAX(qtd_mes) AS recorde
FROM (
    SELECT 
        f.nome_funcao, 
        COUNT(*) AS qtd_mes
    FROM funcao_imagem fi
    JOIN funcao f ON f.idfuncao = fi.funcao_id
    WHERE fi.prazo <> '0000-00-00' AND fi.valor > 1 AND fi.funcao_id = 4
    GROUP BY f.nome_funcao, MONTH(fi.prazo), YEAR(fi.prazo)
) AS sub
GROUP BY nome_funcao";
$resRecorde = $conn->query($sqlRecorde);
$recordes = $resRecorde->fetch_all(MYSQLI_ASSOC);
$recordeIndexado = [];
foreach ($recordes as $rec) {
    $recordeIndexado[$rec['nome_funcao']] = $rec['recorde'];
}

// 3. Contribuição por colaborador no mês atual
$sqlContrib = "SELECT 
    f.nome_funcao, 
    c.nome_colaborador, 
    COUNT(*) AS quantidade
FROM funcao_imagem fi
JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
JOIN funcao f ON f.idfuncao = fi.funcao_id
WHERE MONTH(fi.prazo) = 05 AND YEAR(fi.prazo) = ? AND fi.valor > 1 AND fi.funcao_id = 4
GROUP BY f.nome_funcao, c.nome_colaborador";
$stmtContrib = $conn->prepare($sqlContrib);
$stmtContrib->bind_param("i",  $ano);
$stmtContrib->execute();
$resContrib = $stmtContrib->get_result();
$contribuicoes = $resContrib->fetch_all(MYSQLI_ASSOC);

// Organiza os dados finais
$resultado = [];

foreach ($dadosTotais as $linha) {
    $funcao = $linha['nome_funcao'];
    $total = $linha['total_mes'];
    $meta = $recordeIndexado[$funcao] ?? 0;

    // Pegamos contribuições individuais
    $contribuintes = array_filter($contribuicoes, fn($c) => $c['nome_funcao'] === $funcao);
    $colabs = [];
    foreach ($contribuintes as $c) {
        $porcentagem = round(($c['quantidade'] / $total) * 100, 1);
        $colabs[] = [
            'nome' => $c['nome_colaborador'],
            'quantidade' => $c['quantidade'],
            'porcentagem' => $porcentagem
        ];
    }

    $resultado[] = [
        'funcao' => $funcao,
        'total_mes' => $total,
        'recorde' => $meta,
        'colaboradores' => $colabs
    ];
}

header('Content-Type: application/json');
echo json_encode($resultado);
