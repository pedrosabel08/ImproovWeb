<?php
$conn = new mysqli('192.168.0.202', 'admin', 'admin', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}


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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <title>Lista Pós-Produção</title>
</head>

<body class="bg-gray-100">

    <header class="flex justify-around items-center">
        <h1 class="text-3xl font-bold py-8">Lista Pós-Produção</h1>
        <button class="p-3 border-2 border-black bg-green-500 rounded-full font-bold" onclick="window.location.href='#form-inserir'">Inserir render</button>
    </header>

    <div class="flex justify-center">
        <div class="overflow-x-auto w-full">
            <table class="table-auto border-collapse w-11/12 mx-auto bg-white shadow-md rounded-lg">
                <thead>
                    <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-center">Nome Finalizador</th>
                        <th class="py-3 px-6 text-center">Nome Cliente</th>
                        <th class="py-3 px-6 text-center">Nome Obra</th>
                        <th class="py-3 px-6 text-center">Data</th>
                        <th class="py-3 px-6 text-center">Nome imagem</th>
                        <th class="py-3 px-6 text-center">Caminho Pasta</th>
                        <th class="py-3 px-6 text-center">Número BG</th>
                        <th class="py-3 px-6 text-center">Referências/Caminho</th>
                        <th class="py-3 px-6 text-center">Observação</th>
                        <th class="py-3 px-6 text-center">Status</th>
                        <th class="py-3 px-6 text-center">Revisão</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $conn = new mysqli('192.168.0.202', 'admin', 'admin', 'improov');

                    // Verificar a conexão
                    if ($conn->connect_error) {
                        die("Falha na conexão: " . $conn->connect_error);
                    }

                    $sql = "SELECT 
                    p.idpos_producao,
                    col.nome_colaborador,
                     cli.nome_cliente,
                      o.nome_obra,
                      p.data_pos,
                       i.imagem_nome,
                        p.caminho_pasta,
                         p.numero_bg,
                          p.refs, 
                          p.obs, 
                          p.status_pos, 
                          s.nome_status 
                          from pos_producao p
                    inner join colaborador col on  p.colaborador_id = col.idcolaborador
                    inner join cliente cli on p.cliente_id = cli.idcliente
                    inner join obra o on p.obra_id = o.idobra
                    inner join imagens_cliente_obra i on p.imagem_id = i.idimagens_cliente_obra
                    inner join status_imagem s on p.status_id = s.idstatus";

                    $result = $conn->query($sql);

                    // Verificar se houve erro na execução da consulta
                    if (!$result) {
                        die("Erro na consulta SQL: " . $conn->error);
                    }

                    if ($result->num_rows > 0) {
                        // Exibir os dados em linhas na tabela
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr class='linha-tabela' data-id='" . htmlspecialchars($row["idpos_producao"]) . "'>";
                            echo "<td>" . htmlspecialchars($row["nome_colaborador"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["nome_cliente"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["nome_obra"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["data_pos"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["imagem_nome"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["caminho_pasta"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["numero_bg"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["refs"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["obs"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["status_pos"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["nome_status"]) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='13'>Nenhum dado encontrado</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="w-[90%] bg-white p-6 rounded-lg shadow-md mx-auto hidden">
        <h2 class="text-2xl font-bold mb-6">Formulário de Dados</h2>
        <form action="#" method="POST">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="nomeFinalizador" class="block text-sm font-medium text-gray-700">Nome Finalizador</label>
                    <select name="final_id" id="opcao_finalizador">
                        <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                <?= htmlspecialchars($colab['nome_colaborador']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="nomeCliente" class="block text-sm font-medium text-gray-700">Nome Cliente</label>
                    <select name="cliente_id" id="opcao_cliente">
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= htmlspecialchars($cliente['idcliente']); ?>">
                                <?= htmlspecialchars($cliente['nome_cliente']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="nomeObra" class="block text-sm font-medium text-gray-700">Nome Obra</label>
                    <select name="opcao" id="opcao_obra">
                        <?php foreach ($obras as $obra): ?>
                            <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="data" class="block text-sm font-medium text-gray-700">Data</label>
                    <input type="date" id="data" name="data" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50" required>
                </div>
                <div>
                    <label for="nomeImagem" class="block text-sm font-medium text-gray-700">Nome Imagem</label>
                    <input type="text" id="nomeImagem" name="nomeImagem" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50" required>
                </div>
                <div>
                    <label for="caminhoPasta" class="block text-sm font-medium text-gray-700">Caminho Pasta</label>
                    <input type="text" id="caminhoPasta" name="caminhoPasta" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50" required>
                </div>
                <div>
                    <label for="numeroBG" class="block text-sm font-medium text-gray-700">Número BG</label>
                    <input type="text" id="numeroBG" name="numeroBG" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50" required>
                </div>
                <div>
                    <label for="referenciasCaminho" class="block text-sm font-medium text-gray-700">Referências/Caminho</label>
                    <input type="text" id="referenciasCaminho" name="referenciasCaminho" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50" required>
                </div>
                <div>
                    <label for="observacao" class="block text-sm font-medium text-gray-700">Observação</label>
                    <textarea id="observacao" name="observacao" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50" required></textarea>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status_id" id="opcao_status">
                        <?php foreach ($status_imagens as $status): ?>
                            <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                                <?= htmlspecialchars($status['nome_status']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="revisao" class="block text-sm font-medium text-gray-700">Revisão</label>
                    <input type="text" id="revisao" name="revisao" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50" required>
                </div>
            </div>
            <div class="mt-6">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-semibold rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-50">Enviar</button>
            </div>
        </form>
    </div>
</body>

</html>