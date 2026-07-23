<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: ../index.html');
    exit;
}
$root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$root . '/flow/ImproovWeb/config/version.php', $root . '/ImproovWeb/config/version.php'] as $path) {
    if ($path && is_file($path)) {
        require_once $path;
        break;
    }
}
$initialPlanId = (int) ($_GET['plano_id'] ?? 0);
$asset = static fn(string $file): string => rawurlencode((string) (@filemtime(__DIR__ . '/' . $file) ?: time()));
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Planejamento Fotográfico</title>
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?= $asset('style.css') ?>">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
</head>

<body><?php include __DIR__ . '/../sidebar.php'; ?><main class="foto-app" data-initial-plan="<?= $initialPlanId ?>">
        <div id="fotoMessage" class="foto-message" hidden></div>
        <section id="fotoListView">
            <header class="foto-page-head">
                <div>
                    <div class="foto-breadcrumb">Fotográfico <i class="fa-solid fa-chevron-right"></i> Planos</div>
                    <h1>Planejamento Fotográfico</h1>
                    <p>Planos, execução e conferência do material compartilhado.</p>
                </div><button class="foto-btn foto-btn-primary" id="fotoNewCampaign" hidden><i class="fa-solid fa-plus"></i> Nova campanha</button>
            </header>
            <div class="foto-list-toolbar"><label><i class="fa-solid fa-magnifying-glass"></i><input id="fotoSearch" type="search" placeholder="Buscar obra ou responsável"></label><select id="fotoStatusFilter">
                    <option value="">Todos os estados</option>
                    <option value="EM_ELABORACAO">Em elaboração</option>
                    <option value="PRONTO_EXECUCAO">Pronto para execução</option>
                    <option value="EM_CONFERENCIA">Em conferência</option>
                    <option value="HOLD">Em HOLD</option>
                </select></div>
            <div id="fotoPlanList" class="foto-plan-list"></div>
        </section>
        <section id="fotoDetailView" hidden>
            <header class="foto-page-head foto-detail-head">
                <div>
                    <div class="foto-breadcrumb"><button id="fotoBack" class="foto-text-btn">Fotográfico</button><i class="fa-solid fa-chevron-right"></i><span>Planos</span><i class="fa-solid fa-chevron-right"></i><strong id="fotoCrumb">Plano</strong></div>
                    <div class="foto-title-row">
                        <h1 id="fotoTitle">Plano fotográfico</h1><span id="fotoStatus" class="foto-status"></span>
                    </div>
                    <p id="fotoSubtitle"></p>
                </div>
                <div class="foto-actions"><button class="foto-btn foto-btn-ghost" id="fotoRefresh"><i class="fa-solid fa-rotate"></i> Atualizar</button><button class="foto-btn foto-btn-ghost" id="fotoPendingButton"><i class="fa-solid fa-triangle-exclamation"></i> Pendências <span id="fotoPendingCount">0</span></button><button class="foto-btn foto-btn-primary" id="fotoPublish" title="Resolva todas as pendências para publicar."><i class="fa-solid fa-check"></i> Publicar plano</button><button class="foto-btn foto-btn-ghost" id="fotoRevision">Criar revisão</button></div>
            </header>
            <nav class="foto-tabs"><button class="is-active" data-tab="overview">Visão geral</button><button data-tab="plan">Plano</button><button data-tab="execution">Execução</button><button data-tab="issues">Pendências e HOLD</button><button data-tab="history">Histórico</button></nav>
            <section class="foto-panel is-active" data-panel="overview">
                <div id="fotoOverviewStats" class="foto-stats"></div>
                <div class="foto-grid two">
                    <article class="foto-card">
                        <div class="foto-card-head">
                            <h2>Dados gerais</h2><small class="foto-muted">Salvamento automático</small>
                        </div>
                        <div class="foto-key-values"><label>Endereço<input id="fotoAddress"></label><label>Link do Maps<input id="fotoMapsUrl" type="url" placeholder="https://maps.google.com/"></label><label>Responsável pela execução<select id="fotoExecutor">
                                    <option value="">Selecione</option>
                                </select></label><label>Data planejada<input id="fotoPlannedDate" type="date"></label></div>
                    </article>
                    <article class="foto-card">
                        <div class="foto-card-head">
                            <h2>Resumo do plano</h2>
                        </div>
                        <div id="fotoOverviewSummary" class="foto-summary-grid"></div>
                        <div id="fotoSla" class="foto-sla"></div>
                    </article>
                </div>
                <article id="fotoReadiness" class="foto-readiness"></article>
            </section>
            <section class="foto-panel" data-panel="plan">
                <article id="fotoPlanGuidance" class="foto-plan-guidance"></article>
                <div class="foto-plan-layout">
                    <div class="foto-plan-main">
                        <article id="fotoMapCard" class="foto-card foto-map-card">
                            <div class="foto-card-head">
                                <div>
                                    <h2>Mapa de posições <span id="fotoMapBadge" class="foto-section-badge"></span></h2>
                                    <p>Envie o mapa para criar e mover pontos.</p>
                                </div>
                                <div><input id="fotoMapFile" accept="image/jpeg,image/png,image/webp" type="file" hidden><button class="foto-btn foto-btn-secondary" id="fotoMapUpload">Enviar mapa</button><button class="foto-btn foto-btn-ghost" id="fotoMapZoom" hidden>Ampliar</button></div>
                            </div>
                            <div id="fotoMapEmpty" class="foto-map-empty"><i class="fa-regular fa-map"></i><strong>Envie a imagem do mapa para começar.</strong><span>JPG, JPEG, PNG ou WEBP.</span></div>
                            <div id="fotoMapViewport" class="foto-map-viewport" hidden>
                                <div id="fotoMapStage" class="foto-map-stage"><img id="fotoMapImage" alt="Mapa de posições">
                                    <div id="fotoPins"></div>
                                </div>
                            </div>
                            <p class="foto-hint">Clique na imagem para criar um pin. Arraste um pin para reposicioná-lo.</p>
                        </article>
                        <article id="fotoPointsFullCard" class="foto-card foto-points-full-card">
                            <div class="foto-card-head">
                                <div>
                                    <h2>Pontos fotográficos <span id="fotoPointsBadge" class="foto-section-badge"></span></h2>
                                    <p id="fotoPositionCount"></p>
                                </div><button class="foto-icon-btn" id="fotoAddPosition" aria-label="Adicionar posição"><i class="fa-solid fa-plus"></i></button>
                            </div>
                            <div id="fotoPositionCards" class="foto-point-list"></div>
                        </article>
                        <article id="fotoImagesCard" class="foto-card foto-contract-images">
                            <div class="foto-card-head">
                                <div>
                                    <h2>Imagens contratadas <span id="fotoImagesBadge" class="foto-section-badge"></span></h2>
                                    <p>Decida e vincule cada imagem a um ponto e período.</p>
                                </div>
                            </div>
                            <div id="fotoImages" class="foto-images"></div>
                        </article>
                    </div>
                    <aside class="foto-plan-sidebar">
                        <article id="fotoPointsCard" class="foto-card foto-points-card foto-points-preview-card">
                            <div class="foto-card-head">
                                <div>
                                    <h2>Pontos fotográficos <span id="fotoPointsBadge" class="foto-section-badge"></span></h2>
                                    <p id="fotoPositionCount"></p>
                                </div><button class="foto-icon-btn" id="fotoAddPosition" aria-label="Adicionar posição"><i class="fa-solid fa-plus"></i></button>
                            </div>
                            <div id="fotoPointsPreview" class="foto-points-preview"></div>
                        </article>
                        <aside id="fotoPlanAbout" class="foto-card foto-plan-about"></aside>
                    </aside>
                </div>
            </section>
            <dialog id="fotoChecklistDialog" class="foto-checklist-dialog">
                <div class="foto-dialog-head">
                    <h2>Pendências do plano</h2><button type="button" class="foto-icon-btn" id="fotoChecklistClose" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div id="fotoChecklistList" class="foto-checklist-list"></div>
            </dialog>
            <section class="foto-panel" data-panel="execution">
                <div id="fotoExecutionStats" class="foto-stats"></div>
                <div class="foto-grid two">
                    <article class="foto-card">
                        <div class="foto-card-head">
                            <h2>Registrar nova execução</h2>
                        </div>
                        <div class="foto-key-values"><label>Data e hora da execução<input id="fotoExecutedAt" type="datetime-local"></label><label>Link da pasta no Drive<input id="fotoMaterialUrl" type="url" placeholder="https://drive.google.com/"></label><label class="wide">Observações gerais<textarea id="fotoExecutionNote" rows="4"></textarea></label><label class="wide">Anexos leves<input id="fotoEvidenceFiles" accept="image/jpeg,image/png,image/webp,application/pdf" type="file" multiple></label></div><button class="foto-btn foto-btn-primary" id="fotoSubmitExecution">Enviar para conferência</button>
                    </article>
                    <article class="foto-card">
                        <div class="foto-card-head">
                            <h2>Conferência global</h2>
                        </div>
                        <p class="foto-muted">A conferência avalia o conteúdo completo do Drive, sem checklist por captura.</p><label class="foto-field">Decisão<select id="fotoReviewDecision">
                                <option value="">Selecione</option>
                                <option value="APROVADO">Aprovado</option>
                                <option value="APROVADO_COM_RESSALVAS">Aprovado com ressalvas</option>
                                <option value="COMPLEMENTO_NECESSARIO">Complemento necessário</option>
                                <option value="REPROVADO">Reprovado</option>
                            </select></label><label class="foto-field">Consideração<textarea id="fotoReviewNote" rows="5" placeholder="Obrigatória para ressalva, complemento ou reprovação."></textarea></label><button class="foto-btn foto-btn-primary" id="fotoReviewSubmit">Registrar conferência</button>
                    </article>
                </div>
                <article class="foto-card tentativas">
                    <div class="foto-card-head">
                        <h2>Tentativas</h2>
                    </div>
                    <div id="fotoExecutions" class="foto-timeline"></div>
                </article>
            </section>
            <section class="foto-panel" data-panel="issues">
                <div id="fotoIssueStats" class="foto-stats"></div>
                <div class="foto-grid two">
                    <article class="foto-card">
                        <div class="foto-card-head">
                            <h2>Pendências</h2>
                        </div>
                        <div id="fotoIssues" class="foto-timeline"></div>
                    </article>
                    <article class="foto-card">
                        <div class="foto-card-head">
                            <h2>HOLD</h2><button class="foto-btn foto-btn-danger" id="fotoOpenHold">Abrir HOLD</button>
                        </div>
                        <div id="fotoHolds" class="foto-timeline"></div>
                    </article>
                </div>
            </section>
            <section class="foto-panel" data-panel="history">
                <article class="foto-card">
                    <div class="foto-card-head">
                        <h2>Linha do tempo</h2>
                    </div>
                    <div id="fotoHistory" class="foto-timeline"></div>
                </article>
            </section>
        </section>
    </main>
    <dialog id="fotoHoldDialog" class="foto-dialog">
        <form method="dialog">
            <h2>Abrir HOLD</h2><label class="foto-field">Classificação<select id="fotoHoldCode">
                    <option value="CLIMA">Condição climática</option>
                    <option value="INFORMACAO_INCOMPLETA">Informação incompleta</option>
                    <option value="ALTERACAO_PLANO">Alteração no plano</option>
                    <option value="REAGENDAMENTO">Reagendamento</option>
                </select></label><label class="foto-field">Observação<textarea id="fotoHoldDetails" rows="4"></textarea></label>
            <div class="foto-actions"><button value="cancel" class="foto-btn foto-btn-ghost">Cancelar</button><button type="button" class="foto-btn foto-btn-danger" id="fotoConfirmHold">Abrir HOLD</button></div>
        </form>
    </dialog>
    <script>
        window.FOTOGRAFICO_INITIAL = {
            planId: <?= $initialPlanId ?>
        };
    </script>
    <script src="../assets/js/upload-ws.js?v=<?= $asset('../assets/js/upload-ws.js') ?>"></script>
    <script src="script.js?v=<?= $asset('script.js') ?>"></script>
    <script src="../script/sidebar.js?v=<?= $asset('../script/sidebar.js') ?>"></script>
</body>

</html>