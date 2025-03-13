<?php
$host = 'mysql.improov.com.br';
$db = 'improov';
$user = 'improov';
$pass = 'Impr00v';

header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar com o banco de dados: " . $e->getMessage());
}

// Parâmetros recebidos
$dataIdFuncoes = json_decode($_POST['dataIdFuncoes'], true);

// Gera um novo índice de envio para cada grupo de upload
$query = $pdo->prepare("SELECT MAX(indice_envio) AS max_indice FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = :funcao_imagem_id");
$query->execute([':funcao_imagem_id' => $dataIdFuncoes[0]]); // Usando o primeiro data-id-funcao como exemplo
$result = $query->fetch(PDO::FETCH_ASSOC);
$indice_envio = ($result['max_indice'] ?? 0) + 1;

// Função para realizar o upload da imagem
function uploadImagem($imagem, $destino)
{
    if (!is_dir($destino)) {
        mkdir($destino, 0777, true);
    }

    if ($imagem['error'] == UPLOAD_ERR_OK) {
        $extensao = pathinfo($imagem['name'], PATHINFO_EXTENSION);
        $nomeArquivo = uniqid('imagem_') . '.' . $extensao;
        $caminhoDestino = $destino . '/' . $nomeArquivo;

        if (move_uploaded_file($imagem['tmp_name'], $caminhoDestino)) {
            return $caminhoDestino;
        } else {
            return false;
        }
    }
    return false;
}

// Processar as imagens enviadas
if (isset($_FILES['imagens'])) {
    $imagens = $_FILES['imagens'];
    $totalImagens = count($imagens['name']);
    $destino = 'uploads';
    $imagensEnviadas = [];

    for ($i = 0; $i < $totalImagens; $i++) {
        $imagemAtual = [
            'name' => $imagens['name'][$i],
            'type' => $imagens['type'][$i],
            'tmp_name' => $imagens['tmp_name'][$i],
            'error' => $imagens['error'][$i],
            'size' => $imagens['size'][$i]
        ];

        $imagem = uploadImagem($imagemAtual, $destino);

        if ($imagem) {
            try {
                $query = $pdo->prepare("INSERT INTO historico_aprovacoes_imagens (funcao_imagem_id, imagem, indice_envio) 
                                        VALUES (:funcao_imagem_id, :imagem, :indice_envio)");
                $query->execute([
                    ':funcao_imagem_id' => $dataIdFuncoes[0], // Usando o data-id-funcao correspondente
                    ':imagem' => $imagem,
                    ':indice_envio' => $indice_envio
                ]);
                $imagensEnviadas[] = $imagem;
            } catch (PDOException $e) {
                echo json_encode(["error" => "Erro ao salvar a imagem {$imagemAtual['name']}: " . $e->getMessage()]);
                exit;
            }
        } else {
            echo json_encode(["error" => "Erro ao enviar a imagem {$imagemAtual['name']}!"]);
            exit;
        }
    }

    if (!empty($imagensEnviadas)) {
        echo json_encode(["success" => "Imagens enviadas e salvas com sucesso!", "indice_envio" => $indice_envio]);
    }
}
