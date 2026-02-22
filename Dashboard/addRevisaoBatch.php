<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
include '../conexao.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

function adicionarDiasUteis($dataInicial, $diasUteis)
{
    $diasAdicionados = 0;
    $data = strtotime($dataInicial);
    $feriadosFixos = ['01-01', '04-21', '05-01', '09-07', '10-12', '11-02', '11-15', '12-25'];

    while ($diasAdicionados < $diasUteis) {
        $data = strtotime('+1 day', $data);
        $diaSemana = date('N', $data);
        $mesDia = date('m-d', $data);
        $ano = date('Y', $data);

        if ($diaSemana >= 6) {
            continue;
        }
        if (in_array($mesDia, $feriadosFixos, true)) {
            continue;
        }
        if (in_array(date('Y-m-d', $data), feriadosMoveis($ano), true)) {
            continue;
        }

        $diasAdicionados++;
    }

    return date('Y-m-d', $data);
}

function feriadosMoveis($ano)
{
    $pascoa = easter_date($ano);
    return [
        date('Y-m-d', $pascoa),
        date('Y-m-d', strtotime('-2 days', $pascoa)),
        date('Y-m-d', strtotime('+60 days', $pascoa)),
        date('Y-m-d', strtotime('+47 days', $pascoa)),
        date('Y-m-d', strtotime('-48 days', $pascoa)),
        date('Y-m-d', strtotime('-49 days', $pascoa)),
    ];
}

function proximoStatusPorContagem($totalAlteracoes)
{
    switch ((int)$totalAlteracoes) {
        case 0:
            return 3;
        case 1:
            return 4;
        case 2:
            return 5;
        case 3:
            return 14;
        default:
            return 15;
    }
}

if (!$data || !isset($data['ids']) || !is_array($data['ids']) || empty($data['data_recebimento'])) {
    echo json_encode(['status' => 'erro', 'message' => 'Dados inválidos.']);
    exit;
}

$ids = array_values(array_unique(array_map('intval', $data['ids'])));
$colaboradorId = isset($data['colaborador_id']) && $data['colaborador_id'] !== '' ? (int)$data['colaborador_id'] : null;
$dataRecebimento = (string)$data['data_recebimento'];
$responsavelId = $_SESSION['idcolaborador'] ?? null;

$dataObj = DateTime::createFromFormat('Y-m-d', $dataRecebimento);
if (!$dataObj || $dataObj->format('Y-m-d') !== $dataRecebimento) {
    echo json_encode(['status' => 'erro', 'message' => 'Data de recebimento inválida.']);
    exit;
}

if (empty($ids)) {
    echo json_encode(['status' => 'erro', 'message' => 'Nenhuma imagem selecionada.']);
    exit;
}

$novoPrazo = adicionarDiasUteis($dataRecebimento, 7);
$mapaStatus = [
    3 => 'R01',
    4 => 'R02',
    5 => 'R03',
    14 => 'R04',
    15 => 'R05',
];

$conn->begin_transaction();

$statusPorImagem = []; // collect novo_status per imagem_id

try {
    $stmtObra = $conn->prepare('SELECT obra_id, imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?');
    $stmtCheckFuncao = $conn->prepare('SELECT idfuncao_imagem FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = 6');
    $stmtInsertFuncao = $conn->prepare('INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id) VALUES (?, NULL, 6)');
    $stmtUpdateColab = $conn->prepare('UPDATE funcao_imagem SET colaborador_id = ? WHERE idfuncao_imagem = ?');
    $stmtCountAlt = $conn->prepare('SELECT COUNT(*) as total FROM alteracoes WHERE funcao_id = ?');
    $stmtUpdateImagem = $conn->prepare('UPDATE imagens_cliente_obra SET status_id = ?, prazo = ? WHERE idimagens_cliente_obra = ?');
    $stmtInsertEvento = $conn->prepare('INSERT INTO eventos_obra (descricao, data_evento, tipo_evento, obra_id, responsavel_id) VALUES (?, ?, ?, ?, ?)');
    $stmtInsertAlt = $conn->prepare('INSERT INTO alteracoes (funcao_id, data_recebimento, status_id) VALUES (?, ?, ?)');

    foreach ($ids as $imagemId) {
        $obraId = null;
        $nomeImagem = '';

        $stmtObra->bind_param('i', $imagemId);
        $stmtObra->execute();
        $resObra = $stmtObra->get_result();
        if ($rowObra = $resObra->fetch_assoc()) {
            $obraId = (int)$rowObra['obra_id'];
            $nomeImagem = (string)$rowObra['imagem_nome'];
        }

        $stmtCheckFuncao->bind_param('i', $imagemId);
        $stmtCheckFuncao->execute();
        $stmtCheckFuncao->bind_result($funcaoIdExistente);
        $temFuncao = $stmtCheckFuncao->fetch();
        $stmtCheckFuncao->free_result();

        if ($temFuncao && $funcaoIdExistente) {
            $funcaoId = (int)$funcaoIdExistente;
        } else {
            $stmtInsertFuncao->bind_param('i', $imagemId);
            $stmtInsertFuncao->execute();
            $funcaoId = (int)$conn->insert_id;
        }

        if ($colaboradorId !== null) {
            $stmtUpdateColab->bind_param('ii', $colaboradorId, $funcaoId);
            $stmtUpdateColab->execute();
        }

        $stmtCountAlt->bind_param('i', $funcaoId);
        $stmtCountAlt->execute();
        $resCount = $stmtCountAlt->get_result();
        $totalAlteracoes = (int)($resCount->fetch_assoc()['total'] ?? 0);

        $novoStatus = proximoStatusPorContagem($totalAlteracoes);

        $statusPorImagem[] = ['imagem_id' => $imagemId, 'novo_status' => $novoStatus];

        $stmtUpdateImagem->bind_param('isi', $novoStatus, $novoPrazo, $imagemId);
        $stmtUpdateImagem->execute();

        $statusNome = $mapaStatus[$novoStatus] ?? 'Desconhecido';
        $descricao = trim($nomeImagem) . " - Entrega Alteração ($statusNome)";
        $tipoEvento = 'Entrega';
        $stmtInsertEvento->bind_param('sssii', $descricao, $novoPrazo, $tipoEvento, $obraId, $responsavelId);
        $stmtInsertEvento->execute();

        $stmtInsertAlt->bind_param('isi', $funcaoId, $dataRecebimento, $novoStatus);
        $stmtInsertAlt->execute();
    }

    $stmtObra->close();
    $stmtCheckFuncao->close();
    $stmtInsertFuncao->close();
    $stmtUpdateColab->close();
    $stmtCountAlt->close();
    $stmtUpdateImagem->close();
    $stmtInsertEvento->close();
    $stmtInsertAlt->close();

    $conn->commit();
    echo json_encode(['status' => 'sucesso', 'message' => 'Revisões adicionadas com sucesso.', 'novo_prazo' => $novoPrazo, 'status_por_imagem' => $statusPorImagem]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['status' => 'erro', 'message' => $e->getMessage()]);
}

$conn->close();
