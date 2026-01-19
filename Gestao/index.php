<?php
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

session_start();
// $nome_usuario = $_SESSION['nome_usuario'];

include '../conexaoMain.php';
include '../conexao.php';

// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
//     // Se não estiver logado, redirecionar para a página de login
//     header("Location: ../index.html");
//     exit();
// }

// Buscar a quantidade de funções do colaborador com status "Em andamento"
// $colaboradorId = $_SESSION['idcolaborador'];
// $funcoesCountSql = "SELECT COUNT(*) AS total_funcoes_em_andamento
//                     FROM funcao_imagem
//                     WHERE colaborador_id = ? AND status = 'Em andamento'";
// $funcoesCountStmt = $conn->prepare($funcoesCountSql);
// $funcoesCountStmt->bind_param("i", $colaboradorId);
// $funcoesCountStmt->execute();
// $funcoesCountResult = $funcoesCountStmt->get_result();

// Armazenar a quantidade na sessão
// $funcoesCount = $funcoesCountResult->fetch_assoc();

// $funcoesCountStmt->close();

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$imagens = obterImagens($conn);
$status_etapa = obterStatus($conn);

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt/dist/frappe-gantt.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>Tela Gestao</title>
</head>

<body>

    <?php

    include '../sidebar.php';

    ?>
    <main>
        <div class="header">
            <p>Olá, Pedro</p>
        </div>
        <div class="main-content">
            <div class="section-metrics">
                <div class="card-metric">
                    <p class="card-title">Projetos ativos</p>
                    <div class="card-count"><span><strong></strong> projetos</span></div>
                </div>
                <div class="card-metric">
                    <p class="card-title">Total de imagens</p>
                    <div class="card-count"><span><strong></strong> imagens</span></div>
                </div>
                <!-- CARD AGRUPADOR DE TAREFAS -->
                <div class="card-metric card-tarefas">
                    <!-- <p class="card-title">Tarefas</p> -->
                    <div class="tarefas-grid">
                        <div class="subcard">
                            <p class="sub-count"><strong></strong></p>
                            <p class="sub-title">TO-DO</p>
                        </div>
                        <div class="subcard">
                            <p class="sub-count"><strong></strong></p>
                            <p class="sub-title">TEA</p>
                        </div>
                        <div class="subcard">
                            <p class="sub-count"><strong></strong></p>
                            <p class="sub-title">Concluídas</p>
                        </div>
                        <div class="subcard">
                            <p class="sub-count"><strong></strong></p>
                            <p class="sub-title">HOLD</p>
                        </div>
                        <div class="subcard">
                            <p class="sub-count"><strong></strong></p>
                            <p class="sub-title">Em aprovação</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="second-row">

                <div class="section-entregas">
                    <div class="kanban">
                        <div class="kanban-columns">
                            <div class="kanban-title">
                                <p class="kanban-title">Próximas</p>
                                <i class="fa-solid fa-ellipsis-vertical" id="menuBtn"></i>
                            </div>
                            <div class="content">
                            </div>
                        </div>
                        <div class="kanban-columns">
                            <p class="kanban-title">Atrasadas</p>
                            <div class="content">
                            </div>
                        </div>
                        <div class="kanban-columns">
                            <p class="kanban-title">Entregues</p>
                            <div class="content">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="section-calendar">
                    <div id="calendar">
                    </div>
                </div>
            </div>
            <div class="section-tasks">
                <div class="card-group" data-funcao="Caderno,Filtro de assets">
                    <div class="title">
                        <div class="funcao-info">
                            <span>Caderno/Filtro</span>
                        </div>
                    </div>
                    <div class="colabs"></div>
                    <div class="progress">
                        <div class="progress-bar" style="width:72%"></div>
                        <small class="progress-count">0/0 tarefas</small>
                    </div>
                </div>
                <div class="card-group" data-funcao="Modelagem">
                    <div class="title">
                        <div class="funcao-info">
                            <span>Modelagem</span>
                        </div>
                    </div>
                    <div class="colabs"></div>
                    <div class="progress">
                        <div class="progress-bar" style="width:45%"></div>
                        <small class="progress-count">0/0 tarefas</small>
                    </div>
                </div>

                <div class="card-group" data-funcao="Composição">
                    <div class="title">
                        <div class="funcao-info">
                            <span>Composição</span>
                        </div>
                    </div>
                    <div class="colabs"></div>
                    <div class="progress">
                        <div class="progress-bar" style="width:80%"></div>
                        <small class="progress-count">0/0 tarefas</small>
                    </div>
                </div>

                <div class="card-group" data-funcao="Finalização">
                    <div class="title">
                        <div class="funcao-info">
                            <span>Finalização</span>
                        </div>
                    </div>
                    <div class="colabs"></div>
                    <div class="progress">
                        <div class="progress-bar" style="width:92%"></div>
                        <small class="progress-count">0/0 tarefas</small>
                    </div>
                </div>

                <div class="card-group" data-funcao="Pós-produção">
                    <div class="title">
                        <div class="funcao-info">
                            <span>Pós-produção</span>
                        </div>
                    </div>
                    <div class="colabs"></div>
                    <div class="progress">
                        <div class="progress-bar" style="width:60%"></div>
                        <small class="progress-count">0/0 tarefas</small>
                    </div>
                </div>

                <div class="card-group" data-funcao="Alteração">
                    <div class="title">
                        <div class="funcao-info">
                            <span>Alteração</span>
                        </div>
                    </div>
                    <div class="colabs"></div>
                    <div class="progress">
                        <div class="progress-bar" style="width:20%"></div>
                        <small class="progress-count">0/0 tarefas</small>
                    </div>
                </div>
            </div>

            <section class="section-gantt">
                <h2>Planejamento (Gantt) — imagens por dia</h2>
                <div id="gantt" class="gantt-grid" role="table" aria-label="Gantt"></div>
            </section>

            <!-- <iframe src="../Projetos/index.php" frameborder="0" style="
    width: 100vw;
    height: 100vh;
"></iframe> -->
            <div class="menu-popup" id="menuPopup">
                <button id="addEntrega"><i class="fa-solid fa-plus"></i> Adicionar Entrega</button>
            </div>

            <!-- Modal Adicionar Entrega -->
            <div id="modalAdicionarEntrega" class="modal">
                <div class="modal-content">
                    <h2>Adicionar Entrega</h2>
                    <form id="formAdicionarEntrega">
                        <div>
                            <label>Obra:</label>
                            <select name="obra_id" id="obra_id" required>
                                <option value="">Selecione a obra</option>
                                <?php foreach ($obras as $obra): ?>
                                    <option value="<?= $obra['idobra']; ?>">
                                        <?= htmlspecialchars($obra['nomenclatura']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Status:</label>
                            <select name="status_id" id="status_id" required>
                                <option value="">Selecione o status</option>
                                <?php foreach ($status_imagens as $status): ?>
                                    <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                                        <?= htmlspecialchars($status['nome_status']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="imagens_container" class="imagens-container">
                            <p>Selecione uma obra e status para listar as imagens.</p>
                        </div>

                        <div>
                            <label>Prazo:</label>
                            <input type="date" name="prazo" id="prazo">
                        </div>
                        <div>
                            <label>Observações</label>
                            <textarea name="observacoes" id="observacoes"></textarea>
                        </div>

                        <button type="submit" class="btn-salvar">Salvar Entrega</button>
                    </form>
                </div>
            </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.5.0/dist/frappe-gantt.min.js"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('script.js'); ?>"></script>

</body>

</html>