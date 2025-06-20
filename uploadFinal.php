<?php
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

// Dados FTP
$ftp_user = "flow";
$ftp_pass = "flow@2025";
$ftp_host = "imp-nas.ddns.net";
$ftp_port = 2121;

// Recebe dados do POST
$nome_funcao = $_POST['nome_funcao'] ?? '';
$numeroImagem = $_POST['numeroImagem'] ?? '';
$nomenclatura = $_POST['nomenclatura'] ?? '';
$primeiraPalavra = $_POST['primeiraPalavra'] ?? '';

// Diretórios base para pesquisa, ordem preferida
$clientes_base = ['/clientes/2024', '/clientes/2025'];

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
$respostas = [];

// Tenta upload em cada base até funcionar
$upload_ok = false;
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
    $ftp_url = "ftp://$ftp_host:$ftp_port$remote_path";

    $file = fopen($tmp_name, 'rb');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ftp_url);
    curl_setopt($ch, CURLOPT_USERPWD, "$ftp_user:$ftp_pass");
    curl_setopt($ch, CURLOPT_UPLOAD, 1);
    curl_setopt($ch, CURLOPT_INFILE, $file);
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($tmp_name));
    curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
    curl_setopt($ch, CURLOPT_FTP_SSL, CURLFTPSSL_ALL);
    curl_setopt($ch, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_TLS);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_VERBOSE, true); // para debug
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    fclose($file);

    if (!$response) {
        error_log("Erro cURL: $error");
    }
}
// Se não encontrou, erro
if (!$upload_ok) {
    echo json_encode(['error' => "Nomenclatura '$nomenclatura' não encontrada nas pastas clientes/2024 ou 2025, ou não foi possível fazer upload."]);
    exit;
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
    $ftp_url = "ftp://$ftp_host:$ftp_port$remote_path";

    // Abre o arquivo temporário em modo binário de leitura
    $file = fopen($tmp_name, 'rb');
    if (!$file) {
        $respostas[] = [
            'arquivo' => $nome_original,
            'status' => 'falha',
            'erro' => 'Erro ao abrir arquivo temporário para leitura binária'
        ];
        continue;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ftp_url);
    curl_setopt($ch, CURLOPT_USERPWD, "$ftp_user:$ftp_pass");
    curl_setopt($ch, CURLOPT_UPLOAD, 1);
    curl_setopt($ch, CURLOPT_INFILE, $file);
    curl_setopt($ch, CURLOPT_INFILESIZE, $tamanho_arquivo); // Usar o tamanho do arquivo original
    curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
    curl_setopt($ch, CURLOPT_FTP_SSL, CURLFTPSSL_ALL);
    curl_setopt($ch, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_TLS);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Mantenha isso como true durante a depuração

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);


    if ($response) {
        $respostas[] = [
            'arquivo' => $nome_original,
            'status' => 'sucesso',
            'destino' => $remote_path
        ];
    } else {
        $respostas[] = [
            'arquivo' => $nome_original,
            'status' => 'falha',
            'erro' => curl_error($ch)
        ];
    }

    curl_close($ch);
    fclose($file);
}

echo json_encode($respostas);
