<?php
// inclui conexao central (mantém compatibilidade com PDO local abaixo)
include_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// OBS: este arquivo também usa PDO diretamente. Mantive a criação PDO
// para preservar o funcionamento atual. Se quiser, posso migrar
// a lógica para usar o `$conn` do `conexao.php` posteriormente.

$host = 'mysql.improov.com.br';
$db = 'improov';
$user = 'improov';
$pass = 'Impr00v';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["error" => "Erro na conexão com o banco de dados: " . $e->getMessage()]);
    exit;
}

// Verifica se recebeu o ID
if (!isset($_POST['dataIdFuncoes'])) {
    echo json_encode(["error" => "ID da função não recebido."]);
    exit;
}

$dataIdFuncoes = json_decode($_POST['dataIdFuncoes'], true);
$funcao_imagem_id = $dataIdFuncoes[0] ?? null;

if (!$funcao_imagem_id) {
    echo json_encode(["error" => "ID da função inválido."]);
    exit;
}

// Descobre próximo índice de envio
$query = $pdo->prepare("SELECT MAX(indice_envio) AS max_indice FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = :funcao_imagem_id");
$query->execute([':funcao_imagem_id' => $funcao_imagem_id]);
$result = $query->fetch(PDO::FETCH_ASSOC);
$indice_envio = ($result['max_indice'] ?? 0) + 1;

// Cria pasta se necessário
function uploadImagem($imagem, $destino)
{
    if (!is_dir($destino)) {
        mkdir($destino, 0777, true);
    }

    if ($imagem['error'] === UPLOAD_ERR_OK) {
        $extensao = pathinfo($imagem['name'], PATHINFO_EXTENSION);
        $nomeArquivo = uniqid('imagem_') . '.' . $extensao;
        $caminhoDestino = $destino . '/' . $nomeArquivo;

        if (move_uploaded_file($imagem['tmp_name'], $caminhoDestino)) {
            return $caminhoDestino;
        }
    }
    return false;
}

// Envio das imagens
if (isset($_FILES['imagens'])) {
    $imagens = $_FILES['imagens'];
    $total = count($imagens['name']);
    $destino = 'uploads';
    $enviadas = [];

    for ($i = 0; $i < $total; $i++) {
        $atual = [
            'name' => $imagens['name'][$i],
            'type' => $imagens['type'][$i],
            'tmp_name' => $imagens['tmp_name'][$i],
            'error' => $imagens['error'][$i],
            'size' => $imagens['size'][$i]
        ];

        $caminho = uploadImagem($atual, $destino);

        if ($caminho) {
            try {
                $insert = $pdo->prepare("
                    INSERT INTO historico_aprovacoes_imagens (funcao_imagem_id, imagem, indice_envio) 
                    VALUES (:funcao_imagem_id, :imagem, :indice_envio)
                ");
                $insert->execute([
                    ':funcao_imagem_id' => $funcao_imagem_id,
                    ':imagem' => $caminho,
                    ':indice_envio' => $indice_envio
                ]);
                $enviadas[] = $caminho;
            } catch (PDOException $e) {
                echo json_encode(["error" => "Erro ao salvar imagem: " . $e->getMessage()]);
                exit;
            }
        } else {
            echo json_encode(["error" => "Falha ao mover imagem: " . $atual['name']]);
            exit;
        }
    }

    echo json_encode([
        "success" => "Imagens enviadas com sucesso!",
        "indice_envio" => $indice_envio,
        "imagens" => $enviadas
    ]);
} else {
    echo json_encode(["error" => "Nenhuma imagem enviada."]);
}
