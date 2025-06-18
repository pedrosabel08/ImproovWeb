<?php
header('Content-Type: application/json');

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

// Procurar o valor correto ignorando case
foreach ($mapa_funcao_pasta as $key => $pasta) {
    if (mb_strtolower($key, 'UTF-8') === $nome_funcao_lower) {
        $pasta_funcao = $pasta;
        break;
    }
}

// Se não encontrou pasta para a função, erro
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

// Função para procurar diretório base com a nomenclatura
function encontrarDiretorioBase($clientes_base, $nomenclatura)
{
    foreach ($clientes_base as $base) {
        $dir = $base . '/' . $nomenclatura;
        if (is_dir($dir)) {
            return $dir;
        }
    }
    return false;
}

// Vamos checar os arquivos enviados
if (!isset($_FILES['arquivo_final'])) {
    echo json_encode(['error' => 'Nenhum arquivo enviado']);
    exit;
}

$arquivos = $_FILES['arquivo_final'];
$respostas = [];

// Procura o diretório base com a nomenclatura
$dir_base_encontrado = encontrarDiretorioBase($clientes_base, $nomenclatura);
if (!$dir_base_encontrado) {
    echo json_encode(['error' => "Nomenclatura '$nomenclatura' não encontrada nas pastas clientes/2024 ou 2025"]);
    exit;
}

// Monta caminho completo final
$destino_base = $dir_base_encontrado . '/' . $pasta_funcao;

// Cria pasta destino se não existir
if (!is_dir($destino_base)) {
    if (!mkdir($destino_base, 0777, true)) {
        echo json_encode(['error' => "Falha ao criar pasta destino: $destino_base"]);
        exit;
    }
}

for ($i = 0; $i < count($arquivos['name']); $i++) {
    $nome_original = $arquivos['name'][$i];
    $tmp_name = $arquivos['tmp_name'][$i];
    $extensao = pathinfo($nome_original, PATHINFO_EXTENSION);

    $tipo = detectarTipoArquivo($extensao);

    // Monta o nome final:
    // (nº imagem).(nomeclatura)-(primeira_palavra)-(tipo)-(processo)-(revisao).extensão;
    // Processo e revisão podem vir do formulário ou pode ajustar aqui.  
    // Vou assumir que você queira colocar os mesmos valores fixos que você tinha no exemplo original, caso queira passar por POST é só adaptar.

    $processo = 'CMP';  // você pode adaptar para receber via POST
    $revisao = 'R00';   // você pode adaptar para receber via POST

    $nome_final = "{$numeroImagem}.{$nomenclatura}-{$primeiraPalavra}-{$tipo}-{$processo}-{$revisao}.{$extensao}";

    $destino_final = $destino_base . '/' . $nome_final;

    if (move_uploaded_file($tmp_name, $destino_final)) {
        $respostas[] = [
            'arquivo' => $nome_original,
            'status' => 'sucesso',
            'destino' => $destino_final
        ];
    } else {
        $respostas[] = [
            'arquivo' => $nome_original,
            'status' => 'falha',
            'erro' => 'Erro ao mover arquivo para destino final'
        ];
    }
}

echo json_encode($respostas);
