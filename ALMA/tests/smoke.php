<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../alma_helpers.php';
require_once __DIR__ . '/../../conexaoMain.php';

function alma_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Falhou: ' . $message);
    }
    echo 'OK: ' . $message . PHP_EOL;
}

$conn = conectarBanco();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$admin = $conn->query("SELECT idusuario FROM usuario WHERE ativo = 1 AND nivel_acesso = 1 ORDER BY idusuario LIMIT 1")->fetch_assoc();
alma_test((bool) $admin, 'há um usuário administrativo ativo para validar o adaptador de capacidades');
$_SESSION['logado'] = true;
$_SESSION['idusuario'] = (int) $admin['idusuario'];

$permissions = alma_permissions($conn);
alma_test(count(array_filter($permissions)) === 4, 'as quatro capacidades conceituais são resolvidas pelo adaptador V1');

$version = alma_library_version($conn);
alma_test($version !== null && $version['codigo'] === '1.0' && $version['estado'] === 'PUBLICADA', 'Biblioteca ALMA v1.0 publicada está disponível');
$library = alma_library_payload($conn, (int) $version['id']);
alma_test(count($library['pilares']) === 7, 'a jornada possui exatamente sete pilares');
alma_test(array_column($library['pilares'], 'etapa_nome') === ['Sentir', 'Construir', 'Materializar', 'Iluminar', 'Viver', 'Observar', 'Contar'], 'a ordem oficial da jornada foi preservada');

$byCode = [];
foreach ($library['dimensoes'] as $dimension) {
    $byCode[$dimension['codigo']] = $dimension;
}
alma_test(isset($byCode['luz_momento'], $byCode['luz_linguagem']), 'Luz mantém Momento e Linguagem como dimensões separadas');
alma_test(isset($byCode['fotografia_direcao'], $byCode['fotografia_teste_angulos'], $byCode['fotografia_enquadramento'], $byCode['fotografia_referencias_sire']), 'Fotografia mantém suas quatro dimensões oficiais');
alma_test(count($byCode['atmosfera']['itens']) === 9 && count($byCode['luz_linguagem']['itens']) === 6, 'itens oficiais centrais foram importados');
$completeness = $conn->query(
    "SELECT COUNT(*) total,
            SUM(i.resumo IS NULL OR i.resumo='') sem_resumo,
            SUM(i.principio_fundamental IS NULL OR i.principio_fundamental='') sem_principio,
            SUM(i.diretriz_completa IS NULL OR i.diretriz_completa='') sem_diretriz
       FROM alma_biblioteca_item i
       JOIN alma_biblioteca_dimensao d ON d.id=i.dimensao_id
      WHERE d.codigo <> 'luz_momento'"
)->fetch_assoc();
alma_test((int) $completeness['total'] === 40 && (int) $completeness['sem_resumo'] === 0 && (int) $completeness['sem_principio'] === 0 && (int) $completeness['sem_diretriz'] === 0, 'os 40 itens detalhados mantêm resumo, princípio e diretriz oficiais');
$officialSources = (int) $conn->query(
    "SELECT COUNT(DISTINCT i.id) n
       FROM alma_biblioteca_item i
       JOIN alma_biblioteca_dimensao d ON d.id=i.dimensao_id
       JOIN alma_biblioteca_item_secao s ON s.item_id=i.id AND s.codigo='fonte_oficial'
      WHERE d.codigo <> 'luz_momento' AND s.conteudo IS NOT NULL"
)->fetch_assoc()['n'];
alma_test($officialSources === 40, 'cada item detalhado mantém o bloco integral de proveniência do PDF');

$candidate = $conn->query(
    'SELECT i.idimagens_cliente_obra AS id
       FROM imagens_cliente_obra i
       LEFT JOIN alma_direcao d ON d.imagem_id = i.idimagens_cliente_obra
      WHERE d.id IS NULL ORDER BY i.idimagens_cliente_obra LIMIT 1'
)->fetch_assoc();
alma_test((bool) $candidate, 'há uma imagem sem ALMA para o teste transacional');
$imageId = (int) $candidate['id'];
alma_test(alma_image_context($conn, $imageId) !== null, 'o contexto e a preview da imagem são resolvidos pelo modelo atual do Flow');
alma_test(alma_summary($conn, $imageId)['possui_alma'] === false, 'imagem sem direção retorna resumo mínimo e seguro');
alma_test(count(alma_sire_search($conn, '', 1)) > 0, 'a busca ALMA reutiliza referências existentes do SIRE');

$beforeDirections = (int) $conn->query('SELECT COUNT(*) n FROM alma_direcao')->fetch_assoc()['n'];
$conn->begin_transaction();
try {
    $actor = (int) $admin['idusuario'];
    $stmt = $conn->prepare('INSERT INTO alma_direcao (imagem_id, criada_por_usuario_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $imageId, $actor);
    $stmt->execute();
    $directionId = (int) $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO alma_direcao_revisao (direcao_id, numero, biblioteca_versao_id, estado, intencao_geral, sintese_narrativa, criada_por_usuario_id, atualizada_por_usuario_id) VALUES (?, 1, ?, 'RASCUNHO', 'Intenção de teste', 'Síntese de teste', ?, ?)");
    $versionId = (int) $version['id'];
    $stmt->bind_param('iiii', $directionId, $versionId, $actor, $actor);
    $stmt->execute();
    $revisionId = (int) $conn->insert_id;
    $stmt->close();

    $required = ['atmosfera', 'arquitetura', 'materialidade', 'luz_momento', 'luz_linguagem', 'lifestyle', 'fotografia_direcao', 'composicao'];
    foreach ($required as $order => $code) {
        $dimension = $byCode[$code];
        $dimensionId = (int) $dimension['id'];
        $itemId = $dimension['itens'][0]['id'] ?? null;
        $context = $code === 'fotografia_direcao' ? 'Direção fotográfica contextual de teste' : null;
        $application = 'Aplicação transacional de ' . $dimension['nome'];
        $stmt = $conn->prepare('INSERT INTO alma_revisao_selecao (revisao_id, dimensao_id, item_biblioteca_id, principal, resumo_contextual, aplicacao_imagem, ordem) VALUES (?, ?, ?, 1, ?, ?, ?)');
        $stmt->bind_param('iiissi', $revisionId, $dimensionId, $itemId, $context, $application, $order);
        $stmt->execute();
        $stmt->close();
    }

    $sire = $conn->query('SELECT id FROM sire_referencia ORDER BY id LIMIT 1')->fetch_assoc();
    if ($sire) {
        $dimensionId = (int) $byCode['luz_linguagem']['id'];
        $sireId = (int) $sire['id'];
        $stmt = $conn->prepare("INSERT INTO alma_revisao_referencia (revisao_id, dimensao_id, sire_referencia_id, representa, aplicar, nao_copiar, criada_por_usuario_id) VALUES (?, ?, ?, 'Contraste de temperatura', 'Aplicar transição suave', 'Não copiar mobiliário', ?)");
        $stmt->bind_param('iiii', $revisionId, $dimensionId, $sireId, $actor);
        $stmt->execute();
        $stmt->close();
    }
    alma_event($conn, $directionId, $revisionId, 'REVISAO', $revisionId, 'REVISAO_CRIADA', null, ['teste' => true]);

    $snapshot = alma_revision_snapshot($conn, $revisionId);
    alma_test($snapshot !== null && count($snapshot['selecoes']) === 8, 'revisão relacional é reconstruída com seleções e contexto');
    if ($sire) {
        alma_test(count($snapshot['referencias']) === 1 && $snapshot['referencias'][0]['representa'] === 'Contraste de temperatura', 'referência SIRE preserva interpretação e dimensão apoiada');
    }

    $stmt = $conn->prepare("UPDATE alma_direcao_revisao SET estado='ATIVA', ativa_token='ATIVA', ativada_em=NOW() WHERE id=?");
    $stmt->bind_param('i', $revisionId);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare('UPDATE alma_direcao SET revisao_ativa_id=? WHERE id=?');
    $stmt->bind_param('ii', $revisionId, $directionId);
    $stmt->execute();
    $stmt->close();

    $summary = alma_summary($conn, $imageId);
    alma_test($summary['possui_alma'] === true && count($summary['pilares']) === 7, 'resumo ativo da tarefa retorna somente os sete pilares da imagem');

    $stmt = $conn->prepare("UPDATE alma_direcao_revisao SET estado='SUBSTITUIDA', ativa_token=NULL WHERE id=?");
    $stmt->bind_param('i', $revisionId);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare("INSERT INTO alma_direcao_revisao (direcao_id, numero, biblioteca_versao_id, revisao_anterior_id, estado, criada_por_usuario_id, atualizada_por_usuario_id) VALUES (?, 2, ?, ?, 'ATIVA', ?, ?)");
    $stmt->bind_param('iiiii', $directionId, $versionId, $revisionId, $actor, $actor);
    $stmt->execute();
    $revision2 = (int) $conn->insert_id;
    $stmt->close();
    $stmt = $conn->prepare("UPDATE alma_direcao_revisao SET ativa_token='ATIVA', ativada_em=NOW() WHERE id=?");
    $stmt->bind_param('i', $revision2);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare('UPDATE alma_direcao SET revisao_ativa_id=? WHERE id=?');
    $stmt->bind_param('ii', $revision2, $directionId);
    $stmt->execute();
    $stmt->close();
    $chain = $conn->query('SELECT numero, revisao_anterior_id, estado FROM alma_direcao_revisao WHERE direcao_id=' . $directionId . ' ORDER BY numero')->fetch_all(MYSQLI_ASSOC);
    alma_test(count($chain) === 2 && $chain[0]['estado'] === 'SUBSTITUIDA' && (int) $chain[1]['revisao_anterior_id'] === $revisionId, 'nova revisão substitui a anterior preservando a cadeia histórica');

    $conn->rollback();
} catch (Throwable $error) {
    $conn->rollback();
    throw $error;
}

$afterDirections = (int) $conn->query('SELECT COUNT(*) n FROM alma_direcao')->fetch_assoc()['n'];
alma_test($afterDirections === $beforeDirections, 'o teste transacional não deixou dados artificiais no Flow');
$conn->close();
echo "SMOKE ALMA: aprovado\n";
