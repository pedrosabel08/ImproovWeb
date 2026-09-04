<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../SIRE/sire_helpers.php';

const ALMA_CAP_VIEW = 'alma.visualizar';
const ALMA_CAP_EDIT = 'alma.editar';
const ALMA_CAP_ACTIVATE = 'alma.ativar';
const ALMA_CAP_LIBRARY_ADMIN = 'alma.administrar_biblioteca';

function alma_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function alma_require_auth(): void
{
    if (empty($_SESSION['logado'])) {
        alma_json(['success' => false, 'message' => 'Não autorizado.'], 401);
    }
}

function alma_user_id(): ?int
{
    $id = (int) ($_SESSION['idusuario'] ?? 0);
    return $id > 0 ? $id : null;
}

function alma_normalize_role(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    return preg_replace('/[^a-z0-9]+/', ' ', $ascii !== false ? $ascii : $value) ?: '';
}

/**
 * Capability boundary for V1.
 * Flow has no granular permission table yet, so this adapter centralizes the
 * legacy access level and role-name fallback without collaborator IDs.
 */
function alma_can(mysqli $conn, string $capability, ?int $userId = null): bool
{
    if (empty($_SESSION['logado'])) {
        return false;
    }
    if ($capability === ALMA_CAP_VIEW) {
        return true;
    }

    $userId = $userId ?? alma_user_id();
    if (!$userId) {
        return false;
    }

    static $profiles = [];
    if (!isset($profiles[$userId])) {
        $stmt = $conn->prepare(
            'SELECT u.nivel_acesso, GROUP_CONCAT(DISTINCT c.nome SEPARATOR "|") AS cargos
               FROM usuario u
               LEFT JOIN usuario_cargo uc ON uc.usuario_id = u.idusuario
               LEFT JOIN cargo c ON c.id = uc.cargo_id
              WHERE u.idusuario = ? AND u.ativo = 1
              GROUP BY u.idusuario, u.nivel_acesso'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $profiles[$userId] = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
    }
    $profile = $profiles[$userId];
    if ((int) ($profile['nivel_acesso'] ?? 0) === 1) {
        return true;
    }

    $roles = array_filter(array_map('alma_normalize_role', explode('|', (string) ($profile['cargos'] ?? ''))));
    $hasRole = static function (array $needles) use ($roles): bool {
        foreach ($roles as $role) {
            foreach ($needles as $needle) {
                if (str_contains($role, $needle)) {
                    return true;
                }
            }
        }
        return false;
    };

    return match ($capability) {
        ALMA_CAP_EDIT => $hasRole(['diretor', 'gestor de projetos', 'arquiteta']),
        ALMA_CAP_ACTIVATE => $hasRole(['diretor', 'gestor de projetos']),
        ALMA_CAP_LIBRARY_ADMIN => false,
        default => false,
    };
}

function alma_require_capability(mysqli $conn, string $capability): void
{
    if (!alma_can($conn, $capability)) {
        alma_json(['success' => false, 'message' => 'Você não possui a capacidade ' . $capability . '.'], 403);
    }
}

function alma_permissions(mysqli $conn): array
{
    return [
        ALMA_CAP_VIEW => alma_can($conn, ALMA_CAP_VIEW),
        ALMA_CAP_EDIT => alma_can($conn, ALMA_CAP_EDIT),
        ALMA_CAP_ACTIVATE => alma_can($conn, ALMA_CAP_ACTIVATE),
        ALMA_CAP_LIBRARY_ADMIN => alma_can($conn, ALMA_CAP_LIBRARY_ADMIN),
    ];
}

function alma_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return $_POST;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        alma_json(['success' => false, 'message' => 'JSON inválido.'], 400);
    }
    return $data;
}

function alma_event(
    mysqli $conn,
    int $directionId,
    ?int $revisionId,
    string $entityType,
    ?int $entityId,
    string $action,
    mixed $before = null,
    mixed $after = null
): void {
    $actor = alma_user_id();
    $beforeJson = $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $afterJson = $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = $conn->prepare(
        'INSERT INTO alma_evento
            (direcao_id, revisao_id, ator_usuario_id, entidade_tipo, entidade_id, acao, antes_json, depois_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iiisisss', $directionId, $revisionId, $actor, $entityType, $entityId, $action, $beforeJson, $afterJson);
    $stmt->execute();
    $stmt->close();
}

function alma_image_context(mysqli $conn, int $imageId): ?array
{
    $stmt = $conn->prepare(
        'SELECT i.idimagens_cliente_obra AS imagem_id, i.imagem_nome, i.tipo_imagem, i.obra_id,
                o.nomenclatura AS obra_nomenclatura, o.nome_obra, si.nome_status AS status_imagem
           FROM imagens_cliente_obra i
           JOIN obra o ON o.idobra = i.obra_id
           LEFT JOIN status_imagem si ON si.idstatus = i.status_id
          WHERE i.idimagens_cliente_obra = ? LIMIT 1'
    );
    $stmt->bind_param('i', $imageId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$row) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT caminho
           FROM arquivos
          WHERE imagem_id = ? AND status = 'atualizado'
          ORDER BY (LOWER(CONCAT(COALESCE(caminho,''), ' ', COALESCE(nome_interno,''))) LIKE '%angulo_definido%') DESC,
                   (LOWER(COALESCE(tipo,'')) IN ('jpg','jpeg','png','webp')) DESC,
                   recebido_em DESC
          LIMIT 1"
    );
    $stmt->bind_param('i', $imageId);
    $stmt->execute();
    $preview = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $row['preview_url'] = !empty($preview['caminho'])
        ? '../thumb.php?' . http_build_query(['path' => $preview['caminho'], 'w' => 1400, 'q' => 85], '', '&', PHP_QUERY_RFC3986)
        : null;
    return $row;
}

function alma_library_version(mysqli $conn, ?int $versionId = null, bool $allowDraft = false): ?array
{
    if ($versionId) {
        $stmt = $conn->prepare('SELECT * FROM alma_biblioteca_versao WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $versionId);
    } else {
        $stmt = $conn->prepare("SELECT * FROM alma_biblioteca_versao WHERE estado = 'PUBLICADA' ORDER BY publicada_em DESC, id DESC LIMIT 1");
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if ($row && !$allowDraft && $row['estado'] !== 'PUBLICADA') {
        return null;
    }
    return $row;
}

function alma_library_payload(mysqli $conn, int $versionId): array
{
    $version = alma_library_version($conn, $versionId, true);
    if (!$version) {
        throw new RuntimeException('Versão da Biblioteca ALMA não encontrada.');
    }

    $dimensions = [];
    $dimensionById = [];
    $stmt = $conn->prepare(
        'SELECT id, dimensao_pai_id, codigo, etapa_codigo, etapa_nome, pilar_codigo, pilar_nome,
                nome, tipo_conteudo, ordem_jornada, ordem_no_pilar, permite_multiplas,
                exige_item_biblioteca, ativa
           FROM alma_biblioteca_dimensao
          WHERE versao_id = ?
          ORDER BY ordem_jornada, ordem_no_pilar, id'
    );
    $stmt->bind_param('i', $versionId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        $row['dimensao_pai_id'] = $row['dimensao_pai_id'] !== null ? (int) $row['dimensao_pai_id'] : null;
        $row['ordem_jornada'] = (int) $row['ordem_jornada'];
        $row['ordem_no_pilar'] = (int) $row['ordem_no_pilar'];
        $row['permite_multiplas'] = (bool) $row['permite_multiplas'];
        $row['exige_item_biblioteca'] = (bool) $row['exige_item_biblioteca'];
        $row['ativa'] = (bool) $row['ativa'];
        $row['itens'] = [];
        $row['filhas'] = [];
        $dimensions[$row['codigo']] = $row;
        $dimensionById[$row['id']] = $row['codigo'];
    }
    $stmt->close();

    if ($dimensionById) {
        $ids = implode(',', array_map('intval', array_keys($dimensionById)));
        $items = $conn->query(
            "SELECT id, dimensao_id, codigo, titulo, resumo, diferenca_principal, descricao,
                    principio_fundamental, diretriz_completa, ordem, ativo
               FROM alma_biblioteca_item
              WHERE dimensao_id IN ($ids)
              ORDER BY dimensao_id, ordem, titulo"
        );
        $itemById = [];
        while ($item = $items->fetch_assoc()) {
            $item['id'] = (int) $item['id'];
            $item['dimensao_id'] = (int) $item['dimensao_id'];
            $item['ordem'] = (int) $item['ordem'];
            $item['ativo'] = (bool) $item['ativo'];
            $item['secoes'] = [];
            $itemById[$item['id']] = $item;
        }

        if ($itemById) {
            $itemIds = implode(',', array_map('intval', array_keys($itemById)));
            $sections = $conn->query(
                "SELECT id, item_id, codigo, titulo, conteudo, ordem
                   FROM alma_biblioteca_item_secao
                  WHERE item_id IN ($itemIds) ORDER BY item_id, ordem, id"
            );
            $sectionById = [];
            while ($section = $sections->fetch_assoc()) {
                $section['id'] = (int) $section['id'];
                $section['item_id'] = (int) $section['item_id'];
                $section['ordem'] = (int) $section['ordem'];
                $section['entradas'] = [];
                $sectionById[$section['id']] = $section;
            }
            if ($sectionById) {
                $sectionIds = implode(',', array_map('intval', array_keys($sectionById)));
                $entries = $conn->query(
                    "SELECT id, secao_id, tipo, texto, ordem
                       FROM alma_biblioteca_secao_entrada
                      WHERE secao_id IN ($sectionIds) ORDER BY secao_id, ordem, id"
                );
                while ($entry = $entries->fetch_assoc()) {
                    $entry['id'] = (int) $entry['id'];
                    $entry['secao_id'] = (int) $entry['secao_id'];
                    $entry['ordem'] = (int) $entry['ordem'];
                    $sectionById[$entry['secao_id']]['entradas'][] = $entry;
                }
            }
            foreach ($sectionById as $section) {
                $itemById[$section['item_id']]['secoes'][] = $section;
            }
        }
        foreach ($itemById as $item) {
            $code = $dimensionById[$item['dimensao_id']] ?? null;
            if ($code) {
                $dimensions[$code]['itens'][] = $item;
            }
        }
    }

    foreach ($dimensions as $code => $dimension) {
        if ($dimension['dimensao_pai_id'] !== null) {
            $parentCode = $dimensionById[$dimension['dimensao_pai_id']] ?? null;
            if ($parentCode) {
                $dimensions[$parentCode]['filhas'][] = $dimension;
            }
        }
    }
    $roots = array_values(array_filter($dimensions, static fn(array $dimension): bool => $dimension['dimensao_pai_id'] === null));
    return ['versao' => $version, 'pilares' => $roots, 'dimensoes' => array_values($dimensions)];
}

function alma_reference_data(mysqli $conn, int $referenceId): ?array
{
    $stmt = $conn->prepare(
        'SELECT sr.*, ri.nome_arquivo AS flow_nome_arquivo, ri.nomenclatura AS flow_nomenclatura,
                i.imagem_nome, o.nomenclatura AS obra_nomenclatura
           FROM sire_referencia sr
           LEFT JOIN referencias_imagens ri ON ri.id = sr.referencia_imagem_id
           LEFT JOIN funcao_imagem fi ON fi.idfuncao_imagem = ri.funcao_imagem_id
           LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
           LEFT JOIN obra o ON o.idobra = i.obra_id
          WHERE sr.id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $referenceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$row) {
        return null;
    }
    $row['id'] = (int) $row['id'];
    $row['titulo_exibicao'] = $row['titulo'] ?: $row['flow_nomenclatura'] ?: $row['flow_nome_arquivo'] ?: ('Referência #' . $row['id']);
    $row['thumbnail_url'] = sire_reference_thumbnail_url($row, 480, 78);
    $row['imagem_url'] = sire_reference_image_url($row);
    return $row;
}

function alma_sire_search(mysqli $conn, string $query, int $page = 1): array
{
    $page = max(1, $page);
    $limit = 24;
    $offset = ($page - 1) * $limit;
    $like = '%' . $query . '%';
    $stmt = $conn->prepare(
        'SELECT sr.*, ri.nome_arquivo AS flow_nome_arquivo, ri.nomenclatura AS flow_nomenclatura,
                i.imagem_nome, o.nomenclatura AS obra_nomenclatura
           FROM sire_referencia sr
           LEFT JOIN referencias_imagens ri ON ri.id = sr.referencia_imagem_id
           LEFT JOIN funcao_imagem fi ON fi.idfuncao_imagem = ri.funcao_imagem_id
           LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
           LEFT JOIN obra o ON o.idobra = i.obra_id
          WHERE (? = "" OR sr.titulo LIKE ? OR sr.descricao LIKE ? OR ri.nomenclatura LIKE ? OR ri.nome_arquivo LIKE ?)
          ORDER BY sr.golden_sample DESC, sr.criado_em DESC, sr.id DESC LIMIT ? OFFSET ?'
    );
    $stmt->bind_param('sssssii', $query, $like, $like, $like, $like, $limit, $offset);
    $stmt->execute();
    $items = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        $row['titulo_exibicao'] = $row['titulo'] ?: $row['flow_nomenclatura'] ?: $row['flow_nome_arquivo'] ?: ('Referência #' . $row['id']);
        $row['thumbnail_url'] = sire_reference_thumbnail_url($row, 360, 75);
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}

function alma_revision_snapshot(mysqli $conn, int $revisionId): ?array
{
    $stmt = $conn->prepare(
        'SELECT r.*, v.codigo AS biblioteca_codigo,
                COALESCE(criador.nome_usuario, "Usuário removido") AS criador,
                COALESCE(atualizador.nome_usuario, "Usuário removido") AS atualizador
           FROM alma_direcao_revisao r
           JOIN alma_biblioteca_versao v ON v.id = r.biblioteca_versao_id
           LEFT JOIN usuario criador ON criador.idusuario = r.criada_por_usuario_id
           LEFT JOIN usuario atualizador ON atualizador.idusuario = r.atualizada_por_usuario_id
          WHERE r.id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $revisionId);
    $stmt->execute();
    $revision = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$revision) {
        return null;
    }

    $revision['id'] = (int) $revision['id'];
    $revision['direcao_id'] = (int) $revision['direcao_id'];
    $revision['numero'] = (int) $revision['numero'];
    $revision['biblioteca_versao_id'] = (int) $revision['biblioteca_versao_id'];
    $revision['lock_version'] = (int) $revision['lock_version'];
    $revision['selecoes'] = [];
    $selectionById = [];
    $stmt = $conn->prepare(
        'SELECT s.*, d.codigo AS dimensao_codigo, d.nome AS dimensao_nome, d.pilar_codigo, d.pilar_nome,
                d.etapa_codigo, d.etapa_nome, d.ordem_jornada, d.ordem_no_pilar,
                i.codigo AS item_codigo, i.titulo AS item_titulo, i.resumo AS item_resumo,
                i.diferenca_principal, i.descricao AS item_descricao,
                i.principio_fundamental, i.diretriz_completa
           FROM alma_revisao_selecao s
           JOIN alma_biblioteca_dimensao d ON d.id = s.dimensao_id
           LEFT JOIN alma_biblioteca_item i ON i.id = s.item_biblioteca_id
          WHERE s.revisao_id = ?
          ORDER BY d.ordem_jornada, d.ordem_no_pilar, s.ordem, s.id'
    );
    $stmt->bind_param('i', $revisionId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        foreach (['id', 'revisao_id', 'dimensao_id', 'item_biblioteca_id', 'ordem_jornada', 'ordem_no_pilar', 'ordem'] as $key) {
            $row[$key] = $row[$key] !== null ? (int) $row[$key] : null;
        }
        $row['principal'] = (bool) $row['principal'];
        $row['referencias'] = [];
        $selectionById[$row['id']] = count($revision['selecoes']);
        $revision['selecoes'][] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare(
        'SELECT ar.*, d.codigo AS dimensao_codigo, d.nome AS dimensao_nome,
                sr.titulo, sr.origem, sr.url_externa, sr.nome_arquivo, sr.caminho_arquivo,
                ri.nome_arquivo AS flow_nome_arquivo, ri.nomenclatura AS flow_nomenclatura
           FROM alma_revisao_referencia ar
           JOIN alma_biblioteca_dimensao d ON d.id = ar.dimensao_id
           JOIN sire_referencia sr ON sr.id = ar.sire_referencia_id
           LEFT JOIN referencias_imagens ri ON ri.id = sr.referencia_imagem_id
          WHERE ar.revisao_id = ? ORDER BY ar.id'
    );
    $stmt->bind_param('i', $revisionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $references = [];
    while ($row = $result->fetch_assoc()) {
        foreach (['id', 'revisao_id', 'selecao_id', 'dimensao_id', 'sire_referencia_id'] as $key) {
            $row[$key] = $row[$key] !== null ? (int) $row[$key] : null;
        }
        $row['titulo_exibicao'] = $row['titulo'] ?: $row['flow_nomenclatura'] ?: $row['flow_nome_arquivo'] ?: ('Referência #' . $row['sire_referencia_id']);
        $row['thumbnail_url'] = sire_reference_thumbnail_url($row, 480, 78);
        $row['imagem_url'] = sire_reference_image_url($row);
        $references[] = $row;
        if ($row['selecao_id'] && isset($selectionById[$row['selecao_id']])) {
            $revision['selecoes'][$selectionById[$row['selecao_id']]]['referencias'][] = $row;
        }
    }
    $stmt->close();
    $revision['referencias'] = $references;
    return $revision;
}

function alma_direction_full(mysqli $conn, int $imageId, ?int $revisionId = null): array
{
    $image = alma_image_context($conn, $imageId);
    if (!$image) {
        throw new RuntimeException('Imagem não encontrada.');
    }
    $stmt = $conn->prepare('SELECT * FROM alma_direcao WHERE imagem_id = ? LIMIT 1');
    $stmt->bind_param('i', $imageId);
    $stmt->execute();
    $direction = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$direction) {
        return ['imagem' => $image, 'direcao' => null, 'revisao' => null, 'revisoes' => []];
    }
    $direction['id'] = (int) $direction['id'];
    $direction['imagem_id'] = (int) $direction['imagem_id'];
    $direction['revisao_ativa_id'] = $direction['revisao_ativa_id'] !== null ? (int) $direction['revisao_ativa_id'] : null;

    $stmt = $conn->prepare(
        'SELECT r.id, r.numero, r.estado, r.biblioteca_versao_id, v.codigo AS biblioteca_codigo,
                r.revisao_anterior_id, r.criada_em, r.atualizada_em, r.ativada_em,
                COALESCE(u.nome_usuario, "Usuário removido") AS criador
           FROM alma_direcao_revisao r
           JOIN alma_biblioteca_versao v ON v.id = r.biblioteca_versao_id
           LEFT JOIN usuario u ON u.idusuario = r.criada_por_usuario_id
          WHERE r.direcao_id = ? ORDER BY r.numero DESC'
    );
    $stmt->bind_param('i', $direction['id']);
    $stmt->execute();
    $revisions = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        foreach (['id', 'numero', 'biblioteca_versao_id', 'revisao_anterior_id'] as $key) {
            $row[$key] = $row[$key] !== null ? (int) $row[$key] : null;
        }
        $revisions[] = $row;
    }
    $stmt->close();
    if (!$revisionId) {
        $revisionId = $direction['revisao_ativa_id'];
        if (!$revisionId) {
            $revisionId = $revisions[0]['id'] ?? null;
        }
    }
    $revision = $revisionId ? alma_revision_snapshot($conn, $revisionId) : null;
    if ($revision && $revision['direcao_id'] !== $direction['id']) {
        throw new RuntimeException('A revisão não pertence à direção desta imagem.');
    }
    return ['imagem' => $image, 'direcao' => $direction, 'revisao' => $revision, 'revisoes' => $revisions];
}

function alma_summary(mysqli $conn, int $imageId): array
{
    $stmt = $conn->prepare(
        "SELECT d.id AS direction_id, r.id AS revision_id, r.numero, r.estado,
                s.resumo_contextual, s.aplicacao_imagem,
                dim.codigo AS dimensao_codigo, dim.nome AS dimensao_nome,
                dim.pilar_codigo, dim.pilar_nome, dim.ordem_jornada, dim.ordem_no_pilar,
                item.titulo AS item_titulo
           FROM alma_direcao d
           JOIN alma_direcao_revisao r ON r.id = d.revisao_ativa_id AND r.estado = 'ATIVA'
           LEFT JOIN alma_revisao_selecao s ON s.revisao_id = r.id AND s.principal = 1
           LEFT JOIN alma_biblioteca_dimensao dim ON dim.id = s.dimensao_id
           LEFT JOIN alma_biblioteca_item item ON item.id = s.item_biblioteca_id
          WHERE d.imagem_id = ?
          ORDER BY dim.ordem_jornada, dim.ordem_no_pilar, s.ordem"
    );
    $stmt->bind_param('i', $imageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    if (!$rows || empty($rows[0]['direction_id'])) {
        return ['possui_alma' => false, 'direction_id' => null, 'revision_id' => null, 'status' => null, 'pilares' => []];
    }

    $pillars = [];
    foreach ($rows as $row) {
        if (empty($row['pilar_codigo'])) {
            continue;
        }
        $code = $row['pilar_codigo'];
        if (!isset($pillars[$code])) {
            $pillars[$code] = [
                'codigo' => $code,
                'nome' => $row['pilar_nome'],
                'ordem' => (int) $row['ordem_jornada'],
                'escolhas' => [],
            ];
        }
        $label = trim((string) ($row['item_titulo'] ?: $row['resumo_contextual'] ?: $row['aplicacao_imagem'] ?: ''));
        if ($label !== '') {
            $pillars[$code]['escolhas'][] = [
                'dimensao' => $row['dimensao_nome'],
                'valor' => mb_strimwidth($label, 0, 100, '…', 'UTF-8'),
            ];
        }
    }
    foreach ($pillars as &$pillar) {
        $pillar['resumo'] = implode(' · ', array_column($pillar['escolhas'], 'valor')) ?: 'Direção definida';
    }
    unset($pillar);
    usort($pillars, static fn(array $a, array $b): int => $a['ordem'] <=> $b['ordem']);

    return [
        'possui_alma' => true,
        'direction_id' => (int) $rows[0]['direction_id'],
        'revision_id' => (int) $rows[0]['revision_id'],
        'revision_number' => (int) $rows[0]['numero'],
        'status' => $rows[0]['estado'],
        'pilares' => array_values($pillars),
    ];
}
