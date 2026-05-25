<?php
require_once __DIR__ . '/../conexao.php'; // conexão com o banco
require_once __DIR__ . '/onboarding_helpers.php';

// Prepare response arrays for categories
$response = [
    'onboarding' => [],
    'hold' => [],
    'esperando' => [],
    'producao' => []
];

$onboardingProgress = dashboard_get_onboarding_progress($conn);

// First: get total images per obra (all images for active and onboarding obras)
$totalsQuery = "SELECT o.idobra, o.nomenclatura AS nome_obra, COUNT(*) AS total_obra_imagens
FROM imagens_cliente_obra i
JOIN obra o ON o.idobra = i.obra_id
WHERE o.status_obra IN (0, 2)
GROUP BY o.idobra";
$totalsResult = $conn->query($totalsQuery);
$totals = [];
if ($totalsResult) {
    while ($r = $totalsResult->fetch_assoc()) {
        $totals[$r['idobra']] = (int)$r['total_obra_imagens'];
    }
}

// Query: count images per obra grouped by category
$query = "SELECT 
    o.idobra,
    o.nomenclatura AS nome_obra,
    CASE
        WHEN i.substatus_id = 7 THEN 'HOLD'
        WHEN i.substatus_id = 2
             AND NOT EXISTS (
                  SELECT 1 FROM funcao_imagem fi 
                  WHERE fi.imagem_id = i.idimagens_cliente_obra 
                    AND fi.status <> 'Não iniciado'
              ) THEN 'Esperando iniciar'
        ELSE 'Em produção'
    END AS categoria,
    COUNT(*) AS total_imagens
FROM imagens_cliente_obra i
JOIN obra o ON o.idobra = i.obra_id
WHERE o.status_obra = 0
    AND i.substatus_id NOT IN (8,9,6)
GROUP BY o.idobra, categoria";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categoria = $row['categoria'];
        $idobra = (int) $row['idobra'];
        $pendingChecklistItems = isset($onboardingProgress[$idobra])
            ? (int) $onboardingProgress[$idobra]['pending_items']
            : 0;

        if ($pendingChecklistItems > 0) {
            continue;
        }

        $item = [
            'idobra' => $idobra,
            'nome_obra' => $row['nome_obra'],
            'total_imagens' => (int)$row['total_imagens'],
            'total_obra' => isset($totals[$idobra]) ? $totals[$idobra] : (int)$row['total_imagens'],
            'pending_checklist_items' => 0
        ];

        if ($categoria === 'HOLD') {
            $response['hold'][] = $item;
        } elseif ($categoria === 'Esperando iniciar') {
            $response['esperando'][] = $item;
        } else {
            $response['producao'][] = $item;
        }
    }
}

foreach ($onboardingProgress as $obraId => $state) {
    $pendingItems = (int) ($state['pending_items'] ?? 0);
    if ($pendingItems <= 0) {
        continue;
    }

    $response['onboarding'][] = [
        'idobra' => (int) $obraId,
        'nome_obra' => (string) ($state['nome_obra'] ?? ''),
        'total_imagens' => isset($totals[$obraId]) ? (int) $totals[$obraId] : 0,
        'total_obra' => isset($totals[$obraId]) ? (int) $totals[$obraId] : 0,
        'pending_checklist_items' => $pendingItems,
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
