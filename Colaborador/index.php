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
$nome_usuario = $_SESSION['nome_usuario'];

include '../conexaoMain.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

$conn = conectarBanco();

// Consulta para obter os cargos organizados por função
$sql = "SELECT * FROM cargo ORDER BY funcao, nome";
$result = $conn->query($sql);

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colaboradores</title>
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <!-- Font Awesome 6.6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
    <!-- Toastify -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" />
    <!-- Select2 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <!-- Projeto -->
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div class="page-header-left">
                <img src="../gif/assinatura_preto.gif" id="gif" style="height:36px; opacity:0.85" alt="ImproovWeb" />
                <h1 class="page-title">Colaboradores</h1>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <span class="results-badge" id="resultsBadge">
                    <i class="fa-solid fa-users"></i>
                    <span id="resultsCount">0</span> colaboradores
                </span>
                <button class="btn-primary" id="btnAdicionar">
                    <i class="fa-solid fa-plus"></i> Adicionar
                </button>
            </div>
        </div>

        <div class="table-scroll-area">
            <div class="table-section">
                <div class="table-wrap">
                    <table id="usuarios" class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Colaborador</th>
                                <th>Nome Usuário</th>
                                <th>Login</th>
                                <th>Nível</th>
                                <th>Cargos</th>
                                <th class="col-center">Ativo</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Novo colaborador</h2>
                <button class="modal-close" id="btnFecharModal" type="button">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form id="form">
                <div class="modal-body">
                    <input type="hidden" id="idusuario" name="idusuario">
                    <input type="hidden" id="idcolaborador" name="idcolaborador">
                    <input type="hidden" id="action" name="action" value="create">

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="nome_colaborador">Colaborador</label>
                            <input type="text" class="form-input" id="nome_colaborador" name="nome_colaborador" required placeholder="Nome completo">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="nome_usuario">Nome usuário</label>
                            <input type="text" class="form-input" id="nome_usuario" name="nome_usuario" required placeholder="Nome exibido">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="login">Login</label>
                            <input type="text" class="form-input" id="login" name="login" required placeholder="login@email.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="senha">Senha</label>
                            <input type="password" class="form-input" id="senha" name="senha" placeholder="Deixe em branco para manter">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="nivel_acesso">Nível de acesso</label>
                            <input type="number" class="form-input" id="nivel_acesso" name="nivel_acesso" min="0" step="1" placeholder="0">
                        </div>
                        <div class="form-group full">
                            <label class="form-label" for="cargoSelect">Cargos</label>
                            <select id="cargoSelect" name="cargos[]" multiple="multiple" style="width: 100%;">
                                <?php
                                $currentFuncao = '';
                                while ($row = $result->fetch_assoc()) {
                                    if ($row['funcao'] != $currentFuncao) {
                                        if ($currentFuncao != '') {
                                            echo '</optgroup>';
                                        }
                                        echo '<optgroup label="' . htmlspecialchars($row['funcao']) . '">';
                                        $currentFuncao = $row['funcao'];
                                    }
                                    echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['nome']) . '</option>';
                                }
                                echo '</optgroup>';
                                ?>
                            </select>
                        </div>
                    </div>
                </div><!-- /.modal-body -->

                <div class="modal-footer">
                    <button type="button" class="btn-toggle-status" id="btnToggleStatus">
                        <i class="fa-solid fa-power-off"></i>
                        <span id="toggleStatusText">Desativar</span>
                    </button>
                    <div class="modal-footer-right">
                        <button type="button" class="btn-action btn-secundario" id="btnCancelar">Cancelar</button>
                        <button type="button" class="btn-action btn-danger" id="btnExcluir" style="display:none;">
                            <i class="fa-solid fa-trash"></i> Excluir
                        </button>
                        <button type="submit" class="btn-action btn-primario" id="btnSalvar">
                            <i class="fa-solid fa-floppy-disk"></i> Salvar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="context-menu" id="statusMenu" aria-hidden="true">
        <button type="button" id="toggleStatusBtn"></button>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
</body>

</html>