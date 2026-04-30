<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);


// session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

$conn = null;

include_once __DIR__ . '/../conexao.php';

$idusuario = $_SESSION['idusuario'];
$tela_atual = basename($_SERVER['PHP_SELF']);
// Use DB server time for ultima_atividade to avoid clock/timezone mismatches
// $ultima_atividade = date('Y-m-d H:i:s');

// We already extracted needed session values; close the session to release the lock
// before performing heavier DB work below.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Use MySQL NOW() so the database records its own current timestamp
$sql2 = "UPDATE logs_usuarios 
         SET tela_atual = ?, ultima_atividade = NOW()
         WHERE usuario_id = ?";
$stmt2 = $conn->prepare($sql2);

if (!$stmt2) {
    die("Erro no prepare: " . $conn->error);
}

// 'si' indica os tipos: string, integer
$stmt2->bind_param("si", $tela_atual, $idusuario);

if (!$stmt2->execute()) {
    die("Erro no execute: " . $stmt2->error);
}
$stmt2->close();

$sql_clientes = "SELECT idcliente, nome_cliente FROM cliente";
$result_cliente = $conn->query($sql_clientes);
$clientes = array();
if ($result_cliente->num_rows > 0) {
    while ($row = $result_cliente->fetch_assoc()) {
        $clientes[] = $row;
    }
}

$sql_obras = "SELECT idobra, nome_obra FROM obra";
$result_obra = $conn->query($sql_obras);
$obras = array();
if ($result_obra->num_rows > 0) {
    while ($row = $result_obra->fetch_assoc()) {
        $obras[] = $row;
    }
}

$sql_colaboradores = "SELECT idcolaborador, nome_colaborador FROM colaborador order by nome_colaborador";
$result_colaboradores = $conn->query($sql_colaboradores);
$colaboradores = array();
if ($result_colaboradores->num_rows > 0) {
    while ($row = $result_colaboradores->fetch_assoc()) {
        $colaboradores[] = $row;
    }
}

$sql_status = "SELECT idstatus, nome_status 
               FROM status_imagem 
               ORDER BY 
                   CASE 
                       WHEN nome_status = 'Sem status' THEN 0 
                       ELSE 1 
                   END, 
                   idstatus";

$result_status = $conn->query($sql_status);
$status_imagens = array();
if ($result_status->num_rows > 0) {
    while ($row = $result_status->fetch_assoc()) {
        $status_imagens[] = $row;
    }
}
// $sql_imagens = "SELECT idimagens_cliente_obra, imagem_nome FROM imagens_cliente_obra";
// $result_imagens = $conn->query($sql_imagens);
// $imagens = array();
// if ($result_imagens->num_rows > 0) {
//     while ($row = $result_imagens->fetch_assoc()) {
//         $imagens[] = $row;
//     }
// }

include '../conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$imagens = obterImagens($conn);

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo asset_url('stylePos.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.2.0/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />

    <title>Lista Pós-Produção</title>
    <style>

    </style>
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
</head>

<body>

    <?php include '../sidebar.php'; ?>

    <div id="filtro">
        <header>
            <div class="header-left">
                <img src="../gif/assinatura_preto.gif" class="page-header-logo" id="gif" alt="">
                <h1>Lista Pós-Produção</h1>
            </div>
            <div class="header-right">
                <button id="openModalBtn" style="display: none;">Inserir render</button>
                <button id="openModalBtnRender">Ver Render Elements</button>
            </div>
        </header>

        <!-- Metric Cards -->
        <section class="metricas-section">
            <div class="metric-card" id="card-pendentes" data-filter="pendentes">
                <div class="metric-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                <div class="metric-info">
                    <span class="metric-value" id="metric-pendentes">—</span>
                    <span class="metric-label">Pendentes</span>
                </div>
            </div>
            <div class="metric-card metric-card--danger" id="card-atraso" data-filter="atraso">
                <div class="metric-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="metric-info">
                    <span class="metric-value" id="metric-atraso">—</span>
                    <span class="metric-label">Em Atraso</span>
                </div>
            </div>
            <div class="metric-card metric-card--success" id="card-hoje" data-filter="hoje">
                <div class="metric-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div class="metric-info">
                    <span class="metric-value" id="metric-hoje">—</span>
                    <span class="metric-label">Finalizados Hoje</span>
                </div>
            </div>
            <div class="metric-card metric-card--blue" id="card-semana" data-filter="semana">
                <div class="metric-icon"><i class="fa-solid fa-calendar-week"></i></div>
                <div class="metric-info">
                    <span class="metric-value" id="metric-semana">—</span>
                    <span class="metric-label">Esta Semana</span>
                </div>
            </div>
        </section>

        <!-- Filter Bar -->
        <section class="filter-bar">
            <div class="filter-bar__search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="fb-busca" placeholder="Buscar imagem...">
            </div>
            <select id="fb-status-render">
                <option value="">Status Render</option>
                <option value="Aprovado">Aprovado</option>
                <option value="Em aprovação">Em aprovação</option>
                <option value="Em andamento">Em andamento</option>
                <option value="Erro">Erro</option>
                <option value="Reprovado">Reprovado</option>
                <option value="Finalizado">Finalizado</option>
            </select>
            <select id="fb-status-pos">
                <option value="">Status Pós</option>
                <option value="1">Não começou</option>
                <option value="0">Finalizado</option>
            </select>
            <select id="fb-obra">
                <option value="">Nome Obra</option>
            </select>
            <select id="fb-finalizador">
                <option value="">Finalizador</option>
            </select>
            <div class="filter-bar__actions">
                <button id="fb-aplicar"><i class="fa-solid fa-filter"></i> Aplicar</button>
                <button id="fb-limpar"><i class="fa-solid fa-xmark"></i> Limpar</button>
            </div>
        </section>

        <div class="tabela-container">
            <nav>
                <div id="widget-prazos">
                    <h3>Próximos Prazos</h3>
                    <div id="widget-prazos-content">Carregando...</div>
                </div>
                <p style="margin: 15px 0; text-align: right;">Total de pós: <span id="total-pos"></span></p>
            </nav>
            <div id="tabela-imagens"></div>
        </div>
    </div>



    <div id="modal" class="modal">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header">
                <div class="modal-header-left">
                    <i class="fa-solid fa-film modal-header-icon"></i>
                    <div>
                        <h2 class="modal-title" id="modal-title-imagem">Pós-Produção</h2>
                        <span class="modal-subtitle" id="modal-subtitle-obra"></span>
                    </div>
                </div>
                <button class="modal-close" id="closeModalBtn" title="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="modal-body">
                <form id="formPosProducao">
                    <input type="hidden" name="id-pos" id="id-pos">
                    <input type="hidden" id="alterar_imagem" name="alterar_imagem" value="false">
                    <input type="hidden" id="render_id_pos" name="render_id_pos">

                    <!-- Grid 2 colunas: Finalizador + Responsável -->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fa-solid fa-user"></i> Finalizador
                                <span class="save-status" id="ss-finalizador"></span>
                            </label>
                            <select name="final_id" id="opcao_finalizador" class="form-select" required>
                                <option value="0">Selecione um colaborador</option>
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fa-solid fa-user-tie"></i> Responsável
                                <span class="save-status" id="ss-responsavel"></span>
                            </label>
                            <select name="responsavel_id" id="responsavel_id" class="form-select">
                                <option value="14">Adriana</option>
                                <option value="28">Eduardo</option>
                            </select>
                        </div>
                    </div>

                    <!-- Grid 2 colunas: Obra + Revisão -->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fa-solid fa-building"></i> Obra
                                <span class="save-status" id="ss-obra"></span>
                            </label>
                            <select name="obra_id" id="opcao_obra" class="form-select" onchange="buscarImagens()" required>
                                <option value="0">Selecione uma obra</option>
                                <?php foreach ($obras as $obra): ?>
                                    <option value="<?= $obra['idobra']; ?>">
                                        <?= htmlspecialchars($obra['nome_obra']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fa-solid fa-rotate"></i> Revisão
                                <span class="save-status" id="ss-status"></span>
                            </label>
                            <select name="status_id" id="opcao_status" class="form-select">
                                <?php foreach ($status_imagens as $status): ?>
                                    <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                                        <?= htmlspecialchars($status['nome_status']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Imagem (full width) -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fa-solid fa-image"></i> Imagem
                        </label>
                        <select id="imagem_id_pos" name="imagem_id_pos" class="form-select" required>
                            <option value="">Selecione uma imagem</option>
                            <?php foreach ($imagens as $imagem): ?>
                                <option value="<?= $imagem['idimagens_cliente_obra']; ?>">
                                    <?= htmlspecialchars($imagem['imagem_nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Caminho Pasta (full width) com botão de cópia -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fa-solid fa-folder-open"></i> Caminho Pasta
                            <span class="save-status" id="ss-caminho"></span>
                        </label>
                        <div class="input-copy-wrap">
                            <input type="text" id="caminhoPasta" name="caminho_pasta" class="form-input" placeholder="\\servidor\pasta\projeto">
                            <button type="button" class="btn-copy-field" id="btnCopyCaminho" title="Copiar caminho">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Grid 2 colunas: BG + Referências -->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fa-solid fa-hashtag"></i> Número BG
                                <span class="save-status" id="ss-bg"></span>
                            </label>
                            <input type="text" id="numeroBG" name="numero_bg" class="form-input" placeholder="Ex: BG_001">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fa-solid fa-link"></i> Referências
                                <span class="save-status" id="ss-refs"></span>
                            </label>
                            <input type="text" id="referenciasCaminho" name="refs" class="form-input" placeholder="Link ou caminho de refs">
                        </div>
                    </div>

                    <!-- Observação (full width) -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fa-solid fa-comment-dots"></i> Observação
                            <span class="save-status" id="ss-obs"></span>
                        </label>
                        <textarea id="observacao" name="obs" rows="3" class="form-textarea" placeholder="Observações sobre esta pós-produção..."></textarea>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <button type="button" class="btn-modal-danger" id="deleteButton">
                    <i class="fa-solid fa-trash"></i> Excluir
                </button>
                <button type="button" class="btn-modal-finalizar" id="btnFinalizarPos">
                    <i class="fa-solid fa-circle-check"></i> Finalizar pós
                </button>
            </div>

        </div>
    </div>

    <div id="renderModal" class="modal">
        <div class="modal-content">
            <span class="closeModalRender">&times;</span>
            <h2>Render Elements</h2>
            <p>Aqui estão os elementos de render que você deve considerar:</p>
            <ul>
                <li>Alpha</li>
                <li>Máscara RGB para vegetações</li>
                <li>Máscara RGB para vidros</li>
                <li>Máscara RGB para paredes das fachadas</li>
                <li>Máscara RGB para detalhes arquitetônicos</li>
                <li>(Essas máscaras RGB não precisam ser necessariamente cada uma em um element diferente, apenas cores
                    diferentes)</li>
                <li>Wire Color</li>
                <li>Masking ID</li>
                <li>Direct</li>
                <li>Indirect</li>
                <li>Beauty</li>
                <li>Bloom glare</li>
                <li>Environment</li>
                <li>Light Select - Sol ou outras que sejam importantes para a cena</li>
                <li>(Não precisa separar cada luz da cena em um element diferente)</li>
                <li>Raw component</li>
                <li>Component</li>
                <li>Translucency</li>
                <li>Reflect</li>
                <li>Refract</li>
                <li>Texmap</li>
                <li>Albedo</li>
                <li>Zdeph</li>
            </ul>
            <p><strong>Observações sobre luzes:</strong></p>
            <h3>Alguns mandam cada luz da cena num element, como a academia que tinha um element para cada spot, não
                precisa.</h3>
            <p><strong>Observações sobre Zdeph:</strong></p>
            <h3>Sobre o Zdeph, eu não sei como são feitas as configurações, mas alguns vem todo preto e outros já num
                gradiente, o legal é esse gradiente, que dá pra usar pra colocar um fog e separar os planos da imagem.
            </h3>
        </div>
    </div>


    <script src="<?php echo asset_url('scriptPos.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://unpkg.com/tabulator-tables@6.2.0/dist/js/tabulator.min.js"></script>

    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
</body>

</html>