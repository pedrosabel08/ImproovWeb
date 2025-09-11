<?php
require 'conexao.php';
require 'vendor/autoload.php';

use phpseclib3\Net\SFTP;

$ftp_user = "flow";
$ftp_pass = "flow@2025";
$ftp_host = "imp-nas.ddns.net";
$ftp_port = 2222;

$base_path = "/mnt/clientes/2025/TES_TES/05.Exchange/";

$log = []; // array de log

function resposta($success, $log, $error = null)
{
    echo json_encode([
        'success' => $success,
        'error' => $error,
        'log' => $log
    ], JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $log[] = "Requisição inválida (use POST).";
    resposta(false, $log, "Método inválido.");
}

$idArquivo      = isset($_POST['arquivoId']) ? (int) $_POST['arquivoId'] : 0;
$responsavel    = $_POST['responsavel'] ?? 'revisor';
$status_arquivo = $_POST['status_arquivo'] ?? null;
$tipo_arquivo   = $_POST['tipo_arquivo'] ?? null;
$substitui_arquivo = $_POST['substitui_arquivo'] ?? null;
$tipo_imagem    = $_POST['tipo_imagem'] ?? null;
$observacao     = $_POST['observacao'] ?? null;

if (!$idArquivo || !$tipo_arquivo) {
    $log[] = "Parâmetros inválidos. idArquivo=$idArquivo, tipo_arquivo=$tipo_arquivo";
    resposta(false, $log, "Parâmetros inválidos.");
}

$log[] = "Iniciando revisão para arquivo ID $idArquivo";

// Busca registro do arquivo
$stmt = $conn->prepare("SELECT * FROM arquivos WHERE idarquivo = ?");
$stmt->bind_param("i", $idArquivo);
$stmt->execute();
$res = $stmt->get_result();
$arquivo = $res->fetch_assoc();
$stmt->close();

if (!$arquivo) {
    $log[] = "Arquivo não encontrado no banco (id: $idArquivo).";
    resposta(false, $log, "Arquivo não encontrado no banco.");
}
$log[] = "Arquivo encontrado no banco: " . $arquivo['nome_original'];

function normalizar_nome($str)
{
    $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
    $str = preg_replace('/[^A-Za-z0-9._ -]/', '', $str);
    return $str;
}

$nomeOriginalNorm = normalizar_nome(basename($arquivo['nome_original']));
$inputFolder = rtrim($base_path, '/') . "/01.Input/Arquivos_Pendentes";
$inputBase = rtrim($base_path, '/') . "/01.Input"; // <- define antes de substituir
$caminhoAtual = $inputFolder . "/" . $nomeOriginalNorm;

$log[] = "Nome normalizado: $nomeOriginalNorm";
$log[] = "Caminho atual esperado: $caminhoAtual";

// Conecta SFTP
$sftp = new SFTP($ftp_host, $ftp_port);
if (!$sftp->login($ftp_user, $ftp_pass)) {
    $log[] = "Falha na autenticação SFTP.";
    resposta(false, $log, "Falha na autenticação SFTP.");
}
$log[] = "Conexão SFTP bem-sucedida.";

// Verifica arquivo
$stat = @$sftp->stat($caminhoAtual);
if ($stat === false) {
    $log[] = "Arquivo não encontrado diretamente, listando pasta $inputFolder";
    $arquivos = $sftp->nlist($inputFolder);
    $log[] = "Arquivos disponíveis em $inputFolder: " . implode(", ", $arquivos);

    $arquivoEncontrado = null;
    foreach ($arquivos as $arq) {
        if ($arq === "." || $arq === "..") continue;
        if (normalizar_nome($arq) === $nomeOriginalNorm) {
            $arquivoEncontrado = $arq;
            break;
        }
    }

    if (!$arquivoEncontrado) {
        $log[] = "Arquivo não encontrado no servidor SFTP (mesmo após normalização): $nomeOriginalNorm";
        resposta(false, $log, "Arquivo não encontrado no servidor SFTP.");
    }

    $caminhoAtual = $inputFolder . "/" . $arquivoEncontrado;
    $log[] = "Arquivo localizado no servidor com nome real: $arquivoEncontrado";
}


if (!empty($substitui_arquivo)) {
    $log[] = "Arquivo irá substituir o ID $substitui_arquivo.";

    // Busca registro do arquivo antigo
    $stmtOld = $conn->prepare("SELECT * FROM arquivos WHERE idarquivo = ?");
    $stmtOld->bind_param("i", $substitui_arquivo);
    $stmtOld->execute();
    $resOld = $stmtOld->get_result();
    $arquivoAntigo = $resOld->fetch_assoc();
    $stmtOld->close();

    if ($arquivoAntigo) {
        $caminhoAntigo = $arquivoAntigo['caminho'];
        $nomeAntigoNorm = normalizar_nome(basename($arquivoAntigo['nome_original']));
        $pastaAntigos = $inputBase . "/Arquivos_Antigos/";

        $log[] = "Caminho do arquivo antigo (banco): $caminhoAntigo";

        $statOld = @$sftp->stat($caminhoAntigo);
        if ($statOld === false) {
            $log[] = "Arquivo antigo não existe no SFTP nesse caminho: $caminhoAntigo";
        } else {
            $log[] = "Arquivo antigo encontrado no SFTP.";
        }

        if (!$sftp->is_dir($pastaAntigos)) {
            $sftp->mkdir($pastaAntigos, 0777, true);
            $log[] = "Criada pasta de arquivos antigos: $pastaAntigos";
        }

        $novoCaminhoAntigo = $pastaAntigos . $nomeAntigoNorm;

        if ($sftp->rename($caminhoAntigo, $novoCaminhoAntigo)) {
            $log[] = "Arquivo antigo movido para Arquivos_Antigos: $novoCaminhoAntigo";
        } else {
            // fallback: copy + delete
            $conteudoAntigo = $sftp->get($caminhoAntigo);
            if ($conteudoAntigo !== false) {
                $sftp->put($novoCaminhoAntigo, $conteudoAntigo);
                if (!$sftp->delete($caminhoAntigo)) {
                    $log[] = "Aviso: não foi possível apagar o arquivo antigo após copy: $caminhoAntigo";
                }
                $log[] = "Arquivo antigo movido via fallback para Arquivos_Antigos: $novoCaminhoAntigo";
            } else {
                $log[] = "Erro ao mover arquivo antigo (id $substitui_arquivo)";
                resposta(false, $log, "Não foi possível mover o arquivo antigo, operação abortada.");
            }
        }

        // Atualiza status do arquivo antigo no banco
        $updOld = $conn->prepare("UPDATE arquivos SET status = 'antigo', caminho = ? WHERE idarquivo = ?");
        $updOld->bind_param("si", $novoCaminhoAntigo, $substitui_arquivo);
        $updOld->execute();
        $updOld->close();
        $log[] = "Tabela arquivos atualizada para arquivo antigo (id $substitui_arquivo).";
    } else {
        $log[] = "Arquivo antigo não encontrado no banco (id $substitui_arquivo).";
    }
}

// Determina pasta destino
$inputBase = rtrim($base_path, '/') . "/01.Input";
if ($status_arquivo === 'completo') {
    $novaPasta = $inputBase . "/Arquivos_Atualizados/";
    $novoStatus = 'atualizado';
} else {
    $novaPasta = $inputBase . "/Arquivos_Antigos/";
    $novoStatus = 'antigo';
}
$log[] = "Nova pasta destino: $novaPasta (status=$novoStatus)";

// Cria pasta destino
if (!$sftp->is_dir($novaPasta)) {
    if (!$sftp->mkdir($novaPasta, 0777, true)) {
        $log[] = "Falha ao criar pasta no servidor SFTP: $novaPasta";
        resposta(false, $log, "Falha ao criar pasta destino.");
    }
    $log[] = "Pasta criada: $novaPasta";
} else {
    $log[] = "Pasta destino já existe.";
}

$novoCaminho = $novaPasta . $nomeOriginalNorm;
$log[] = "Novo caminho remoto: $novoCaminho";

// Move arquivo
$moved = false;
$method = null;
if ($sftp->rename($caminhoAtual, $novoCaminho)) {
    $moved = true;
    $method = 'rename';
    $log[] = "Arquivo movido com sucesso via rename.";
} else {
    $log[] = "Falha no rename, tentando fallback (get->put).";
    $conteudo = $sftp->get($caminhoAtual);
    if ($conteudo === false) resposta(false, $log, "Falha ao ler arquivo no fallback.");
    if (!$sftp->put($novoCaminho, $conteudo)) resposta(false, $log, "Falha ao gravar arquivo no fallback.");
    if (!$sftp->delete($caminhoAtual)) $log[] = "Aviso: não foi possível apagar o original em $caminhoAtual após copy.";
    $moved = true;
    $method = 'copy';
    $log[] = "Arquivo movido via fallback (copy).";
}

if (!$moved) resposta(false, $log, "Não foi possível mover o arquivo.");

// Atualiza arquivo
$upd = $conn->prepare("UPDATE arquivos SET status = ?, caminho = ?, revisado_por = ?, revisado_em = NOW() WHERE idarquivo = ?");
$upd->bind_param("sssi", $novoStatus, $novoCaminho, $responsavel, $idArquivo);
$upd->execute();
$upd->close();
$log[] = "Tabela arquivos atualizada.";

// Atualiza tarefas
$upd2 = $conn->prepare("UPDATE tarefas_arquivos SET status = 'concluida', concluido_em = NOW() WHERE arquivo_id = ?");
$upd2->bind_param("i", $idArquivo);
$upd2->execute();
$upd2->close();
$log[] = "Tabela tarefas_arquivos atualizada.";

// Inserir revisão
$stmtRev = $conn->prepare("INSERT INTO revisoes 
    (arquivo_id, status_arquivo, substitui_id, relacao, observacao, criado_em, criado_por)
    VALUES (?, 1, ?, ?, ?, NOW(), ?)");
if (!$stmtRev) resposta(false, $log, "Erro ao preparar query revisoes.");

if (!$stmtRev->bind_param("iisss", $idArquivo,  $substitui_arquivo, $tipo_arquivo, $observacao, $responsavel))
    resposta(false, $log, "Erro ao vincular parâmetros revisoes.");

if (!$stmtRev->execute()) resposta(false, $log, "Erro ao inserir revisao.");
$stmtRev->close();
$log[] = "Registro de revisão inserido com sucesso.";

if (!empty($_POST['batch_imagens'])) {
    $batchImagens = $_POST['batch_imagens']; // array dos checkboxes
    $stmtAI = $conn->prepare("INSERT INTO arquivos_imagem (arquivo_id, imagem_id, criado_por) VALUES (?, ?, ?)");
    if (!$stmtAI) resposta(false, $log, "Erro ao preparar query arquivos_imagem.");

    foreach ($batchImagens as $imgId) {
        $stmtAI->bind_param("iis", $idArquivo, $imgId, $responsavel);
        if (!$stmtAI->execute()) {
            resposta(false, $log, "Erro ao inserir relação com imagem ID $imgId.");
        }
    }
    $stmtAI->close();
    $log[] = "Relacionamentos em arquivos_imagem inseridos com sucesso.";
}


// Retorno final
$log[] = "✅ Processo concluído com sucesso. Método usado: $method.";
resposta(true, $log);
