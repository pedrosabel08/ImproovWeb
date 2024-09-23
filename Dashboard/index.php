<?php
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

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

$sql_status = "SELECT idstatus, nome_status FROM status_imagem order by idstatus";
$result_status = $conn->query($sql_status);

$status_imagens = array();
if ($result_status->num_rows > 0) {
    while ($row = $result_status->fetch_assoc()) {
        $status_imagens[] = $row;
    }
}

$sql_ano = "SELECT idano, ano FROM ano order by idano";
$result_ano = $conn->query($sql_ano);

$anos = array();
if ($result_ano->num_rows > 0) {
    while ($row = $result_ano->fetch_assoc()) {
        $anos[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/styleDashboard.css">
    <title>Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

    <div class="sidebar">
        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" alt="">

        <label for="ano">Ano:</label>
        <select name="ano" id="ano">
            <option value="0">Selecione:</option>
            <?php foreach ($anos as $ano): ?>
                <option value="<?= $ano['idano']; ?>"><?= htmlspecialchars($ano['ano']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="cliente">Cliente:</label>
        <select name="cliente" id="cliente">
            <option value="0">Selecione:</option>
            <?php foreach ($clientes as $cliente): ?>
                <option value="<?= htmlspecialchars($cliente['idcliente']); ?>">
                    <?= htmlspecialchars($cliente['nome_cliente']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="obra">Obra:</label>
        <select name="obra" id="obra">
            <option value="0">Selecione:</option>
            <?php foreach ($obras as $obra): ?>
                <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="colaborador">Colaborador:</label>
        <select name="colaborador" id="colaborador">
            <option value="0">Selecione:</option>
            <?php foreach ($colaboradores as $colab): ?>
                <option value="<?= $colab['idcolaborador']; ?>"><?= htmlspecialchars($colab['nome_colaborador']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <main>
        <div class="header">
            <button class="nav-toggle" aria-label="Toggle navigation" onclick="toggleNav()">
                &#9776;
            </button>
            <nav class="nav-menu">
                <button onclick="openModal('tabela-colab', this)">Colaboradores</button>
            </nav>
            <h1>Dashboard</h1>
        </div>
        <button id="atualizar-pagamento">Atualizar Pagamento</button>

        <div class="container">
            <table id="tabela-faturamento">
                <thead>
                    <tr>
                        <th>Nome imagem</th>
                        <th>Status</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </main>

    <script src="../script/scriptDashboard.js"></script>

</body>

</html>