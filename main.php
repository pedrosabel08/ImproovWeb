<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="./css/styleMain.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

    <title>Tabela com imagens</title>
</head>

<button id="voltar" onclick="window.location.href='index.html'">Voltar</button>

<header>

    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        alt="Improov">

</header>

<nav>
    <a href="#add-cliente" onclick="openModal('add-cliente', this)">Adicionar Cliente ou Obra</a>
    <a href="#add-imagem" onclick="openModal('add-imagem', this)">Adicionar imagem</a>
    <a href="#filtro" onclick="openModal(null, this)" class="active">Ver imagens</a>
</nav>
<!-- Adicionar cliente ou obra -->
<?php
// Obter a lista de clientes
// Conectar ao banco de dados
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

// Obter a lista de obras
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

// Fechar a conexão
$conn->close();
?>

<main>

    <main>
        <div id="add-cliente" class="modal">
            <label class="add">Adicionar Cliente ou Obra</label>
            <form id="form-add" onsubmit="submitForm(event)">
                <select name="opcao" id="opcao-cliente">
                    <option value="cliente">Cliente</option>
                    <option value="obra">Obra</option>
                </select>
                <label for="nome">Digite o nome:</label>
                <input type="text" name="nome" id="nome" required>
                <div class="buttons">
                    <button type="submit" id="salvar">Salvar</button>
                    <button type="button" onclick="closeModal('add-cliente', this)" id="fechar">Fechar</button>
                </div>
            </form>
        </div>

        <div id="add-imagem" class="modal">
            <form id="form-add" onsubmit="submitFormImagem(event)">
                <label class="add">Adicionar imagem</label>
                <label for="opcao">Cliente:</label>
                <select name="cliente_id" id="opcao_cliente">
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= htmlspecialchars($cliente['idcliente']); ?>">
                            <?= htmlspecialchars($cliente['nome_cliente']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="opcao">Obra:</label>
                <select name="opcao" id="opcao_obra">
                    <?php foreach ($obras as $obra): ?>
                        <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="arquivos">Recebimento de arquivos: </label>
                <input type="date" name="arquivos" id="arquivos">

                <label for="data_inicio">Data Início: </label>
                <input type="date" name="data_inicio" id="data_inicio">

                <label for="prazo">Prazo: </label>
                <input type="date" name="prazo" id="prazo">

                <label for="nome-imagem">Digite o nome da imagem:</label>
                <input type="text" name="nome" id="nome-imagem">
                <div class="buttons">
                    <button type="submit" id="salvar">Salvar</button>
                    <button type="button" onclick="closeModal('add-cliente', this)" id="fechar">Fechar</button>
                </div>
            </form>
        </div>


        <section class="tabela-form">

            <!-- Tabela com filtros -->
            <div class="filtro-tabela">

                <div id="filtro">
                    <h1>Filtro</h1>
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
                            $conn = new mysqli('192.168.0.202', 'admin', 'admin', 'improov');

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
                                i.recebimento_arquivos,
                                i.data_inicio,
                                i.prazo,
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
                                    echo "<td>" . htmlspecialchars($row["recebimento_arquivos"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["data_inicio"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["prazo_estimado"]) . "</td>";
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
            <div class="form-edicao">
                <form id="form-add" method="post" action="insereFuncao.php">
                    <h1>Funções</h1>
                    <input type="hidden" id="imagem_id" name="imagem_id">
                    <label id="campoNomeImagem" name="nomeImagem" readonly></label>
                    <div class="funcao">
                        <p id="caderno">Caderno</p>
                        <select name="caderno_id" id="opcao_caderno">
                            <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                    <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="status_caderno" id="status_caderno" placeholder="Status">
                        <input type="date" name="prazo_caderno" id="prazo_caderno">
                        <input type="text" name="obs_caderno" id="obs_caderno" placeholder="Observação">
                    </div>
                    <div class="funcao">
                        <p id="modelagem">Modelagem</p>
                        <select name="model_id" id="opcao_model">
                            <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                    <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="status_modelagem" id="status_modelagem" placeholder="Status">
                        <input type="date" name="prazo_modelagem" id="prazo_modelagem">
                        <input type="text" name="obs_modelagem" id="obs_modelagem" placeholder="Observação">

                    </div>
                    <div class="funcao">
                        <p id="comp">Composição</p>
                        <select name="comp_id" id="opcao_comp">
                            <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                    <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="status_comp" id="status_comp" placeholder="Status">
                        <input type="date" name="prazo_comp" id="prazo_comp">
                        <input type="text" name="obs_comp" id="obs_comp" placeholder="Observação">
                    </div>
                    <div class="funcao">
                        <p id="finalizacao">Finalização</p>
                        <select name="final_id" id="opcao_final">
                            <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                    <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="status_finalizacao" id="status_finalizacao" placeholder="Status">
                        <input type="date" name="prazo_finalizacao" id="prazo_finalizacao">
                        <input type="text" name="obs_finalizacao" id="obs_finalizacao" placeholder="Observação">

                    </div>
                    <div class="funcao">
                        <p id="pos">Pós-Produção</p>
                        <select name="pos_id" id="opcao_pos">
                            <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                    <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="status_pos" id="status_pos" placeholder="Status">
                        <input type="date" name="prazo_pos" id="prazo_pos">
                        <input type="text" name="obs_pos" id="obs_pos" placeholder="Observação">
                    </div>
                    <div class="funcao">
                        <p id="planta_humanizada">Planta Humanizada</p>
                        <select name="planta_id" id="opcao_planta">
                            <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                    <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="status_planta_humanizada" id="status_planta_humanizada" placeholder="Status">
                        <input type="date" name="prazo_planta_humanizada" id="prazo_planta_humanizada">
                        <input type="text" name="obs_planta_humanizada" id="obs_planta_humanizada" placeholder="Observação">
                    </div>
                    <div class="buttons">
                        <button type="submit" id="salvar_funcoes">Salvar</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script src="./script/script.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    </body>

</html>