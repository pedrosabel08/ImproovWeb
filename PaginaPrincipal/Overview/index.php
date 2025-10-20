<?php
session_start();

include '../../conexao.php';
include '../../conexaoMain.php';
// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
//     header("Location: ../index.html");
//     exit();
// }



$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <!-- Global standard styles (variables, resets) -->
    <link rel="stylesheet" href="../../css/stylePadrao.css">
    <link rel="stylesheet" href="../../css/styleSidebar.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <title>Painel de Produção</title>
</head>

<body>

    <?php

    include '../../sidebar.php';

    ?>

    <div class="container">
            <header>
                <div class="brand">
                    <div class="logo">OV</div>
                    <div>
                        <h1>Overview do Colaborador</h1>
                        <p class="sub">Resumo rápido das suas tarefas, atualizações e feedbacks</p>
                    </div>
                </div>
                <div style="text-align:right">
                    <div class="pill">Olá, Pedro</div>
                </div>
            </header>

            <!-- Metrics -->
            <section class="metrics" aria-label="Métricas principais">
                <div class="card">
                    <div class="metric-value" id="pct-completed">--%</div>
                    <div class="metric-label">% tarefas concluídas (mês)</div>
                    <div class="delta" id="pct-completed-delta">&nbsp;</div>
                </div>

                <div class="card">
                    <div class="metric-value" id="avg-time">-- dias</div>
                    <div class="metric-label">Tempo médio de conclusão</div>
                    <div class="delta" id="avg-time-delta">&nbsp;</div>
                </div>

                <div class="card">
                    <div class="metric-value" id="approval-rate">--%</div>
                    <div class="metric-label">Taxa de aprovação</div>
                    <div class="delta" id="approval-delta">&nbsp;</div>
                </div>

                <div class="card">
                    <div class="metric-value" id="due-today">0</div>
                    <div class="metric-label">Tarefas com prazo hoje</div>
                    <div class="delta" id="due-delta">&nbsp;</div>
                </div>
            </section>

            <main>
                <section>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                        <h2 style="margin:0;font-size:16px">Novidades / Atualizações</h2>
                        <div style="display:flex;gap:8px;align-items:center">
                            <select id="feed-filter" class="pill" aria-label="Filtro do feed">
                                <option value="all">Tudo</option>
                                <option value="my">Minhas tarefas</option>
                                <option value="action">Ação necessária</option>
                            </select>
                            <button id="mark-read" class="btn">Marcar tudo como lido</button>
                        </div>
                    </div>

                    <div class="feed" id="updates-list" aria-live="polite">
                        <!-- example/mock updates -->
                        <div class="feed-item">
                            <div class="avatar">PB</div>
                            <div class="content">
                                <div class="meta"><strong>Pedro</strong> · <span>2h</span></div>
                                <div class="title" style="font-weight:700;margin-top:6px">Atualização no projeto Slack Web Redesign</div>
                                <div class="body" style="margin-top:6px;color:var(--muted)">Nova versão enviada para revisão. Comentários no arquivo principal.</div>
                                <div class="actions">
                                    <button class="btn">Comentar</button>
                                    <button class="btn">Ver tarefa</button>
                                </div>
                            </div>
                        </div>

                        <div class="feed-item">
                            <div class="avatar">AR</div>
                            <div class="content">
                                <div class="meta"><strong>Ana</strong> · <span>1d</span></div>
                                <div class="title" style="font-weight:700;margin-top:6px">Feedback: ajustes nas imagens de loja</div>
                                <div class="body" style="margin-top:6px;color:var(--muted)">Pequenas correções solicitadas no banner principal. Ver detalhes no comentário.</div>
                                <div class="actions">
                                    <button class="btn">Marcar como lido</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <aside class="right-col">
                    <div class="mini">
                        <div style="display:flex;justify-content:space-between;align-items:center">
                            <div>
                                <div class="tiny">Próxima ação</div>
                                <div style="font-weight:700">Revisar implantação lojas</div>
                                <div class="tiny">Prazo: 2025-10-16</div>
                            </div>
                            <div>
                                <button class="primary" id="go-to-next">Ir</button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 style="margin:8px 0 6px 0">Últimos feedbacks</h3>
                        <div class="feedback" id="feedback-list">
                            <!-- feedbacks -->
                        </div>
                    </div>

                    <div style="margin-top:6px">
                        <h3 style="margin:8px 0 6px 0">Atalhos</h3>
                        <div style="display:flex;gap:8px">
                            <button class="btn" id="create-task">Criar tarefa</button>
                            <button class="btn" id="request-review">Pedir revisão</button>
                        </div>
                    </div>
                </aside>
            </main>

            <footer>
                <small>Este é um MVP demonstrativo — os dados são mockados. Use os botões para testar
                    interações.</small>
            </footer>
        </div>

        <!-- Task panel (simulated navigation target) -->
        <div id="task-panel" role="dialog" aria-modal="false">
            <h3>Tarefa</h3>
            <div id="task-details">Carregando...</div>
            <div style="margin-top:10px;text-align:right">
                <button class="btn" id="close-task">Fechar</button>
            </div>
        </div>

        <!-- Comment modal -->
        <div class="modal-backdrop" id="modal-backdrop" role="dialog" aria-modal="true">
            <div class="modal" role="document" aria-labelledby="modal-title">
                <h2 id="modal-title">Comentários</h2>
                <div id="modal-update-info" class="tiny">—</div>

                <div class="comments" id="modal-comments">
                    <!-- comments -->
                </div>

                <div style="display:flex;gap:8px;margin-top:12px">
                    <input id="comment-input" placeholder="Adicionar comentário..."
                        style="flex:1;padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,0.03);background:transparent;color:inherit" />
                    <button class="primary" id="send-comment">Enviar</button>
                    <button class="btn" id="close-modal">Fechar</button>
                </div>
            </div>
        </div>

        <script src="script.js"></script>
        <script src="../../script/sidebar.js"></script>
    </body>

</html>