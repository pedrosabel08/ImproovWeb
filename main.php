<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="./css/styleMain.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">

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
    <a href="#filtro" onclick="openModalClass('tabela-form', this)" class="active">Ver imagens</a>
    <a href="#filtro-colab" onclick="openModal('filtro-colab', this)">Filtro colaboradores</a>
    <a href="#filtro-obra" onclick="openModal('filtro-obra', this)">Filtro por obra</a>
    <a href="#follow-up" onclick="openModal('follow-up', this)">Follow Up</a>
</nav>

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


        <section class="tabela-form" id="tabela-form">

            <!-- Tabela com filtros -->
            <div class="filtro-tabela">

                <div id="filtro">
                    <h1>Filtro</h1>
                    <select id="colunaFiltro">
                        <option value="0">Nome Cliente</option>
                        <option value="1">Nome Obra</option>
                        <option value="2">Nome Imagem</option>
                        <option value="3">Prazo Estimado</option>
                        <option value="4">Status</option>
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
                                <th>Status</th>
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
                                s.nome_status
                                FROM imagens_cliente_obra i
                                JOIN cliente c ON i.cliente_id = c.idcliente
                                JOIN obra o ON i.obra_id = o.idobra
                                LEFT JOIN funcao_imagem fi ON i.idimagens_cliente_obra = fi.imagem_id
                                LEFT JOIN funcao f ON fi.funcao_id = f.idfuncao
                                LEFT JOIN colaborador co ON fi.colaborador_id = co.idcolaborador
                                LEFT JOIN status_imagem s ON i.status_id = s.idstatus
                                GROUP BY i.idimagens_cliente_obra";

                            // Aplicar filtro se necessário
                            if ($filtro) {
                                $colunas = [
                                    'nome_cliente',
                                    'nome_obra',
                                    'imagem_nome',
                                    'prazo_estimado',
                                    'nome_status'
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
                        <p id="alteracao">Alteração</p>
                        <select name="alteracao_id" id="opcao_alteracao">
                            <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                    <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="status_alteracao" id="status_alteracao" placeholder="Status">
                        <input type="date" name="prazo_alterao" id="prazo_alteracao">
                        <input type="text" name="obs_alteracao" id="obs_alteracao" placeholder="Observação">
                    </div>
                    <div class="funcao">
                        <p id="planta">Planta Humanizada</p>
                        <select name="planta_id" id="opcao_planta">
                            <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                    <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="status_planta" id="status_planta" placeholder="Status">
                        <input type="date" name="prazo_planta" id="prazo_planta">
                        <input type="text" name="obs_planta" id="obs_planta" placeholder="Observação">
                    </div>
                    <div class="funcao">
                        <p id="status">Status</p>
                        <select name="status_id" id="opcao_status">
                            <?php foreach ($status_imagens as $status): ?>
                                <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                                    <?= htmlspecialchars($status['nome_status']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="buttons">
                        <button type="submit" id="salvar_funcoes">Salvar</button>
                    </div>
                </form>
            </div>
        </section>

        <div id="filtro-colab" class="modal">
            <h1>Filtro colaboradores</h1>

            <label for="colaboradorSelect">Selecionar Colaborador:</label>
            <select id="colaboradorSelect">
                <option value="1">Selecione:</option>
                <?php foreach ($colaboradores as $colab): ?>
                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="dataInicio">Data Início:</label>
            <input type="date" id="dataInicio">

            <label for="dataFim">Data Fim:</label>
            <input type="date" id="dataFim">

            <div class="image-count">
                <strong>Total de Imagens:</strong> <span id="totalImagens">0</span>
            </div>

            <table id="tabela-colab">
                <thead>
                    <tr>
                        <th id="nome">Nome da Imagem</th>
                        <th>Função</th>
                        <th>Status</th>
                        <th>Prazo</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- O corpo da tabela será preenchido pelo PHP -->
                </tbody>
            </table>
        </div>

        <div id="filtro-obra">
            <h1>Filtro por obra:</h1>

            <label for="obra">Obra:</label>
            <select name="obra" id="obra">
                <option value="1">Selecione:</option>
                <?php foreach ($obras as $obra): ?>
                    <option value="<?= htmlspecialchars($obra['idobra']); ?>">
                        <?= htmlspecialchars($obra['nome_obra']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <table id="tabela-obra">
                <thead>
                    <th>Nome da Imagem</th>
                    <th>Caderno</th>
                    <th>Status</th>
                    <th>Modelagem</th>
                    <th>Status</th>
                    <th>Composição</th>
                    <th>Status</th>
                    <th>Finalização</th>
                    <th>Status</th>
                    <th>Pós-produção</th>
                    <th>Status</th>
                    <th>Alteração</th>
                    <th>Status</th>
                </thead>

                <tbody>

                </tbody>
            </table>
        </div>

        <div id="follow-up">
            <h1>Follow up</h1>
            <label for="obra">Obra:</label>
            <select name="obra-follow" id="obra-follow">
                <option value="1">Selecione:</option>
                <?php foreach ($obras as $obra): ?>
                    <option value="<?= htmlspecialchars($obra['idobra']); ?>">
                        <?= htmlspecialchars($obra['nome_obra']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <table id="tabela-follow">
                <thead>
                    <th>Nome da Imagem</th>
                    <th>Status Imagem</th>
                    <th>Caderno</th>
                    <th>Modelagem</th>
                    <th>Composição</th>
                    <th>Finalização</th>
                    <th>Pós-produção</th>
                    <th>Alteração</th>
                </thead>
                <tbody>

                </tbody>
            </table>
        </div>
    </main>

    <script src="./script/script.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    </body>

</html>