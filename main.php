<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: index.html");
    exit();
}

$idusuario = $_SESSION['idusuario'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="./css/styleMain.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

    <title>Improov+Flow</title>
</head>


<header>
    <button id="menuButton">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div id="menu" class="hidden">
        <a href="inicio.php" id="tab-imagens">Página Principal</a>
        <a href="main.php" id="tab-imagens">Visualizar tabela com imagens</a>
        <a href="Pos-Producao/index.php">Lista Pós-Produção</a>

        <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
            <a href="infoCliente/index.php">Informações clientes</a>
            <a href="Acompanhamento/index.html">Acompanhamentos</a>
        <?php endif; ?>

        <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
            <a href="Animacao/index.php">Lista Animação</a>
        <?php endif; ?>
        <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
            <a href="Imagens/index.php">Lista Imagens</a>
        <?php endif; ?>

        <a href="Metas/index.php">Metas e progresso</a>

        <a id="calendar" class="calendar-btn" href="Calendario/index.php">
            <i class="fa-solid fa-calendar-days"></i>
        </a>
    </div>

    <img src="assets/ImproovFlow - logo.png" alt="Logo Improov + Flow">

</header>


<button class="nav-toggle" aria-label="Toggle navigation" onclick="toggleNav()">
    &#9776;
</button>

<nav class="nav-menu">
    <?php if ($_SESSION['nivel_acesso'] == 1): ?>
        <a href="#add-cliente" onclick="openModal('add-cliente', this)">Adicionar Cliente ou Obra</a>
        <a href="#add-imagem" onclick="openModal('add-imagem', this)">Adicionar Imagem</a>
    <?php endif; ?>
    <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
        <a href="#add-acomp" onclick="openModal('add-acomp', this)">Adicionar Acompanhamento</a>
    <?php endif; ?>
    <a href="#filtro" onclick="openModalClass('filtro-tabela', this)" class="active">Ver Imagens</a>
    <a href="#filtro-colab" onclick="openModal('filtro-colab', this)">Filtro Colaboradores</a>
    <a href="#filtro-obra" onclick="openModal('filtro-obra', this)">Filtro por Obra</a>
    <a href="#follow-up" onclick="openModal('follow-up', this)">Follow Up</a>
</nav>

<?php
include 'conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

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

                <label for="nome-imagem">Nome da imagem:</label>
                <input type="text" name="nome" id="nome-imagem">

                <label for="tipo-imagem">Tipo da imagem:</label>
                <input type="text" name="tipo" id="tipo-imagem">
                <div class="buttons">
                    <button type="submit" id="salvar">Salvar</button>
                    <button type="button" onclick="closeModal('add-cliente', this)" id="fechar">Fechar</button>
                </div>
            </form>
        </div>


        <!-- Tabela com filtros -->
        <div class="filtro-tabela" id="filtro-tabela">

            <div id="filtro">
                <h1>Filtro</h1>
                <select id="colunaFiltro">
                    <option value="0">Cliente</option>
                    <option value="1">Obra</option>
                    <option value="2">Imagem</option>
                    <option value="4">Status</option>
                </select>
                <input type="text" id="pesquisa" onkeyup="filtrarTabela()" placeholder="Buscar...">

                <select id="tipoImagemFiltro" onchange="filtrarTabela()">
                    <option value="">Todos os Tipos de Imagem</option>
                    <option value="Fachada">Fachada</option>
                    <option value="Imagem Interna">Imagem Interna</option>
                    <option value="Imagem Externa">Imagem Externa</option>
                    <option value="Planta Humanizada">Planta Humanizada</option>
                </select>
            </div>

            <div class="tabelaClientes">
                <div class="image-count">
                    <strong>Total de Imagens:</strong> <span id="total-imagens">0</span>
                </div>
                <table id="tabelaClientes">
                    <thead>
                        <tr>
                            <th id="cliente">Cliente</th>
                            <th id="obra">Obra</th>
                            <th id="nome-imagem">Imagem</th>
                            <th id="status">Status</th>
                            <th>Tipo Imagem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

                        if ($conn->connect_error) {
                            die("Falha na conexão: " . $conn->connect_error);
                        }
                        $conn->set_charset('utf8mb4');

                        $filtro = isset($_GET['filtro']) ? $conn->real_escape_string($_GET['filtro']) : '';
                        $colunaFiltro = isset($_GET['colunaFiltro']) ? intval($_GET['colunaFiltro']) : 0;
                        $tipoImagemFiltro = isset($_GET['tipoImagemFiltro']) ? $conn->real_escape_string($_GET['tipoImagemFiltro']) : '';

                        $sql = "SELECT i.idimagens_cliente_obra, c.nome_cliente, o.nome_obra, i.recebimento_arquivos, i.data_inicio, i.prazo, MAX(i.imagem_nome) AS imagem_nome, i.prazo AS prazo_estimado, s.nome_status, i.tipo_imagem FROM imagens_cliente_obra i 
                            JOIN cliente c ON i.cliente_id = c.idcliente 
                            JOIN obra o ON i.obra_id = o.idobra 
                            LEFT JOIN funcao_imagem fi ON i.idimagens_cliente_obra = fi.imagem_id 
                            LEFT JOIN funcao f ON fi.funcao_id = f.idfuncao 
                            LEFT JOIN colaborador co ON fi.colaborador_id = co.idcolaborador 
                            LEFT JOIN status_imagem s ON i.status_id = s.idstatus 
                            GROUP BY i.idimagens_cliente_obra";

                        if ($filtro) {
                            $colunas = [
                                'nome_cliente',
                                'nome_obra',
                                'imagem_nome',
                                'nome_status'
                            ];
                            $coluna = $colunas[$colunaFiltro];
                            $sql .= " HAVING LOWER($coluna) LIKE LOWER('%$filtro%')";
                        }

                        if ($tipoImagemFiltro) {
                            $sql .= " HAVING LOWER(tipo_imagem) = LOWER('$tipoImagemFiltro')";
                        }

                        $result = $conn->query($sql);

                        if (!$result) {
                            die("Erro na consulta SQL: " . $conn->error);
                        }

                        if ($result->num_rows > 0) {

                            while ($row = $result->fetch_assoc()) {
                                echo "<tr class='linha-tabela' data-id='" . htmlspecialchars($row["idimagens_cliente_obra"]) . "'>";
                                echo "<td title='" . htmlspecialchars($row["nome_cliente"]) . "'>" . htmlspecialchars($row["nome_cliente"]) . "</td>";
                                echo "<td title='" . htmlspecialchars($row["nome_obra"]) . "'>" . htmlspecialchars($row["nome_obra"]) . "</td>";
                                echo "<td title='" . htmlspecialchars($row["imagem_nome"]) . "'>" . htmlspecialchars($row["imagem_nome"]) . "</td>";
                                echo "<td title='" . htmlspecialchars($row["nome_status"]) . "'>" . htmlspecialchars($row["nome_status"]) . "</td>";
                                echo "<td title='" . htmlspecialchars($row["tipo_imagem"]) . "'>" . htmlspecialchars($row["tipo_imagem"]) . "</td>";
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
        <div class="form-edicao" id="form-edicao">
            <form id="form-add" method="post" action="insereFuncao.php">
                <div class="titulo-funcoes">
                    <span id="campoNomeImagem"></span>
                </div> <input type="hidden" id="imagem_id" name="imagem_id">
                <div class="funcao">
                    <div class="titulo">
                        <p id="caderno">Caderno</p>
                        <i class="fas fa-chevron-down toggle-options"></i>
                    </div>
                    <div class="opcoes" style="display: none;">
                        <?php if ($_SESSION['nivel_acesso'] == 1): ?>
                            <select name="caderno_id" id="opcao_caderno">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <select name="caderno_id" id="opcao_caderno" disabled>
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <select name="status_caderno" id="status_caderno">
                            <option value="Não iniciado">Não iniciado</option>
                            <option value="Em andamento">Em andamento</option>
                            <option value="Finalizado">Finalizado</option>
                            <option value="HOLD">HOLD</option>
                            <option value="Não se aplica">Não se aplica</option>
                            <option value="Em aprovação">Em aprovação</option>
                        </select>
                        <input type="date" name="prazo_caderno" id="prazo_caderno">
                        <input type="text" name="obs_caderno" id="obs_caderno" placeholder="Observação">
                    </div>
                </div>
                <div class="funcao">
                    <div class="titulo">
                        <p id="filtro">Filtro de assets</p>
                        <i class="fas fa-chevron-down" id="toggle-options"></i>
                    </div>
                    <div class="opcoes" id="opcoes" style="display: none;">
                        <?php if ($_SESSION['nivel_acesso'] == 1): ?>
                            <select name="filtro_id" id="opcao_filtro">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <select name="filtro_id" id="opcao_filtro" disabled>
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <select name="status_filtro" id="status_filtro">
                            <option value="Não iniciado">Não iniciado</option>
                            <option value="Em andamento">Em andamento</option>
                            <option value="Finalizado">Finalizado</option>
                            <option value="HOLD">HOLD</option>
                            <option value="Não se aplica">Não se aplica</option>
                            <option value="Em aprovação">Em aprovação</option>
                        </select>
                        <input type="date" name="prazo_filtro" id="prazo_filtro">
                        <input type="text" name="obs_filtro" id="obs_filtro" placeholder="Observação">
                    </div>
                </div>
                <div class="funcao">
                    <div class="titulo">
                        <p id="modelagem">Modelagem</p>
                        <i class="fas fa-chevron-down" id="toggle-options"></i>
                    </div>
                    <div class="opcoes" style="display: none;">
                        <?php if ($_SESSION['nivel_acesso'] == 1): ?>
                            <select name="model_id" id="opcao_model">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <select name="model_id" id="opcao_model" disabled>
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <select name="status_modelagem" id="status_modelagem">
                            <option value="Não iniciado">Não iniciado</option>
                            <option value="Em andamento">Em andamento</option>
                            <option value="Finalizado">Finalizado</option>
                            <option value="HOLD">HOLD</option>
                            <option value="Não se aplica">Não se aplica</option>
                            <option value="Em aprovação">Em aprovação</option>
                        </select>
                        <input type="date" name="prazo_modelagem" id="prazo_modelagem">
                        <input type="text" name="obs_modelagem" id="obs_modelagem" placeholder="Observação">
                    </div>
                </div>
                <div class="funcao">
                    <div class="titulo">
                        <p id="comp">Composição</p>
                        <i class="fas fa-chevron-down" id="toggle-options"></i>
                    </div>
                    <div class="opcoes" id="opcoes" style="display: none;">
                        <?php if ($_SESSION['nivel_acesso'] == 1): ?>
                            <select name="comp_id" id="opcao_comp">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <select name="comp_id" id="opcao_comp" disabled>
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <select name="status_comp" id="status_comp">
                            <option value="Não iniciado">Não iniciado</option>
                            <option value="Em andamento">Em andamento</option>
                            <option value="Finalizado">Finalizado</option>
                            <option value="HOLD">HOLD</option>
                            <option value="Não se aplica">Não se aplica</option>
                            <option value="Em aprovação">Em aprovação</option>
                        </select>
                        <input type="date" name="prazo_comp" id="prazo_comp">
                        <input type="text" name="obs_comp" id="obs_comp" placeholder="Observação">
                    </div>
                </div>
                <div class="funcao">
                    <div class="titulo">
                        <p id="final">Finalização</p>
                        <i class="fas fa-chevron-down" id="toggle-options"></i>
                    </div>
                    <div class="opcoes" id="opcoes" style="display: none;">
                        <?php if ($_SESSION['nivel_acesso'] == 1): ?>

                            <select name="final_id" id="opcao_final">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <select name="final_id" id="opcao_final" disabled>
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <select name="status_finalizacao" id="status_finalizacao">
                            <option value="Não iniciado">Não iniciado</option>
                            <option value="Em andamento">Em andamento</option>
                            <option value="Finalizado">Finalizado</option>
                            <option value="HOLD">HOLD</option>
                            <option value="Não se aplica">Não se aplica</option>
                            <option value="Em aprovação">Em aprovação</option>
                        </select>
                        <input type="date" name="prazo_finalizacao" id="prazo_finalizacao">
                        <input type="text" name="obs_finalizacao" id="obs_finalizacao" placeholder="Observação">
                    </div>
                </div>
                <div class="funcao">
                    <div class="titulo">
                        <p id="pos">Pós-Produção</p>
                        <i class="fas fa-chevron-down" id="toggle-options"></i>
                    </div>
                    <div class="opcoes" id="opcoes" style="display: none;">
                        <?php if ($_SESSION['nivel_acesso'] == 1): ?>

                            <select name="pos_id" id="opcao_pos">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <select name="pos_id" id="opcao_pos" disabled>
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <select name="status_pos" id="status_pos">
                            <option value="Não iniciado">Não iniciado</option>
                            <option value="Em andamento">Em andamento</option>
                            <option value="Finalizado">Finalizado</option>
                            <option value="HOLD">HOLD</option>
                            <option value="Não se aplica">Não se aplica</option>
                            <option value="Em aprovação">Em aprovação</option>
                        </select>
                        <input type="date" name="prazo_pos" id="prazo_pos">
                        <input type="text" name="obs_pos" id="obs_pos" placeholder="Observação">
                    </div>
                </div>
                <div class="funcao">
                    <div class="titulo">
                        <p id="alteracao">Alteração</p>
                        <i class="fas fa-chevron-down" id="toggle-options"></i>
                    </div>
                    <div class="opcoes" id="opcoes" style="display: none;">
                        <?php if ($_SESSION['nivel_acesso'] == 1): ?>

                            <select name="alteracao_id" id="opcao_alteracao">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <select name="alteracao_id" id="opcao_alteracao" disabled>
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <select name="status_alteracao" id="status_alteracao">
                            <option value="Não iniciado">Não iniciado</option>
                            <option value="Em andamento">Em andamento</option>
                            <option value="Finalizado">Finalizado</option>
                            <option value="HOLD">HOLD</option>
                            <option value="Não se aplica">Não se aplica</option>
                            <option value="Em aprovação">Em aprovação</option>
                        </select>
                        <input type="date" name="prazo_alteracao" id="prazo_alteracao">
                        <input type="text" name="obs_alteracao" id="obs_alteracao" placeholder="Observação">
                    </div>
                </div>
                <div class="funcao">
                    <div class="titulo">
                        <p id="planta">Planta Humanizada</p>
                        <i class="fas fa-chevron-down" id="toggle-options"></i>
                    </div>
                    <div class="opcoes" id="opcoes" style="display: none;">
                        <?php if ($_SESSION['nivel_acesso'] == 1): ?>

                            <select name="planta_id" id="opcao_planta">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <select name="planta_id" id="opcao_planta" disabled>
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <select name="status_planta" id="status_planta">
                            <option value="Não iniciado">Não iniciado</option>
                            <option value="Em andamento">Em andamento</option>
                            <option value="Finalizado">Finalizado</option>
                            <option value="HOLD">HOLD</option>
                            <option value="Não se aplica">Não se aplica</option>
                            <option value="Em aprovação">Em aprovação</option>
                        </select>
                        <input type="date" name="prazo_planta" id="prazo_planta">
                        <input type="text" name="obs_planta" id="obs_planta" placeholder="Observação">
                    </div>
                </div>
                <div class="funcao" id="status_funcao">
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

        <div id="filtro-colab" class="modal">
            <h1>Filtro colaboradores</h1>
            <button id="mostrarLogsBtn" disabled>Mostrar Logs</button>
            <label for="colaboradorSelect">Selecionar Colaborador:</label>
            <select id="colaboradorSelect">
                <option value="0">Selecione:</option>
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

            <label for="obra">Obra:</label>
            <select name="obraSelect" id="obraSelect">
                <option value="">Selecione:</option>
                <?php foreach ($obras as $obra): ?>
                    <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="funcaoSelect">Função:</label>
            <select id="funcaoSelect">
                <option value="0">Selecione a Função:</option>
                <?php foreach ($funcoes as $funcao): ?>
                    <option value="<?= htmlspecialchars($funcao['idfuncao']); ?>">
                        <?= htmlspecialchars($funcao['nome_funcao']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="statusSelect">Status:</label>
            <select id="statusSelect">
                <option value="0">Selecione um status:</option>
                <option value="Não iniciado">Não iniciado</option>
                <option value="Em andamento">Em andamento</option>
                <option value="Finalizado">Finalizado</option>
                <option value="HOLD">HOLD</option>
                <option value="Não se aplica">Não se aplica</option>
                <option value="Em aprovação">Em aprovação</option>
            </select>

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
                </tbody>
            </table>

            <div id="modalLogs" class="modal">
                <div class="modal-content-log">
                    <span class="close">&times;</span>
                    <h2>Logs de Alterações</h2>
                    <table id="tabela-logs">
                        <thead>
                            <tr>
                                <th>Imagem</th>
                                <th>Obra</th>
                                <th>Status Anterior</th>
                                <th>Status Novo</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div id="filtro-obra">
            <h1>Filtro por obra:</h1>

            <label for="obra">Obra:</label>
            <select name="obraFiltro" id="obraFiltro">
                <option value="0">Selecione:</option>
                <?php foreach ($obras as $obra): ?>
                    <option value="<?= htmlspecialchars($obra['idobra']); ?>">
                        <?= htmlspecialchars($obra['nome_obra']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="tipo_imagem" id="tipo_imagem">
                <option value="0">Todos</option>
                <option value="Fachada">Fachada</option>
                <option value="Imagem Interna">Imagem Interna</option>
                <option value="Imagem Externa">Imagem Externa</option>
                <option value="Planta Humanizada">Planta Humanizada</option>
            </select>

            <table id="tabela-obra">
                <thead>
                    <th>Nome da Imagem</th>
                    <th>Tipo</th>
                    <th>Caderno</th>
                    <th>Status</th>
                    <th>Model</th>
                    <th>Status</th>
                    <th>Comp</th>
                    <th>Status</th>
                    <th>Final</th>
                    <th>Status</th>
                    <th>Pós</th>
                    <th>Status</th>
                    <th>Alteração</th>
                    <th>Status</th>
                    <th>Planta</th>
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

            <select name="tipo_imagem_follow" id="tipo_imagem_follow">
                <option value="0">Todos</option>
                <option value="Fachada">Fachada</option>
                <option value="Imagem Interna">Imagem Interna</option>
                <option value="Imagem Externa">Imagem Externa</option>
                <option value="Planta Humanizada">Planta Humanizada</option>
            </select>

            <select name="status_imagem" id="status_imagem">
                <option value="0">Todos</option>
                <option value="1">P00</option>
                <option value="2">R00</option>
                <option value="3">R01</option>
                <option value="4">R02</option>
                <option value="5">R03</option>
                <option value="6">EF</option>
                <option value="7">Sem status</option>
                <option value="9">HOLD</option>
            </select>

            <button id="generate-pdf">Gerar PDF</button>

            <table id="tabela-follow">
                <thead>
                    <th>Nome da Imagem</th>
                    <th>Status</th>
                    <th>Prazo</th>
                    <th>Caderno</th>
                    <th>Filtro</th>
                    <th>Model</th>
                    <th>Comp</th>
                    <th>Final</th>
                    <th>Pós</th>
                    <th>Alteração</th>
                    <th>Planta</th>
                    <th>Revisões</th>

                </thead>
                <tbody>

                </tbody>
            </table>
        </div>

        <div id="add-acomp" class="modal">
            <h1 class="acompanhamento">Adicionar acompanhamento</h1>
            <form id="form-add-acomp" onsubmit="submitFormAcomp(event)">

                <label for="">Tipo de acompanhamento:</label>
                <select name="tipo" id="tipo">
                    <option value="1">Obra</option>
                    <option value="2">Email</option>
                </select>
                <label for="">Obra:</label>
                <select name="obraAcomp" id="obraAcomp">
                    <option value="">Selecione:</option>
                    <?php foreach ($obras as $obra): ?>
                        <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="nome">Colaborador:</label>
                <select name="colab_id" id="colab_id">
                    <?php foreach ($colaboradores as $colab): ?>
                        <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                            <?= htmlspecialchars($colab['nome_colaborador']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="assunto-email" style="display: none">
                    <label for="">Assunto do email:</label>

                    <textarea name="assunto" id="assunto"></textarea>
                </div>

                <div class="buttons">
                    <button type="submit" id="salvar">Salvar</button>
                    <button type="button" onclick="closeModal('add-acomp', this)" id="fechar">Fechar</button>
                </div>
            </form>
        </div>

    </main>

    <script src="./script/script.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>



    </body>

</html>