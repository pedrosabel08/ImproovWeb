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

session_start();
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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
    <!-- Adicionando o CSS do Select2 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

    <title>Colaboradores</title>
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <main>
        <section class="page-header">
            <div>
                <h1>Colaboradores</h1>
                <p>Gerencie colaboradores e usuários com cargos e níveis de acesso.</p>
            </div>
            <button class="btn-primary" id="btnAdicionar">Adicionar colaborador</button>
        </section>

        <section class="card">
            <table id="usuarios">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Colaborador</th>
                        <th>Nome Usuário</th>
                        <th>Login</th>
                        <th>Nível</th>
                        <th>Cargos</th>
                        <th>Ativo</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </section>
    </main>

    <div class="modal" id="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Novo colaborador</h2>
                <span class="close">&times;</span>
            </div>
            <form id="form">
                <input type="hidden" id="idusuario" name="idusuario">
                <input type="hidden" id="idcolaborador" name="idcolaborador">
                <input type="hidden" id="action" name="action" value="create">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="nome_colaborador">Colaborador</label>
                        <input type="text" id="nome_colaborador" name="nome_colaborador" required>
                    </div>
                    <div class="form-group">
                        <label for="nome_usuario">Nome usuário</label>
                        <input type="text" id="nome_usuario" name="nome_usuario" required>
                    </div>
                    <div class="form-group">
                        <label for="login">Login</label>
                        <input type="text" id="login" name="login" required>
                    </div>
                    <div class="form-group">
                        <label for="senha">Senha</label>
                        <input type="password" id="senha" name="senha" placeholder="Informe a senha">
                    </div>
                    <div class="form-group">
                        <label for="nivel_acesso">Nível de acesso</label>
                        <input type="number" id="nivel_acesso" name="nivel_acesso" min="0" step="1">
                    </div>
                    <div class="form-group full">
                        <label for="cargoSelect">Cargos</label>
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

                <div class="form-actions">
                    <button type="button" class="btn-secondary" id="btnCancelar">Cancelar</button>
                    <button type="button" class="btn-danger" id="btnExcluir">Excluir</button>
                    <button type="submit" class="btn-primary" id="btnSalvar">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="context-menu" id="statusMenu" aria-hidden="true">
        <button type="button" id="toggleStatusBtn"></button>
    </div>

    <!-- Carregando jQuery e plugins (Select2, DataTables) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Adicionando o JS do Select2 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>


    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
</body>

</html>