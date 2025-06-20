<?php
header('Content-Type: application/json');
require 'conexao.php';

// Parâmetros recebidos
$dataIdFuncoes   = json_decode($_POST['dataIdFuncoes'] ?? '[]', true);
$numeroImagem    = preg_replace('/\D/', '', $_POST['numeroImagem'] ?? '');
$nomenclatura    = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['nomenclatura'] ?? '');
$nomeFuncao      = $_POST['nome_funcao'] ?? '';
$processo        = strtoupper(substr($nomeFuncao, 0, 3)); // Ex: CAD, RES etc.

if (empty($dataIdFuncoes) || !$numeroImagem || !$nomenclatura || !$nomeFuncao) {
    echo json_encode(["error" => "Parâmetros insuficientes"]);
    exit;
}

$idFuncaoImagem = $dataIdFuncoes[0];

// Buscar índice de envio
$stmt = $conn->prepare("SELECT MAX(indice_envio) AS max_indice FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ?");
$stmt->bind_param("i", $idFuncaoImagem);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$indice_envio = ($result['max_indice'] ?? 0) + 1;
$stmt->close();

// Função para upload com nome customizado
function uploadImagem($imagem, $destino, $nomeFinalSemExtensao)
{
    if (!is_dir($destino)) {
        mkdir($destino, 0777, true);
    }

    if ($imagem['error'] == UPLOAD_ERR_OK) {
        $extensao = pathinfo($imagem['name'], PATHINFO_EXTENSION);
        $nomeFinal = $nomeFinalSemExtensao . '.' . $extensao;
        $caminhoDestino = $destino . '/' . $nomeFinal;

        if (move_uploaded_file($imagem['tmp_name'], $caminhoDestino)) {
            return $caminhoDestino;
        }
    }
    return false;
}

// Processar imagens
if (isset($_FILES['imagens'])) {
    $imagens = $_FILES['imagens'];
    $totalImagens = count($imagens['name']);
    $destino = 'uploads';
    $imagensEnviadas = [];

    for ($i = 0; $i < $totalImagens; $i++) {
        $numeroPrevia = $i + 1;

        $imagemAtual = [
            'name' => $imagens['name'][$i],
            'type' => $imagens['type'][$i],
            'tmp_name' => $imagens['tmp_name'][$i],
            'error' => $imagens['error'][$i],
            'size' => $imagens['size'][$i]
        ];

        $nomeFinalSemExt = "{$numeroImagem}.{$nomenclatura}-{$processo}-{$indice_envio}-{$numeroPrevia}";
        $imagem = uploadImagem($imagemAtual, $destino, $nomeFinalSemExt);

        if ($imagem) {
            $stmt = $conn->prepare("INSERT INTO historico_aprovacoes_imagens (funcao_imagem_id, imagem, indice_envio) 
                                    VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $idFuncaoImagem, $imagem, $indice_envio);
            if ($stmt->execute()) {
                $imagensEnviadas[] = $imagem;
            } else {
                echo json_encode(["error" => "Erro ao salvar no banco: " . $stmt->error]);
                exit;
            }
            $stmt->close();
        } else {
            echo json_encode(["error" => "Erro ao enviar a imagem {$imagemAtual['name']}"]);
            exit;
        }
    }

    // Atualizar status e prazo na tabela funcao_imagem
    $hoje = date('Y-m-d');
    $stmt = $conn->prepare("UPDATE funcao_imagem 
                            SET status = 'Em aprovação', prazo = ? 
                            WHERE idfuncao_imagem = ?");
    $stmt->bind_param("si", $hoje, $idFuncaoImagem);
    if (!$stmt->execute()) {
        echo json_encode(["error" => "Erro ao atualizar status/prazo: " . $stmt->error]);
        exit;
    }
    $stmt->close();

    echo json_encode([
        "success" => "Imagens enviadas com sucesso!",
        "indice_envio" => $indice_envio,
        "imagens" => $imagensEnviadas
    ]);
} else {
    echo json_encode(["error" => "Nenhuma imagem recebida"]);
}
