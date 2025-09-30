<?php
require_once __DIR__ . '/../vendor/autoload.php';

use phpseclib3\Net\SFTP;

include '../conexao.php';
header('Content-Type: application/json');

$host = "imp-nas.ddns.net";
$port = 2222;
$username = "flow";
$password = "flow@2025";
$pastaBase = "/mnt/clientes/2025/TES_TES/05.Exchange/01.Input/";

// Dados do formulário
$obra_id      = intval($_POST['obra_id']);
$tipo_arquivo = $_POST['tipo_arquivo'] ?? "outros";
$descricao    = $_POST['descricao'] ?? "";
$flag_master  = isset($_POST['flag_master']) ? 1 : 0;
$substituicao = isset($_POST['flag_substituicao']);
$tiposImagem  = $_POST['tipo_imagem'] ?? [];

// Arquivos principais
$arquivosTmp  = $_FILES['arquivos']['tmp_name'] ?? [];
$arquivosName = $_FILES['arquivos']['name'] ?? [];

// Arquivos por imagem (refs/skp)
$arquivosPorImagem = $_FILES['arquivos_por_imagem'] ?? [];

$sftp = new SFTP($host, $port);
if (!$sftp->login($username, $password)) {
    echo json_encode(['errors' => ["Erro ao conectar no servidor SFTP."]]);
    exit;
}

// Função para buscar o ID do tipo_imagem pelo nome
function buscarTipoImagemId($conn, $nomeTipo)
{
    $nomeTipo = $conn->real_escape_string($nomeTipo);
    $res = $conn->query("SELECT id_tipo_imagem FROM tipo_imagem WHERE nome='$nomeTipo'");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return $row['id_tipo_imagem'];
    }
    return null;
}

// Função para gerar nome interno (ajustada para receber nomeTipo)
function gerarNomeInterno($conn, $obra_id, $tipo_id, $nomeTipo, $tipo_arquivo, $ext)
{
    $res = $conn->query("SELECT nome_interno FROM arquivos 
        WHERE obra_id=$obra_id AND tipo_imagem_id=$tipo_id AND tipo='$tipo_arquivo'
        ORDER BY idarquivo DESC LIMIT 1");
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (preg_match('/_v(\d+)\./', $row['nome_interno'], $matches)) {
            $versao = intval($matches[1]) + 1;
        } else {
            $versao = 1;
        }
    } else {
        $versao = 1;
    }

    $obraRow = $conn->query("SELECT nome_obra FROM obra WHERE idobra=$obra_id")->fetch_assoc();
    $nomeObra = preg_replace('/\s+/', '', $obraRow['nome_obra']);
    $nomeTipoLimpo = preg_replace('/\s+/', '', $nomeTipo);

    return "{$nomeObra}_{$nomeTipoLimpo}_{$tipo_arquivo}_v{$versao}.{$ext}";
}

$success = [];
$errors = [];

// =======================
// Upload principal
// =======================
foreach ($arquivosTmp as $index => $fileTmp) {
    $fileOriginalName = basename($arquivosName[$index]);
    $ext = pathinfo($fileOriginalName, PATHINFO_EXTENSION);

    foreach ($tiposImagem as $nomeTipo) {
        $tipo_id = buscarTipoImagemId($conn, $nomeTipo);
        if (!$tipo_id) {
            $errors[] = "Tipo de imagem '$nomeTipo' não encontrado.";
            continue;
        }
        $destDir = $pastaBase . $nomeTipo;
        if (!$sftp->is_dir($destDir)) $sftp->mkdir($destDir, 0777, true);
        if (!$sftp->is_dir($destDir . "/OLD")) $sftp->mkdir($destDir . "/OLD", 0777, true);

        $fileNomeInterno = gerarNomeInterno($conn, $obra_id, $tipo_id, $nomeTipo, $tipo_arquivo, $ext);
        $destFile = $destDir . "/" . $fileNomeInterno;

        // Substituição
        $check = $conn->query("SELECT * FROM arquivos 
            WHERE obra_id=$obra_id AND tipo_imagem_id=$tipo_id AND tipo='$tipo_arquivo' AND status='atualizado'");
        if ($check->num_rows > 0 && $substituicao) {
            while ($old = $check->fetch_assoc()) {
                $oldPath = $destDir . "/" . $old['nome_interno'];
                $newPath = $destDir . "/OLD/" . $old['nome_interno'];
                @$sftp->rename($oldPath, $newPath);
                $conn->query("UPDATE arquivos SET status='antigo' WHERE idarquivo=" . $old['idarquivo']);
            }
        }

        // Verifique se o arquivo existe e não está vazio
        if (!empty($fileTmp) && file_exists($fileTmp)) {
            if ($sftp->put($destFile, $fileTmp, SFTP::SOURCE_LOCAL_FILE)) {
                $stmt = $conn->prepare("INSERT INTO arquivos 
                    (obra_id, tipo_imagem_id, nome_original, nome_interno, caminho, tipo, status, origem, recebido_por, categoria) 
                    VALUES (?, ?, ?, ?, ?, ?, 'atualizado', 'upload_web', 'sistema', 'Arquitetonico')");
                $stmt->bind_param("iissss", $obra_id, $tipo_id, $fileOriginalName, $fileNomeInterno, $destFile, $tipo_arquivo);
                $stmt->execute();
                $success[] = "Arquivo '$fileOriginalName' enviado para $nomeTipo como '$fileNomeInterno'";
            } else {
                $errors[] = "Erro ao enviar '$fileOriginalName' para $nomeTipo";
            }
        } else {
            $errors[] = "Arquivo '$fileOriginalName' não encontrado ou está vazio.";
        }
    }
}

// =======================
// Upload refs/skp por imagem
// =======================
if (in_array($tipo_arquivo, ['refs', 'skp']) && !empty($arquivosPorImagem)) {
    foreach ($arquivosPorImagem['name'] as $imagem_id => $arquivosArray) {
        foreach ($arquivosArray as $index => $nomeOriginal) {
            $tmpFile = $arquivosPorImagem['tmp_name'][$imagem_id][$index];
            $ext = pathinfo($nomeOriginal, PATHINFO_EXTENSION);

            $nomeTipo = $tiposImagem[0] ?? '';
            $tipo_id = buscarTipoImagemId($conn, $nomeTipo);
            if (!$tipo_id) {
                $errors[] = "Tipo de imagem '$nomeTipo' não encontrado.";
                continue;
            }
            $nomeTipoLimpo = preg_replace('/\s+/', '', $nomeTipo);

            $destDir = "{$pastaBase}{$nomeTipoLimpo}/{$tipo_arquivo}/Imagem_{$imagem_id}";
            if (!$sftp->is_dir($destDir)) $sftp->mkdir($destDir, 0777, true);

            $fileNomeInterno = gerarNomeInterno($conn, $obra_id, $tipo_id, $nomeTipo, $tipo_arquivo, $ext);
            $destFile = "$destDir/$fileNomeInterno";

            // Verifique se o arquivo existe e não está vazio
            if (!empty($tmpFile) && file_exists($tmpFile)) {
                if ($sftp->put($destFile, $tmpFile, SFTP::SOURCE_LOCAL_FILE)) {
                    $stmt = $conn->prepare("INSERT INTO arquivos 
                        (obra_id, tipo_imagem_id, imagem_id, nome_original, nome_interno, caminho, tipo, status, origem, recebido_por, categoria) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'atualizado', 'upload_web', 'sistema', 'Arquitetonico')");
                    $stmt->bind_param("iiissss", $obra_id, $tipo_id, $imagem_id, $nomeOriginal, $fileNomeInterno, $destFile, $tipo_arquivo);
                    $stmt->execute();
                    $success[] = "Arquivo '$nomeOriginal' enviado para Imagem $imagem_id";
                } else {
                    $errors[] = "Erro ao enviar '$nomeOriginal' para Imagem $imagem_id";
                }
            } else {
                $errors[] = "Arquivo '$nomeOriginal' não encontrado ou está vazio.";
            }
        }
    }
}

$conn->close();
echo json_encode(['success' => $success, 'errors' => $errors]);
