<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('ALMA_API_LIBRARY_ONLY', true);
require_once __DIR__ . '/../api.php';

function operational_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Falhou: ' . $message);
    }
    echo 'OK: ' . $message . PHP_EOL;
}

function operational_selection(array $revision, string $code): ?array
{
    foreach ($revision['selecoes'] ?? [] as $selection) {
        if ($selection['dimensao_codigo'] === $code) {
            return $selection;
        }
    }
    return null;
}

function operational_throws(callable $operation, string $expectedMessage, string $message): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        operational_test(str_contains($error->getMessage(), $expectedMessage), $message);
        return;
    }
    throw new RuntimeException('Falhou: ' . $message);
}

$conn = conectarBanco();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$admin = $conn->query("SELECT idusuario FROM usuario WHERE ativo=1 AND nivel_acesso=1 ORDER BY idusuario LIMIT 1")->fetch_assoc();
operational_test((bool) $admin, 'há usuário administrativo para o teste');
$_SESSION['logado'] = true;
$_SESSION['idusuario'] = (int) $admin['idusuario'];

$version = alma_library_version($conn);
$library = alma_library_payload($conn, (int) $version['id']);
$dimensions = [];
foreach ($library['dimensoes'] as $dimension) {
    $dimensions[$dimension['codigo']] = $dimension;
}
operational_test(
    !$dimensions['fotografia_teste_angulos']['ativa'] && !$dimensions['fotografia_enquadramento']['ativa'] && $dimensions['fotografia_direcao']['ativa'],
    'somente Direção Fotográfica está ativa na experiência operacional'
);
operational_test(count($dimensions['fotografia_direcao']['itens']) > 0, 'Direção Fotográfica reutiliza itens administrados no SIRE');

$candidate = $conn->query(
    "SELECT i.obra_id, GROUP_CONCAT(i.idimagens_cliente_obra ORDER BY i.idimagens_cliente_obra) ids
       FROM imagens_cliente_obra i
       LEFT JOIN alma_direcao d ON d.imagem_id=i.idimagens_cliente_obra
       LEFT JOIN alma_projeto_direcao pd ON pd.obra_id=i.obra_id
      WHERE d.id IS NULL AND pd.id IS NULL AND (i.tipo_imagem IS NULL OR i.tipo_imagem <> 'Planta Humanizada')
      GROUP BY i.obra_id HAVING COUNT(*) >= 3 ORDER BY i.obra_id LIMIT 1"
)->fetch_assoc();
operational_test((bool) $candidate, 'há obra com três imagens elegíveis e sem ALMA para o teste isolado');
$obraId = (int) $candidate['obra_id'];
$imageIds = array_slice(array_map('intval', explode(',', $candidate['ids'])), 0, 3);
[$sourceImageId, $targetImageId, $batchImageId] = $imageIds;
$atmosphereTitle = (string) $dimensions['atmosfera']['itens'][0]['titulo'];
$stmtExistingValue = $conn->prepare(
    "SELECT pv.id
       FROM sire_pilar_valor pv
       JOIN sire_pilar p ON p.id=pv.pilar_id
      WHERE p.codigo='atmosfera' AND LOWER(pv.nome)=LOWER(?)
      LIMIT 1"
);
$stmtExistingValue->bind_param('s', $atmosphereTitle);
$stmtExistingValue->execute();
$existingAtmosphereValue = $stmtExistingValue->get_result()->fetch_assoc();
$stmtExistingValue->close();
$unclassifiedFilter = $existingAtmosphereValue
    ? ' WHERE NOT EXISTS (SELECT 1 FROM sire_referencia_valor rv WHERE rv.referencia_id=r.id AND rv.valor_id=' . (int) $existingAtmosphereValue['id'] . ')'
    : '';
$referenceRows = $conn->query('SELECT r.id FROM sire_referencia r' . $unclassifiedFilter . ' ORDER BY r.id LIMIT 2')->fetch_all(MYSQLI_ASSOC);
operational_test(count($referenceRows) === 2, 'há referências SIRE para os cenários');
$referenceIds = array_map(static fn(array $row): int => (int) $row['id'], $referenceRows);
$initialValueIds = array_map('intval', array_column($conn->query('SELECT id FROM sire_pilar_valor')->fetch_all(MYSQLI_ASSOC), 'id'));
$initialLinks = [];
$result = $conn->query('SELECT referencia_id, valor_id FROM sire_referencia_valor WHERE referencia_id IN (' . implode(',', $referenceIds) . ')');
while ($row = $result->fetch_assoc()) {
    $initialLinks[(int) $row['referencia_id'] . ':' . (int) $row['valor_id']] = true;
}

try {
    $imageSelections = [];
    foreach (ALMA_IMAGE_DIMENSIONS as $code) {
        operational_test(!empty($dimensions[$code]['itens'][0]['id']), $code . ' possui item selecionável');
        $imageSelections[] = ['dimensao_codigo' => $code, 'item_biblioteca_id' => (int) $dimensions[$code]['itens'][0]['id']];
    }
    $sourceDraft = alma_create_revision($conn, ['imagem_id' => $sourceImageId]);
    $sourceSaved = alma_save_revision($conn, [
        'revisao_id' => $sourceDraft['revisao']['id'],
        'lock_version' => $sourceDraft['revisao']['lock_version'],
        'intencao_geral' => '',
        'selecoes' => $imageSelections,
        'referencias' => [
            ['dimensao_codigo' => 'atmosfera', 'sire_referencia_id' => $referenceIds[0]],
            ['dimensao_codigo' => 'luz_momento', 'sire_referencia_id' => $referenceIds[1]],
        ],
    ]);
    operational_test($sourceSaved['revisao']['estado'] === 'ATIVA', 'Salvar ALMA persiste e torna a revisão vigente sem aprovação manual');
    operational_test(trim((string) $sourceSaved['revisao']['intencao_geral']) === '', 'Intenção Geral vazia não impede salvar');
    operational_test(count(array_filter($sourceSaved['revisao']['selecoes'], static fn(array $selection): bool => in_array($selection['dimensao_codigo'], ALMA_IMAGE_DIMENSIONS, true))) === 5, 'completo depende apenas das cinco decisões específicas');

    $atmosphere = operational_selection($sourceSaved['revisao'], 'atmosfera');
    $taxonomy = alma_dimension_and_item($conn, (int) $version['id'], 'atmosfera', (int) $atmosphere['item_biblioteca_id']);
    $value = alma_sire_value_for_item($conn, $taxonomy);
    $beforeIdempotent = (int) $conn->query('SELECT COUNT(*) n FROM sire_referencia_valor WHERE referencia_id=' . $referenceIds[0] . ' AND valor_id=' . $value['id'])->fetch_assoc()['n'];
    operational_test(!isset($initialLinks[$referenceIds[0] . ':' . $value['id']]) && $beforeIdempotent === 1, 'salvar ALMA adiciona classificação SIRE ausente');
    alma_classify_references($conn, $taxonomy, [$referenceIds[0]]);
    $afterIdempotent = (int) $conn->query('SELECT COUNT(*) n FROM sire_referencia_valor WHERE referencia_id=' . $referenceIds[0] . ' AND valor_id=' . $value['id'])->fetch_assoc()['n'];
    operational_test($beforeIdempotent === 1 && $afterIdempotent === 1, 'classificação SIRE é idempotente pela chave referencia + valor');

    $differentAtmosphere = $dimensions['atmosfera']['itens'][1];
    $targetDraft = alma_create_revision($conn, ['imagem_id' => $targetImageId]);
    alma_save_revision($conn, [
        'revisao_id' => $targetDraft['revisao']['id'],
        'lock_version' => $targetDraft['revisao']['lock_version'],
        'selecoes' => [
            ['dimensao_codigo' => 'atmosfera', 'item_biblioteca_id' => (int) $differentAtmosphere['id']],
            ['dimensao_codigo' => 'composicao', 'item_biblioteca_id' => (int) $dimensions['composicao']['itens'][1]['id']],
        ],
        'referencias' => [],
    ]);
    operational_throws(
        static fn() => alma_copy_from_image($conn, [
            'imagem_origem_id' => $sourceImageId,
            'imagem_destino_id' => $targetImageId,
            'dimensoes' => ['atmosfera'],
            'confirmar_conflitos' => false,
        ]),
        'Confirme explicitamente',
        'usar base bloqueia conflito sem confirmação explícita'
    );
    $copied = alma_copy_from_image($conn, [
        'imagem_origem_id' => $sourceImageId,
        'imagem_destino_id' => $targetImageId,
        'dimensoes' => ['atmosfera', 'luz_momento'],
        'confirmar_conflitos' => true,
    ]);
    operational_test(operational_selection($copied['revisao'], 'atmosfera')['item_codigo'] === $atmosphere['item_codigo'], 'usar base copia a dimensão escolhida');
    operational_test(operational_selection($copied['revisao'], 'luz_momento') !== null, 'usar base copia Luz escolhida');
    operational_test(operational_selection($copied['revisao'], 'composicao') !== null, 'usar base preserva dimensão não escolhida no destino');

    $batchDraft = alma_create_revision($conn, ['imagem_id' => $batchImageId]);
    alma_save_revision($conn, [
        'revisao_id' => $batchDraft['revisao']['id'],
        'lock_version' => $batchDraft['revisao']['lock_version'],
        'selecoes' => [[
            'dimensao_codigo' => 'luz_momento',
            'item_biblioteca_id' => (int) $dimensions['luz_momento']['itens'][1]['id'],
        ]],
        'referencias' => [],
    ]);
    operational_throws(
        static fn() => alma_apply_dimension($conn, [
            'imagem_origem_id' => $sourceImageId,
            'dimensao_codigo' => 'luz_momento',
            'imagens_destino_ids' => [$batchImageId],
            'conflitos_confirmados_ids' => [],
        ]),
        'Confirme explicitamente',
        'aplicar dimensão bloqueia conflito sem confirmação explícita'
    );
    $applied = alma_apply_dimension($conn, [
        'imagem_origem_id' => $sourceImageId,
        'dimensao_codigo' => 'luz_momento',
        'imagens_destino_ids' => [$batchImageId],
        'conflitos_confirmados_ids' => [$batchImageId],
    ]);
    $batchRevision = alma_direction_full($conn, $batchImageId)['revisao'];
    operational_test(operational_selection($batchRevision, 'luz_momento') !== null && count($batchRevision['selecoes']) === 1, 'aplicar para outras altera somente o bloco da dimensão');

    $projectSelections = [];
    foreach (ALMA_PROJECT_DIMENSIONS as $code) {
        $projectSelections[] = ['dimensao_codigo' => $code, 'item_biblioteca_id' => (int) $dimensions[$code]['itens'][0]['id']];
    }
    alma_save_project($conn, [
        'obra_id' => $obraId,
        'biblioteca_versao_id' => (int) $version['id'],
        'selecoes' => $projectSelections,
        'referencias' => [['dimensao_codigo' => 'arquitetura', 'sire_referencia_id' => $referenceIds[0]]],
    ]);
    $summary = alma_summary($conn, $sourceImageId);
    operational_test(count($summary['pilares']) === 7 && $summary['status'] === 'COMPLETO', 'resumo efetivo combina três globais e cinco decisões em sete pilares');

    $removeDraft = alma_create_revision($conn, ['imagem_id' => $sourceImageId, 'forcar_nova' => true]);
    $withoutAtmosphereReference = array_values(array_filter(
        $sourceSaved['revisao']['referencias'],
        static fn(array $reference): bool => !($reference['dimensao_codigo'] === 'atmosfera' && (int) $reference['sire_referencia_id'] === $referenceIds[0])
    ));
    alma_save_revision($conn, [
        'revisao_id' => $removeDraft['revisao']['id'],
        'lock_version' => $removeDraft['revisao']['lock_version'],
        'selecoes' => $imageSelections,
        'referencias' => array_map(static fn(array $reference): array => ['dimensao_codigo' => $reference['dimensao_codigo'], 'sire_referencia_id' => $reference['sire_referencia_id']], $withoutAtmosphereReference),
    ]);
    $stillClassified = (int) $conn->query('SELECT COUNT(*) n FROM sire_referencia_valor WHERE referencia_id=' . $referenceIds[0] . ' AND valor_id=' . $value['id'])->fetch_assoc()['n'];
    operational_test($stillClassified === 1, 'remover referência do ALMA não remove classificação do SIRE');

    $eligibleIds = array_column(alma_project_images($conn, $obraId), 'imagem_id');
    $plantCount = (int) $conn->query("SELECT COUNT(*) n FROM imagens_cliente_obra WHERE obra_id=$obraId AND tipo_imagem='Planta Humanizada' AND idimagens_cliente_obra IN (" . ($eligibleIds ? implode(',', array_map('intval', $eligibleIds)) : '0') . ')')->fetch_assoc()['n'];
    operational_test($plantCount === 0, 'Planta Humanizada é excluída pelo campo canônico tipo_imagem');
} finally {
    $conn->query('DELETE FROM alma_projeto_direcao WHERE obra_id=' . $obraId);
    $conn->query('DELETE FROM alma_direcao WHERE imagem_id IN (' . implode(',', $imageIds) . ')');
    $links = $conn->query('SELECT referencia_id, valor_id FROM sire_referencia_valor WHERE referencia_id IN (' . implode(',', $referenceIds) . ')')->fetch_all(MYSQLI_ASSOC);
    foreach ($links as $link) {
        $key = (int) $link['referencia_id'] . ':' . (int) $link['valor_id'];
        if (!isset($initialLinks[$key])) {
            $conn->query('DELETE FROM sire_referencia_valor WHERE referencia_id=' . (int) $link['referencia_id'] . ' AND valor_id=' . (int) $link['valor_id']);
        }
    }
    $newValues = array_values(array_diff(array_map('intval', array_column($conn->query('SELECT id FROM sire_pilar_valor')->fetch_all(MYSQLI_ASSOC), 'id')), $initialValueIds));
    foreach ($newValues as $valueId) {
        $used = (int) $conn->query('SELECT COUNT(*) n FROM sire_referencia_valor WHERE valor_id=' . $valueId)->fetch_assoc()['n'];
        if ($used === 0) {
            $conn->query('DELETE FROM sire_pilar_valor WHERE id=' . $valueId);
        }
    }
}

$conn->close();
echo "SMOKE ALMA OPERACIONAL: aprovado\n";
