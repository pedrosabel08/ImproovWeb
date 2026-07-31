<?php
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);


require_once __DIR__ . '/_common.php';
include_once __DIR__ . '/../conexao.php';

$flashOk = $_GET['ok'] ?? null;
$flashErr = $_GET['err'] ?? null;

$tableReady = true;

$usuarios = getAllUsuarios($conn);
$modulos = notificacaoGetModules($conn);
$versaoAtual = notificacaoCurrentVersion();

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$editRow = null;
$editTargets = [];
$editAttachments = [];

if ($editId) {
    $stmt = $conn->prepare('SELECT * FROM notificacoes WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $editId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $editRow = $res ? $res->fetch_assoc() : null;
        }
        $stmt->close();
    }

    if ($editRow && notificacaoAnexosTableExists($conn)) {
        $stmtA = $conn->prepare('SELECT id, nome_original, caminho, mime_type, tamanho FROM notificacoes_anexos WHERE notificacao_id = ? ORDER BY ordem, id');
        if ($stmtA) {
            $stmtA->bind_param('i', $editId);
            $stmtA->execute();
            $resA = $stmtA->get_result();
            while ($resA && ($rowA = $resA->fetch_assoc())) $editAttachments[] = $rowA;
            $stmtA->close();
        }
    }

    $stmtT = $conn->prepare('SELECT tipo, alvo_id FROM notificacoes_alvos WHERE notificacao_id = ?');
    if ($stmtT) {
        $stmtT->bind_param('i', $editId);
        $stmtT->execute();
        $resT = $stmtT->get_result();
        while ($resT && ($row = $resT->fetch_assoc())) {
            $t = (string)$row['tipo'];
            $v = (int)$row['alvo_id'];
            if (!isset($editTargets[$t])) {
                $editTargets[$t] = [];
            }
            $editTargets[$t][] = $v;
        }
        $stmtT->close();
    }
}

$funcoesById = [];
foreach ($funcoes as $f) {
    $funcoesById[(int)$f['idfuncao']] = $f['nome_funcao'];
}

$obrasById = [];
foreach (array_merge($obras, $obras_inativas) as $o) {
    $obrasById[(int)$o['idobra']] = $o['nomenclatura'] ?? $o['nome_obra'] ?? ('Obra #' . (int)$o['idobra']);
}

$segmentacaoLabel = function ($tipo) {
    if ($tipo === 'geral') return 'Geral';
    if ($tipo === 'funcao') return 'Por função';
    if ($tipo === 'pessoa') return 'Por pessoa';
    if ($tipo === 'projeto') return 'Por projeto';
    return $tipo;
};


$notificacoes = [];
$sqlList = "SELECT n.*, m.nome AS modulo_nome, m.codigo AS modulo_codigo,
                  u.nome_usuario AS criado_por_nome,
                  COALESCE(x.total, 0) AS dest_total,
                  COALESCE(x.vistos, 0) AS dest_vistos
           FROM notificacoes n
           LEFT JOIN notificacoes_modulos m ON m.id = n.modulo_id
           LEFT JOIN usuario u ON u.idusuario = n.criado_por
           LEFT JOIN (
             SELECT notificacao_id,
                    COUNT(*) AS total,
                    SUM(CASE WHEN visto_em IS NOT NULL THEN 1 ELSE 0 END) AS vistos
             FROM notificacoes_destinatarios
             GROUP BY notificacao_id
           ) x ON x.notificacao_id = n.id
           ORDER BY n.prioridade DESC, n.criado_em DESC";

$res = $conn->query($sqlList);
if ($res === false) {
    $tableReady = false;
} else {
    while ($row = $res->fetch_assoc()) {
        $notificacoes[] = $row;
    }
}

$versionLogs = [];
$versionTableReady = true;
$sqlVer = "SELECT id, versao, descricao, tipo, criado_em, criado_por
           FROM versionamentos
           ORDER BY criado_em DESC
           LIMIT 20";
$resVer = $conn->query($sqlVer);
if ($resVer === false) {
    $versionTableReady = false;
} else {
    while ($row = $resVer->fetch_assoc()) {
        $versionLogs[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" />
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>" />
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalNotificacoes.css'); ?>" />
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>" />
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>" />
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet" />
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <title>Notificações</title>
</head>

<body>

    <?php include '../sidebar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div class="page-header-left">
                <img src="../gif/assinatura_preto.gif" class="page-header-logo" id="gif" style="height:36px; opacity:0.85" />
                <h1 class="page-title">Notificações</h1>
            </div>
            <div class="inline">
                <button class="btn-apply" type="button" id="btnOpenCreate"><i class="fa-solid fa-plus"></i> Adicionar notificação</button>
            </div>
        </div>

        <?php if (!$tableReady): ?>
            <div class="alert-box danger"><i class="fa-solid fa-circle-xmark"></i>
                Tabela <b>notificacoes</b> não encontrada. Rode o SQL em
                <b>sql/2026-01-14_notificacoes_module.sql</b>.
            </div>
        <?php endif; ?>

        <div class="grid-scroll-area">
        <div class="table-section">
            <div class="table-section-header">
                <span class="table-section-title"><i class="fa-solid fa-bell"></i> Notificações cadastradas</span>
                <span class="table-section-count" id="notifCount">0</span>
            </div>
            <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Versão</th>
                        <th>Módulo</th>
                        <th>Segmentação</th>
                        <th>Status</th>
                        <th>Criado por</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notificacoes)): ?>
                        <tr>
                            <td colspan="8" class="small">Nenhuma notificação ainda.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($notificacoes as $n): ?>
                        <?php $statusWorkflow = notificacaoStatusEfetivo($n); ?>
                        <tr>
                            <td>
                                <div><?= h($n['titulo']) ?></div>
                                <?php if (!empty($n['motivo_rejeicao']) && $statusWorkflow === 'REJEITADA'): ?><div class="small">Motivo: <?= h($n['motivo_rejeicao']) ?></div><?php endif; ?>
                            </td>
                            <td><span class="status-badge s-info">FLOW <?= h($n['versao_publicacao'] ?? $versaoAtual) ?></span></td>
                            <td><?= h($n['modulo_nome'] ?? '-') ?></td>
                            <td>
                                <span class="status-badge s-info"><?= h($segmentacaoLabel($n['segmentacao_tipo'] ?? 'geral')) ?></span>
                            </td>
                            <td><span class="status-badge status-workflow status-workflow--<?= strtolower(str_replace('_', '-', $statusWorkflow)) ?>"><?= h(notificacaoStatusLabel($statusWorkflow)) ?></span></td>
                            <td><?= h($n['criado_por_nome'] ?? '-') ?></td>
                            <td class="small"><?= h($n['criado_em'] ?? '-') ?></td>
                            <td>
                                <div class="inline">
                                    <button class="btn-row neutral" type="button" data-action="preview" data-id="<?= (int)$n['id'] ?>"><i class="fa-solid fa-eye"></i> Prévia</button>
                                    <?php if (in_array($statusWorkflow, ['RASCUNHO', 'REJEITADA', 'PUBLICADA'], true)): ?><a class="btn-row neutral" href="index.php?edit=<?= (int)$n['id'] ?>#modal"><i class="fa-solid fa-pen"></i> Editar</a><?php endif; ?>
                                    <?php if (in_array($statusWorkflow, ['RASCUNHO', 'REJEITADA'], true)): ?><form method="POST" action="actions/workflow.php" style="display:inline;" data-workflow-action="enviar_aprovacao"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>" /><input type="hidden" name="acao" value="enviar_aprovacao" /><button class="btn-row neutral" type="submit">Enviar para aprovação</button></form><?php endif; ?>
                                    <?php if ($statusWorkflow === 'AGUARDANDO_APROVACAO'): ?><form method="POST" action="actions/workflow.php" style="display:inline;" data-workflow-action="aprovar"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>" /><input type="hidden" name="acao" value="aprovar" /><button class="btn-row neutral" type="submit">Aprovar</button></form><form method="POST" action="actions/workflow.php" style="display:inline;" data-workflow-action="rejeitar"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>" /><input type="hidden" name="acao" value="rejeitar" /><button class="btn-row danger" type="submit">Rejeitar</button></form><?php endif; ?>
                                    <?php if ($statusWorkflow === 'APROVADA'): ?><form method="POST" action="actions/workflow.php" style="display:inline;" data-workflow-action="publicar"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>" /><input type="hidden" name="acao" value="publicar" /><button class="btn-row neutral" type="submit">Publicar</button></form><?php endif; ?>
                                    <?php if ($statusWorkflow === 'PUBLICADA'): ?><form method="POST" action="actions/workflow.php" style="display:inline;" data-workflow-action="encerrar"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>" /><input type="hidden" name="acao" value="encerrar" /><button class="btn-row neutral" type="submit">Encerrar</button></form><button class="btn-row neutral" type="button" data-action="status" data-id="<?= (int)$n['id'] ?>">Leituras</button><?php endif; ?>

                                    <form method="POST" action="actions/delete.php" style="display:inline;" onsubmit="confirmDelete(event);">
                                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>" />
                                        <button class="btn-row danger" type="submit"><i class="fa-solid fa-trash"></i> Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                </table>
            </div>
        </div>
        </div>

    </div>

    <!-- Modal Criar/Editar -->
    <div class="modal" id="modal" aria-hidden="true">
        <div class="modal__overlay" data-close="1"></div>
        <div class="modal__panel">
            <div class="modal__header">
                <div>
                    <div class="modal__title"><?= $editRow ? 'Editar notificação' : 'Adicionar notificação' ?></div>
                    <div class="small">Formulário e versionamento</div>
                </div>
                <div class="inline">
                    <?php if ($editRow): ?>
                        <a class="btn-row neutral" href="index.php">Sair da edição</a>
                    <?php endif; ?>
                    <button class="btn-row neutral" type="button" data-close="1"><i class="fa-solid fa-xmark"></i> Fechar</button>
                </div>
            </div>

            <div class="modal__cols modal__cols--single">
                <div class="modal__col">
                    <div class="tabs">
                        <button class="tab is-active" type="button" data-tab-target="notif">Notificação</button>
                        <button class="tab" type="button" data-tab-target="version">Versionamento</button>
                    </div>

                    <div class="tab-panel is-active" data-tab-panel="notif">
                        <form id="notificationForm" method="POST" action="<?= $editRow ? 'actions/update.php' : 'actions/create.php' ?>" enctype="multipart/form-data">
                            <?php if ($editRow): ?>
                                <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>" />
                            <?php endif; ?>

                        <div class="grid">
                            <div class="row">
                                <label>Título</label>
                                <input id="f_titulo" type="text" name="titulo" maxlength="200" required value="<?= h($editRow['titulo'] ?? '') ?>" />
                            </div>
                            <div class="row">
                                <label>Prioridade</label>
                                <input id="f_prioridade" type="number" name="prioridade" value="<?= h($editRow['prioridade'] ?? 0) ?>" />
                            </div>
                        </div>

                        <div class="row">
                            <label>Mensagem</label>
                            <textarea id="f_mensagem" name="mensagem" class="quill-value" aria-hidden="true" tabindex="-1"><?= h($editRow['mensagem'] ?? '') ?></textarea>
                            <div id="mensagem-quill-editor" aria-label="Mensagem da notificação"></div>
                        </div>

                        <div class="grid">
                            <div class="row">
                                <label>Versão da publicação</label>
                                <div class="readonly-field">FLOW <?= h($versaoAtual) ?></div>
                                <div class="small">A versão atual do Flow será vinculada ao salvar.</div>
                            </div>
                            <div class="row">
                                <label>Módulo relacionado (opcional)</label>
                                <?php $moduloSelecionado = (int)($editRow['modulo_id'] ?? 0); ?>
                                <select id="f_modulo" name="modulo_id">
                                    <option value="">Nenhum módulo</option>
                                    <?php foreach ($modulos as $modulo): ?>
                                        <option value="<?= (int)$modulo['id'] ?>" <?= (int)$modulo['id'] === $moduloSelecionado ? 'selected' : '' ?>><?= h($modulo['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <label class="checkbox"><input type="checkbox" name="exige_confirmacao" <?= (($editRow['exige_confirmacao'] ?? 0) ? 'checked' : '') ?> /> Exige confirmação de leitura</label>
                        </div>

                        <details class="advanced-settings">
                            <summary>Configurações avançadas</summary>
                            <div class="advanced-settings__content">

                        <div class="grid-3">
                            <div class="row">
                                <label>Tipo</label>
                                <?php $tipo = $editRow['tipo'] ?? 'info'; ?>
                                <select id="f_tipo" name="tipo">
                                    <option value="info" <?= $tipo === 'info' ? 'selected' : '' ?>>info</option>
                                    <option value="warning" <?= $tipo === 'warning' ? 'selected' : '' ?>>warning</option>
                                    <option value="danger" <?= $tipo === 'danger' ? 'selected' : '' ?>>danger</option>
                                    <option value="success" <?= $tipo === 'success' ? 'selected' : '' ?>>success</option>
                                </select>
                            </div>
                            <div class="row">
                                <label>Canal</label>
                                <?php $canal = $editRow['canal'] ?? 'banner'; ?>
                                <select id="f_canal" name="canal">
                                    <option value="banner" <?= $canal === 'banner' ? 'selected' : '' ?>>banner</option>
                                    <option value="toast" <?= $canal === 'toast' ? 'selected' : '' ?>>toast</option>
                                    <option value="modal" <?= $canal === 'modal' ? 'selected' : '' ?>>modal</option>
                                    <option value="card" <?= $canal === 'card' ? 'selected' : '' ?>>card</option>
                                </select>
                            </div>
                            <div class="row">
                                <label>Status</label>
                                <div class="inline" style="padding-top: 4px;">
                                    <label class="checkbox"><input type="checkbox" name="ativa" <?= (($editRow['ativa'] ?? 1) ? 'checked' : '') ?> /> Ativa</label>
                                    <label class="checkbox"><input type="checkbox" name="fixa" <?= (($editRow['fixa'] ?? 0) ? 'checked' : '') ?> /> Fixa</label>
                                    <label class="checkbox"><input type="checkbox" name="fechavel" <?= (($editRow['fechavel'] ?? 1) ? 'checked' : '') ?> /> Fechável</label>
                                </div>
                            </div>
                        </div>

                        <div class="grid">
                            <div class="row">
                                <label>Início</label>
                                <input type="datetime-local" name="inicio_em" value="<?= h(toDatetimeLocalValue($editRow['inicio_em'] ?? null)) ?>" />
                            </div>
                            <div class="row">
                                <label>Fim</label>
                                <input type="datetime-local" name="fim_em" value="<?= h(toDatetimeLocalValue($editRow['fim_em'] ?? null)) ?>" />
                            </div>
                        </div>

                        <div class="grid">
                            <div class="row">
                                <label>CTA label (opcional)</label>
                                <input id="f_cta_label" type="text" name="cta_label" maxlength="100" value="<?= h($editRow['cta_label'] ?? '') ?>" />
                            </div>
                            <div class="row">
                                <label>CTA URL (opcional)</label>
                                <input id="f_cta_url" type="text" name="cta_url" maxlength="500" value="<?= h($editRow['cta_url'] ?? '') ?>" />
                            </div>
                        </div>

                            </div>
                        </details>

                        <div class="row">
                            <label>Segmentação</label>
                            <?php $seg = $editRow['segmentacao_tipo'] ?? 'geral'; ?>
                            <select id="f_segmentacao" name="segmentacao_tipo">
                                <option value="geral" <?= $seg === 'geral' ? 'selected' : '' ?>>Geral</option>
                                <option value="funcao" <?= $seg === 'funcao' ? 'selected' : '' ?>>Por função</option>
                                <option value="pessoa" <?= $seg === 'pessoa' ? 'selected' : '' ?>>Por pessoa</option>
                                <option value="projeto" <?= $seg === 'projeto' ? 'selected' : '' ?>>Por projeto</option>
                            </select>
                            <div class="small">Sem segmentação por página nesta etapa.</div>
                        </div>

                        <div class="row segment" id="seg_funcao" style="display:none;">
                            <label>Funções</label>
                            <?php $selFuncoes = $editTargets['funcao'] ?? []; ?>
                            <select name="funcao_ids[]" multiple size="6">
                                <?php foreach ($funcoes as $f): ?>
                                    <?php $fid = (int)$f['idfuncao']; ?>
                                    <option value="<?= $fid ?>" <?= in_array($fid, $selFuncoes, true) ? 'selected' : '' ?>><?= h($f['nome_funcao']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row segment" id="seg_pessoa" style="display:none;">
                            <label>Pessoas (usuários)</label>
                            <input id="userFilter" type="text" placeholder="Filtrar usuário..." />
                            <div class="userlist" id="userList">
                                <?php $selUsers = $editTargets['pessoa'] ?? []; ?>
                                <?php foreach ($usuarios as $u): ?>
                                    <?php $uid = (int)$u['idusuario']; ?>
                                    <label class="useritem" data-name="<?= h(strtolower($u['nome_usuario'])) ?>">
                                        <input type="checkbox" name="usuario_ids[]" value="<?= $uid ?>" <?= in_array($uid, $selUsers, true) ? 'checked' : '' ?> />
                                        <span><?= h($u['nome_usuario']) ?></span>
                                        <?php if ((int)$u['ativo'] !== 1): ?>
                                            <span class="badge off">inativo</span>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="row segment" id="seg_projeto" style="display:none;">
                            <label>Projetos (obras)</label>
                            <?php $selObras = $editTargets['projeto'] ?? []; ?>
                            <select name="obra_ids[]" multiple size="6">
                                <?php foreach ($obras as $o): ?>
                                    <?php $oid = (int)$o['idobra']; ?>
                                    <option value="<?= $oid ?>" <?= in_array($oid, $selObras, true) ? 'selected' : '' ?>><?= h($o['nomenclatura'] ?? $o['nome_obra']) ?></option>
                                <?php endforeach; ?>
                                <?php if (!empty($obras_inativas)): ?>
                                    <optgroup label="Inativas">
                                        <?php foreach ($obras_inativas as $o): ?>
                                            <?php $oid = (int)$o['idobra']; ?>
                                            <option value="<?= $oid ?>" <?= in_array($oid, $selObras, true) ? 'selected' : '' ?>><?= h($o['nomenclatura'] ?? $o['nome_obra']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                            <div class="small">Destinatários do projeto são calculados por colaboradores que possuem funções na obra.</div>
                        </div>

                        <details class="advanced-settings">
                            <summary>Payload avançado</summary>
                            <div class="advanced-settings__content row">
                            <label>Payload JSON (opcional)</label>
                            <textarea id="f_payload" name="payload_json" placeholder='{"versao":"3.2.0","arquivo_id":123}'><?= h($editRow['payload_json'] ?? '') ?></textarea>
                            </div>
                        </details>

                        <div class="row">
                            <label>Arquivo (PDF ou imagem) (opcional)</label>
                            <input id="f_arquivos" type="file" name="arquivos[]" accept="application/pdf,image/png,image/jpeg,image/gif,image/webp,image/bmp" multiple />
                            <div class="small">Até 10 arquivos, 10 MB por arquivo e 40 MB no total. Formatos aceitos: PDF, PNG, JPG, GIF, WEBP e BMP.</div>
                            <div id="f_arquivos_feedback" class="small" aria-live="polite"></div>
                            <?php if (!empty($editAttachments)): ?>
                                <div class="small">Anexos já enviados:</div>
                                <ul class="attachment-list">
                                    <?php foreach ($editAttachments as $attachment): ?>
                                        <li><a href="../<?= h($attachment['caminho']) ?>" target="_blank" rel="noopener noreferrer"><?= h($attachment['nome_original']) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (!empty($editRow['arquivo_path'])): ?>
                                <div class="small">Arquivo atual: <a href="../<?= h($editRow['arquivo_path']) ?>" target="_blank" rel="noopener noreferrer"><?= h($editRow['arquivo_nome'] ?? 'Arquivo') ?></a></div>
                            <?php endif; ?>
                            <div class="small">O arquivo será salvo em uploads/notificacao.</div>
                        </div>

                            <div class="inline">
                                <button class="btn-apply" type="submit"><i class="fa-solid fa-check"></i> <?= $editRow ? 'Salvar' : 'Criar' ?></button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-panel" data-tab-panel="version">
                        <form method="POST" action="actions/version_bump.php" data-async-action>
                            <div class="row">
                                <label>Versão atual</label>
                                <div class="small"><b><?= h(defined('APP_VERSION') ? APP_VERSION : 'dev') ?></b></div>
                            </div>

                            <div class="grid-3">
                                <div class="row">
                                    <label>Tipo de atualização</label>
                                    <select id="f_version_type" name="version_type">
                                        <option value="patch">Pequena (patch)</option>
                                        <option value="minor">Média (minor)</option>
                                        <option value="major">Grande (major)</option>
                                        <option value="manual">Manual</option>
                                    </select>
                                </div>
                                <div class="row">
                                    <label>Versão manual (opcional)</label>
                                    <input id="f_version_manual" type="text" name="version_manual" placeholder="1.2.3" disabled />
                                    <div class="small">Use apenas no modo Manual.</div>
                                </div>
                            </div>

                            <div class="row">
                                <label>Descrição da versão</label>
                                <textarea id="f_version_desc" name="version_desc" maxlength="2000" placeholder="Descreva as mudanças desta versão..."></textarea>
                            </div>

                            <div class="inline">
                                <button class="btn-apply" type="submit"><i class="fa-solid fa-tag"></i> Registrar versão</button>
                            </div>
                        </form>

                        <div class="card" style="margin: 16px 0 0 0;">
                            <h3 style="margin:0 0 10px 0; font-size: 14px;">Últimos registros</h3>
                            <?php if (!$versionTableReady): ?>
                                <div class="alert err">Tabela <b>versionamentos</b> não encontrada. Rode o SQL em <b>sql/2026-01-19_versionamentos.sql</b>.</div>
                            <?php else: ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Versão</th>
                                            <th>Tipo</th>
                                            <th>Descrição</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($versionLogs)): ?>
                                            <tr>
                                                <td colspan="4" class="small">Nenhum registro ainda.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($versionLogs as $v): ?>
                                            <tr>
                                                <td class="small"><?= h($v['criado_em'] ?? '-') ?></td>
                                                <td><span class="status-badge s-info"><?= h($v['versao'] ?? '-') ?></span></td>
                                                <td class="small"><?= h($v['tipo'] ?? '-') ?></td>
                                                <td class="small"><?= nl2br(h($v['descricao'] ?? '')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Status (quem viu) -->
    <div class="modal" id="statusModal" aria-hidden="true">
        <div class="modal__overlay" data-close-status="1"></div>
        <div class="modal__panel modal__panel--narrow">
            <div class="modal__header">
                <div>
                    <div class="modal__title">Status de leitura</div>
                    <div class="small">Quem viu / quem falta</div>
                </div>
                <button class="btn-row neutral" type="button" data-close-status="1"><i class="fa-solid fa-xmark"></i> Fechar</button>
            </div>
            <div class="card" style="margin: 0;">
                <div id="statusSummary" class="small" style="margin-bottom: 12px;"></div>
                <table class="table" id="statusTable">
                    <thead>
                        <tr>
                            <th>Usuário</th>
                            <th>Visto em</th>
                            <th>Confirmado</th>
                            <th>Dispensado</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        window.__editOpen = <?= $editRow ? 'true' : 'false' ?>;
        window.__legacyToast = <?= json_encode($flashOk ?: $flashErr ?: '', JSON_UNESCAPED_UNICODE) ?>;
        window.__legacyToastType = <?= json_encode($flashErr ? 'error' : 'success') ?>;
        // Count badge
        document.addEventListener('DOMContentLoaded', () => {
            const rows = document.querySelectorAll('.data-table tbody tr');
            const count = document.getElementById('notifCount');
            if (count) count.textContent = rows.length;
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="<?php echo asset_url('render.js'); ?>"></script>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
</body>

</html>
