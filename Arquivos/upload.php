<?php
require_once __DIR__ . '/../vendor/autoload.php';

use phpseclib3\Net\SFTP;

include '../conexao.php';
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: https://improov.com.br");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

$host = "imp-nas.ddns.net";
$port = 2222;
$username = "flow";
$password = "flow@2025";

// Variáveis do log
$log = [];
$success = [];
$errors = [];

// Dados do formulário
$obra_id      = intval($_POST['obra_id']);
$tipo_arquivo = $_POST['tipo_arquivo'] ?? "outros";
$descricao    = $_POST['descricao'] ?? "";
$flag_master  = !empty($_POST['flag_master']) ? 1 : 0;
$substituicao = !empty($_POST['flag_substituicao']);
$tiposImagem  = $_POST['tipo_imagem'] ?? [];
$categoria  = $_POST['tipo_categoria'] ?? "";
$refsSkpModo = $_POST['refsSkpModo'] ?? 'geral';

$log[] = "Recebido: obra_id=$obra_id, tipo_arquivo=$tipo_arquivo, substituicao=" . ($substituicao ? 'SIM' : 'NAO');
$log[] = "Tipos imagem: " . json_encode($tiposImagem);

// Arquivos principais
$arquivosTmp  = $_FILES['arquivos']['tmp_name'] ?? [];
$arquivosName = $_FILES['arquivos']['name'] ?? [];

// Arquivos por imagem (refs/skp)
$arquivosPorImagem = $_FILES['arquivos_por_imagem'] ?? [];

$sftp = new SFTP($host, $port);
if (!$sftp->login($username, $password)) {
    echo json_encode(['errors' => ["Erro ao conectar no servidor SFTP."], 'log' => $log]);
    exit;
}
$log[] = "Conectado no servidor SFTP.";

// Funções auxiliares
function buscarTipoImagemId($conn, $nomeTipo, &$log)
{
    $nomeTipo = $conn->real_escape_string($nomeTipo);
    $res = $conn->query("SELECT id_tipo_imagem FROM tipo_imagem WHERE nome='$nomeTipo'");
    $log[] = "Query buscarTipoImagemId: $nomeTipo (" . ($res && $res->num_rows > 0 ? "ENCONTRADO" : "NÃO ENCONTRADO") . ")";
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return $row['id_tipo_imagem'];
    }
    return null;
}

function buscarNomeCategoria($categoriaId)
{
    $categorias = [
        1 => 'Arquitetonico',
        2 => 'Referencias',
        3 => 'Paisagismo',
        4 => 'Luminotecnico',
        5 => 'Estrutural',
        6 => 'Alteracoes'
    ];
    return $categorias[$categoriaId] ?? 'Outros';
}

function buscarNomenclatura($conn, $obra_id)
{
    $stmt = $conn->prepare("SELECT nomenclatura FROM obra WHERE idobra = ?");
    $stmt->bind_param("i", $obra_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        return $row['nomenclatura'];
    }

    return null;
}

function buscarPastaBaseSFTP($sftp, $conn, $obra_id)
{
    // Bases de clientes (anos possíveis)
    $clientes_base = ['/mnt/clientes/2024', '/mnt/clientes/2025'];

    // Busca a nomenclatura da obra
    $stmt = $conn->prepare("SELECT nomenclatura FROM obra WHERE idobra = ?");
    $stmt->bind_param("i", $obra_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || !$row = $result->fetch_assoc()) {
        return null;
    }

    $nomenclatura = $row['nomenclatura'];

    // Verifica em qual base a obra existe **no SFTP**
    foreach ($clientes_base as $base) {
        $pasta = $base . "/" . $nomenclatura . "/05.Exchange/01.Input/";
        if ($sftp->is_dir($pasta)) {
            return $pasta; // Retorna caminho válido no servidor
        }
    }

    return null; // Não encontrado
}



$pastaBase = buscarPastaBaseSFTP($sftp, $conn, $obra_id);
if (!$pastaBase) {
    $errors[] = "Pasta da obra não encontrada no servidor SFTP para Obra ID $obra_id.";
    echo json_encode(['success' => $success, 'errors' => $errors, 'log' => $log]);
    exit;
}


function gerarNomeInterno($conn, $obra_id, $tipo_id, $categoria, $nomeTipo, $tipo_arquivo, $ext, &$log, $imagem_id = null, $fileOriginalName = null)
{
    $obraRes = $conn->query("SELECT nomenclatura FROM obra WHERE idobra = $obra_id");
    $nomenclatura = ($obraRes && $obraRes->num_rows > 0) ? $obraRes->fetch_assoc()['nomenclatura'] : "OBRA{$obra_id}";

    $nomeTipoLimpo = preg_replace('/[^A-Za-z0-9]/', '', $nomeTipo);
    $categoriaNome = buscarNomeCategoria($categoria);

    // Adiciona parte do nome original para garantir unicidade
    $fileOriginalBase = $fileOriginalName ? preg_replace('/[^A-Za-z0-9]/', '', pathinfo($fileOriginalName, PATHINFO_FILENAME)) : '';

    $sql = "SELECT nome_interno FROM arquivos 
        WHERE obra_id = ? 
          AND tipo_imagem_id = ? 
          AND categoria_id = ? 
          AND tipo = ?
          AND nome_original = ?";
    $params = [$obra_id, $tipo_id, $categoria, $tipo_arquivo, $fileOriginalName];
    $types = "iiiss";

    if ($imagem_id && ($tipo_arquivo === 'SKP' || $tipo_arquivo === 'IMG')) {
        $sql .= " AND imagem_id = ?";
        $params[] = $imagem_id;
        $types .= "i";
    }

    $sql .= " ORDER BY idarquivo DESC LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $versao = 1;
    if ($row = $res->fetch_assoc()) {
        if (preg_match('/_v(\d+)/', $row['nome_interno'], $m)) {
            $versao = intval($m[1]) + 1;
        }
    }

    // Inclui parte do nome original para diferenciar
    $nomeInterno = "{$nomenclatura}_{$categoriaNome}_{$nomeTipoLimpo}_{$fileOriginalBase}_{$tipo_arquivo}_v{$versao}.{$ext}";
    $log[] = "Gerado nome interno: $nomeInterno";

    return $nomeInterno;
}

// =======================
// Upload principal
// =======================
if (!empty($arquivosTmp) && count($arquivosTmp) > 0 && ($refsSkpModo === 'geral' || $tipo_arquivo !== 'SKP')) {

    foreach ($arquivosTmp as $index => $fileTmp) {
        $fileOriginalName = basename($arquivosName[$index]);
        $ext = pathinfo($fileOriginalName, PATHINFO_EXTENSION);
        $log[] = "Processando upload principal: $fileOriginalName";

        foreach ($tiposImagem as $nomeTipo) {
            $tipo_id = buscarTipoImagemId($conn, $nomeTipo, $log);
            if (!$tipo_id) {
                $errors[] = "Tipo de imagem '$nomeTipo' não encontrado.";
                continue;
            }

            $categoriaNome = buscarNomeCategoria($categoria);

            $destDir = $pastaBase . $categoriaNome . "/" . $nomeTipo . "/" . $tipo_arquivo;
            if (!$sftp->mkdir($destDir)) {
                $sftp->mkdir($destDir, 0777, true);
                $log[] = "Criado diretório: $destDir";
            }
            if (!$sftp->mkdir($destDir . "/OLD")) {
                $sftp->mkdir($destDir . "/OLD", 0777, true);
                $log[] = "Criado diretório: $destDir/OLD";
            }

            $fileNomeInterno = gerarNomeInterno($conn, $obra_id, $tipo_id, $categoria, $nomeTipo, $tipo_arquivo, $ext, $log, null, $fileOriginalName);
            $destFile = $destDir . "/" . $fileNomeInterno;
            $log[] = "Destino final: $destFile";

            // Substituição
            $check = $conn->prepare("SELECT * FROM arquivos 
    WHERE obra_id = ? 
      AND tipo_imagem_id = ? 
      AND tipo = ? 
      AND status = 'atualizado'
      AND nome_original = ?");
            $check->bind_param("iiss", $obra_id, $tipo_id, $tipo_arquivo, $fileOriginalName);
            $check->execute();
            $result = $check->get_result();
            $log[] = "Encontrados {$result->num_rows} arquivos antigos para $fileOriginalName.";
            if ($result->num_rows > 0 && $substituicao) {
                while ($old = $result->fetch_assoc()) {
                    $oldPath = $destDir . "/" . $old['nome_interno'];
                    $newPath = $destDir . "/OLD/" . $old['nome_interno'];
                    $log[] = "Movendo $oldPath => $newPath";
                    if (!$sftp->rename($oldPath, $newPath)) {
                        $errors[] = "Falha ao mover {$old['nome_interno']} para OLD.";
                    }
                    $conn->query("UPDATE arquivos SET status='antigo' WHERE idarquivo=" . $old['idarquivo']);
                }
            }

            if (!empty($fileTmp) && file_exists($fileTmp)) {
                if ($sftp->put($destFile, $fileTmp, SFTP::SOURCE_LOCAL_FILE)) {
                    $stmt = $conn->prepare("INSERT INTO arquivos 
                    (obra_id, tipo_imagem_id, nome_original, nome_interno, caminho, tipo, status, origem, recebido_por, categoria_id) 
                    VALUES (?, ?, ?, ?, ?, ?, 'atualizado', 'upload_web', 'sistema', ?)");
                    $stmt->bind_param("iissssi", $obra_id, $tipo_id, $fileOriginalName, $fileNomeInterno, $destFile, $tipo_arquivo, $categoria);
                    $stmt->execute();
                    $success[] = "Arquivo '$fileOriginalName' enviado para $nomeTipo como '$fileNomeInterno'";
                    $log[] = "Arquivo enviado com sucesso: $destFile";
                } else {
                    $errors[] = "Erro ao enviar '$fileOriginalName' para $nomeTipo";
                    $log[] = "Falha ao enviar: $destFile";
                }
            }
        }
    }
}

// =======================
// Upload refs/skp por imagem
// =======================
if ((!empty($arquivosPorImagem)) && ($categoria == 2 || $tipo_arquivo === 'SKP') && $refsSkpModo === 'porImagem') {
    $log[] = "Iniciando upload refs/skp por imagem...";
    foreach ($arquivosPorImagem['name'] as $imagem_id => $arquivosArray) {
        // Substituição apenas uma vez por imagem
        $check = $conn->query("SELECT * FROM arquivos 
        WHERE obra_id=$obra_id AND tipo='$tipo_arquivo' AND imagem_id=$imagem_id 
        AND status='atualizado'");
        $log[] = "Encontrados {$check->num_rows} arquivos refs/skp antigos para imagem $imagem_id.";
        if ($check->num_rows > 0 && $substituicao) {
            while ($old = $check->fetch_assoc()) {
                $oldPath = $old['caminho'];
                $newPath = dirname($oldPath) . "/OLD/" . basename($oldPath);
                $log[] = "Movendo $oldPath => $newPath";
                if ($sftp->file_exists($oldPath)) {
                    if (!$sftp->rename($oldPath, $newPath)) {
                        $errors[] = "Falha ao mover {$old['nome_interno']} para OLD.";
                    }
                } else {
                    $errors[] = "Arquivo antigo {$old['nome_interno']} não encontrado.";
                }
                $conn->query("UPDATE arquivos SET status='antigo' WHERE idarquivo=" . $old['idarquivo']);
            }
        }

        // Agora envia todos os arquivos novos para essa imagem
        foreach ($arquivosArray as $index => $nomeOriginal) {
            $tmpFile = $arquivosPorImagem['tmp_name'][$imagem_id][$index];
            if (empty($tmpFile) || !file_exists($tmpFile)) {
                $log[] = "Arquivo vazio ou inexistente: $nomeOriginal";
                continue;
            }
            $ext = pathinfo($nomeOriginal, PATHINFO_EXTENSION);

            $nomeTipo = $tiposImagem[0] ?? '';
            $tipo_id = buscarTipoImagemId($conn, $nomeTipo, $log);
            if (!$tipo_id) {
                $errors[] = "Tipo de imagem '$nomeTipo' não encontrado.";
                continue;
            }

            $queryImagem = $conn->query("SELECT imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra=$imagem_id");
            if ($queryImagem->num_rows == 0) {
                $errors[] = "Imagem ID $imagem_id não encontrada.";
                $log[] = "Imagem ID $imagem_id não encontrada.";
                continue;
            }
            $nome_imagem = $queryImagem->fetch_assoc()['imagem_nome'];

            $categoriaNome = buscarNomeCategoria($categoria);

            $destDir = $pastaBase . $categoriaNome . "/" . $nomeTipo . "/" . $tipo_arquivo . "/" . $nome_imagem;
            if (!$sftp->is_dir($destDir)) {
                $sftp->mkdir($destDir, 0777, true);
                $log[] = "Criado diretório: $destDir";
            }
            if (!$sftp->is_dir($destDir . "/OLD")) {
                $sftp->mkdir($destDir . "/OLD", 0777, true);
                $log[] = "Criado diretório: $destDir/OLD";
            }

            $fileNomeInterno = gerarNomeInterno($conn, $obra_id, $tipo_id, $categoria, $nomeTipo, $tipo_arquivo, $ext, $log, $imagem_id);
            $destFile = "$destDir/$fileNomeInterno";
            $log[] = "Destino final: $destFile";

            if ($sftp->put($destFile, $tmpFile, SFTP::SOURCE_LOCAL_FILE)) {
                $stmt = $conn->prepare("INSERT INTO arquivos 
                (obra_id, tipo_imagem_id, imagem_id, nome_original, nome_interno, caminho, tipo, status, origem, recebido_por, categoria_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'atualizado', 'upload_web', 'sistema', ?)");
                $stmt->bind_param("iiissssi", $obra_id, $tipo_id, $imagem_id, $nomeOriginal, $fileNomeInterno, $destFile, $tipo_arquivo, $categoria);
                $stmt->execute();
                $success[] = "Arquivo '$nomeOriginal' enviado para Imagem $imagem_id";
                $log[] = "Arquivo enviado com sucesso: $destFile";
            } else {
                $errors[] = "Erro ao enviar '$nomeOriginal' para Imagem $imagem_id";
                $log[] = "Falha ao enviar: $destFile";
            }
        }
    }
}

$conn->close();
echo json_encode(['success' => $success, 'errors' => $errors, 'log' => $log]);
