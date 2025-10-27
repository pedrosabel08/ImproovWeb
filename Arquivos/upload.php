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

// ---------- Dados FTP ----------
$ftp_host = "ftp.improov.com.br";
$ftp_port = 21; // porta padrão FTP
$ftp_user = "improov";
$ftp_pass = "Impr00v";
$ftp_base = "/www/sistema/uploads/angulo_definido";

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
$categoria  = intval($_POST['tipo_categoria'] ?? 0);
$refsSkpModo = $_POST['refsSkpModo'] ?? 'geral';
$descricao    = $_POST['descricao'] ?? "";

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
        6 => 'Alteracoes',
        7 => 'Angulo definido'
    ];
    return $categorias[$categoriaId] ?? 'Outros';
}

// Normaliza um nome para uso em pasta: remove acentos, caracteres perigosos e substitui espaços por '_'
function sanitizeDirName($str)
{
    $map = [
        '/[áàãâä]/ui' => 'a',
        '/[éèêë]/ui' => 'e',
        '/[íìîï]/ui' => 'i',
        '/[óòõôö]/ui' => 'o',
        '/[úùûü]/ui' => 'u',
        '/[ç]/ui' => 'c'
    ];
    $str = preg_replace(array_keys($map), array_values($map), $str);
    // remove caracteres não alfanuméricos exceto espaço e underscore
    $str = preg_replace('/[^A-Za-z0-9 _-]/', '', $str);
    $str = preg_replace('/\s+/', '_', trim($str));
    return $str;
}

// Tenta resolver a pasta de categoria existente no SFTP; se não encontrar, cria uma pasta sanitizada
function resolveCategoriaDir($sftp, $pastaBase, $categoriaNome, &$log)
{
    $candidates = [];
    $candidates[] = $categoriaNome;
    $candidates[] = str_replace(' ', '', $categoriaNome);
    $candidates[] = str_replace(' ', '_', $categoriaNome);
    $candidates[] = sanitizeDirName($categoriaNome);

    foreach ($candidates as $cand) {
        $path = rtrim($pastaBase, '/') . '/' . $cand;
        if ($sftp->is_dir($path)) {
            $log[] = "Categoria encontrada: $path (usando candidato '$cand')";
            return $cand;
        }
    }

    // Não encontrou — cria com versão sanitizada
    $safe = sanitizeDirName($categoriaNome);
    $pathSafe = rtrim($pastaBase, '/') . '/' . $safe;
    if (!$sftp->is_dir($pathSafe)) {
        if ($sftp->mkdir($pathSafe, 0777, true)) {
            $log[] = "Criada pasta de categoria sanitizada: $pathSafe";
        } else {
            $log[] = "Falha ao criar pasta de categoria: $pathSafe";
        }
    }
    return $safe;
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

// Garante que um caminho exista no FTP (cria recursivamente). Retorna true/false e adiciona logs.
function ensureFtpDir($ftp, $path, &$log)
{
    // Remove barras duplicadas e mantém caminho absoluto
    $parts = array_filter(explode('/', $path), 'strlen');
    $cur = '/';
    // Salva diretório atual
    $orig = @ftp_pwd($ftp);
    foreach ($parts as $p) {
        $cur = rtrim($cur, '/') . '/' . $p;
        // tenta mudar para o diretório
        if (@ftp_chdir($ftp, $cur) === false) {
            // tenta criar
            if (@ftp_mkdir($ftp, $cur) === false) {
                $log[] = "Falha ao criar pasta FTP: $cur";
                // tenta voltar
                if ($orig) @ftp_chdir($ftp, $orig);
                return false;
            } else {
                $log[] = "Criada pasta FTP: $cur";
            }
        }
    }
    // volta para o original, se possível
    if ($orig) @ftp_chdir($ftp, $orig);
    return true;
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

// Variável de conexão FTP (inicializada apenas se necessária)
$ftp_conn = null;


function gerarNomeInterno($conn, $obra_id, $tipo_id, $categoria, $nomeTipo, $tipo_arquivo, $ext, &$log, $imagem_id = null, $fileOriginalName = null, $indiceEnvio = 1)
{
    // 🔹 Busca nomenclatura da obra
    $obraRes = $conn->query("SELECT nomenclatura FROM obra WHERE idobra = $obra_id");
    $nomenclatura = ($obraRes && $obraRes->num_rows > 0)
        ? $obraRes->fetch_assoc()['nomenclatura']
        : "OBRA{$obra_id}";

    // 🔹 Limpeza e redução dos nomes
    $nomeTipoLimpo = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nomeTipo), 0, 3));
    $categoriaNome = strtoupper(substr(buscarNomeCategoria($categoria), 0, 3));
    $tipoArquivoAbrev = strtoupper(substr($tipo_arquivo, 0, 3));

    // 🔹 Parte base do nome original (sem extensão)
    $fileOriginalBase = $fileOriginalName
        ? preg_replace('/[^A-Za-z0-9]/', '', pathinfo($fileOriginalName, PATHINFO_FILENAME))
        : '';

    // 🔹 Se for SKP ou IMG → buscar versão pelo campo versao
    if ($tipo_arquivo === 'SKP' || $tipo_arquivo === 'IMG') {
        // Primeiro tenta encontrar por nome_original (comportamento antigo)
        $sql = "SELECT versao FROM arquivos 
                WHERE obra_id = ? 
                  AND tipo_imagem_id = ? 
                  AND categoria_id = ? 
                  AND tipo = ? 
                  AND nome_original = ?";
        $params = [$obra_id, $tipo_id, $categoria, $tipo_arquivo, $fileOriginalName];
        $types = "iiiss";

        if ($imagem_id) {
            $sql .= " AND imagem_id = ?";
            $params[] = $imagem_id;
            $types .= "i";
        }

        $sql .= " ORDER BY versao DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $versao = 1;
        if ($row = $res->fetch_assoc()) {
            $versao = intval($row['versao']) + 1;
        } else {
            // Fallback: procurar última versão por obra/tipo_imagem/categoria/(imagem_id) independente do nome_original
            $sql2 = "SELECT versao FROM arquivos 
                     WHERE obra_id = ? 
                       AND tipo_imagem_id = ? 
                       AND categoria_id = ? 
                       AND tipo = ?";
            $params2 = [$obra_id, $tipo_id, $categoria, $tipo_arquivo];
            $types2 = "iiii";
            if ($imagem_id) {
                $sql2 .= " AND imagem_id = ?";
                $params2[] = $imagem_id;
                $types2 .= "i";
            }
            $sql2 .= " ORDER BY versao DESC LIMIT 1";
            $stmt2 = $conn->prepare($sql2);
            if ($stmt2) {
                $stmt2->bind_param($types2, ...$params2);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                if ($row2 = $res2->fetch_assoc()) {
                    $versao = intval($row2['versao']) + 1;
                }
                $stmt2->close();
            }
        }
    }
    // 🔹 Demais tipos → busca pela convenção antiga
    else {
        $sql = "SELECT nome_interno FROM arquivos 
                WHERE obra_id = ? 
                  AND tipo_imagem_id = ? 
                  AND categoria_id = ? 
                  AND tipo = ? 
                  AND nome_original = ?";
        $params = [$obra_id, $tipo_id, $categoria, $tipo_arquivo, $fileOriginalName];
        $types = "iiiss";

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
    }

    // 🔹 Montagem final do nome interno
    $envioStr = sprintf("env%02d", $indiceEnvio);
    $nomeInterno = "{$nomenclatura}_{$categoriaNome}_{$tipoArquivoAbrev}_{$envioStr}_v{$versao}.{$ext}";

    $log[] = "Gerado nome interno: $nomeInterno (versão $versao, envio $indiceEnvio)";

    return [$nomeInterno, $versao];
}


// =======================
// Upload principal
// =======================
if (!empty($arquivosTmp) && count($arquivosTmp) > 0 && ($refsSkpModo === 'geral' || $tipo_arquivo !== 'SKP')) {
    $indice = 1;

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
            // resolve candidate folder name no SFTP (tenta variações e cria uma pasta sanitizada caso necessário)
            $categoriaDir = resolveCategoriaDir($sftp, $pastaBase, $categoriaNome, $log);

            $destDir = rtrim($pastaBase, '/') . '/' . $categoriaDir . '/' . $nomeTipo . '/' . $tipo_arquivo;
            if (!$sftp->is_dir($destDir)) {
                $sftp->mkdir($destDir, 0777, true);
                $log[] = "Criado diretório: $destDir";
            }
            if (!$sftp->is_dir($destDir . "/OLD")) {
                $sftp->mkdir($destDir . "/OLD", 0777, true);
                $log[] = "Criado diretório: $destDir/OLD";
            }

            list($fileNomeInterno, $versao) = gerarNomeInterno($conn, $obra_id, $tipo_id, $categoria, $nomeTipo, $tipo_arquivo, $ext, $log, null, $fileOriginalName, $indice);
            $destFile = $destDir . "/" . $fileNomeInterno;
            $log[] = "Destino final: $destFile";
            $indice++;

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
                    (obra_id, tipo_imagem_id, imagem_id, nome_original, nome_interno, caminho, tipo, versao, status, origem, recebido_por, categoria_id, descricao) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'atualizado', 'upload_web', 'sistema', ?, ?)");
                    $stmt->bind_param("iiissssiii", $obra_id, $tipo_id, $imagem_id, $fileOriginalName, $fileNomeInterno, $destFile, $tipo_arquivo, $versao, $categoria, $descricao);

                    $stmt->execute();
                    $success[] = "Arquivo '$fileOriginalName' enviado para $nomeTipo como '$fileNomeInterno'";
                    $log[] = "Arquivo enviado com sucesso: $destFile";

                    // Se for categoria 7, também envia ao FTP secundário
                    if ($categoria == 7) {
                        if ($ftp_conn === null) {
                            $ftp_conn = @ftp_connect($ftp_host, $ftp_port, 10);
                            if ($ftp_conn && @ftp_login($ftp_conn, $ftp_user, $ftp_pass)) {
                                @ftp_pasv($ftp_conn, true);
                                $log[] = "Conectado no FTP secundário: $ftp_host";
                            } else {
                                $log[] = "Falha ao conectar no FTP secundário: $ftp_host";
                                $errors[] = "Falha ao conectar no FTP secundário.";
                                $ftp_conn = null;
                            }
                        }

                        if ($ftp_conn) {
                            $nomen = buscarNomenclatura($conn, $obra_id);
                            $ftpTargetDir = rtrim($ftp_base, '/') . '/' . ($nomen ? $nomen : 'OBRA' . $obra_id) . '/' . $categoriaDir . '/' . $nomeTipo . '/' . $tipo_arquivo;
                            if (ensureFtpDir($ftp_conn, $ftpTargetDir, $log)) {
                                $ftpDest = $ftpTargetDir . '/' . $fileNomeInterno;
                                if (@ftp_put($ftp_conn, $ftpDest, $fileTmp, FTP_BINARY)) {
                                    $log[] = "Arquivo enviado para FTP: $ftpDest";
                                } else {
                                    $errors[] = "Erro ao enviar para FTP: $ftpDest";
                                    $log[] = "Falha FTP put: $ftpDest";
                                }
                            }
                        }
                    }
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
// Permitir upload por-imagem também para categoria 7 (Ângulo definido) além da categoria 2 ou SKP
if ((!empty($arquivosPorImagem)) && ($categoria == 2 || $tipo_arquivo === 'SKP' || $categoria == 7) && $refsSkpModo === 'porImagem') {


    $log[] = "Iniciando upload refs/skp por imagem...";
    foreach ($arquivosPorImagem['name'] as $imagem_id => $arquivosArray) {
        // Substituição apenas uma vez por imagem
        // Filtra por obra_id, tipo, imagem_id e categoria_id para evitar mover arquivos de outras categorias
        $stmtCheck = $conn->prepare("SELECT * FROM arquivos 
        WHERE obra_id = ? AND tipo = ? AND imagem_id = ? AND categoria_id = ? AND status = 'atualizado'");
        if ($stmtCheck) {
            $stmtCheck->bind_param("isii", $obra_id, $tipo_arquivo, $imagem_id, $categoria);
            $stmtCheck->execute();
            $check = $stmtCheck->get_result();
            $log[] = "Encontrados {$check->num_rows} arquivos refs/skp antigos para imagem $imagem_id (categoria $categoria).";
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
            $stmtCheck->close();
        } else {
            $log[] = "Falha ao preparar consulta de verificação de arquivos antigos: " . $conn->error;
        }
        $indice = 1;

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
            $categoriaDir = resolveCategoriaDir($sftp, $pastaBase, $categoriaNome, $log);

            $destDir = rtrim($pastaBase, '/') . '/' . $categoriaDir . '/' . $nomeTipo . '/' . $tipo_arquivo . '/' . $nome_imagem;
            if (!$sftp->is_dir($destDir)) {
                $sftp->mkdir($destDir, 0777, true);
                $log[] = "Criado diretório: $destDir";
            }
            if (!$sftp->is_dir($destDir . "/OLD")) {
                $sftp->mkdir($destDir . "/OLD", 0777, true);
                $log[] = "Criado diretório: $destDir/OLD";
            }

            list($fileNomeInterno, $versao) = gerarNomeInterno($conn, $obra_id, $tipo_id, $categoria, $nomeTipo, $tipo_arquivo, $ext, $log, $imagem_id, $nomeOriginal, $indice);
            $destFile = "$destDir/$fileNomeInterno";
            $log[] = "Destino final: $destFile";

            $indice++;

            if ($sftp->put($destFile, $tmpFile, SFTP::SOURCE_LOCAL_FILE)) {
                $stmt = $conn->prepare("INSERT INTO arquivos 
                (obra_id, tipo_imagem_id, imagem_id, nome_original, nome_interno, caminho, tipo, versao, status, origem, recebido_por, categoria_id, descricao) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'atualizado', 'upload_web', 'sistema', ?, ?)");
                $stmt->bind_param("iiissssiii", $obra_id, $tipo_id, $imagem_id, $nomeOriginal, $fileNomeInterno, $destFile, $tipo_arquivo, $versao, $categoria, $descricao);

                $stmt->execute();
                $success[] = "Arquivo '$nomeOriginal' enviado para Imagem $imagem_id";
                $log[] = "Arquivo enviado com sucesso: $destFile";

                // Se for categoria 7, envia também ao FTP secundário
                if ($categoria == 7) {
                    if ($ftp_conn === null) {
                        $ftp_conn = @ftp_connect($ftp_host, $ftp_port, 10);
                        if ($ftp_conn && @ftp_login($ftp_conn, $ftp_user, $ftp_pass)) {
                            @ftp_pasv($ftp_conn, true);
                            $log[] = "Conectado no FTP secundário: $ftp_host";
                        } else {
                            $log[] = "Falha ao conectar no FTP secundário: $ftp_host";
                            $errors[] = "Falha ao conectar no FTP secundário.";
                            $ftp_conn = null;
                        }
                    }

                    if ($ftp_conn) {
                        $nomen = buscarNomenclatura($conn, $obra_id);
                        $ftpTargetDir = rtrim($ftp_base, '/') . '/' . ($nomen ? $nomen : 'OBRA' . $obra_id) . '/' . $categoriaDir . '/' . $nomeTipo . '/' . $tipo_arquivo . '/' . $nome_imagem;
                        if (ensureFtpDir($ftp_conn, $ftpTargetDir, $log)) {
                            $ftpDest = $ftpTargetDir . '/' . $fileNomeInterno;
                            if (@ftp_put($ftp_conn, $ftpDest, $tmpFile, FTP_BINARY)) {
                                $log[] = "Arquivo enviado para FTP: $ftpDest";
                            } else {
                                $errors[] = "Erro ao enviar para FTP: $ftpDest";
                                $log[] = "Falha FTP put: $ftpDest";
                            }
                        }
                    }
                }
            } else {
                $errors[] = "Erro ao enviar '$nomeOriginal' para Imagem $imagem_id";
                $log[] = "Falha ao enviar: $destFile";
            }
        }
    }
}

$conn->close();
if ($ftp_conn) {
    @ftp_close($ftp_conn);
    $log[] = "Conexão FTP fechada.";
}
echo json_encode(['success' => $success, 'errors' => $errors, 'log' => $log]);
