<?php

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
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="../script/scriptEditCliente.js"></script>
</head>

<body>
    <header>
        <h1>Informações de clientes</h1>
    </header>

    <div class="container mt-5">
        <h2>Selecionar Cliente</h2>
        <select name="cliente" id="cliente" class="form-control">
            <option value="">Selecione um cliente:</option>
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="saveChanges">Salvar mudanças</button>
                </div>
            </div>
        </div>
    </div>

</body>

</html>