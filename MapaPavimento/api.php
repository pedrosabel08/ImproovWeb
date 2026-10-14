<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/referencia_arquitetonica_helper.php';
require_once __DIR__ . '/../helpers/pdf_reference_helper.php';
require_once __DIR__ . '/../helpers/subtipo_imagem_helper.php';
require_once __DIR__ . '/../helpers/mapa_modelo_3d_helper.php';
require_once __DIR__ . '/../helpers/mapa_espacial_helper.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
$conn = null;
try {
    if (($_SESSION['logado'] ?? false) !== true) throw new RuntimeException('Sua sessão expirou. Entre novamente.', 401);
    $method = $_SERVER['REQUEST_METHOD'];
    $action = (string)($_GET['action'] ?? '');
    $writes = ['subtipo_salvar', 'documento_upload', 'pagina_vincular', 'legado_associar', 'referencia_configurar', 'ambiente_salvar', 'ambiente_excluir', 'modelo_3d_vincular', 'espacial_salvar'];
    if ($action === 'modelos_3d') $_SESSION['mapa_pavimento_csrf'] ??= bin2hex(random_bytes(32));
    if (in_array($action, $writes, true)) {
        if ($method !== 'POST') throw new RuntimeException('Use POST para esta operação.', 405);
        if (!in_array((int)($_SESSION['nivel_acesso'] ?? 0), [1, 2], true)) throw new RuntimeException('Você não tem permissão para editar o mapa.', 403);
        if (empty($_SESSION['mapa_pavimento_csrf']) || !hash_equals($_SESSION['mapa_pavimento_csrf'], (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) throw new RuntimeException('Atualize a página e tente novamente.', 403);
    } elseif ($method !== 'GET') throw new RuntimeException('Método não permitido.', 405);
    $author = (int)($_SESSION['idcolaborador'] ?? 0);
    $input = $method === 'POST' ? ($action === 'documento_upload' ? $_POST : json_decode(file_get_contents('php://input'), true, 64, JSON_THROW_ON_ERROR)) : $_GET;
    if (!is_array($input)) throw new InvalidArgumentException('Dados inválidos.');
    $obra = (int)($input['obra_id'] ?? 0);
    $subtype = (int)($input['subtipo_id'] ?? 0);
    $conn = conectarBanco();
    mp_require_obra($conn, $obra);
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $result = [];
    switch ($action) {
        case 'espacial_configuracao':
            $result['configuracao'] = mpe_get($conn, $obra);
            break;
        case 'espacial_salvar':
            $result['configuracao'] = mpe_save($conn, $obra, $input, $author);
            break;
        case 'modelos_3d':
            $result['csrf'] = $_SESSION['mapa_pavimento_csrf'];
            $result['can_edit'] = in_array((int)($_SESSION['nivel_acesso'] ?? 0), [1, 2], true);
            $result['obras'] = array_map(fn($o) => ['id' => (int)$o['idobra'], 'nome' => $o['nomenclatura'] . ' — ' . $o['nome_obra']], obterObras($conn));
            $result['modelos'] = mp_rows($conn, 'SELECT id,nome,arquivo FROM mapa_modelos_3d WHERE obra_id=? ORDER BY nome,id', 'i', [$obra]);
            foreach ($result['modelos'] as &$model) $model['id'] = (int)$model['id'];
            unset($model);
            break;
        case 'modelo_3d_contexto':
            $model = mp3d_model($conn, $obra, (int)($input['modelo_id'] ?? 0));
            $result['modelo'] = $model;
            $result['modelo']['src'] = './' . rawurlencode($model['arquivo']) . '?v=' . filemtime(mp3d_file($model['arquivo']));
            $result['subtipos'] = mp3d_subtypes($conn, $obra);
            $result['vinculos'] = mp3d_links($conn, $model['id']);
            break;
        case 'modelo_3d_vincular':
            $modelId = (int)($input['modelo_id'] ?? 0);
            mp3d_save_link($conn, $obra, $modelId, (string)($input['object_name'] ?? ''), $subtype, (int)($input['versao'] ?? 0), $author);
            $result['vinculos'] = mp3d_links($conn, $modelId);
            break;
        case 'contexto':
            $groups = flow_subtipos($conn, $obra);
            $used = array_map('intval', array_column(mp_rows($conn, 'SELECT subtipo_id FROM referencias_arquitetonicas WHERE obra_id=? AND subtipo_id IS NOT NULL', 'i', [$obra]), 'subtipo_id'));
            foreach ($groups['demais'] as $index => $item) if (in_array($item['id'], $used, true)) {
                $groups['obra'][] = $item;
                unset($groups['demais'][$index]);
            }
            $groups['demais'] = array_values($groups['demais']);
            $result['subtipos'] = $groups;
            $result['documentos'] = mp_rows($conn, 'SELECT d.id,d.nome_original,d.total_paginas,d.tamanho_bytes,COUNT(DISTINCT r.pagina) AS paginas_utilizadas FROM mapa_documentos d LEFT JOIN referencias_arquitetonicas r ON r.documento_id=d.id WHERE d.obra_id=? GROUP BY d.id ORDER BY d.id DESC', 'i', [$obra]);
            $result['legados'] = mp_rows($conn, "SELECT r.id,JSON_UNQUOTE(JSON_EXTRACT(r.legado_json,'$.pavimento.nome')) AS nome FROM referencias_arquitetonicas r WHERE r.obra_id=? AND r.subtipo_id IS NULL ORDER BY r.id", 'i', [$obra]);
            $result['migracoes'] = mp_rows($conn, "SELECT id,subtipo_id,JSON_UNQUOTE(JSON_EXTRACT(legado_json,'$.pavimento.id')) AS pavimento_id FROM referencias_arquitetonicas WHERE obra_id=? AND legado_json IS NOT NULL", 'i', [$obra]);
            break;
        case 'subtipo_salvar':
            $result['subtipo'] = flow_subtipo_save($conn, $input['nome'] ?? null);
            break;
        case 'mapa':
            $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
            if (!empty($input['legado_id'])) {
                $reference = mp_row($conn, 'SELECT r.*,d.nome_original,d.total_paginas FROM referencias_arquitetonicas r LEFT JOIN mapa_documentos d ON d.id=r.documento_id WHERE r.id=? AND r.obra_id=? AND r.subtipo_id IS NULL', 'ii', [(int)$input['legado_id'], $obra]);
                if (!$reference) throw new RuntimeException('Configuração antiga não encontrada.', 404);
            } else {
                $result['subtipo'] = mp_subtype($conn, $subtype);
                $reference = mp_row($conn, 'SELECT r.*,d.nome_original,d.total_paginas FROM referencias_arquitetonicas r LEFT JOIN mapa_documentos d ON d.id=r.documento_id WHERE r.obra_id=? AND r.subtipo_id=?', 'ii', [$obra, $subtype]);
            }
            $result['referencia'] = $reference ? mp_reference_config($reference) : null;
            $refId = (int)($reference['id'] ?? 0);
            $environments = mp_rows($conn, 'SELECT a.id,a.nome,a.versao_edicao,d.geometria_json FROM ambientes a JOIN ambiente_demarcacoes d ON d.ambiente_id=a.id AND d.referencia_id=a.referencia_id WHERE a.referencia_id=? ORDER BY a.nome', 'i', [$refId]);
            $links = mp_rows($conn, 'SELECT ai.ambiente_id,ai.imagem_id FROM ambiente_imagens ai JOIN ambientes a ON a.id=ai.ambiente_id WHERE a.referencia_id=?', 'i', [$refId]);
            $byEnvironment = [];
            foreach ($links as $link) $byEnvironment[(int)$link['ambiente_id']][] = (int)$link['imagem_id'];
            foreach ($environments as &$environment) {
                $environment['id'] = (int)$environment['id'];
                $environment['versao_edicao'] = (int)$environment['versao_edicao'];
                $geometry = json_decode($environment['geometria_json'], true, 64, JSON_THROW_ON_ERROR);
                $crop = $result['referencia']['area_util'];
                $environment['geometria'] = mp_geometry_rebase($geometry, $crop, $crop);
                $environment['imagem_ids'] = $byEnvironment[$environment['id']] ?? [];
                unset($environment['geometria_json']);
            }
            unset($environment);
            $result['ambientes'] = $environments;
            $conn->commit();
            break;
        case 'imagens':
            $refId = (int)($input['referencia_id'] ?? 0);
            if ($refId) {
                $ref = mp_row($conn, 'SELECT id,subtipo_id FROM referencias_arquitetonicas WHERE id=? AND obra_id=?', 'ii', [$refId, $obra]);
                if (!$ref || (int)$ref['subtipo_id'] !== $subtype) throw new RuntimeException('Mapa não encontrado neste contexto.', 404);
            }
            $result['imagens'] = mp_map_images($conn, $obra);
            $result['preservadas'] = [];
            break;
        case 'documento_pdf':
            $doc = mp_row($conn, 'SELECT caminho FROM mapa_documentos WHERE id=? AND obra_id=?', 'ii', [(int)($input['documento_id'] ?? 0), $obra]);
            if (!$doc) throw new RuntimeException('Documento não encontrado nesta obra.', 404);
            $conn->close();
            $conn = null;
            mp_pdf_stream($doc['caminho']);
        case 'paginas':
            $doc = mp_row($conn, 'SELECT id,total_paginas FROM mapa_documentos WHERE id=? AND obra_id=?', 'ii', [(int)($input['documento_id'] ?? 0), $obra]);
            if (!$doc) throw new RuntimeException('Documento não encontrado nesta obra.', 404);
            $result['documento'] = $doc;
            $result['usos'] = mp_rows($conn, "SELECT r.pagina,r.subtipo_id,COALESCE(s.nome,JSON_UNQUOTE(JSON_EXTRACT(r.legado_json,'$.pavimento.nome'))) AS nome FROM referencias_arquitetonicas r LEFT JOIN subtipo_imagem s ON s.id=r.subtipo_id WHERE r.documento_id=? ORDER BY r.pagina,r.id", 'i', [$doc['id']]);
            break;
        case 'documento_upload':
            $file = $_FILES['pdf'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Não foi possível receber o PDF.');
            if ($file['size'] <= 0 || $file['size'] > 30 * 1024 * 1024) throw new InvalidArgumentException('Envie um PDF de até 30 MB.');
            $meta = mp_pdf_metadata($file['tmp_name']);
            $hash = hash_file('sha256', $file['tmp_name']);
            $original = mb_substr(basename(str_replace('\\', '/', (string)$file['name'])), 0, 255);
            $conn->begin_transaction();
            $destination = null;
            try {
                mp_row($conn, 'SELECT idobra FROM obra WHERE idobra=? FOR UPDATE', 'i', [$obra]);
                $existing = mp_row($conn, 'SELECT id,nome_original FROM mapa_documentos WHERE obra_id=? AND sha256=?', 'is', [$obra, $hash]);
                if ($existing) {
                    $result['id'] = (int)$existing['id'];
                    $result['duplicado'] = true;
                    $result['nome_original'] = $existing['nome_original'];
                } else {
                    $directory = mp_pdf_storage_directory() . '/' . $obra;
                    if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new RuntimeException('Não foi possível preparar o armazenamento.');
                    $filename = bin2hex(random_bytes(20)) . '.pdf';
                    $destination = $directory . '/' . $filename;
                    if (!move_uploaded_file($file['tmp_name'], $destination)) throw new RuntimeException('Não foi possível salvar o PDF.');
                    mp_query($conn, 'INSERT INTO mapa_documentos (obra_id,caminho,nome_original,tamanho_bytes,total_paginas,sha256,criado_por) VALUES (?,?,?,?,?,?,?)', 'issiisi', [$obra, 'uploads/referencias_arquitetonicas/' . $obra . '/' . $filename, $original, $file['size'], $meta['pages'], $hash, $author])->close();
                    $result['id'] = (int)$conn->insert_id;
                    $result['duplicado'] = false;
                }
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                if ($destination && is_file($destination)) unlink($destination);
                throw $e;
            }
            break;
        case 'pagina_vincular':
            mp_subtype($conn, $subtype);
            $docId = (int)($input['documento_id'] ?? 0);
            $page = (int)($input['pagina'] ?? 0);
            $conn->begin_transaction();
            try {
                mp_row($conn, 'SELECT idobra FROM obra WHERE idobra=? FOR UPDATE', 'i', [$obra]);
                $doc = mp_row($conn, 'SELECT id,total_paginas FROM mapa_documentos WHERE id=? AND obra_id=?', 'ii', [$docId, $obra]);
                if (!$doc) throw new RuntimeException('Documento não encontrado nesta obra.', 404);
                if ($page < 1 || $page > (int)$doc['total_paginas']) throw new InvalidArgumentException('Página inválida.');
                $current = mp_row($conn, 'SELECT * FROM referencias_arquitetonicas WHERE obra_id=? AND subtipo_id=? FOR UPDATE', 'ii', [$obra, $subtype]);
                if ((int)($current['versao_config'] ?? 0) !== (int)($input['versao_config'] ?? 0)) throw new RuntimeException('O mapa foi alterado. Atualize a página.', 409);
                $same = $current && (int)$current['documento_id'] === $docId && (int)$current['pagina'] === $page;
                if (!$same) {
                    if ($current && mp_row($conn, 'SELECT id FROM ambientes WHERE referencia_id=? LIMIT 1', 'i', [$current['id']])) throw new RuntimeException('Este mapa possui ambientes. Exclua as demarcações antes de trocar o documento ou a página.', 409);
                    if (mp_row($conn, 'SELECT id FROM referencias_arquitetonicas WHERE documento_id=? AND pagina=? AND (subtipo_id IS NULL OR subtipo_id<>?) LIMIT 1', 'iii', [$docId, $page, $subtype]) && ($input['confirmar_reuso'] ?? false) !== true) throw new RuntimeException('Esta página já está em uso. Confirme o compartilhamento.', 409);
                    if ($current) mp_query($conn, 'UPDATE referencias_arquitetonicas SET documento_id=?,pagina=?,area_util_json=NULL,layers_json=NULL,has_layers=NULL,versao_config=versao_config+1 WHERE id=?', 'iii', [$docId, $page, $current['id']])->close();
                    else mp_query($conn, 'INSERT INTO referencias_arquitetonicas (obra_id,subtipo_id,documento_id,pagina,criado_por) VALUES (?,?,?,?,?)', 'iiiii', [$obra, $subtype, $docId, $page, $author])->close();
                }
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
            break;
        case 'legado_associar':
            mp_subtype($conn, $subtype);
            $conn->begin_transaction();
            try {
                mp_row($conn, 'SELECT idobra FROM obra WHERE idobra=? FOR UPDATE', 'i', [$obra]);
                $legacy = mp_row($conn, 'SELECT id,versao_config FROM referencias_arquitetonicas WHERE id=? AND obra_id=? AND subtipo_id IS NULL FOR UPDATE', 'ii', [(int)($input['referencia_id'] ?? 0), $obra]);
                if (!$legacy) throw new RuntimeException('Configuração antiga não encontrada.', 404);
                if (mp_row($conn, 'SELECT id FROM referencias_arquitetonicas WHERE obra_id=? AND subtipo_id=?', 'ii', [$obra, $subtype])) throw new RuntimeException('Este subtipo já possui um mapa. Escolha outro subtipo para preservar ambas as configurações.', 409);
                mp_query($conn, 'UPDATE referencias_arquitetonicas SET subtipo_id=?,versao_config=versao_config+1 WHERE id=?', 'ii', [$subtype, $legacy['id']])->close();
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
            break;
        case 'referencia_configurar':
            mp_save_reference_config($conn, $obra, $input);
            break;
        case 'ambiente_salvar':
            $result['id'] = mp_save_environment($conn, $obra, $input, $author);
            break;
        case 'ambiente_excluir':
            $conn->begin_transaction();
            try {
                $ref = mp_map_context($conn, (int)($input['referencia_id'] ?? 0), $obra, $subtype, true);
                $env = mp_row($conn, 'SELECT versao_edicao FROM ambientes WHERE id=? AND referencia_id=? FOR UPDATE', 'ii', [(int)($input['id'] ?? 0), $ref['id']]);
                if (!$env) throw new RuntimeException('Ambiente não encontrado.', 404);
                if ((int)$env['versao_edicao'] !== (int)($input['versao_edicao'] ?? 0)) throw new RuntimeException('Este ambiente foi alterado. Atualize a página.', 409);
                mp_query($conn, 'DELETE FROM ambientes WHERE id=? AND referencia_id=?', 'ii', [(int)$input['id'], $ref['id']])->close();
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
            break;
        default:
            throw new RuntimeException('Operação não encontrada. Atualize a página.', 404);
    }
    echo json_encode(['sucesso' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if ($e instanceof InvalidArgumentException || $e instanceof JsonException) {
        $status = 422;
        $message = $e instanceof JsonException ? 'Dados JSON inválidos.' : $e->getMessage();
    } elseif ($e instanceof mysqli_sql_exception) {
        $status = $e->getCode() === 1062 ? 409 : 503;
        $message = $status === 409 ? 'Já existe uma configuração neste contexto.' : 'O mapa está temporariamente indisponível.';
        error_log('MapaPavimento: ' . $e->getMessage());
    } else {
        $status = in_array($e->getCode(), [401, 403, 404, 405, 409], true) ? $e->getCode() : 500;
        $message = $status === 500 ? 'Não foi possível concluir a operação. Tente novamente.' : $e->getMessage();
        if ($status === 500) error_log('MapaPavimento: ' . $e->getMessage());
    }
    http_response_code($status);
    echo json_encode(['sucesso' => false, 'erro' => $message], JSON_UNESCAPED_UNICODE);
} finally {
    if ($conn instanceof mysqli) $conn->close();
}

function mp_map_images(mysqli $conn, int $obra): array
{
    $links = mp_rows($conn, "SELECT ai.imagem_id,a.id,a.nome,r.subtipo_id,s.nome AS pavimento
        FROM ambiente_imagens ai JOIN ambientes a ON a.id=ai.ambiente_id
        JOIN referencias_arquitetonicas r ON r.id=a.referencia_id
        JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=ai.imagem_id
        LEFT JOIN subtipo_imagem s ON s.id=r.subtipo_id
        WHERE r.obra_id=? AND i.obra_id=? ORDER BY s.nome,a.nome,a.id", 'ii', [$obra, $obra]);
    $byImage = [];
    foreach ($links as $link) $byImage[(int)$link['imagem_id']][] = [
        'id' => (int)$link['id'],
        'nome' => $link['nome'],
        'pavimento' => $link['pavimento']
    ];
    $images = mp_rows($conn, "SELECT i.idimagens_cliente_obra AS id,i.imagem_nome,
        (SELECT hi.imagem FROM historico_aprovacoes_imagens hi JOIN funcao_imagem fi ON fi.idfuncao_imagem=hi.funcao_imagem_id WHERE fi.imagem_id=i.idimagens_cliente_obra AND hi.imagem IS NOT NULL AND hi.imagem<>'' ORDER BY hi.data_envio DESC,hi.id DESC LIMIT 1) AS preview
        FROM imagens_cliente_obra i WHERE i.obra_id=? ORDER BY i.imagem_nome,i.idimagens_cliente_obra", 'i', [$obra]);
    foreach ($images as &$image) {
        $image['id'] = (int)$image['id'];
        $path = trim((string)$image['preview']);
        $image['preview_url'] = null;
        if (preg_match('~^https?://~i', $path) && filter_var($path, FILTER_VALIDATE_URL)) $image['preview_url'] = $path;
        elseif ($path !== '' && !str_contains($path, '..') && preg_match('~^(?:/?(?:flow/)?ImproovWeb/)?(?:uploads|review)/~i', $path)) $image['preview_url'] = improov_app_base_path() . '/thumb.php?path=' . rawurlencode(ltrim(preg_replace('~^/?(?:flow/)?ImproovWeb/~i', '', $path), '/')) . '&w=450&q=85';
        $image['ambientes'] = $byImage[$image['id']] ?? [];
        unset($image['preview']);
    }
    unset($image);
    return $images;
}
