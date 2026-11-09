<?php
function render_ef_rows(mysqli $conn, string $sql, string $types = '', array $values = []): array
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function render_ef_exec(mysqli $conn, string $sql, string $types, array $values): void
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
}

function render_ef_manager(array $session, array $roles = []): bool
{
    if (in_array((int)($session['nivel_acesso'] ?? 0), [1, 5], true)) return true;
    $roles[] = (string)($session['cargo_colaborador'] ?? '');
    foreach ($roles as $role) foreach (['gestor', 'diretor', 'gerente'] as $term) {
        if (mb_stripos($role, $term, 0, 'UTF-8') !== false) return true;
    }
    return false;
}

function render_ef_authorized(mysqli $conn, array $session, array $source): bool
{
    $actor = (int)($session['idcolaborador'] ?? 0);
    if (empty($session['logado']) || $actor < 1) return false;
    $roles = array_column(render_ef_rows($conn, 'SELECT c.nome FROM usuario_cargo uc JOIN cargo c ON c.id=uc.cargo_id WHERE uc.usuario_id=?', 'i', [(int)($session['idusuario'] ?? 0)]), 'nome');
    if (render_ef_manager($session, $roles)) return true;
    if ($actor === (int)$source['responsavel_id']) return true;
    return (bool)render_ef_rows($conn, 'SELECT fi.idfuncao_imagem FROM funcao_imagem fi JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id WHERE fi.imagem_id=? AND i.obra_id=? AND fi.colaborador_id=? AND fi.funcao_id IN (4,6) LIMIT 1', 'iii', [(int)$source['imagem_id'], (int)$source['obra_id'], $actor]);
}

function render_ef_files(mysqli $conn, int $image): array
{
    return render_ef_rows($conn, "SELECT al.id,al.nome_arquivo,al.caminho,al.status,al.criado_em,al.tamanho,al.colaborador_id,f.nome_funcao
        FROM arquivo_log al JOIN funcao_imagem fi ON fi.idfuncao_imagem=al.funcao_imagem_id
        LEFT JOIN funcao f ON f.idfuncao=fi.funcao_id
        WHERE fi.imagem_id=? AND LOWER(al.nome_arquivo) LIKE '%.max'
        AND al.status IN ('concluido','atualizado','finalizado','historico')
        ORDER BY al.criado_em DESC,al.id DESC", 'i', [$image]);
}

function render_ef_audit(mysqli $conn, int $requestId, int $actor): void
{
    render_ef_exec($conn,"INSERT INTO render_ef_eventos (solicitacao_id,etapa,status,mensagem,colaborador_id,origem) SELECT id,etapa,status,mensagem,?,'USUARIO' FROM render_ef_solicitacoes WHERE id=?",'ii',[$actor,$requestId]);
}

function render_ef_public_request(?array $request): ?array
{
    if (!$request) return null;
    $plan = json_decode((string)$request['plano_json'], true) ?: [];
    $request['plan_hash'] = hash('sha256', (string)$request['plano_json']);
    $request['plan'] = array_intersect_key($plan, array_flip(['origin','scene','snapshot_id','approved_attempt_id','snapshot_sha256','file_confirmation','old_width','old_height','width','height','camera','renderer','max_version','frames','destination','job_name','outputs','rounding','generic','parameter_sources','blockers']));
    unset($request['plano_json'], $request['configuracao_json'], $request['worker_id']);
    return $request;
}
