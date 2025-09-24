<?php

header("Access-Control-Allow-Origin: https://improov.com.br");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'conexao.php';

require __DIR__ . '/vendor/autoload.php';

use phpseclib3\Net\SFTP;
use phpseclib3\Exception\UnableToConnectException;

header('Content-Type: application/json');

// Log dos limites do PHP
error_log('upload_max_filesize: ' . ini_get('upload_max_filesize'));
error_log('post_max_size: ' . ini_get('post_max_size'));
error_log('memory_limit: ' . ini_get('memory_limit'));
error_log('max_file_uploads: ' . ini_get('max_file_uploads'));

// Log do array $_FILES
// file_put_contents(__DIR__ . '/debug_files.txt', print_r($_FILES, true));

// file_put_contents(__DIR__ . '/log_debug_entrada.txt', print_r($_FILES, true));
// error_log('Tamanho do POST: ' . $_SERVER['CONTENT_LENGTH']);

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

    // // Só copia se for PDF e não houver erro
    // if ($erro === 0 && $tamanho > 0 && strtolower(pathinfo($nome_original, PATHINFO_EXTENSION)) === 'pdf') {
    //     copy($tmp_name, __DIR__ . "/debug_upload_{$i}.pdf");
    // }
}

$caminhoAtual = ''; // garante que existe


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
$nome_imagem = $_POST['nome_imagem'] ?? '';
$nomeStatus = $_POST['status_nome'] ?? '';

// Diretórios base para pesquisa, ordem preferida
$clientes_base = ['/mnt/clientes/2024', '/mnt/clientes/2025'];

// Define subdiretório conforme nome_funcao
$mapa_funcao_pasta = [
    'Caderno' => '02.Projetos',
    'Filtro de assets' => '02.Projetos',
    'modelagem' => '03.Models',
    'composição' => '03.Models',
    'finalização' => '03.Models',
    'pré-finalização' => '03.Models',
    'alteração' => '03.Models',
    'Pós-Produção' => '04.Finalizacao',
    'Planta Humanizada' => '04.Finalizacao',
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

        if ($sftp->put($arquivoRemoto, $arquivoLocal, SFTP::SOURCE_LOCAL_FILE)) {
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

// Tenta encontrar um diretório válido, sem enviar arquivo
$upload_ok = false;
$base_ok = '';
foreach ($clientes_base as $base) {
    $destino_base = $base . '/' . $nomenclatura . '/' . $pasta_funcao;

    $sftp = new SFTP($ftp_host, $ftp_port);
    if ($sftp->login($ftp_user, $ftp_pass) && $sftp->is_dir($destino_base)) {
        $upload_ok = $destino_base;
        $base_ok = $base;
        break;
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
function removerTodosAcentos($str)
{
    return preg_replace(
        ['/[áàãâä]/ui', '/[éèêë]/ui', '/[íìîï]/ui', '/[óòõôö]/ui', '/[úùûü]/ui', '/[ç]/ui'],
        ['a', 'e', 'i', 'o', 'u', 'c'],
        $str
    );
}
function sanitizeFilename($str)
{
    $str = removerTodosAcentos($str);
    $str = preg_replace('/[\/\\\:*?"<>|]/', '', $str); // remove caracteres perigosos
    $str = preg_replace('/\s+/', '_', $str); // substitui espaços por "_"
    return $str;
}

// Agora faça o upload dos demais arquivos normalmente usando $upload_ok
$total = is_array($arquivos['name']) ? count($arquivos['name']) : 1;
for ($i = 0; $i < $total; $i++) {
    $nome_original = is_array($arquivos['name']) ? $arquivos['name'][$i] : $arquivos['name'];
    $tmp_name = is_array($arquivos['tmp_name']) ? $arquivos['tmp_name'][$i] : $arquivos['tmp_name'];
    if (empty($nome_original) || empty($tmp_name)) continue;

    $extensao = pathinfo($nome_original, PATHINFO_EXTENSION);
    $tipo = detectarTipoArquivo($extensao);

    // Processo (3 primeiras letras sem acento e em maiúsculo)
    $semAcento = removerTodosAcentos($nome_funcao);
    $processo = strtoupper(mb_substr($semAcento, 0, 3, 'UTF-8'));

    $nome_base = "{$numeroImagem}.{$nomenclatura}-{$primeiraPalavra}-{$tipo}-{$processo}";

    $maiorRevisao = -1;
    $arquivo_antigo = '';
    $padrao = "/^" . preg_quote($nome_base, '/') . "-R(\d{2})\." . preg_quote($extensao, '/') . "$/i";
    $revisao = $nomeStatus;

    $sftp = new SFTP($ftp_host, $ftp_port);
    if (!$sftp->login($ftp_user, $ftp_pass)) {
        error_log("Falha ao conectar SFTP para revisão do arquivo $nome_original");
        $remote_path = "$upload_ok/{$nome_base}-{$revisao}.{$extensao}";
    } else {
        $remote_dir = $upload_ok;

        if ($pasta_funcao === '03.Models') {
            $nomeImagemSanitizado = sanitizeFilename($nome_imagem);

            $subpasta_img = $nomeImagemSanitizado;
            $funcao_key = mb_strtolower($nome_funcao, 'UTF-8');

            if ($funcao_key === 'alteração') {
                $caminhoAtual = $remote_dir;

                $caminhoAtual .= "/$subpasta_img";
                if (!$sftp->is_dir($caminhoAtual)) {
                    $sftp->mkdir($caminhoAtual);
                    $sftp->chmod(0777, $caminhoAtual); // define permissão completa
                }

                $caminhoAtual .= "/Final";
                if (!$sftp->is_dir($caminhoAtual)) {
                    $sftp->mkdir($caminhoAtual);
                    $sftp->chmod(0777, $caminhoAtual); // define permissão completa
                }

                $caminhoAtual .= "/$revisao";
                if (!$sftp->is_dir($caminhoAtual)) {
                    $sftp->mkdir($caminhoAtual);
                    $sftp->chmod(0777, $caminhoAtual); // define permissão completa
                }

                $remote_dir = $caminhoAtual;
            } else {
                $mapa_funcao = [
                    'modelagem' => 'MT',
                    'composição' => 'Comp',
                    'finalização' => 'Final',
                    'pré-finalização' => 'Final'
                ];
                $subpasta_funcao = $mapa_funcao[$funcao_key] ?? 'OUTROS';

                if (!$sftp->is_dir("$remote_dir/$subpasta_img")) {
                    $sftp->mkdir("$remote_dir/$subpasta_img");
                    $sftp->chmod(0777, $caminhoAtual); // define permissão completa
                }

                if (!$sftp->is_dir("$remote_dir/$subpasta_img/$subpasta_funcao")) {
                    $sftp->mkdir("$remote_dir/$subpasta_img/$subpasta_funcao");
                    $sftp->chmod(0777, $caminhoAtual); // define permissão completa
                }

                $remote_dir = "$remote_dir/$subpasta_img/$subpasta_funcao";
            }

            $remote_path = "$remote_dir/{$nome_base}-{$revisao}.{$extensao}";
        } else {
            $remote_path = "$remote_dir/{$nome_base}-{$revisao}.{$extensao}";
        }
    }

    // Excluir arquivo antigo, se necessário
    if ($arquivo_antigo && $pasta_funcao === '02.Projetos') {
        $caminho_antigo = "$upload_ok/$arquivo_antigo";
        $sftp->delete($caminho_antigo);
    }

    // === BLOCO ESPECIAL PARA PÓS-PRODUÇÃO E PLANTA HUMANIZADA ===
    $funcao_normalizada = mb_strtolower($nome_funcao, 'UTF-8');
    if ($funcao_normalizada === 'pós-produção' || $funcao_normalizada === 'planta humanizada') {
        $nome_final = "{$nome_imagem}_{$revisao}.{$extensao}";
        $pasta_revisao = $revisao;

        if (!$sftp->is_dir("$upload_ok/$pasta_revisao")) {
            $sftp->mkdir("$upload_ok/$pasta_revisao");
            $sftp->chmod(0777, $caminhoAtual); // define permissão completa
        }

        if ($funcao_normalizada === 'planta humanizada') {
            if (!$sftp->is_dir("$upload_ok/$pasta_revisao/PH")) {
                $sftp->mkdir("$upload_ok/$pasta_revisao/PH");
                $sftp->chmod(0777, $caminhoAtual); // define permissão completa
            }
            $remote_path = "$upload_ok/$pasta_revisao/PH/{$nome_final}";
        } else {
            $remote_path = "$upload_ok/$pasta_revisao/{$nome_final}";
        }
    } else {
        if (!isset($remote_path)) {
            $remote_path = "$upload_ok/{$nome_base}-{$revisao}.{$extensao}";
        }
        $nome_final = "{$nome_base}-{$revisao}.{$extensao}";
    }

    list($ok, $msg) = enviarArquivoSFTP(
        $ftp_host,
        $ftp_user,
        $ftp_pass,
        $tmp_name,
        $remote_path,
        $ftp_port
    );

    $respostaArquivo = [
        'arquivo' => $nome_original,
        'status' => $ok ? 'sucesso' : 'falha',
        'destino' => $remote_path,
        'nome_arquivo' => "{$nome_base}-{$revisao}.{$extensao}"
    ];

    if ($ok && strtolower($extensao) === 'pdf' && !empty($dataIdFuncoes)) {
        error_log("Tentando inserir PDF: $nome_final para funções: " . implode(',', $dataIdFuncoes));
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
}
$conn->close();


echo json_encode($respostas);
