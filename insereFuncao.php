<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include 'conexao.php';

function emptyToNull($value)
{
    return ($value !== '' && $value !== null) ? $value : null;
}

function enviarNotificacao($colaborador_id, $mensagem, $conn)
{
    $stmt = $conn->prepare("INSERT INTO notificacoes (colaborador_id, mensagem) VALUES (?, ?)");
    $stmt->bind_param("is", $colaborador_id, $mensagem);

    if ($stmt->execute()) {
        $stmt->close();
        return "Notificação enviada para colaborador $colaborador_id: $mensagem";
    } else {
        $erro = $stmt->error;
        $stmt->close();
        throw new Exception("Erro ao enviar notificação: " . $erro);
    }
}

$data = $_POST;
$imagem_id = isset($data['imagem_id']) ? (int)$data['imagem_id'] : null;
$status_id = isset($data['status_id']) ? (int)$data['status_id'] : null;

$funcao_ids = [
    'Caderno' => 1,
    'Modelagem' => 2,
    'Composição' => 3,
    'Finalização' => 4,
    'Pós-Produção' => 5,
    'Alteração' => 6,
    'Planta Humanizada' => 7,
    'Filtro de assets' => 8,
    'Pré-Finalização' => 9
];

$funcao_parametros = [
    'Caderno' => 'caderno',
    'Modelagem' => 'modelagem',
    'Composição' => 'comp',
    'Finalização' => 'finalizacao',
    'Pós-Produção' => 'pos',
    'Alteração' => 'alteracao',
    'Planta Humanizada' => 'planta',
    'Filtro de assets' => 'filtro',
    'Pré-Finalização' => 'pre'
];

$ordem_funcoes = [1, 8, 2, 3, 9, 4, 5, 6, 7];
$funcao_concluida_id = null;

$conn->begin_transaction();

try {
    // Atualiza o status da imagem
    $update_image_status = $conn->prepare("UPDATE imagens_cliente_obra SET status_id = ? WHERE idimagens_cliente_obra = ?");
    $update_image_status->bind_param('ii', $status_id, $imagem_id);
    $update_image_status->execute();
    $update_image_status->close();

    // Prepara statement de insert/update
    $stmt = $conn->prepare("INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id, prazo, status, observacao, check_funcao)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE colaborador_id = VALUES(colaborador_id), prazo = VALUES(prazo), 
                            status = VALUES(status), observacao = VALUES(observacao), check_funcao = VALUES(check_funcao)");

    foreach ($funcao_ids as $funcao => $funcao_id) {
        $parametro = $funcao_parametros[$funcao];

        if (!empty($data[$parametro . '_id'])) {
            $colaborador_id = (int)emptyToNull($data[$parametro . '_id']);
            $prazo = emptyToNull($data['prazo_' . $parametro]);
            $status = emptyToNull($data['status_' . $parametro]);
            $obs = emptyToNull($data['obs_' . $parametro]);
            $check_funcao = !empty($data['check_' . $parametro]) && $data['check_' . $parametro] == 1 ? 1 : 0;

            // Verifica se o colaborador existe
            $check_colaborador = $conn->prepare("SELECT COUNT(*) FROM colaborador WHERE idcolaborador = ?");
            $check_colaborador->bind_param("i", $colaborador_id);
            $check_colaborador->execute();
            $check_colaborador->bind_result($exists);
            $check_colaborador->fetch();
            $check_colaborador->close();

            if (!$exists) {
                throw new Exception("Colaborador ID $colaborador_id não encontrado na tabela colaborador. parametro_id = {$parametro}_id");
            }

            $stmt->bind_param("iiisssi", $imagem_id, $colaborador_id, $funcao_id, $prazo, $status, $obs, $check_funcao);
            $stmt->execute();

            // Se função concluída, guardamos o ID
            if (strtolower(trim($status)) === 'finalizado' || strtolower(trim($status)) === 'aprovado' || strtolower(trim($status)) === 'aprovado com ajustes') {
                $funcao_concluida_id = $funcao_id;
            }
        }
    }

    $stmt->close();

    // Descobre a próxima função e envia notificação
    if ($funcao_concluida_id !== null) {
        $posicao = array_search($funcao_concluida_id, $ordem_funcoes);
        $notificacoes = [];
        // Procura a próxima função com colaborador cadastrado
        for ($i = $posicao + 1; $i < count($ordem_funcoes); $i++) {
            $proxima_funcao_id = $ordem_funcoes[$i];

            // Busca colaborador da próxima função (exceto colaborador_id 15)
            $proximo_stmt = $conn->prepare("SELECT colaborador_id FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = ? AND colaborador_id <> 15");
            $proximo_stmt->bind_param("ii", $imagem_id, $proxima_funcao_id);
            $proximo_stmt->execute();
            $proximo_stmt->bind_result($proximo_colaborador_id);
            $tem_colaborador = $proximo_stmt->fetch();
            $proximo_stmt->close();

            if ($tem_colaborador && !empty($proximo_colaborador_id)) {
                // Busca nome da função
                $stmtFuncao = $conn->prepare("SELECT nome_funcao FROM funcao WHERE idfuncao = ?");
                $stmtFuncao->bind_param("i", $proxima_funcao_id);
                $stmtFuncao->execute();
                $stmtFuncao->bind_result($nome_funcao);
                $stmtFuncao->fetch();
                $stmtFuncao->close();

                // Busca nome da imagem
                $stmtImagem = $conn->prepare("SELECT imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?");
                $stmtImagem->bind_param("i", $imagem_id);
                $stmtImagem->execute();
                $stmtImagem->bind_result($imagem_nome);
                $stmtImagem->fetch();
                $stmtImagem->close();

                $msg = "A função $nome_funcao da imagem $imagem_nome já pode ser iniciada. 🚀";
                $resultado_notificacao = enviarNotificacao($proximo_colaborador_id, $msg, $conn);
                $notificacoes[] = $resultado_notificacao;
                break; // Para no primeiro que encontrar
            }
        }
    }

    $conn->commit();
    echo json_encode([
        'success' => 'Dados inseridos/atualizados com sucesso!',
        'notificacoes' => $notificacoes
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => 'Erro ao executar a transação: ' . $e->getMessage()]);
}

$conn->close();
