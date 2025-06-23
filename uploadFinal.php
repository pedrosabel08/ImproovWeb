<?php

include 'conexao.php';

require __DIR__ . '/vendor/autoload.php';

use phpseclib3\Net\SFTP;
use phpseclib3\Exception\UnableToConnectException;

header('Content-Type: application/json');

// Log dos limites do PHP
error_log('upload_max_filesize: ' . ini_get('upload_max_filesize'));
error_log('post_max_size: ' . ini_get('post_max_size'));
error_log('max_file_uploads: ' . ini_get('max_file_uploads'));

// Log do array $_FILES
file_put_contents(__DIR__ . '/debug_files.txt', print_r($_FILES, true));

// Checa arquivos enviados
if (
    !isset($_FILES['arquivo_final']) ||
    empty($_FILES['arquivo_final']['name']) ||
    (is_array($_FILES['arquivo_final']['name']) && empty($_FILES['arquivo_final']['name'][0]))
) {
    echo json_encode(['error' => 'Nenhum arquivo enviado']);
    exit;
}
$arquivos = $_FILES['arquivo_final'];
$total = is_array($arquivos['name']) ? count($arquivos['name']) : 1;

// Diagnóstico: copia o arquivo temporário para debug antes do upload
for ($i = 0; $i < $total; $i++) {
    $nome_original = is_array($arquivos['name']) ? $arquivos['name'][$i] : $arquivos['name'];
    $tmp_name = is_array($arquivos['tmp_name']) ? $arquivos['tmp_name'][$i] : $arquivos['tmp_name'];
    $erro = is_array($arquivos['error']) ? $arquivos['error'][$i] : $arquivos['error'];
    $tamanho = is_array($arquivos['size']) ? $arquivos['size'][$i] : $arquivos['size'];

    // Loga informações do arquivo
    error_log("Arquivo $i: nome=$nome_original, tmp_name=$tmp_name, erro=$erro, tamanho=$tamanho");

    // Só copia se for PDF e não houver erro
    if ($erro === 0 && $tamanho > 0 && strtolower(pathinfo($nome_original, PATHINFO_EXTENSION)) === 'pdf') {
        copy($tmp_name, __DIR__ . "/debug_upload_{$i}.pdf");
    }
}

// Dados SFTP
$ftp_user = "flow";
$ftp_pass = "flow@2025";
$ftp_host = "imp-nas.ddns.net";
$ftp_port = 2222;

// Recebe dados do POST
$nome_funcao = $_POST['nome_funcao'] ?? '';
$numeroImagem = $_POST['numeroImagem'] ?? '';
$nomenclatura = $_POST['nomenclatura'] ?? '';
$primeiraPalavra = $_POST['primeiraPalavra'] ?? '';

// Diretórios base para pesquisa, ordem preferida
$clientes_base = ['/mnt/clientes/2024', '/mnt/clientes/2025'];

// Define subdiretório conforme nome_funcao
$mapa_funcao_pasta = [
    'Caderno' => '02.Projetos',
    'Filtro' => '02.Projetos',
    'modelagem' => '03.Models',
    'composição' => '03.Models',
    'finalização' => '03.Models',
    'Pós-Produção' => '04.Finalizacao',
];

// Define pasta destino baseado no nome_funcao (case insensitive)
$nome_funcao_lower = mb_strtolower($nome_funcao, 'UTF-8');
$pasta_funcao = '';
foreach ($mapa_funcao_pasta as $key => $pasta) {
    if (mb_strtolower($key, 'UTF-8') === $nome_funcao_lower) {
        $pasta_funcao = $pasta;
        break;
    }
}
if (!$pasta_funcao) {
    echo json_encode(['error' => 'Função inválida ou não mapeada para pasta']);
    exit;
}

// Função para detectar tipo pelo mime e extensão
function detectarTipoArquivo($ext)
{
    $ext = strtolower($ext);
    $pdfs = ['pdf'];
    $imagens = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'];
    if (in_array($ext, $pdfs)) return 'PDF';
    if (in_array($ext, $imagens)) return 'IMG';
    return 'MDL';
}

// Função para envio SFTP
function enviarArquivoSFTP($host, $usuario, $senha, $arquivoLocal, $arquivoRemoto, $porta = 2222)
{
    if (!file_exists($arquivoLocal)) {
        return [false, "❌ Arquivo local não encontrado: $arquivoLocal"];
    }

    try {
        $sftp = new SFTP($host, $porta);
        if (!$sftp->login($usuario, $senha)) {
            return [false, "❌ Falha na autenticação SFTP."];
        }

        $diretorio = dirname($arquivoRemoto);
        // NÃO criar diretório, apenas verifica se existe
        if (!$sftp->is_dir($diretorio)) {
            return [false, "❌ Diretório remoto não existe: $diretorio"];
        }

        if ($sftp->put($arquivoRemoto, file_get_contents($arquivoLocal))) {
            return [true, "✅ Arquivo enviado com sucesso via SFTP!"];
        } else {
            return [false, "⚠ Erro ao enviar o arquivo via SFTP."];
        }
    } catch (UnableToConnectException $e) {
        return [false, "❌ Erro ao conectar ao servidor SFTP: " . $e->getMessage()];
    } catch (\Exception $e) {
        return [false, "⚠ Ocorreu um erro inesperado: " . $e->getMessage()];
    }
}

$arquivos = $_FILES['arquivo_final'];
$respostas = [];

// Tenta upload em cada base até funcionar
$upload_ok = false;
$base_ok = '';
foreach ($clientes_base as $base) {
    $destino_base = $base . '/' . $nomenclatura . '/' . $pasta_funcao;

    // Testa upload do primeiro arquivo para validar o caminho
    $nome_original = is_array($arquivos['name']) ? $arquivos['name'][0] : $arquivos['name'];
    $tmp_name = is_array($arquivos['tmp_name']) ? $arquivos['tmp_name'][0] : $arquivos['tmp_name'];
    if (empty($nome_original) || empty($tmp_name)) continue;

    $extensao = pathinfo($nome_original, PATHINFO_EXTENSION);
    $tipo = detectarTipoArquivo($extensao);
    $processo = strtoupper(mb_substr($nome_funcao, 0, 3, 'UTF-8'));
    $revisao = 'R00';
    $nome_final = "{$numeroImagem}.{$nomenclatura}-{$primeiraPalavra}-{$tipo}-{$processo}-{$revisao}.{$extensao}";
    $remote_path = "$destino_base/$nome_final";

    list($ok, $msg) = enviarArquivoSFTP(
        $ftp_host,
        $ftp_user,
        $ftp_pass,
        $tmp_name,
        $remote_path,
        $ftp_port
    );

    if ($ok) {
        $upload_ok = $destino_base;
        $base_ok = $base;
        break;
    } else {
        error_log("Erro SFTP: $msg");
    }
}

// Se não encontrou, erro
if (!$upload_ok) {
    echo json_encode(['error' => "Nomenclatura '$nomenclatura' não encontrada nas pastas clientes/2024 ou 2025, ou não foi possível fazer upload."]);
    exit;
}

$dataIdFuncoes = [];
if (isset($_POST['dataIdFuncoes'])) {
    $raw = $_POST['dataIdFuncoes'];
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $dataIdFuncoes = $decoded;
    } elseif (!empty($raw)) {
        $dataIdFuncoes = [$raw];
    }
}


// Agora faça o upload dos demais arquivos normalmente usando $upload_ok
$total = is_array($arquivos['name']) ? count($arquivos['name']) : 1;
for ($i = 0; $i < $total; $i++) {
    $nome_original = is_array($arquivos['name']) ? $arquivos['name'][$i] : $arquivos['name'];
    $tmp_name = is_array($arquivos['tmp_name']) ? $arquivos['tmp_name'][$i] : $arquivos['tmp_name'];
    if (empty($nome_original) || empty($tmp_name)) continue;

    $extensao = pathinfo($nome_original, PATHINFO_EXTENSION);
    $tipo = detectarTipoArquivo($extensao);
    $processo = strtoupper(mb_substr($nome_funcao, 0, 3, 'UTF-8'));
    $revisao = 'R00';
    $nome_final = "{$numeroImagem}.{$nomenclatura}-{$primeiraPalavra}-{$tipo}-{$processo}-{$revisao}.{$extensao}";
    $remote_path = "$upload_ok/$nome_final";

    list($ok, $msg) = enviarArquivoSFTP(
        $ftp_host,
        $ftp_user,
        $ftp_pass,
        $tmp_name,
        $remote_path,
        $ftp_port
    );

    if ($ok) {
        $respostaArquivo = [
            'arquivo' => $nome_original,
            'status' => 'sucesso',
            'destino' => $remote_path
        ];

        // Se for PDF, faz o insert/update
        if (strtolower($extensao) === 'pdf' && !empty($dataIdFuncoes)) {
            error_log("Tentando inserir PDF: $nome_final para funções: " . implode(',', $dataIdFuncoes));
            // Conexão com o banco (ajuste conforme seu projeto)
            if ($conn->connect_errno) {
                $respostaArquivo['erro_db'] = "Erro MySQL: " . $conn->connect_error;
                error_log("Erro MySQL: " . $conn->connect_error);
            } else {
                foreach ($dataIdFuncoes as $id_funcao) {
                    $stmt = $conn->prepare("INSERT INTO funcao_imagem_pdf (funcao_imagem_id, nome_pdf) VALUES (?, ?) ON DUPLICATE KEY UPDATE nome_pdf = VALUES(nome_pdf)");
                    if (!$stmt) {
                        $respostaArquivo['erro_db'] = "Prepare failed: " . $conn->error;
                        error_log("Prepare failed: " . $conn->error);
                        break;
                    }
                    if (!$stmt->bind_param("is", $id_funcao, $nome_final)) {
                        $respostaArquivo['erro_db'] = "Bind failed: " . $stmt->error;
                        error_log("Bind failed: " . $stmt->error);
                        $stmt->close();
                        break;
                    }
                    if (!$stmt->execute()) {
                        $respostaArquivo['erro_db'] = "Execute failed: " . $stmt->error;
                        error_log("Execute failed: " . $stmt->error);
                        $stmt->close();
                        break;
                    }
                    $stmt->close();
                }
            }
        }

        $respostas[] = $respostaArquivo;
    } else {
        $respostas[] = [
            'arquivo' => $nome_original,
            'status' => 'falha',
            'erro' => $msg
        ];
    }
}
$conn->close();


echo json_encode($respostas);
