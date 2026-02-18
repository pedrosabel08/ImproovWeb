<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
include '../conexao.php';

$data = json_decode(file_get_contents("php://input"), true);

function adicionarDiasUteis($dataInicial, $diasUteis)
{
    $diasAdicionados = 0;
    $data = strtotime($dataInicial);
    $feriadosFixos = ['01-01', '04-21', '05-01', '09-07', '10-12', '11-02', '11-15', '12-25'];

    while ($diasAdicionados < $diasUteis) {
        $data = strtotime("+1 day", $data);
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

if ($data && isset($data['imagem_id']) && !empty($data['data_recebimento'])) {
    $imagem_id = $data['imagem_id'];
    $colaborador_id = isset($data['colaborador_id']) && $data['colaborador_id'] !== '' ? (int)$data['colaborador_id'] : null;
    $responsavel_id = $_SESSION['idcolaborador'] ?? null;
    $obra_id = $data['obra_id'] ?? null;
    $nomenclatura = $data['nomenclatura'] ?? null;
    $data_recebimento = $data['data_recebimento'];

    $dataObj = DateTime::createFromFormat('Y-m-d', $data_recebimento);
    if (!$dataObj || $dataObj->format('Y-m-d') !== $data_recebimento) {
        echo json_encode([
            'status' => 'erro',
            'message' => 'Data de recebimento inválida.'
        ]);
        exit;
    }

    $conn->begin_transaction();

    try {
        // 1. Verifica se já existe funcao_imagem para essa imagem e função 6
        $stmtCheck = $conn->prepare("SELECT idfuncao_imagem FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = 6");
        $stmtCheck->bind_param("i", $imagem_id);
        $stmtCheck->execute();
        $stmtCheck->bind_result($idExistente);
        $stmtCheck->fetch();
        $stmtCheck->close();

        if ($idExistente) {
            $funcao_id = $idExistente;
        } else {
            // Se não existir, insere nova função
            $stmtFuncao = $conn->prepare("INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id) VALUES (?, NULL, 6)");
            $stmtFuncao->bind_param("i", $imagem_id);
            $stmtFuncao->execute();
            $funcao_id = $conn->insert_id;
            $stmtFuncao->close();
        }

        if ($colaborador_id !== null) {
            $stmtColab = $conn->prepare("UPDATE funcao_imagem SET colaborador_id = ? WHERE idfuncao_imagem = ?");
            $stmtColab->bind_param("ii", $colaborador_id, $funcao_id);
            $stmtColab->execute();
            $stmtColab->close();
        }

        // 2. Conta quantas alterações já existem para essa função
        $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM alteracoes WHERE funcao_id = ?");
        $stmtCount->bind_param("i", $funcao_id);
        $stmtCount->execute();
        $result = $stmtCount->get_result();
        $total_alteracoes = $result->fetch_assoc()['total'];
        $stmtCount->close();

        // 3. Define o novo status_id com base na contagem
        switch ($total_alteracoes) {
            case 0:
                $novo_status = 3;
                break; // R01
            case 1:
                $novo_status = 4;
                break; // R02
            case 2:
                $novo_status = 5;
                break; // R03
            case 3:
                $novo_status = 14;
                break; // R04
            default:
                $novo_status = 15;
                break; // R05+
        }

        // 4. Calcula novo prazo (7 dias úteis) a partir da data informada
        $novoPrazo = adicionarDiasUteis($data_recebimento, 7);

        // 5. Atualiza a imagem com o novo status e prazo
        $stmtUpdate = $conn->prepare("UPDATE imagens_cliente_obra SET status_id = ?, prazo = ? WHERE idimagens_cliente_obra = ?");
        $stmtUpdate->bind_param("isi", $novo_status, $novoPrazo, $imagem_id);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        // 6. Insere evento
        $mapa_status = [
            3 => 'R01',
            4 => 'R02',
            5 => 'R03',
            14 => 'R04',
            15 => 'R05'
        ];
        $nome_status = $mapa_status[$novo_status] ?? 'Desconhecido';
        $descricao = " $nomenclatura - Entrega Alteração ($nome_status)";
        $tipo_evento = "Entrega";

        $stmtEvento = $conn->prepare("INSERT INTO eventos_obra (descricao, data_evento, tipo_evento, obra_id, responsavel_id) VALUES (?, ?, ?, ?, ?)");
        $stmtEvento->bind_param("sssii", $descricao, $novoPrazo, $tipo_evento, $obra_id, $responsavel_id);
        $stmtEvento->execute();
        $stmtEvento->close();

        // 7. Insere a alteração na tabela alteracoes
        $stmtAlt = $conn->prepare("INSERT INTO alteracoes (funcao_id, data_recebimento, status_id) VALUES (?, ?, ?)");
        $stmtAlt->bind_param("isi", $funcao_id, $data_recebimento, $novo_status);
        $stmtAlt->execute();
        $stmtAlt->close();

        // 8. Confirma transação
        $conn->commit();

        echo json_encode([
            'status' => 'sucesso',
            'message' => "Imagem ID '$imagem_id' registrada com status '$novo_status'. Descrição: '$descricao'"
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'status' => 'erro',
            'message' => 'Erro ao executar as consultas: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'erro',
        'message' => 'Dados incompletos ou inválidos. Informe imagem_id e data_recebimento.'
    ]);
}

$conn->close();
