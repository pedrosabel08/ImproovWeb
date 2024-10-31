<?php
session_start();


include 'conexao.php';

$sql_clientes = "SELECT idcliente, nome_cliente FROM cliente";
$result_cliente = $conn->query($sql_clientes);

$clientes = array();
if ($result_cliente->num_rows > 0) {
    while ($row = $result_cliente->fetch_assoc()) {
        $clientes[] = $row;
    }
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/styleEditClientes.css">
    <title>Document</title>

    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css">

</head>

<body>
    <div class="header">

        <button id="menuButton">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div id="menu" class="hidden">
            <a href="../inicio.php" id="tab-imagens">Página Principal</a>
            <a href="../main.php" id="tab-imagens">Visualizar tabela com imagens</a>

            <a href="../Pos-Producao/index.php">Lista Pós-Produção</a>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
                <a href="../Acompanhamento/index.html">Acompanhamentos</a>
            <?php endif; ?>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
                <a href="../Animacao/index.php">Lista Animação</a>
            <?php endif; ?>

            <a href="../Metas/index.php">Metas e progresso</a>

            <a id="calendar" class="calendar-btn" href="../Calendario/index.php">
                <i class="fa-solid fa-calendar-days"></i>
            </a>
        </div>
        <div>
            <img src="../assets/ImproovFlow - logo.png" alt="Improov" class="logo">
        </div>

    </div>

    <div class="container mt-5">
        <h2>Selecionar Cliente</h2>
        <select name="cliente" id="cliente" class="form-control" onchange="buscarCliente()">
            <option value="0">Selecione um cliente:</option>
            <?php foreach ($clientes as $cliente): ?>
                <option value="<?= htmlspecialchars($cliente['idcliente']); ?>">
                    <?= htmlspecialchars($cliente['nome_cliente']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="infoClienteModal" tabindex="-1" role="dialog" aria-labelledby="infoClienteModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="infoClienteModalLabel">Informações do Cliente</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="modalBody">
                    <form id="formCliente">
                        <div class="form-group">
                            <label for="nomeCliente">Nome do Cliente</label>
                            <input type="text" class="form-control" id="nomeCliente" name="nome_cliente" required>
                        </div>
                        <div id="contatosContainer2">

                        </div>
                        <div id="contatosContainer" class="hidden">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="form-group">
                                <label for="nomeContato">Nome do Contato</label>
                                <input type="text" class="form-control" id="nome_contato" name="nome_contato">
                            </div>
                            <div class="form-group">
                                <label for="cargo">Cargo</label>
                                <input type="text" class="form-control" id="cargo" name="cargo">
                            </div>
                        </div>
                        <input type="text" name="idcliente" id="idcliente" hidden>
                        <input type="text" name="idcontato" id="idcontato" hidden>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" id="addContatoButton">Adicionar Contato</button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                            <button type="submit" class="btn" id="btnSalvar">Salvar mudanças</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="../script/scriptEditCliente.js"></script>

</body>

</html>