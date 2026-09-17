<?php

declare(strict_types=1);
require_once __DIR__ . '/../Briefing/lib.php';

final class PortalError extends RuntimeException
{
    public function __construct(string $message, public readonly int $http = 422)
    {
        parent::__construct($message);
    }
}
function portal_rows(mysqli $db, string $sql, string $types = '', array $args = []): array
{
    $s = briefing_stmt($db, $sql, $types, $args);
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    return $rows;
}
function portal_one(mysqli $db, string $sql, string $types = '', array $args = []): ?array
{
    return portal_rows($db, $sql, $types, $args)[0] ?? null;
}
function portal_exec(mysqli $db, string $sql, string $types = '', array $args = []): void
{
    briefing_stmt($db, $sql, $types, $args)->close();
}
function portal_event(mysqli $db, int $obra, string $type, ?int $user, ?int $contact, array $data = []): void
{
    portal_exec($db, 'INSERT INTO portal_evento(obra_id,tipo,usuario_id,contato_id,dados) VALUES (?,?,?,?,?)', 'isiis', [$obra,$type,$user,$contact,json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
}
function portal_text(mixed $value, int $limit, bool $required = true): string
{
    if (!is_string($value)) {
        throw new PortalError('Confira os campos informados.');
    }
    $v = trim($value);
    if (($required && $v === '') || mb_strlen($v) > $limit || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $v)) {
        throw new PortalError('Confira os campos obrigatórios e o tamanho dos textos.');
    }
    return $v;
}
function portal_ids(mixed $ids): array
{
    if (!is_array($ids) || count($ids) > 40) {
        throw new PortalError('Seleção de disciplinas inválida.');
    }
    $result = [];
    foreach ($ids as $id) {
        if (!ctype_digit((string)$id) || (int)$id < 1) {
            throw new PortalError('Disciplina inválida.');
        } $result[] = (int)$id;
    }
    return array_values(array_unique($result));
}
function portal_internal_user(mysqli $db): array
{
    if (($_SESSION['logado'] ?? false) !== true) {
        throw new PortalError('Entre no Flow para continuar.', 401);
    }
    $u = portal_one($db, 'SELECT idusuario,nivel_acesso FROM usuario WHERE idusuario=? AND ativo=1', 'i', [(int)($_SESSION['idusuario'] ?? 0)]);
    if (!$u) {
        throw new PortalError('Sessão interna inválida.', 401);
    } return $u;
}
function portal_project(mysqli $db, int $obra, bool $lock = false): array
{
    $p = portal_one($db, "SELECT p.*,o.status_obra,o.cliente,o.local,COALESCE(NULLIF(o.nome_real,''),NULLIF(o.nome_completo,''),o.nome_obra) nome_projeto,c.nome_cliente FROM portal_projeto p JOIN obra o ON o.idobra=p.obra_id JOIN cliente c ON c.idcliente=o.cliente WHERE p.obra_id=?" . ($lock ? ' FOR UPDATE' : ''), 'i', [$obra]);
    if (!$p) {
        throw new PortalError('Projeto indisponível.', 404);
    } return $p;
}
function portal_internal_access(array $p, array $user): void
{
    if ((int)$user['nivel_acesso'] !== 1 && (int)$user['idusuario'] !== (int)$p['curador_usuario_id']) {
        throw new PortalError('Você não tem acesso a este projeto.', 403);
    }
}
function portal_open(array $p): void
{
    if ($p['estado'] !== 'ABERTO' || (int)$p['status_obra'] !== 0) {
        throw new PortalError('Este projeto está encerrado. Fale com seu contato na Improov.', 409);
    }
}
function portal_revision(array $p, mixed $expected): void
{
    if ((int)$expected !== (int)$p['revisao']) {
        throw new PortalError('O projeto mudou enquanto você estava aqui. Atualize a página antes de continuar.', 409);
    }
}
function portal_bump(mysqli $db, int $obra): void
{
    portal_exec($db, 'UPDATE portal_projeto SET revisao=revisao+1 WHERE obra_id=?', 'i', [$obra]);
}
function portal_link(mysqli $db, string $token, bool $lock = false): array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new PortalError('Convite indisponível. Peça um novo link à Improov.', 404);
    }
    $id = briefing_scalar($db, 'SELECT obra_id FROM portal_projeto WHERE convite_hash=?', 's', [hash('sha256', $token)]);
    if (!$id) {
        throw new PortalError('Convite indisponível. Peça um novo link à Improov.', 404);
    }
    $p = portal_project($db, (int)$id, $lock);
    if (!hash_equals((string)$p['convite_hash'], hash('sha256', $token))) {
        throw new PortalError('Convite indisponível.', 404);
    }
    return $p;
}
function portal_member(mysqli $db, int $obra, int $contact, bool $requireJoined = true): array
{
    $m = portal_one($db, 'SELECT p.*,c.nome,c.email,c.telefone FROM portal_participante p JOIN obra_contato oc ON oc.obra_id=p.obra_id AND oc.contato_cliente_id=p.contato_id JOIN contato_cliente c ON c.idcontato_cliente=p.contato_id WHERE p.obra_id=? AND p.contato_id=? AND p.removido_em IS NULL AND oc.ativo=1 AND c.ativo=1', 'ii', [$obra,$contact]);
    if (!$m || ($requireJoined && !$m['ingressou_em'])) {
        throw new PortalError('Identifique-se para acessar este projeto.', 403);
    } return $m;
}
function portal_catalog(mysqli $db): array
{
    return portal_rows($db, 'SELECT id,nome,categoria_id FROM flow_disciplina WHERE ativa=1 ORDER BY nome');
}
function portal_selected(mysqli $db, int $obra): array
{
    return array_map('intval', array_column(portal_rows($db, 'SELECT disciplina_id FROM portal_projeto_disciplina WHERE obra_id=?', 'i', [$obra]), 'disciplina_id'));
}
function portal_request(mysqli $db, int $obra): array
{
    return portal_one($db, 'SELECT * FROM portal_solicitacao WHERE obra_id=?', 'i', [$obra]) ?? throw new PortalError('Solicitação indisponível.', 404);
}
function portal_draft(mysqli $db, int $obra): array
{
    $r = portal_request($db, $obra);
    if ($r['estado'] !== 'RASCUNHO') {
        throw new PortalError('A solicitação já foi publicada.', 409);
    } return $r;
}

function portal_configure(mysqli $db, array $user, array $body): array
{
    if ((int)$user['nivel_acesso'] !== 1) {
        throw new PortalError('Somente a gestão pode configurar o Portal.', 403);
    }
    $obra = (int)($body['obra_id'] ?? 0);
    $o = portal_one($db, 'SELECT idobra,cliente,status_obra FROM obra WHERE idobra=? FOR UPDATE', 'i', [$obra]);
    if (!$o || (int)$o['status_obra'] !== 0 || !(int)$o['cliente']) {
        throw new PortalError('Escolha uma obra ativa vinculada a um cliente.');
    }
    if (portal_one($db, 'SELECT obra_id FROM portal_projeto WHERE obra_id=?', 'i', [$obra])) {
        throw new PortalError('O Portal desta obra já está configurado.', 409);
    }
    $curator = (int)($body['curador_usuario_id'] ?? 0);
    if (!briefing_scalar($db, 'SELECT 1 FROM usuario WHERE idusuario=? AND ativo=1', 'i', [$curator])) {
        throw new PortalError('Escolha um curador ativo.');
    }
    $email = briefing_email($body['email'] ?? '');
    if (strlen($email) > 150) {
        throw new PortalError('O e-mail deve ter até 150 caracteres.');
    }
    $name = portal_text($body['nome'] ?? '', 150);
    $phone = portal_text($body['telefone'] ?? '', 30);
    $contact = portal_one($db, 'SELECT idcontato_cliente,ativo FROM contato_cliente WHERE email_normalizado=?', 's', [$email]);
    if ($contact && !(int)$contact['ativo']) {
        throw new PortalError('Contato inativo. Revise seu cadastro no Flow.');
    }
    $cid = $contact ? (int)$contact['idcontato_cliente'] : contact_arch_save_client_contact($db, (int)$o['cliente'], ['name' => $name,'email' => $email,'phone' => $phone,'type' => 'OUTRO'], true);
    $oc = portal_one($db, 'SELECT ativo FROM obra_contato WHERE obra_id=? AND contato_cliente_id=?', 'ii', [$obra,$cid]);
    if ($oc && !(int)$oc['ativo']) {
        throw new PortalError('Vínculo inativo. Revise o contato da obra antes de configurar.');
    }
    contact_arch_link_contact_to_obra($db, $obra, $cid);
    $token = bin2hex(random_bytes(32));
    portal_exec($db, 'INSERT INTO portal_projeto(obra_id,administrador_contato_id,curador_usuario_id,convite_hash,criado_por) VALUES (?,?,?,?,?)', 'iiisi', [$obra,$cid,$curator,hash('sha256', $token),(int)$user['idusuario']]);
    portal_exec($db, 'INSERT INTO portal_participante(obra_id,contato_id) VALUES (?,?)', 'ii', [$obra,$cid]);
    portal_exec($db, 'INSERT INTO portal_solicitacao(obra_id) VALUES (?)', 'i', [$obra]);
    portal_event($db, $obra, 'project.configured', (int)$user['idusuario'], null, ['curator' => $curator,'administrator' => $cid]);
    return ['token' => $token,'obra_id' => $obra];
}

/** Small keyed preparation handlers; each owns its relational answer. */
function portal_prepare(mysqli $db, array $p, int $contact, array $body): void
{
    portal_open($p);
    portal_revision($p, $body['revisao'] ?? null);
    portal_draft($db, (int)$p['obra_id']);
    $handlers = ['disciplinas' => 'portal_prepare_disciplines'];
    $handler = $handlers[$body['question'] ?? ''] ?? null;
    if (!$handler) {
        throw new PortalError('Pergunta indisponível.');
    }
    $handler($db, $p, $contact, $body);
}
function portal_prepare_disciplines(mysqli $db, array $p, int $contact, array $body): void
{
    $ids = portal_ids($body['disciplinas'] ?? null);
    $valid = array_map('intval', array_column(portal_catalog($db), 'id'));
    if (!$ids || array_diff($ids, $valid)) {
        throw new PortalError('Escolha ao menos uma disciplina do projeto.');
    }
    $obra = (int)$p['obra_id'];
    $before = portal_selected($db, $obra);
    foreach (array_diff($before, $ids) as $id) {
        $used = briefing_scalar($db, 'SELECT (SELECT COUNT(*) FROM portal_participante_disciplina WHERE obra_id=? AND disciplina_id=?) + (SELECT COUNT(*) FROM portal_material WHERE obra_id=? AND disciplina_id=?)', 'iiii', [$obra,$id,$obra,$id]);
        if ((int)$used) {
            throw new PortalError('Uma disciplina já possui pessoas ou materiais vinculados. Peça à Improov para revisar a configuração.', 409);
        }
        portal_exec($db, 'DELETE FROM portal_projeto_disciplina WHERE obra_id=? AND disciplina_id=?', 'ii', [$obra,$id]);
    }
    foreach (array_diff($ids, $before) as $id) {
        portal_exec($db, 'INSERT INTO portal_projeto_disciplina(obra_id,disciplina_id) VALUES (?,?)', 'ii', [$obra,$id]);
    }
    portal_exec($db, 'UPDATE portal_projeto SET preparado_em=UTC_TIMESTAMP() WHERE obra_id=?', 'i', [$obra]);
    portal_suggest($db, $obra);
    portal_event($db, $obra, 'preparation.saved', null, $contact, ['before' => $before,'after' => $ids]);
    portal_bump($db, $obra);
}
function portal_suggest(mysqli $db, int $obra): void
{
    $r = portal_draft($db, $obra);
    $disc = portal_rows($db, 'SELECT d.*,c.nome_categoria FROM portal_projeto_disciplina pd JOIN flow_disciplina d ON d.id=pd.disciplina_id JOIN categorias c ON c.idcategoria=d.categoria_id WHERE pd.obra_id=?', 'i', [$obra]);
    foreach ($disc as $d) {
        $key = 'disciplina:'.$d['id'];
        if (briefing_scalar($db, 'SELECT id FROM portal_material WHERE solicitacao_id=? AND sugestao_chave=?', 'is', [(int)$r['id'],$key])) {
            continue;
        }
        portal_exec($db, "INSERT INTO portal_material(solicitacao_id,obra_id,disciplina_id,categoria_id,titulo,momento,contexto,observacao,origem,sugestao_chave) VALUES (?,?,?,?,?,'INICIO',?,'','SUGESTAO',?)", 'iiiisss', [(int)$r['id'],$obra,(int)$d['id'],(int)$d['categoria_id'],'Projeto de '.$d['nome'],'Este material nos ajudará a compreender as decisões de '.$d['nome'].' e preparar a base do seu projeto.',$key]);
        $mid = (int)$db->insert_id;
        $sources = portal_rows($db, "SELECT r.id,r.tipo_arquivo FROM briefing_requisitos_arquivo r JOIN briefing_tipo_imagem t ON t.id=r.briefing_tipo_imagem_id WHERE t.obra_id=? AND r.categoria=? AND r.origem='cliente' AND r.tipo_arquivo<>'INTERNAL'", 'is', [$obra,$d['nome_categoria']]);
        foreach ($sources as $s) {
            portal_exec($db, 'INSERT INTO portal_material_origem(material_id,requisito_id) VALUES (?,?)', 'ii', [$mid,(int)$s['id']]);
            $format = strtoupper($s['tipo_arquivo']);
            if (preg_match('/^[A-Z0-9]{1,12}$/', $format)) {
                portal_exec($db, 'INSERT IGNORE INTO portal_material_formato(material_id,formato) VALUES (?,?)', 'is', [$mid,$format]);
            }
        }
        // Legacy requirements can be deleted/reconfigured by Dashboard. Keep
        // provenance in the audit, but never block that existing workflow.
        portal_event($db, $obra, 'material.suggested', null, null, ['material_id' => $mid, 'categoria_id' => (int)$d['categoria_id'], 'disciplina_id' => (int)$d['id'], 'sources' => $sources]);
    }
}
function portal_profile(mysqli $db, array $p, int $contact, array $body): void
{
    portal_open($p);
    $obra = (int)$p['obra_id'];
    $ids = portal_ids($body['disciplinas'] ?? []);
    if (array_diff($ids, portal_selected($db, $obra))) {
        throw new PortalError('Escolha somente disciplinas presentes neste projeto.');
    }
    $name = portal_text($body['nome'] ?? '', 150);
    $phone = portal_text($body['telefone'] ?? '', 30);
    $before = portal_member($db, $obra, $contact);
    portal_exec($db, 'UPDATE contato_cliente SET nome=?,telefone=? WHERE idcontato_cliente=?', 'ssi', [$name,$phone,$contact]);
    portal_exec($db, 'DELETE FROM portal_participante_disciplina WHERE obra_id=? AND contato_id=?', 'ii', [$obra,$contact]);
    foreach ($ids as $id) {
        portal_exec($db, 'INSERT INTO portal_participante_disciplina(obra_id,contato_id,disciplina_id) VALUES (?,?,?)', 'iii', [$obra,$contact,$id]);
    }
    portal_exec($db, 'UPDATE portal_participante SET perfil_confirmado_em=UTC_TIMESTAMP() WHERE obra_id=? AND contato_id=?', 'ii', [$obra,$contact]);
    portal_event($db, $obra, 'participant.profile_saved', null, $contact, ['disciplinas' => $ids,'before' => ['nome' => $before['nome'],'telefone' => $before['telefone']],'after' => ['nome' => $name,'telefone' => $phone]]);
}
function portal_formats(mixed $input): array
{
    if (!is_string($input)) {
        throw new PortalError('Informe os formatos aceitos.');
    }
    $out = array_values(array_unique(array_filter(preg_split('/[\s,;]+/', strtoupper(trim($input))))));
    if (!$out || count($out) > 20) {
        throw new PortalError('Informe de 1 a 20 formatos aceitos.');
    }
    foreach ($out as $f) {
        if (!preg_match('/^[A-Z0-9]{1,12}$/', $f)) {
            throw new PortalError('Use formatos como DWG, RVT, PDF, separados por vírgula.');
        }
    } return $out;
}
function portal_material_save(mysqli $db, array $p, int $user, array $body): void
{
    portal_open($p);
    portal_revision($p, $body['revisao'] ?? null);
    $obra = (int)$p['obra_id'];
    $r = portal_draft($db, $obra);
    $id = (int)($body['id'] ?? 0);
    $before = null;
    if ($id) {
        $before = portal_one($db, 'SELECT * FROM portal_material WHERE id=? AND obra_id=? AND removido_em IS NULL', 'ii', [$id,$obra]);
        if (!$before) {
            throw new PortalError('Material indisponível.', 404);
        }
    }
    $disc = (int)($body['disciplina_id'] ?? 0);
    $cat = (int)($body['categoria_id'] ?? 0);
    if (!in_array($disc, portal_selected($db, $obra), true) || !briefing_scalar($db, 'SELECT 1 FROM categorias WHERE idcategoria=?', 'i', [$cat])) {
        throw new PortalError('Confira a disciplina e o tipo do material.');
    }
    $title = portal_text($body['titulo'] ?? '', 180);
    $context = portal_text($body['contexto'] ?? '', 3000);
    $note = portal_text($body['observacao'] ?? '', 3000, false);
    $moment = $body['momento'] ?? '';
    if (!in_array($moment, ['INICIO','DURANTE'], true)) {
        throw new PortalError('Momento inválido.');
    } $formats = portal_formats($body['formatos'] ?? '');
    if ($id) {
        portal_exec($db, 'UPDATE portal_material SET disciplina_id=?,categoria_id=?,titulo=?,momento=?,contexto=?,observacao=?,revisado_por=? WHERE id=? AND obra_id=?', 'iissssiii', [$disc,$cat,$title,$moment,$context,$note,$user,$id,$obra]);
    } else {
        portal_exec($db, "INSERT INTO portal_material(solicitacao_id,obra_id,disciplina_id,categoria_id,titulo,momento,contexto,observacao,origem,revisado_por) VALUES (?,?,?,?,?,?,?,?,'CURADORIA',?)", 'iiiissssi', [(int)$r['id'],$obra,$disc,$cat,$title,$moment,$context,$note,$user]);
        $id = (int)$db->insert_id;
    }
    $oldFormats = array_column(portal_rows($db, 'SELECT formato FROM portal_material_formato WHERE material_id=?', 'i', [$id]), 'formato');
    portal_exec($db, 'DELETE FROM portal_material_formato WHERE material_id=?', 'i', [$id]);
    foreach ($formats as $f) {
        portal_exec($db, 'INSERT INTO portal_material_formato(material_id,formato) VALUES (?,?)', 'is', [$id,$f]);
    }
    portal_event($db, $obra, 'material.saved', $user, null, ['id' => $id,'before' => $before,'before_formats' => $oldFormats,'after' => ['disciplina' => $disc,'categoria' => $cat,'titulo' => $title,'momento' => $moment,'contexto' => $context,'observacao' => $note,'formatos' => $formats]]);
    portal_bump($db, $obra);
}
function portal_publish(mysqli $db, array $p, int $user, array $body): void
{
    portal_open($p);
    $obra = (int)$p['obra_id'];
    $r = portal_request($db, $obra);
    if ($r['estado'] === 'PUBLICADA') {
        return;
    }
    portal_revision($p, $body['revisao'] ?? null);
    $items = portal_rows($db, 'SELECT m.*,(SELECT COUNT(*) FROM portal_material_formato f WHERE f.material_id=m.id) formatos FROM portal_material m WHERE m.obra_id=? AND m.removido_em IS NULL', 'i', [$obra]);
    if (!$p['preparado_em'] || !$items) {
        throw new PortalError('Defina as disciplinas e ao menos um material antes de publicar.');
    }
    foreach ($items as $i) {
        if (!$i['revisado_por'] || !trim($i['contexto']) || !(int)$i['formatos']) {
            throw new PortalError('Revise cada material e preencha contexto e formatos antes de publicar.');
        }
    }
    portal_exec($db, "UPDATE portal_solicitacao SET estado='PUBLICADA',publicada_em=UTC_TIMESTAMP(),publicada_por=?,revisao=revisao+1 WHERE id=?", 'ii', [$user,(int)$r['id']]);
    portal_event($db, $obra, 'materials.published', $user, null, ['solicitacao_id' => (int)$r['id'],'materiais' => array_map('intval', array_column($items, 'id'))]);
    portal_bump($db, $obra);
}
function portal_internal_mutate(mysqli $db, array $p, array $u, string $action, array $b): array
{
    portal_internal_access($p, $u);
    $obra = (int)$p['obra_id'];
    $uid = (int)$u['idusuario'];
    if ($action === 'material.save') {
        portal_material_save($db, $p, $uid, $b);
        return [];
    }
    if ($action === 'materials.publish') {
        portal_publish($db, $p, $uid, $b);
        return [];
    }
    portal_revision($p, $b['revisao'] ?? null);
    if ($action === 'material.remove') {
        portal_open($p);
        portal_draft($db, $obra);
        $id = (int)($b['id'] ?? 0);
        $item = portal_one($db, 'SELECT * FROM portal_material WHERE id=? AND obra_id=? AND removido_em IS NULL', 'ii', [$id,$obra]);
        if (!$item) {
            throw new PortalError('Material indisponível.', 404);
        }
        portal_exec($db, 'UPDATE portal_material SET removido_em=UTC_TIMESTAMP() WHERE id=? AND obra_id=?', 'ii', [$id,$obra]);
        portal_event($db, $obra, 'material.removed', $uid, null, ['before' => $item]);
    } elseif ($action === 'participant.remove' || $action === 'participant.restore') {
        $cid = (int)($b['contato_id'] ?? 0);
        if ($cid === (int)$p['administrador_contato_id'] && $action === 'participant.remove') {
            throw new PortalError('Designe outro administrador antes de remover esta pessoa.');
        }
        if (!portal_one($db, 'SELECT contato_id FROM portal_participante WHERE obra_id=? AND contato_id=?', 'ii', [$obra,$cid])) {
            throw new PortalError('Participante indisponível.', 404);
        }
        if ($action === 'participant.remove') {
            portal_exec($db, 'UPDATE portal_participante SET removido_em=UTC_TIMESTAMP(),removido_por=? WHERE obra_id=? AND contato_id=?', 'iii', [$uid,$obra,$cid]);
        } else {
            portal_exec($db, 'UPDATE portal_participante SET removido_em=NULL,removido_por=NULL WHERE obra_id=? AND contato_id=?', 'ii', [$obra,$cid]);
        }
        portal_event($db, $obra, $action, $uid, null, ['contato_id' => $cid]);
    } elseif ($action === 'administrator.set') {
        $cid = (int)($b['contato_id'] ?? 0);
        portal_member($db, $obra, $cid, false);
        portal_exec($db, 'UPDATE portal_projeto SET administrador_contato_id=? WHERE obra_id=?', 'ii', [$cid,$obra]);
        portal_event($db, $obra, 'administrator.changed', $uid, null, ['before' => (int)$p['administrador_contato_id'],'after' => $cid]);
    } elseif ($action === 'project.settings') {
        if ((int)$u['nivel_acesso'] !== 1) {
            throw new PortalError('Somente a gestão altera a configuração.', 403);
        }
        $cur = (int)($b['curador_usuario_id'] ?? 0);
        $state = $b['estado'] ?? '';
        $open = ($b['inscricoes_abertas'] ?? false) === true;
        if (!in_array($state, ['ABERTO','ENCERRADO'], true) || !briefing_scalar($db, 'SELECT 1 FROM usuario WHERE idusuario=? AND ativo=1', 'i', [$cur])) {
            throw new PortalError('Configuração inválida.');
        }
        portal_exec($db, 'UPDATE portal_projeto SET curador_usuario_id=?,estado=?,inscricoes_abertas=? WHERE obra_id=?', 'isii', [$cur,$state,(int)$open,$obra]);
        portal_event($db, $obra, 'project.settings_changed', $uid, null, ['before' => ['curador' => $p['curador_usuario_id'],'estado' => $p['estado'],'inscricoes' => $p['inscricoes_abertas']],'after' => ['curador' => $cur,'estado' => $state,'inscricoes' => $open]]);
    } elseif ($action === 'invite.rotate' || $action === 'invite.revoke') {
        portal_open($p);
        $token = $action === 'invite.rotate' ? bin2hex(random_bytes(32)) : null;
        portal_exec($db, 'UPDATE portal_projeto SET convite_hash=? WHERE obra_id=?', 'si', [$token ? hash('sha256', $token) : null,$obra]);
        portal_event($db, $obra, $action, $uid, null);
        portal_bump($db, $obra);
        return ['token' => $token];
    } else {
        throw new PortalError('Ação indisponível.', 404);
    }
    portal_bump($db, $obra);
    return [];
}

function portal_moment(array $p, array $r, bool $profile): array
{
    if ($p['estado'] !== 'ABERTO' || (int)$p['status_obra'] !== 0) {
        return ['title' => 'Este projeto está encerrado.','context' => 'A memória desta etapa continua aqui para você.','action' => null,'next' => 'Fale com seu contato na Improov sempre que precisar.'];
    }
    if (!$profile) {
        return ['title' => 'Vamos conhecer sua participação.','context' => 'Conte-nos como você acompanha este projeto. Isso nos ajuda a trazer o que é relevante para você.','action' => 'perfil','next' => 'Depois, você poderá acompanhar o projeto e convidar outras pessoas.'];
    }
    if (!$p['preparado_em']) {
        return ['title' => 'Vamos preparar o seu projeto.','context' => 'Quais disciplinas estarão presentes? Com isso, podemos organizar os materiais que vão nos ajudar a começar.','action' => 'preparacao','next' => 'Nossa equipe vai cuidar da seleção dos materiais necessários.'];
    }
    if ($r['estado'] === 'PUBLICADA') {
        return ['title' => 'Os materiais do seu projeto estão organizados.','context' => 'Preparamos uma seleção com o contexto do que vamos precisar em cada momento.','action' => 'materiais','next' => 'O próximo momento será reunir esses materiais com você. O envio pelo Portal ainda não está disponível.'];
    }
    return ['title' => 'Estamos preparando o início do seu projeto.','context' => 'Nossa equipe está organizando os materiais necessários, com atenção às disciplinas que você nos contou.','action' => null,'next' => 'Assim que a seleção estiver pronta, ela aparecerá aqui. Enquanto isso, sua equipe pode continuar entrando.'];
}
function portal_data(mysqli $db, array $p, ?int $contact = null): array
{
    $obra = (int)$p['obra_id'];
    $internal = $contact === null;
    $r = portal_request($db, $obra);
    $selected = portal_selected($db, $obra);
    $me = $contact ? portal_member($db, $obra, $contact) : null;
    $team = portal_rows($db, 'SELECT pp.contato_id,c.nome,pp.ingressou_em,pp.removido_em FROM portal_participante pp JOIN contato_cliente c ON c.idcontato_cliente=pp.contato_id JOIN obra_contato oc ON oc.obra_id=pp.obra_id AND oc.contato_cliente_id=pp.contato_id WHERE pp.obra_id=?'.($internal ? '' : ' AND pp.removido_em IS NULL AND c.ativo=1 AND oc.ativo=1').' ORDER BY c.nome', 'i', [$obra]);
    foreach ($team as &$t) {
        $t['administrador'] = (int)$t['contato_id'] === (int)$p['administrador_contato_id'];
        $t['disciplinas'] = array_map('intval', array_column(portal_rows($db, 'SELECT disciplina_id FROM portal_participante_disciplina WHERE obra_id=? AND contato_id=?', 'ii', [$obra,(int)$t['contato_id']]), 'disciplina_id'));
    } unset($t);
    $materials = [];
    if ($internal || $r['estado'] === 'PUBLICADA') {
        $materials = portal_rows($db, 'SELECT id,disciplina_id,categoria_id,titulo,momento,contexto,observacao,revisado_por FROM portal_material WHERE obra_id=? AND removido_em IS NULL ORDER BY momento,id', 'i', [$obra]);
        foreach ($materials as &$m) {
            $m['formatos'] = array_column(portal_rows($db, 'SELECT formato FROM portal_material_formato WHERE material_id=? ORDER BY formato', 'i', [(int)$m['id']]), 'formato');
            if (!$internal) {
                unset($m['categoria_id'],$m['revisado_por']);
            }
        } unset($m);
    }
    $data = ['project' => ['nome' => $p['nome_projeto'],'cliente' => $p['nome_cliente'],'local' => $p['local'],'revisao' => (int)$p['revisao'],'preparado' => (bool)$p['preparado_em'],'aberto' => $p['estado'] === 'ABERTO' && (int)$p['status_obra'] === 0,'inscricoes_abertas' => (bool)$p['inscricoes_abertas']], 'catalog' => portal_catalog($db),'disciplinas' => $selected,'team' => $team,'materials' => $materials,'published' => $r['estado'] === 'PUBLICADA','moment' => portal_moment($p,$r,(bool)($me['perfil_confirmado_em'] ?? true))];
    if ($me) {
        $data['me'] = ['nome' => $me['nome'],'email' => $me['email'],'telefone' => $me['telefone'],'contato_id' => $contact,'confirmado' => (bool)$me['perfil_confirmado_em'],'disciplinas' => array_map('intval',array_column(portal_rows($db,'SELECT disciplina_id FROM portal_participante_disciplina WHERE obra_id=? AND contato_id=?','ii',[$obra,$contact]),'disciplina_id'))];
        $data['moment'] = portal_moment($p,$r,(bool)$me['perfil_confirmado_em']);
    }
    if ($internal) {
        $data['project'] += ['obra_id' => $obra,'curador_usuario_id' => (int)$p['curador_usuario_id'],'estado' => $p['estado'],'link_ativo' => (bool)$p['convite_hash']];
        $data['categories'] = portal_rows($db,'SELECT idcategoria,nome_categoria FROM categorias ORDER BY nome_categoria');
    } else {
        foreach ($data['catalog'] as &$d) {
            unset($d['categoria_id']);
        } unset($d);
    }
    return $data;
}
