<?php
require_once __DIR__ . '/../config/version.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../conexao.php';
include '../conexaoMain.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../index.html");
    exit();
}

if (!isset($_SESSION['nivel_acesso']) || (int)$_SESSION['nivel_acesso'] !== 1) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit();
}

$conn = conectarBanco();

// colaboradores ativos
$sql = "SELECT c.idcolaborador, c.nome_colaborador, u.email
        FROM colaborador c
        LEFT JOIN usuario u ON u.idcolaborador = c.idcolaborador
        WHERE c.ativo = 1
        ORDER BY c.nome_colaborador";
$result = $conn->query($sql);
$colaboradores = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $colaboradores[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('./style.css'); ?>">
    <title>Contratos</title>
</head>

<body>
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <main class="contratos-main">
        <h1>Contratos</h1>
        <div class="contratos-card">
            <table id="contratos-table">
                <thead>
                    <tr>
                        <th>Colaborador</th>
                        <th>Competência</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($colaboradores as $colab): ?>
                        <tr data-colaborador-id="<?= (int)$colab['idcolaborador']; ?>">
                            <td><?= htmlspecialchars($colab['nome_colaborador']); ?></td>
                            <td class="competencia">-</td>
                            <td class="status">nao_gerado</td>
                            <td class="acoes">
                                <button class="btn-primario gerar">Gerar contrato</button>
                                <button class="btn-secundario reenviar">Reenviar</button>
                                <button class="btn-terciario status">Visualizar status</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <script src="<?php echo asset_url('./contratos.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
</body>

</html>