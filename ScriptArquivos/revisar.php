<?php
require 'conexao.php';
require 'vendor/autoload.php';

use phpseclib3\Net\SFTP;

// Config SFTP
$ftp_user = "flow";
$ftp_pass = "flow@2025";
$ftp_host = "imp-nas.ddns.net";
$ftp_port = 2222;

// Pasta base do projeto (sem a subpasta final)
$inputBase = "/mnt/clientes/2025/TES_TES/05.Exchange/01.Input";

// Função de resposta JSON
function resposta($success, $log = [], $error = "")
{
    echo json_encode([
        "success" => $success,
        "log" => $log,
        "error" => $error
    ]);
    exit;
}

// Normalizar nomes de pastas/arquivos
function normalizar_nome($str)
{
    $str = preg_replace('/[áàãâä]/ui', 'a', $str);
    $str = preg_replace('/[éèêë]/ui', 'e', $str);
    $str = preg_replace('/[íìîï]/ui', 'i', $str);
    $str = preg_replace('/[óòõôö]/ui', 'o', $str);
    $str = preg_replace('/[úùûü]/ui', 'u', $str);
    $str = preg_replace('/[ç]/ui', 'c', $str);
    $str = preg_replace('/[^a-z0-9]/i', '_', $str);
    return $str;
}

$log = [];

// Dados do POST
$idArquivo = $_POST['arquivoId'] ?? null;
$responsavel = 1;
$observacao = $_POST['observacao'] ?? '';
$categoria = $_POST['categoria'] ?? null;
$substituiArquivo = $_POST['substitui_arquivo'] ?? null;

if (!$idArquivo || !$responsavel || !$categoria) {
    resposta(false, $log, "Parâmetros obrigatórios ausentes.");
}

// Normaliza categoria
$categoriaNorm = normalizar_nome($categoria);
$novaPasta = $inputBase . "/" . $categoriaNorm . "/";

// Conecta via SFTP
$sftp = new SFTP($ftp_host, $ftp_port);
if (!$sftp->login($ftp_user, $ftp_pass)) {
    resposta(false, $log, "Falha na conexão SFTP.");
}
$log[] = "Conexão SFTP realizada.";

// Busca informações do arquivo original
$stmt = $conn->prepare("SELECT nome_original, caminho FROM arquivos WHERE idarquivo = ?");
$stmt->bind_param("i", $idArquivo);
$stmt->execute();
$stmt->bind_result($nomeArquivo, $caminhoAntigo);
if (!$stmt->fetch()) {
    $stmt->close();
    resposta(false, $log, "Arquivo não encontrado no banco.");
}
$stmt->close();
$log[] = "Arquivo localizado no banco: $nomeArquivo";

// Garante que a pasta de destino existe
if (!$sftp->is_dir($novaPasta)) {
    $sftp->mkdir($novaPasta, 0777, true);
    $log[] = "Pasta criada no servidor: $novaPasta";
}

// --- PROCESSO DE SUBSTITUIÇÃO ---
if (!empty($substituiArquivo)) {
    $stmtOld = $conn->prepare("SELECT idarquivo, nome_original, caminho FROM arquivos WHERE idarquivo = ?");
    $stmtOld->bind_param("i", $substituiArquivo);
    $stmtOld->execute();
    $stmtOld->bind_result($idOld, $nomeOld, $caminhoOld);
    if ($stmtOld->fetch()) {
        $stmtOld->close();

        // Pasta de antigos dentro da categoria
        $pastaAntigos = $novaPasta . "Antigos/";
        if (!$sftp->is_dir($pastaAntigos)) {
            $sftp->mkdir($pastaAntigos, 0777, true);
            $log[] = "Pasta criada para antigos: $pastaAntigos";
        }

        $novoCaminhoAntigo = $pastaAntigos . $nomeOld;

        if ($sftp->rename($caminhoOld, $novoCaminhoAntigo)) {
            $log[] = "Arquivo antigo movido para $novoCaminhoAntigo";

            // Atualiza o status do arquivo antigo
            $updOld = $conn->prepare("UPDATE arquivos SET status = 'antigo', caminho = ? WHERE idarquivo = ?");
            $updOld->bind_param("si", $novoCaminhoAntigo, $idOld);
            $updOld->execute();
            $updOld->close();
            $log[] = "Arquivo antigo atualizado no banco (status = antigo).";
        } else {
            resposta(false, $log, "Falha ao mover o arquivo antigo para Antigos.");
        }
    } else {
        $stmtOld->close();
        resposta(false, $log, "Arquivo a ser substituído não encontrado.");
    }
}

// --- MOVE NOVO ARQUIVO ---
$novoCaminho = $novaPasta . $nomeArquivo;
if (!$sftp->rename($caminhoAntigo, $novoCaminho)) {
    resposta(false, $log, "Falha ao mover arquivo para $novoCaminho");
}
$log[] = "Arquivo movido para $novoCaminho";

// Atualiza tabela arquivos
$upd = $conn->prepare("UPDATE arquivos 
    SET status = 'atualizado', caminho = ?, categoria = ?, revisado_por = ?, revisado_em = NOW() 
    WHERE idarquivo = ?
");
$upd->bind_param("sssi", $novoCaminho, $categoria, $responsavel, $idArquivo);
if (!$upd->execute()) resposta(false, $log, "Erro ao atualizar arquivos.");
$upd->close();
$log[] = "Tabela arquivos atualizada.";

// Atualiza tarefas vinculadas
$upd2 = $conn->prepare("UPDATE tarefas_arquivos 
    SET status = 'concluida', concluido_em = NOW() 
    WHERE arquivo_id = ?
");
$upd2->bind_param("i", $idArquivo);
$upd2->execute();
$upd2->close();
$log[] = "Tabela tarefas_arquivos atualizada.";

// Inserir log de revisão
$stmtRev = $conn->prepare("INSERT INTO revisoes 
    (arquivo_id, status_arquivo, substitui_id, relacao, observacao, criado_em, criado_por)
    VALUES (?, 1, ?, ?, ?, NOW(), ?)
");
if (!$stmtRev) resposta(false, $log, "Erro ao preparar query revisoes.");

$relacao = !empty($substituiArquivo) ? "Substituição de arquivo $substituiArquivo" : "Revisão categoria: $categoria";
$stmtRev->bind_param("iisss", $idArquivo, $substituiArquivo, $relacao, $observacao, $responsavel);
if (!$stmtRev->execute()) resposta(false, $log, "Erro ao inserir revisão.");
$stmtRev->close();
$log[] = "Registro de revisão inserido com sucesso.";

// Resposta final
resposta(true, $log);
