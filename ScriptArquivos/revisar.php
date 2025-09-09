<?php
require 'conexao.php';
require 'vendor/autoload.php';

use phpseclib3\Net\SFTP;

// Config SFTP
$ftp_user = "flow";
$ftp_pass = "flow@2025";
$ftp_host = "imp-nas.ddns.net";
$ftp_port = 2222;

// Pasta base do projeto (sem o nome da subpasta final)
$base_path = "/mnt/clientes/2025/TES_TES/05.Exchange/";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Use POST.");
}

$idArquivo = isset($_POST['idarquivo']) ? (int) $_POST['idarquivo'] : 0;
$acao = $_POST['acao'] ?? '';
$responsavel = $_POST['responsavel'] ?? 'revisor';

if (!$idArquivo || !in_array($acao, ['aprovado', 'rejeitado'])) {
    die("Parâmetros inválidos.");
}

// Busca registro do arquivo
$stmt = $conn->prepare("SELECT * FROM arquivos WHERE idarquivo = ?");
$stmt->bind_param("i", $idArquivo);
$stmt->execute();
$res = $stmt->get_result();
$arquivo = $res->fetch_assoc();
$stmt->close();

if (!$arquivo) {
    die("Arquivo não encontrado no banco (id: $idArquivo).");
}

$caminhoAtual = $arquivo['caminho'];            // deve ser o path remoto
$nomeOriginal = basename($arquivo['nome_original']);

// Conecta SFTP
$sftp = new SFTP($ftp_host, $ftp_port);
if (!$sftp->login($ftp_user, $ftp_pass)) {
    die("Falha na autenticação SFTP. Verifique usuário/senha/porta.");
}

// Verifica se o arquivo existe no remote
$stat = @$sftp->stat($caminhoAtual);
if ($stat === false) {
    // Lista a pasta input para ajudar no debug
    $inputFolder = rtrim($base_path, '/') . "/01.Input/";
    $list = @json_encode($sftp->nlist($inputFolder));
    die("Arquivo não encontrado no servidor SFTP: $caminhoAtual\nLista da pasta {$inputFolder}: {$list}");
}

// Determina pasta destino (sempre dentro de 01.Input/)
$inputPath = rtrim($base_path, '/') . "/01.Input/";

if ($acao === 'aprovado') {
    $novaPasta = $inputPath . "Arquivos_Atualizados/";
    $novoStatus = 'atualizado';
} else {
    $novaPasta = $inputPath . "Arquivos_Antigos/";
    $novoStatus = 'antigo';
}


// Cria pasta destino se não existir (modo e recursivo corretos)
if (!$sftp->is_dir($novaPasta)) {
    if (!$sftp->mkdir($novaPasta, 0777, true)) {
        die("Falha ao criar pasta no servidor SFTP: $novaPasta (verifique permissões).");
    }
}

// Monta novo caminho remoto
$novoCaminho = $novaPasta . $nomeOriginal;

// 1) Tenta rename (move) remoto
$moved = false;
$method = null;
if ($sftp->rename($caminhoAtual, $novoCaminho)) {
    $moved = true;
    $method = 'rename';
} else {
    // 2) Fallback: copiar (get -> put) e apagar original
    $conteudo = $sftp->get($caminhoAtual);
    if ($conteudo === false) {
        die("Falha ao ler o arquivo remoto para fallback. Talvez problema de permissões/leitura.");
    }
    if (!$sftp->put($novoCaminho, $conteudo)) {
        die("Falha ao gravar o arquivo no destino durante fallback.");
    }
    // tenta remover original; se falhar, apenas avisa mas prossegue
    if (!$sftp->delete($caminhoAtual)) {
        // aviso, mas não falha o processo inteiro
        $warning = "Atenção: não foi possível apagar o original em $caminhoAtual após copy. Verifique permissões.";
    } else {
        $warning = '';
    }
    $moved = true;
    $method = 'copy';
}

if (!$moved) {
    die("Não foi possível mover o arquivo (nem rename nem fallback).");
}

// Atualiza registros no banco
// Atualiza arquivo
$upd = $conn->prepare("UPDATE arquivos SET status = ?, caminho = ?, revisado_por = ?, revisado_em = NOW() WHERE idarquivo = ?");
$upd->bind_param("sssi", $novoStatus, $novoCaminho, $responsavel, $idArquivo);
$upd->execute();
$upd->close();

// Atualiza tarefa(s) associada(s)
$upd2 = $conn->prepare("UPDATE tarefas_arquivos SET status = 'concluida', concluido_em = NOW() WHERE arquivo_id = ?");
$upd2->bind_param("i", $idArquivo);
$upd2->execute();
$upd2->close();

echo "✅ Arquivo movido com sucesso via {$method} para: {$novoCaminho}\n";
if (!empty($warning))
    echo $warning . "\n";
exit;
