<?php

header("Access-Control-Allow-Origin: *"); // Allows all domains
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Allow specific methods
header("Access-Control-Allow-Headers: Content-Type");

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

date_default_timezone_set('America/Sao_Paulo');

// Verificar se os dados foram enviados via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $colaborador_id = $_POST['final_id'];
    $cliente_id = $_POST['cliente_id'];
    $obra_id = $_POST['obra_id'];
    $data_pos = date('Y-m-d H:i');
    $imagem_id = $_POST['imagem_id'];
    $caminho_pasta = $_POST['caminho_pasta'];
    $numero_bg = $_POST['numero_bg'];
    $refs = $_POST['refs'];
    $obs = $_POST['obs'];
    $status_pos = isset($_POST['status_pos']) ? 0 : 1; // Define status_pos com base no checkbox
    $status_id = $_POST['status_id'];

    // Buscar o nome da imagem
    $sql_imagem = "SELECT imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?";
    $stmt_imagem = $conn->prepare($sql_imagem);
    $stmt_imagem->bind_param("i", $imagem_id);
    $stmt_imagem->execute();
    $resultado_imagem = $stmt_imagem->get_result();
    $nome_imagem = $resultado_imagem->fetch_assoc()['imagem_nome'] ?? 'Imagem não encontrada';

    // Buscar o nome da obra
    $sql_obra = "SELECT nome_obra FROM obra WHERE idobra = ?";
    $stmt_obra = $conn->prepare($sql_obra);
    $stmt_obra->bind_param("i", $obra_id);
    $stmt_obra->execute();
    $resultado_obra = $stmt_obra->get_result();
    $nome_obra = $resultado_obra->fetch_assoc()['nome_obra'] ?? 'Obra não encontrada';

    // Inserir ou atualizar dados
    $sql = "INSERT INTO pos_producao (colaborador_id, cliente_id, obra_id, data_pos, imagem_id, caminho_pasta, numero_bg, refs, obs, status_pos, status_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                colaborador_id = VALUES(colaborador_id),
                cliente_id = VALUES(cliente_id),
                obra_id = VALUES(obra_id),
                data_pos = VALUES(data_pos),
                caminho_pasta = VALUES(caminho_pasta),
                numero_bg = VALUES(numero_bg),
                refs = VALUES(refs),
                obs = VALUES(obs),
                status_pos = VALUES(status_pos),
                status_id = VALUES(status_id)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiissssssii", $colaborador_id, $cliente_id, $obra_id, $data_pos, $imagem_id, $caminho_pasta, $numero_bg, $refs, $obs, $status_pos, $status_id);

    if ($stmt->execute()) {
        echo "Dados inseridos ou atualizados com sucesso!";

        // Definir a mensagem com base no status_pos
        if ($status_pos == 1) {
            $mensagem = "Status não começou para a imagem '$nome_imagem' na obra '$nome_obra' na data $data_pos";
        } else {
            $mensagem = "Status atualizado para a imagem '$nome_imagem' na obra '$nome_obra' na data $data_pos";
        }

        // Inserir notificação
        $sql_notificacao = "INSERT INTO notificacoes (mensagem) VALUES (?)";
        $stmt_notificacao = $conn->prepare($sql_notificacao);
        $stmt_notificacao->bind_param("s", $mensagem);
        if ($stmt_notificacao->execute()) {
            $notificacao_id = $stmt_notificacao->insert_id; // Pega o ID da notificação inserida

            // Associar notificação apenas aos usuários com id 1 e 2
            $usuarios_notificar = [1, 2]; // IDs dos usuários que devem receber a notificação

            foreach ($usuarios_notificar as $usuario_id) {
                $sql_notificacao_usuario = "INSERT INTO notificacoes_usuarios (notificacao_id, usuario_id) VALUES (?, ?)";
                $stmt_notificacao_usuario = $conn->prepare($sql_notificacao_usuario);
                $stmt_notificacao_usuario->bind_param("ii", $notificacao_id, $usuario_id);
                $stmt_notificacao_usuario->execute();
            }
        }
    } else {
        echo "Erro ao inserir ou atualizar dados: " . $conn->error;
    }

    $stmt->close();
    $stmt_imagem->close();
    $stmt_obra->close();
    $conn->close();
}
