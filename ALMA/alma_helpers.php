<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../SIRE/sire_helpers.php';

const ALMA_CAP_VIEW = 'alma.visualizar';
const ALMA_CAP_EDIT = 'alma.editar';
const ALMA_CAP_ACTIVATE = 'alma.ativar';
const ALMA_CAP_LIBRARY_ADMIN = 'alma.administrar_biblioteca';

const ALMA_PROJECT_DIMENSIONS = ['arquitetura', 'materialidade', 'lifestyle'];
const ALMA_IMAGE_DIMENSIONS = ['atmosfera', 'luz_momento', 'luz_linguagem', 'fotografia_direcao', 'composicao'];
const ALMA_EXCLUDED_IMAGE_TYPE = 'Planta Humanizada';

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

function alma_dimension_and_item(mysqli $conn, int $versionId, string $dimensionCode, int $itemId): array
{
    $stmt = $conn->prepare(
        'SELECT d.id AS dimensao_id, d.codigo AS dimensao_codigo, d.nome AS dimensao_nome,
                d.pilar_codigo, d.pilar_nome, d.versao_id,
                i.id AS item_id, i.titulo AS item_titulo
           FROM alma_biblioteca_dimensao d
           JOIN alma_biblioteca_item i ON i.dimensao_id = d.id
          WHERE d.versao_id = ? AND d.codigo = ? AND d.ativa = 1
            AND i.id = ? AND i.ativo = 1
          LIMIT 1'
    );
    $stmt->bind_param('isi', $versionId, $dimensionCode, $itemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$row) {
        throw new RuntimeException('O item selecionado não pertence à dimensão ALMA informada.');
    }
    foreach (['dimensao_id', 'versao_id', 'item_id'] as $field) {
        $row[$field] = (int) $row[$field];
    }
    return $row;
}

function alma_sire_value_name(array $taxonomy): string
{
    $name = trim((string) $taxonomy['item_titulo']);
    if (($taxonomy['dimensao_codigo'] ?? '') === 'luz_linguagem') {
        $withoutPrefix = preg_replace('/^luz\s+/iu', '', $name);
        if (is_string($withoutPrefix) && trim($withoutPrefix) !== '') {
            $name = trim($withoutPrefix);
        }
    }
    return $name;
}

/**
 * Resolve a classificação SIRE exclusivamente a partir da taxonomia ALMA
 * validada. Se o vocabulário ainda não possuir o valor oficial, cria-o de
 * forma idempotente no pilar correto.
 */
function alma_sire_value_for_item(mysqli $conn, array $taxonomy): array
{
    $name = alma_sire_value_name($taxonomy);
    $pillarCode = (string) $taxonomy['pilar_codigo'];
    $stmt = $conn->prepare(
        'SELECT v.id, v.nome, p.id AS pilar_id, p.codigo AS pilar_codigo
           FROM sire_pilar p
           LEFT JOIN sire_pilar_valor v ON v.pilar_id = p.id AND v.nome = ?
          WHERE p.codigo = ? LIMIT 1'
    );
    $stmt->bind_param('ss', $name, $pillarCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$row) {
        throw new RuntimeException('O pilar ALMA não possui correspondência válida na taxonomia SIRE.');
    }
    if (empty($row['id'])) {
        $actor = alma_user_id();
        $insert = $conn->prepare(
            'INSERT INTO sire_pilar_valor (pilar_id, nome, descricao, ativo, criado_por)
             VALUES (?, ?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), ativo = 1'
        );
        $description = 'Valor oficial sincronizado pelo ALMA.';
        $pillarId = (int) $row['pilar_id'];
        $insert->bind_param('issi', $pillarId, $name, $description, $actor);
        $insert->execute();
        $row['id'] = (int) $conn->insert_id;
        $insert->close();
    }
    return [
        'id' => (int) $row['id'],
        'nome' => $name,
        'pilar_id' => (int) $row['pilar_id'],
        'pilar_codigo' => $pillarCode,
    ];
}

function alma_classify_references(mysqli $conn, array $taxonomy, array $referenceIds): int
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $referenceIds), static fn(int $id): bool => $id > 0)));
    if (!$ids) {
        return 0;
    }
    $existing = [];
    $result = $conn->query('SELECT id FROM sire_referencia WHERE id IN (' . implode(',', $ids) . ')');
    while ($row = $result->fetch_assoc()) {
        $existing[(int) $row['id']] = true;
    }
    if (count($existing) !== count($ids)) {
        throw new RuntimeException('Uma ou mais referências SIRE não existem.');
    }
    $value = alma_sire_value_for_item($conn, $taxonomy);
    $actor = alma_user_id();
    $insert = $conn->prepare(
        'INSERT IGNORE INTO sire_referencia_valor (referencia_id, valor_id, classificado_por)
         VALUES (?, ?, ?)'
    );
    $added = 0;
    foreach ($ids as $referenceId) {
        $valueId = (int) $value['id'];
        $insert->bind_param('iii', $referenceId, $valueId, $actor);
        $insert->execute();
        $added += max(0, $insert->affected_rows);
    }
    $insert->close();
    return $added;
}

function alma_sire_picker(mysqli $conn, string $query, int $page, int $versionId, string $dimensionCode, int $itemId, array $filters = []): array
{
    $taxonomy = alma_dimension_and_item($conn, $versionId, $dimensionCode, $itemId);
    $valueName = alma_sire_value_name($taxonomy);
    $page = max(1, $page);
    $perPage = 24;
    $offset = ($page - 1) * $perPage;
    $like = '%' . trim($query) . '%';
    $golden = !empty($filters['golden']);
    $where = ' WHERE (? = "" OR sr.titulo LIKE ? OR sr.descricao LIKE ? OR ri.nomenclatura LIKE ? OR ri.nome_arquivo LIKE ? OR o.nomenclatura LIKE ? OR i.tipo_imagem LIKE ?)';
    if ($golden) {
        $where .= ' AND sr.golden_sample = 1';
    }
    $base = ' FROM sire_referencia sr
               LEFT JOIN referencias_imagens ri ON ri.id = sr.referencia_imagem_id
               LEFT JOIN funcao_imagem fi ON fi.idfuncao_imagem = ri.funcao_imagem_id
               LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
               LEFT JOIN obra o ON o.idobra = i.obra_id ';
    $relatedExpression = 'EXISTS (
        SELECT 1 FROM sire_referencia_valor rv
        JOIN sire_pilar_valor pv ON pv.id = rv.valor_id
        JOIN sire_pilar p ON p.id = pv.pilar_id
        WHERE rv.referencia_id = sr.id AND p.codigo = ? AND pv.nome = ?
    )';
    $sql = 'SELECT sr.*, ri.nome_arquivo AS flow_nome_arquivo, ri.nomenclatura AS flow_nomenclatura,
                   i.imagem_nome, i.tipo_imagem AS ambiente, o.nomenclatura AS obra_nomenclatura,
                   ' . $relatedExpression . ' AS relacionada '
        . $base . $where . ' ORDER BY relacionada DESC, sr.golden_sample DESC, sr.criado_em DESC, sr.id DESC LIMIT ? OFFSET ?';
    $stmt = $conn->prepare($sql);
    $emptyOrQuery = trim($query);
    $pillarCode = (string) $taxonomy['pilar_codigo'];
    $stmt->bind_param('sssssssssii', $pillarCode, $valueName, $emptyOrQuery, $like, $like, $like, $like, $like, $like, $perPage, $offset);
    $stmt->execute();
    $related = [];
    $other = [];
    $seen = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        $row['relacionada'] = (bool) $row['relacionada'];
        $row['titulo_exibicao'] = $row['titulo'] ?: $row['flow_nomenclatura'] ?: $row['flow_nome_arquivo'] ?: ('Referência #' . $row['id']);
        $row['thumbnail_url'] = sire_reference_thumbnail_url($row, 360, 75);
        $seen[$row['id']] = true;
        if ($row['relacionada']) {
            $related[] = $row;
        } else {
            $other[] = $row;
        }
    }
    $stmt->close();
    $selectedIds = array_values(array_unique(array_filter(array_map('intval', $filters['selected_ids'] ?? []), static fn(int $id): bool => $id > 0)));
    $missingSelected = array_values(array_filter($selectedIds, static fn(int $id): bool => empty($seen[$id])));
    if ($missingSelected) {
        $selectedSql = 'SELECT sr.*, ri.nome_arquivo AS flow_nome_arquivo, ri.nomenclatura AS flow_nomenclatura,
                               i.imagem_nome, i.tipo_imagem AS ambiente, o.nomenclatura AS obra_nomenclatura,
                               ' . $relatedExpression . ' AS relacionada '
            . $base . ' WHERE sr.id IN (' . implode(',', $missingSelected) . ') ORDER BY sr.id';
        $stmt = $conn->prepare($selectedSql);
        $stmt->bind_param('ss', $pillarCode, $valueName);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int) $row['id'];
            $row['relacionada'] = (bool) $row['relacionada'];
            $row['titulo_exibicao'] = $row['titulo'] ?: $row['flow_nomenclatura'] ?: $row['flow_nome_arquivo'] ?: ('Referência #' . $row['id']);
            $row['thumbnail_url'] = sire_reference_thumbnail_url($row, 360, 75);
            if ($row['relacionada']) {
                array_unshift($related, $row);
            } else {
                array_unshift($other, $row);
            }
        }
        $stmt->close();
    }
    return [
        'relacionadas' => $related,
        'outras' => $other,
        'page' => $page,
        'has_more' => count($related) + count($other) === $perPage,
        'item' => ['titulo' => $taxonomy['item_titulo'], 'dimensao' => $taxonomy['dimensao_nome']],
    ];
}

function alma_project_snapshot(mysqli $conn, int $projectDirectionId): array
{
    $stmt = $conn->prepare(
        'SELECT pd.*, o.nomenclatura AS obra_nomenclatura, o.nome_obra
           FROM alma_projeto_direcao pd JOIN obra o ON o.idobra = pd.obra_id
          WHERE pd.id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $projectDirectionId);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$project) {
        return [];
    }
    foreach (['id', 'obra_id', 'biblioteca_versao_id', 'lock_version'] as $field) {
        $project[$field] = (int) $project[$field];
    }
    $project['selecoes'] = [];
    $stmt = $conn->prepare(
        'SELECT s.id, s.dimensao_id, s.item_biblioteca_id, d.codigo AS dimensao_codigo,
                d.nome AS dimensao_nome, d.pilar_codigo, d.pilar_nome, d.ordem_jornada,
                d.ordem_no_pilar, i.titulo AS item_titulo
           FROM alma_projeto_selecao s
           JOIN alma_biblioteca_dimensao d ON d.id = s.dimensao_id
           JOIN alma_biblioteca_item i ON i.id = s.item_biblioteca_id
          WHERE s.projeto_direcao_id = ? ORDER BY s.ordem, s.id'
    );
    $stmt->bind_param('i', $projectDirectionId);
    $stmt->execute();
    $index = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        foreach (['id', 'dimensao_id', 'item_biblioteca_id', 'ordem_jornada', 'ordem_no_pilar'] as $field) {
            $row[$field] = (int) $row[$field];
        }
        $row['referencias'] = [];
        $index[$row['id']] = count($project['selecoes']);
        $project['selecoes'][] = $row;
    }
    $stmt->close();
    if ($index) {
        $ids = implode(',', array_map('intval', array_keys($index)));
        $result = $conn->query(
            'SELECT pr.selecao_id, pr.sire_referencia_id, sr.titulo, sr.origem, sr.url_externa,
                    sr.nome_arquivo, sr.caminho_arquivo, ri.nomenclatura AS flow_nomenclatura,
                    ri.nome_arquivo AS flow_nome_arquivo
               FROM alma_projeto_referencia pr
               JOIN sire_referencia sr ON sr.id = pr.sire_referencia_id
               LEFT JOIN referencias_imagens ri ON ri.id = sr.referencia_imagem_id
              WHERE pr.selecao_id IN (' . $ids . ') ORDER BY pr.id'
        );
        while ($row = $result->fetch_assoc()) {
            $row['selecao_id'] = (int) $row['selecao_id'];
            $row['sire_referencia_id'] = (int) $row['sire_referencia_id'];
            $row['titulo_exibicao'] = $row['titulo'] ?: $row['flow_nomenclatura'] ?: $row['flow_nome_arquivo'] ?: ('Referência #' . $row['sire_referencia_id']);
            $row['thumbnail_url'] = sire_reference_thumbnail_url($row, 480, 78);
            $project['selecoes'][$index[$row['selecao_id']]]['referencias'][] = $row;
        }
    }
    return $project;
}

function alma_project_direction(mysqli $conn, int $obraId): ?array
{
    $stmt = $conn->prepare('SELECT id FROM alma_projeto_direcao WHERE obra_id = ? LIMIT 1');
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? alma_project_snapshot($conn, (int) $row['id']) : null;
}

function alma_project_images(mysqli $conn, int $obraId): array
{
    $required = "'" . implode("','", ALMA_IMAGE_DIMENSIONS) . "'";
    $stmt = $conn->prepare(
        "SELECT i.idimagens_cliente_obra AS imagem_id, i.imagem_nome, i.tipo_imagem, i.status_id,
                si.nome_status AS status_imagem, d.revisao_ativa_id,
                COUNT(DISTINCT CASE WHEN dim.codigo IN ($required) AND s.item_biblioteca_id IS NOT NULL THEN dim.codigo END) AS decisoes
           FROM imagens_cliente_obra i
           LEFT JOIN status_imagem si ON si.idstatus = i.status_id
           LEFT JOIN alma_direcao d ON d.imagem_id = i.idimagens_cliente_obra
           LEFT JOIN alma_direcao_revisao r ON r.id = COALESCE(d.revisao_ativa_id, (SELECT r2.id FROM alma_direcao_revisao r2 WHERE r2.direcao_id=d.id ORDER BY r2.numero DESC LIMIT 1))
           LEFT JOIN alma_revisao_selecao s ON s.revisao_id = r.id
           LEFT JOIN alma_biblioteca_dimensao dim ON dim.id = s.dimensao_id
          WHERE i.obra_id = ? AND (i.tipo_imagem IS NULL OR i.tipo_imagem <> ?)
          GROUP BY i.idimagens_cliente_obra, i.imagem_nome, i.tipo_imagem, i.status_id, si.nome_status, d.revisao_ativa_id
          ORDER BY i.idimagens_cliente_obra"
    );
    $excluded = ALMA_EXCLUDED_IMAGE_TYPE;
    $stmt->bind_param('is', $obraId, $excluded);
    $stmt->execute();
    $images = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['imagem_id'] = (int) $row['imagem_id'];
        $row['decisoes'] = (int) $row['decisoes'];
        $row['alma_status'] = $row['decisoes'] === 0 ? 'NAO_INICIADO' : ($row['decisoes'] === count(ALMA_IMAGE_DIMENSIONS) ? 'COMPLETO' : 'PARCIAL');
        $row['dimensoes'] = [];
        $images[] = $row;
    }
    $stmt->close();
    if ($images) {
        $imageIndex = [];
        foreach ($images as $index => $image) {
            $imageIndex[$image['imagem_id']] = $index;
        }
        $ids = implode(',', array_map('intval', array_keys($imageIndex)));
        $result = $conn->query(
            "SELECT d.imagem_id, dim.codigo, item.titulo, item.id AS item_id
               FROM alma_direcao d
               JOIN alma_direcao_revisao r ON r.id=COALESCE(d.revisao_ativa_id, (SELECT r2.id FROM alma_direcao_revisao r2 WHERE r2.direcao_id=d.id ORDER BY r2.numero DESC LIMIT 1))
               JOIN alma_revisao_selecao s ON s.revisao_id=r.id
               JOIN alma_biblioteca_dimensao dim ON dim.id=s.dimensao_id
               JOIN alma_biblioteca_item item ON item.id=s.item_biblioteca_id
              WHERE d.imagem_id IN ($ids) AND dim.codigo IN ($required)"
        );
        while ($row = $result->fetch_assoc()) {
            $images[$imageIndex[(int) $row['imagem_id']]]['dimensoes'][$row['codigo']] = [
                'item_id' => (int) $row['item_id'],
                'titulo' => $row['titulo'],
                'referencias' => [],
            ];
        }
        $result = $conn->query(
            "SELECT d.imagem_id, dim.codigo, ar.sire_referencia_id
               FROM alma_direcao d
               JOIN alma_direcao_revisao r ON r.id=COALESCE(d.revisao_ativa_id, (SELECT r2.id FROM alma_direcao_revisao r2 WHERE r2.direcao_id=d.id ORDER BY r2.numero DESC LIMIT 1))
               JOIN alma_revisao_referencia ar ON ar.revisao_id=r.id
               JOIN alma_biblioteca_dimensao dim ON dim.id=ar.dimensao_id
              WHERE d.imagem_id IN ($ids) AND dim.codigo IN ($required)
              ORDER BY ar.id"
        );
        while ($row = $result->fetch_assoc()) {
            $index = $imageIndex[(int) $row['imagem_id']];
            if (isset($images[$index]['dimensoes'][$row['codigo']])) {
                $images[$index]['dimensoes'][$row['codigo']]['referencias'][] = (int) $row['sire_referencia_id'];
            }
        }
    }
    return $images;
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
    $image = alma_image_context($conn, $imageId);
    if (!$image) {
        throw new RuntimeException('Imagem não encontrada.');
    }
    $direction = alma_direction_full($conn, $imageId);
    $revision = $direction['revisao'] ?? null;
    $project = alma_project_direction($conn, (int) $image['obra_id']);
    $projectSelections = array_values(array_filter(
        $project['selecoes'] ?? [],
        static fn(array $selection): bool => in_array($selection['dimensao_codigo'] ?? '', ALMA_PROJECT_DIMENSIONS, true)
    ));
    $imageSelections = array_values(array_filter(
        $revision['selecoes'] ?? [],
        static fn(array $selection): bool => in_array($selection['dimensao_codigo'] ?? '', ALMA_IMAGE_DIMENSIONS, true)
    ));
    $selections = array_merge($projectSelections, $imageSelections);
    $pillars = [];
    $versionId = (int) ($project['biblioteca_versao_id'] ?? $revision['biblioteca_versao_id'] ?? 0);
    $version = $versionId ? alma_library_version($conn, $versionId, true) : alma_library_version($conn);
    if ($version) {
        $stmt = $conn->prepare(
            'SELECT pilar_codigo AS codigo, pilar_nome AS nome, ordem_jornada AS ordem
               FROM alma_biblioteca_dimensao
              WHERE versao_id=? AND dimensao_pai_id IS NULL
              ORDER BY ordem_jornada'
        );
        $versionId = (int) $version['id'];
        $stmt->bind_param('i', $versionId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $pillars[$row['codigo']] = [
                'codigo' => $row['codigo'],
                'nome' => $row['nome'],
                'ordem' => (int) $row['ordem'],
                'escolhas' => [],
                'referencias' => [],
            ];
        }
        $stmt->close();
    }
    foreach ($selections as $selection) {
        $code = (string) ($selection['pilar_codigo'] ?? '');
        $value = trim((string) ($selection['item_titulo'] ?? ''));
        if ($code === '' || $value === '') {
            continue;
        }
        if (!isset($pillars[$code])) {
            $pillars[$code] = [
                'codigo' => $code,
                'nome' => $selection['pilar_nome'],
                'ordem' => (int) ($selection['ordem_jornada'] ?? 0),
                'escolhas' => [],
                'referencias' => [],
            ];
        }
        $pillars[$code]['escolhas'][] = [
            'dimensao' => $selection['dimensao_nome'],
            'valor' => mb_strimwidth($value, 0, 100, '…', 'UTF-8'),
        ];
        foreach (($selection['referencias'] ?? []) as $reference) {
            $pillars[$code]['referencias'][] = [
                'id' => (int) $reference['sire_referencia_id'],
                'titulo' => $reference['titulo_exibicao'],
                'thumbnail_url' => $reference['thumbnail_url'],
                'dimensao' => $selection['dimensao_nome'],
            ];
        }
    }
    foreach ($pillars as &$pillar) {
        $pillar['resumo'] = implode(' · ', array_column($pillar['escolhas'], 'valor')) ?: 'Não definido';
    }
    unset($pillar);
    usort($pillars, static fn(array $a, array $b): int => $a['ordem'] <=> $b['ordem']);
    $decisionCount = 0;
    foreach (($revision['selecoes'] ?? []) as $selection) {
        if (in_array($selection['dimensao_codigo'], ALMA_IMAGE_DIMENSIONS, true) && !empty($selection['item_biblioteca_id'])) {
            $decisionCount++;
        }
    }
    $status = $decisionCount === 0 ? 'NAO_INICIADO' : ($decisionCount === count(ALMA_IMAGE_DIMENSIONS) ? 'COMPLETO' : 'PARCIAL');
    return [
        'possui_alma' => !empty($project['selecoes']) || $decisionCount > 0,
        'direction_id' => $direction['direcao']['id'] ?? null,
        'revision_id' => $revision['id'] ?? null,
        'revision_number' => $revision['numero'] ?? null,
        'status' => $status,
        'intencao_geral' => $revision['intencao_geral'] ?? null,
        'imagem' => ['id' => $imageId, 'nome' => $image['imagem_nome']],
        'obra_id' => (int) $image['obra_id'],
        'pilares' => array_values($pillars),
    ];
}
