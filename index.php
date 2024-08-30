<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="./css/style.css" rel="stylesheet">

    <title>Document</title>
</head>

<body>

    <header>
        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" alt="Improov">
    </header>

    <div class="filtro">
        <p>Filtro</p>
        <select id="colunaFiltro">
            <option value="0">Nome Cliente</option>
            <option value="1">Nome Obra</option>
            <option value="2">Nome Imagem</option>
            <option value="3">Status</option>
            <option value="6">Prazo Estimado</option>
            <option value="7">Caderno</option>
            <option value="8">Modelagem</option>
            <option value="9">Composição</option>
            <option value="10">Finalização</option>
            <option value="11">Pós-produção</option>
            <option value="12">Planta Humanizada</option>
        </select>
        <input type="text" id="pesquisa" onkeyup="filtrarTabela()" placeholder="Buscar...">
    </div>

    <div class="tabelaClientes">
        <table id="tabelaClientes">
            <thead>
                <tr>
                    <th>Nome Cliente</th>
                    <th>Nome Obra</th>
                    <th>Nome Imagem</th>
                    <th>Recebimento de arquivos</th>
                    <th>Data Inicio</th>
                    <th>Prazo Estimado</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Conectar ao banco de dados
                $conn = new mysqli('localhost', 'root', 'improov', 'improov');

                // Verificar a conexão
                if ($conn->connect_error) {
                    die("Falha na conexão: " . $conn->connect_error);
                }

                // Obter o valor do filtro de pesquisa
                $filtro = isset($_GET['filtro']) ? $conn->real_escape_string($_GET['filtro']) : '';
                $colunaFiltro = isset($_GET['colunaFiltro']) ? intval($_GET['colunaFiltro']) : 0;

                // Consulta para buscar os dados com filtro
                $sql = "SELECT 
                                i.idimagens_cliente_obra,
                                c.nome_cliente, 
o.nome_obra, 
i.imagem_nome, 
i.prazo AS prazo_estimado,
GROUP_CONCAT(f.nome_funcao ORDER BY f.nome_funcao SEPARATOR ', ') AS funcoes,
GROUP_CONCAT(co.nome_colaborador ORDER BY f.nome_funcao SEPARATOR ', ') AS colaboradores
FROM imagens_cliente_obra i
JOIN cliente c ON i.cliente_id = c.idcliente
JOIN obra o ON i.obra_id = o.idobra
LEFT JOIN funcao_imagem fi ON i.idimagens_cliente_obra = fi.imagem_id
LEFT JOIN funcao f ON fi.funcao_id = f.idfuncao
LEFT JOIN colaborador co ON fi.colaborador_id = co.idcolaborador
GROUP BY i.idimagens_cliente_obra";

                // Aplicar filtro se necessário
                if ($filtro) {
                    $colunas = [
                        'nome_cliente',
                        'nome_obra',
                        'imagem_nome',
                        'status',
                        'prazo_estimado',
                        'caderno',
                        'modelagem',
                        'composicao',
                        'finalizacao',
                        'pos_producao',
                        'planta_humanizada'
                    ];
                    $coluna = $colunas[$colunaFiltro];
                    $sql .= " HAVING LOWER($coluna) LIKE LOWER('%$filtro%')";
                }

                $result = $conn->query($sql);

                // Verificar se houve erro na execução da consulta
                if (!$result) {
                    die("Erro na consulta SQL: " . $conn->error);
                }

                if ($result->num_rows > 0) {
                    // Exibir os dados em linhas na tabela
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr class='linha-tabela' data-id='" . htmlspecialchars($row["idimagens_cliente_obra"]) . "'>";
                        echo "<td>" . htmlspecialchars($row["nome_cliente"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["nome_obra"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["imagem_nome"]) . "</td>";
                        echo "<td></td>";  // Coluna para Recebimento de arquivos
                        echo "<td></td>";  // Coluna para Data Inicio
                        echo "<td>" . htmlspecialchars($row["prazo_estimado"]) . "</td>";

                        // Separar as funções e colaboradores correspondentes
                        $funcoes = explode(', ', $row["funcoes"]);
                        $colaboradores = explode(', ', $row["colaboradores"]);
                        $funcoes_colaboradores = array_combine($funcoes, $colaboradores);

                        $colunas = [
                            'Caderno',
                            'Modelagem',
                            'Composição',
                            'Finalização',
                            'Pós-produção',
                            'Planta Humanizada'
                        ];

                        foreach ($colunas as $coluna) {
                            echo "<td>" . (array_key_exists($coluna, $funcoes_colaboradores) ? $funcoes_colaboradores[$coluna] : 'Não atribuído') . "</td>";
                        }

                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='13'>Nenhum dado encontrado</td></tr>";
                }

                // Obter a lista de clientes
                $sql_clientes = "SELECT idcliente, nome_cliente FROM cliente";
                $result_cliente = $conn->query($sql_clientes);

                $clientes = array();
                if ($result_cliente->num_rows > 0) {
                    while ($row = $result_cliente->fetch_assoc()) {
                        $clientes[] = $row;
                    }
                }

                // Obter a lista de obras
                $sql_obras = "SELECT idobra, nome_obra FROM obra";
                $result_obra = $conn->query($sql_obras);

                $obras = array();
                if ($result_obra->num_rows > 0) {
                    while ($row = $result_obra->fetch_assoc()) {
                        $obras[] = $row;
                    }
                }


                $sql_colaboradores = "SELECT idcolaborador, nome_colaborador FROM colaborador";
                $result_colaboradores = $conn->query($sql_colaboradores);

                $colaboradores = array();
                if ($result_colaboradores->num_rows > 0) {
                    while ($row = $result_colaboradores->fetch_assoc()) {
                        $colaboradores[] = $row;
                    }
                }

                // Fechar a conexão
                $conn->close();
                ?>
            </tbody>
        </table>
    </div>

    <div class="form">
        <label for="nome_cliente">Nome Cliente</label>
        <div class="cliente">
            <input type="text" name="nome_cliente" id="nome_cliente">
            <select id="nome_cliente">
                <?php foreach ($clientes as $cliente): ?>
                    <option value="<?php echo $cliente['idcliente']; ?>">
                        <?php echo $cliente['nome_cliente']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <label for="nome_obra">Nome Obra</label>

        <div class="obra">
            <input type="text" name="nome_obra" id="nome_obra">
            <select id="nome_obra">
                <?php foreach ($obras as $obra): ?>
                    <option value="<?php echo $obra['idobra']; ?>">
                        <?php echo $obra['nome_obra']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <label for="nome_imagem">Nome Imagem</label>
        <input type="text" name="nome_imagem" id="nome_imagem">
        <label for="arquivos">Recebimento de arquivos</label>
        <input type="date" name="arquivos" id="arquivos">
        <label for="data_inicio">Data inicio</label>
        <input type="date" name="data_inicio" id="data_inicio">
        <label for="prazo">Prazo Estimado</label>
        <input type="date" name="prazo" id="prazo">
        <label for="caderno">Caderno</label>
        <select name="caderno" id="caderno">
            <?php foreach ($colaboradores as $colab): ?>
                <option value="<?php echo $colab['idcolaborador']; ?>">
                    <?php echo $colab['nome_colaborador']; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="modelagem">Modelagem</label>
        <select name="modelagem" id="modelagem">
            <?php foreach ($colaboradores as $colab): ?>
                <option value="<?php echo $colab['idcolaborador']; ?>">
                    <?php echo $colab['nome_colaborador']; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="composicao">Composição</label>
        <select name="composicao" id="composicao">
            <?php foreach ($colaboradores as $colab): ?>
                <option value="<?php echo $colab['idcolaborador']; ?>">
                    <?php echo $colab['nome_colaborador']; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="finalizacao">Finalização</label>
        <select name="finalizacao" id="finalizacao">
            <?php foreach ($colaboradores as $colab): ?>
                <option value="<?php echo $colab['idcolaborador']; ?>">
                    <?php echo $colab['nome_colaborador']; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="pos_producao">Pós-produção</label>
        <select name="pos_producao" id="pos_producao">
            <?php foreach ($colaboradores as $colab): ?>
                <option value="<?php echo $colab['idcolaborador']; ?>">
                    <?php echo $colab['nome_colaborador']; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="planta_humanizada">Planta Humanizada</label>
        <select name="planta_humanizada" id="planta_humanizada">
            <?php foreach ($colaboradores as $colab): ?>
                <option value="<?php echo $colab['idcolaborador']; ?>">
                    <?php echo $colab['nome_colaborador']; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <script src="script.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</body>

</html>