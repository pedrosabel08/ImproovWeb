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
        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
            alt="Improov">
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
                    <th>Status</th>
                    <th>Recebimento de arquivos</th>
                    <th>Data Inicio</th>
                    <th>Prazo Estimado</th>
                    <th>Caderno</th>
                    <th>Modelagem</th>
                    <th>Composição</th>
                    <th>Finalização</th>
                    <th>Pós-produção</th>
                    <th>Planta Humanizada</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Conectar ao banco de dados
                $conn = new mysqli('localhost', 'root', '', 'improov');

                // Verificar a conexão
                if ($conn->connect_error) {
                    die("Falha na conexão: " . $conn->connect_error);
                }

                // Obter o valor do filtro de pesquisa
                $filtro = isset($_GET['filtro']) ? $conn->real_escape_string($_GET['filtro']) : '';
                $colunaFiltro = isset($_GET['colunaFiltro']) ? intval($_GET['colunaFiltro']) : 0;

                // Consulta para buscar os dados com filtro
                $sql = "SELECT 
                        c.nome_cliente, 
                        o.nome_obra, 
                        i.imagem_nome, 
                        i.status, 
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

                if ($result->num_rows > 0) {
                    // Exibir os dados em linhas na tabela
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row["nome_cliente"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["nome_obra"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["imagem_nome"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["status"]) . "</td>";
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

                $conn->close();
                ?>
            </tbody>
        </table>
    </div>

    <script>
        // Função para filtrar a tabela
        function filtrarTabela() {
            var indiceColuna = document.getElementById("colunaFiltro").value;
            var filtro = document.getElementById("pesquisa").value;

            // Atualizar a URL com os parâmetros de filtro
            var url = new URL(window.location.href);
            url.searchParams.set('colunaFiltro', indiceColuna);
            url.searchParams.set('filtro', filtro);
            window.location.href = url.toString();
        }

        // Adiciona um event listener para o campo de pesquisa para filtrar ao pressionar Enter
        document.getElementById("pesquisa").addEventListener("keyup", function (event) {
            if (event.key === "Enter") {
                filtrarTabela();
            }
        });
    </script>

</body>

</html>