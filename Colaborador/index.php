<?php
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
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
    <!-- Adicionando o CSS do Select2 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

    <title>Colaboradores</title>
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <main>
        <table id="usuarios">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome Usuário</th>
                    <th>Cargos</th>
                    <th>Ativo</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </main>

    <div class="modal" id="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <form id="form">
                <input type="hidden" id="idusuario" name="idusuario">

                <label for="nome">Nome</label>
                <input type="text" id="nome_usuario" name="nome_usuario" readonly>

                <!-- Select com optgroup para organizar por função -->
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
                        // Aqui você precisa garantir que o 'value' é o idcargo
                        echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['nome']) . '</option>';
                    }
                    echo '</optgroup>';
                    ?>
                </select>


                <button type="submit">Salvar</button>
            </form>
        </div>
    </div>

    <!-- Carregando jQuery e plugins (Select2, DataTables) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Adicionando o JS do Select2 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>


    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>
</body>

</html>