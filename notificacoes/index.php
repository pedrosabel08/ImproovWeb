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

$flashOk = $_GET['ok'] ?? null;
$flashErr = $_GET['err'] ?? null;

$tableReady = true;

$usuarios = getAllUsuarios($conn);

$previewId = isset($_GET['preview']) ? (int)$_GET['preview'] : null;
$previewRow = null;
if ($previewId) {
    $stmtP = $conn->prepare('SELECT * FROM notificacoes WHERE id = ?');
    if ($stmtP) {
        $stmtP->bind_param('i', $previewId);
        $stmtP->execute();
        $resP = $stmtP->get_result();
        $previewRow = $resP ? $resP->fetch_assoc() : null;
        $stmtP->close();
    }
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$editRow = null;
$editTargets = [];

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
$sqlList = "SELECT n.*,
                  COALESCE(x.total, 0) AS dest_total,
                  COALESCE(x.vistos, 0) AS dest_vistos
           FROM notificacoes n
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

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>" />
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>" />
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <title>Notificações</title>
</head>

<body>

    <?php include '../sidebar.php'; ?>

    <div class="container">
        <div class="header">
            <h1>Notificações</h1>
            <div class="inline">
                <button class="btn primary" type="button" id="btnOpenCreate">Adicionar notificação</button>
                <div class="small">Admin</div>
            </div>
        </div>

        <?php if ($flashOk): ?>
            <div class="alert ok"><?= h($flashOk) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="alert err"><?= h($flashErr) ?></div>
        <?php endif; ?>

        <?php if (!$tableReady): ?>
            <div class="alert err">
                Tabela <b>notificacoes</b> não encontrada. Rode o SQL em
                <b>sql/2026-01-14_notificacoes_module.sql</b>.
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 style="margin:0 0 12px 0; font-size: 16px;">Notificações cadastradas</h2>

            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Título</th>
                        <th>Tipo</th>
                        <th>Canal</th>
                        <th>Segmentação</th>
                        <th>Prioridade</th>
                        <th>Janela</th>
                        <th>Leitura</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notificacoes)): ?>
                        <tr>
                            <td colspan="10" class="small">Nenhuma notificação ainda.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($notificacoes as $n): ?>
                        <tr>
                            <td><?= (int)$n['id'] ?></td>
                            <td>
                                <?php if ((int)$n['ativa'] === 1): ?>
                                    <span class="badge on">ativa</span>
                                <?php else: ?>
                                    <span class="badge off">inativa</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= h($n['titulo']) ?></div>
                                <div class="small">Criado em: <?= h($n['criado_em'] ?? '') ?></div>
                            </td>
                            <td><span class="badge"><?= h($n['tipo']) ?></span></td>
                            <td><span class="badge"><?= h($n['canal']) ?></span></td>
                            <td>
                                <span class="badge"><?= h($segmentacaoLabel($n['segmentacao_tipo'] ?? 'geral')) ?></span>
                            </td>
                            <td><?= (int)$n['prioridade'] ?></td>
                            <td class="small">
                                <div>Início: <?= h($n['inicio_em'] ?? '-') ?></div>
                                <div>Fim: <?= h($n['fim_em'] ?? '-') ?></div>
                            </td>
                            <td class="small">
                                <div><b><?= (int)($n['dest_vistos'] ?? 0) ?></b> / <?= (int)($n['dest_total'] ?? 0) ?> vistos</div>
                                <button class="btn" type="button" data-action="status" data-id="<?= (int)$n['id'] ?>">Ver status</button>
                            </td>
                            <td>
                                <div class="inline">
                                    <a class="btn" href="index.php?edit=<?= (int)$n['id'] ?>#modal">Editar</a>

                                    <form method="POST" action="actions/toggle.php" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>" />
                                        <input type="hidden" name="ativa" value="<?= (int)$n['ativa'] ?>" />
                                        <button class="btn" type="submit"><?= ((int)$n['ativa'] === 1) ? 'Desativar' : 'Ativar' ?></button>
                                    </form>

                                    <form method="POST" action="actions/delete.php" style="display:inline;" onsubmit="return confirmDelete();">
                                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>" />
                                        <button class="btn danger" type="submit">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($previewRow): ?>
            <?php
            $pTipo = $previewRow['tipo'] ?? 'info';
            $pSegLabel = $segmentacaoLabel($previewRow['segmentacao_tipo'] ?? 'geral');
            $pCtaLabel = $previewRow['cta_label'] ?? '';
            $pCtaUrl = $previewRow['cta_url'] ?? '';
            $pArquivoPath = $previewRow['arquivo_path'] ?? '';
            ?>
            <div class="card">
                <h2 style="margin:0 0 12px 0; font-size: 16px;">Preview do modal (após criar/atualizar)</h2>
                <div class="small" style="margin-bottom: 12px;">Este preview aparece apenas após salvar.</div>
                <button class="btn" type="button" id="btnOpenPreview">Abrir preview do modal</button>
            </div>
        <?php endif; ?>

        <div class="small">
            Próxima etapa: exibir notificações para usuários (banner/toast/modal) + segmentação.
        </div>
    </div>

    <!-- Modal Criar/Editar -->
    <div class="modal" id="modal" aria-hidden="true">
        <div class="modal__overlay" data-close="1"></div>
        <div class="modal__panel">
            <div class="modal__header">
                <div>
                    <div class="modal__title"><?= $editRow ? 'Editar notificação' : 'Adicionar notificação' ?></div>
                    <div class="small">Formulário + Preview</div>
                </div>
                <div class="inline">
                    <?php if ($editRow): ?>
                        <a class="btn" href="index.php">Sair da edição</a>
                    <?php endif; ?>
                    <button class="btn" type="button" data-close="1">Fechar</button>
                </div>
            </div>

            <div class="modal__cols">
                <div class="modal__col">
                    <form method="POST" action="<?= $editRow ? 'actions/update.php' : 'actions/create.php' ?>" enctype="multipart/form-data">
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
                            <textarea id="f_mensagem" name="mensagem" required><?= h($editRow['mensagem'] ?? '') ?></textarea>
                        </div>

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
                                    <label class="checkbox"><input type="checkbox" name="exige_confirmacao" <?= (($editRow['exige_confirmacao'] ?? 0) ? 'checked' : '') ?> /> Exige confirmação</label>
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

                        <div class="row">
                            <label>Payload JSON (opcional)</label>
                            <textarea id="f_payload" name="payload_json" placeholder='{"versao":"3.2.0","arquivo_id":123}'><?= h($editRow['payload_json'] ?? '') ?></textarea>
                        </div>

                        <div class="row">
                            <label>Arquivo (PDF ou imagem) (opcional)</label>
                            <input type="file" name="arquivo_pdf" accept="application/pdf,image/*" />
                            <?php if (!empty($editRow['arquivo_path'])): ?>
                                <div class="small">Arquivo atual: <a href="../<?= h($editRow['arquivo_path']) ?>" target="_blank" rel="noopener noreferrer"><?= h($editRow['arquivo_nome'] ?? 'Arquivo') ?></a></div>
                            <?php endif; ?>
                            <div class="small">O arquivo será salvo em uploads/notificacao.</div>
                        </div>

                        <div class="inline">
                            <button class="btn primary" type="submit"><?= $editRow ? 'Salvar' : 'Criar' ?></button>
                        </div>
                    </form>
                </div>

                <div class="modal__col">
                    <div class="preview">
                        <div class="preview__title">Preview</div>
                        <div class="preview__hint small">Simulação do banner acima (visual apenas).</div>

                        <div class="preview__banner" id="previewBanner">
                            <div class="preview__badge" id="previewBadge">info</div>
                            <div>
                                <div class="preview__bannerTitle" id="previewTitle">Título</div>
                                <div class="preview__bannerText" id="previewText">Mensagem</div>
                                <div class="preview__cta" id="previewCta" style="display:none;">
                                    <a href="#" onclick="return false;" id="previewCtaLink">Abrir</a>
                                </div>
                            </div>
                        </div>

                        <div class="preview__meta small" id="previewMeta"></div>
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
                <button class="btn" type="button" data-close-status="1">Fechar</button>
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

    <?php if ($previewRow): ?>
        <?php
        $pTipo = $previewRow['tipo'] ?? 'info';
        $pSegLabel = $segmentacaoLabel($previewRow['segmentacao_tipo'] ?? 'geral');
        $pCtaLabel = $previewRow['cta_label'] ?? '';
        $pCtaUrl = $previewRow['cta_url'] ?? '';
        $pArquivoPath = $previewRow['arquivo_path'] ?? '';
        ?>
        <!-- Modal Preview (após salvar) -->
        <div class="modal modal-preview" id="previewModal" aria-hidden="true" data-tipo="<?= h($pTipo) ?>">
            <div class="modal__overlay" data-close-preview="1"></div>
            <div class="modal__panel modal__panel--narrow">
                <div class="modal__header">
                    <div>
                        <div class="modal__title">Preview do modal</div>
                        <div class="small">Visualização real do canal Modal</div>
                    </div>
                    <button class="btn" type="button" data-close-preview="1">Fechar</button>
                </div>
                <div class="modal-preview__content" data-tipo="<?= h($pTipo) ?>">
                    <div class="modal-preview__title"><?= h($previewRow['titulo'] ?? '') ?></div>
                    <div class="modal-preview__text"><?= nl2br(h($previewRow['mensagem'] ?? '')) ?></div>
                    <div class="modal-preview__meta small">Segmentação: <?= h($pSegLabel) ?></div>

                    <?php if ($pCtaLabel && $pCtaUrl): ?>
                        <div class="modal-preview__actions">
                            <a class="btn primary" href="<?= h($pCtaUrl) ?>" target="_blank" rel="noopener noreferrer"><?= h($pCtaLabel) ?></a>
                        </div>
                    <?php endif; ?>

                    <?php if ((int)($previewRow['exige_confirmacao'] ?? 0) === 1): ?>
                        <div class="modal-preview__actions">
                            <button class="btn primary">Li e entendi</button>
                        </div>
                    <?php endif; ?>

                    <?php if ($pArquivoPath): ?>
                        <?php
                        $pExt = strtolower(pathinfo($pArquivoPath, PATHINFO_EXTENSION));
                        $pIsPdf = $pExt === 'pdf';
                        $pIsImg = in_array($pExt, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'], true);
                        ?>

                        <?php if ($pIsPdf): ?>
                            <div class="modal-preview__pdf" data-pdf-url="../<?= h($pArquivoPath) ?>">
                                <div class="small" style="margin-bottom: 8px;">PDF anexado</div>
                                <canvas class="modal-preview__canvas"></canvas>
                            </div>
                        <?php elseif ($pIsImg): ?>
                            <div class="modal-preview__img" data-img-url="../<?= h($pArquivoPath) ?>">
                                <div class="small" style="margin-bottom: 8px;">Imagem anexada</div>
                                <img class="modal-preview__img-el" alt="Imagem anexada" />
                            </div>
                        <?php else: ?>
                            <div class="small" style="margin-top: 10px;">Arquivo anexado: <a href="../<?= h($pArquivoPath) ?>" target="_blank" rel="noopener noreferrer"><?= h($previewRow['arquivo_nome'] ?? 'Arquivo') ?></a></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        window.__editOpen = <?= $editRow ? 'true' : 'false' ?>;
        window.__previewOpen = <?= $previewRow ? 'true' : 'false' ?>;
    </script>
    <script src="<?php echo asset_url('../assets/pdfjs/pdf.min.js'); ?>"></script>
    window.__previewOpen = <?= $previewRow ? 'true' : 'false' ?>;
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.js" integrity="sha512-2LIYaQTk12F6Q4jqZsPjoQxGByfK4l4iLwG1g9nC5o2nCxfC2uZz7G9gYIzo0WlF1lboS2k0H9rB2bx6qD0XyA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
</body>

</html>