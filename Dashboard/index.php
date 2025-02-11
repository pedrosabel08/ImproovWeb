<?php
session_start();

include '../conexao.php';
include '../conexaoMain.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

$idusuario = $_SESSION['idusuario'];
$nome_usuario = $_SESSION['nome_usuario'];
$idcolaborador = $_SESSION['idcolaborador'];
$nivel_acesso = $_SESSION['nivel_acesso'];

// Consulta para contar as tarefas pendentes
$sql_pendentes = "SELECT COUNT(*) as count_pendentes FROM funcao_imagem WHERE status <> 'Finalizado' AND colaborador_id = ?";
$stmt_pendentes = $conn->prepare($sql_pendentes);
$stmt_pendentes->bind_param("i", $idcolaborador);
$stmt_pendentes->execute();
$result_pendentes = $stmt_pendentes->get_result();
$row_pendentes = $result_pendentes->fetch_assoc();
$count_pendentes = $row_pendentes['count_pendentes'];

// Consulta para imagens pendentes
$sql_imagens = "SELECT 
        i.imagem_nome, 
        o.nome_obra ,
i.prazo
    FROM 
        imagens_cliente_obra i 
    JOIN 
        funcao_imagem f ON i.idimagens_cliente_obra = f.imagem_id 
    JOIN 
        obra o ON o.idobra = i.obra_id  -- Supondo que o nome da tabela da obra seja 'obra'
    WHERE 
        f.colaborador_id = ?
        AND f.status <> 'Finalizado'
		AND i.prazo IS NOT NULL
        LIMIT 10";
$stmt_imagens = $conn->prepare($sql_imagens);
$stmt_imagens->bind_param("i", $idcolaborador);
$stmt_imagens->execute();
$result_imagens = $stmt_imagens->get_result();

$sql_grafico = "SELECT COUNT(*) as imagens, SUM(valor) as total, MONTH(prazo) as mes from funcao_imagem WHERE colaborador_id = ? GROUP BY MONTH(prazo)";
$stmt_grafico = $conn->prepare($sql_grafico);
$stmt_grafico->bind_param("i", $idcolaborador);
$stmt_grafico->execute();
$result_grafico = $stmt_grafico->get_result();

// Consulta para o total de produção
$sql_total = "SELECT ROUND(SUM(fi.valor)) AS total_producao FROM funcao_imagem fi WHERE fi.colaborador_id = ?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param("i", $idcolaborador);
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$row_total = $result_total->fetch_assoc();
$count_total = $row_total['total_producao'];

$stmt_pendentes->close();
$stmt_imagens->close();
$stmt_total->close();

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.css" />
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
</head>


<body>

    <?php

    include '../sidebar.php';

    ?>

    <div class="main-content">
        <!-- Cabeçalho do Dashboard -->
        <div class="dashboard-header">
            <img src="../gif/assinatura_preto.gif" alt="" style="width: 150px;">
        </div>

        <!-- Seção de Estatísticas -->
        <div class="stats-container">
            <?php if ($nivel_acesso == 1): ?>
                <!-- Visível para nível 1 -->
                <div class="stat-card active">
                    <h2>Total da Empresa ($)</h2>
                    <p id="total_orcamentos"></p>
                    <div class="lucro">
                        <p style="filter: blur(0);">Lucro: </p>
                        <span id="lucro_percentual"></span>
                    </div>
                </div>
                <div class="stat-card">
                    <h2>Total da Empresa - Produção ($)</h2>
                    <p id="total_producao"></p>
                </div>

                <div class="stat-card">
                    <h2>Obras Ativas</h2>
                    <p id="obras_ativas" style="filter: blur(0);"></p>
                </div>
            <?php else: ?>
                <!-- Visível para níveis superiores -->
                <div class="stat-card">
                    <h2>Total de Imagens Pendentes</h2>
                    <p style="filter: blur(0);"><?php echo $count_pendentes; ?> Imagens</p>
                </div>

                <div class="stat-card">
                    <h2>Total de Produção ($)</h2>
                    <p><?php echo number_format($count_total, 2); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($nivel_acesso == 1): ?>

            <div id="legenda" class="legenda">
                <div class="legenda-item">
                    <div class="cor" style="background-color: #ff6f61;"></div>
                    <span>Prazo já passou</span>
                </div>
                <div class="legenda-item">
                    <div class="cor" style="background-color: #f7b731;"></div>
                    <span>Prazo próximo (3 dias ou menos)</span>
                </div>
                <div class="legenda-item">
                    <div class="cor" style="background-color: #28a745;"></div>
                    <span>Prazo distante</span>
                </div>
                <div class="legenda-item">
                    <div class="cor" style="background-color: #1bd6f2;"></div>
                    <span>Esperando arquivos</span>
                </div>
            </div>

            <!-- Para nível de acesso 1, exibe a div painel -->
            <div id="painel"></div>
            <button id="ver_todas" style="display: none;">Ver Todas</button>

        <?php else: ?>
            <!-- Para níveis superiores a 1, esconde a div painel e mostra outra div com as imagens pendentes -->
            <div id="painel" style="display: none;"></div>
            <div id="container">
                <div class="grafico">
                    <canvas id="graficoColab"></canvas>
                </div>
                <div class="img-container">
                    <h2>Imagens</h2>
                    <div id="imagens">
                        <?php
                        // Gerando as tags <p> com os dados do banco
                        while ($row_imagens = $result_imagens->fetch_assoc()) {
                            $imagem_nome = htmlspecialchars($row_imagens['imagem_nome']);
                            $nome_obra = htmlspecialchars($row_imagens['nome_obra']);
                            $prazo = htmlspecialchars($row_imagens['prazo']);

                            echo "<p>$imagem_nome - $prazo</p>";
                        }
                        $conn->close();
                        ?></div>
                    <button id="ver_todas">Ver Todas</button>
                </div>
            </div>
        <?php endif; ?>

        <div class="modalInfos" id="modalInfos">
            <div id="infos-obra">
                <!-- <button id="follow-up">Follow-up</button> -->

                <div class="obra-identificacao">
                    <h3 id="nomenclatura"></h3>
                    <h4 id="data_inicio"></h4>
                    <h4 id="dias_trabalhados"></h4>
                    <div id="prazos-list"></div>

                </div>

                <div class="obra-acompanhamento">
                    <!-- <button id="acompanhamento">Acompanhamento</button> -->
                    <button id="orcamento">Orçamento</button>
                    <button id="obra" style="background-color: darkcyan;" onclick="window.location.href='https://improov.com.br/sistema/Dashboard/obra.php'">Ir para a tela da obra</button>
                </div>

                <div class="modalOrcamento" id="modalOrcamento">
                    <div class="orcamento-form">
                        <h2>Fazer Orçamento</h2>
                        <form id="formOrcamento">
                            <input type="hidden" id="idObraOrcamento">
                            <div class="linha">
                                <label for="tipo">Tipo:</label>
                                <input type="text" id="tipo" required>
                            </div>
                            <div class="linha">
                                <label for="valor">Valor:</label>
                                <input type="number" id="valor" required>
                            </div>
                            <div class="linha">
                                <label for="data">Data:</label>
                                <input type="date" id="data" required>
                            </div>
                            <div class="buttons">
                                <button type="submit" id="salvar_orcamento">Salvar Orçamento</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="obra-imagens">
                    <h4 id="total_imagens"></h4>
                    <h4 id="total_imagens_antecipadas"></h4>
                    <!-- <h4 id="classificacao"></h4> -->
                    <div id="funcoes"></div>
                    <div id="grafico">
                        <canvas id="graficoPorcentagem" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- <?php
                        // Exibir somente se o usuário tiver nível de acesso 1
                        if (isset($_SESSION['logado']) && $_SESSION['logado'] === true && $_SESSION['nivel_acesso'] == 1) {
                        ?>
                    <div class="obra-valores">
                        <div class="valor-item">
                            <strong>Valor Orçamento:</strong>
                            <span id="valor_orcamento"></span>
                        </div>
                        <div class="valor-item">
                            <strong>Valor Produção:</strong>
                            <span id="valor_producao"></span>
                        </div>
                        <div class="valor-item">
                            <strong>Valor projetado:</strong>
                            <span id="valor_fixo"></span>
                        </div>
                        <div class="valor-item">
                            <strong>Lucro estimado (produção):</strong>
                            <span id="lucro"></span>
                        </div>
                    </div>
                <?php
                        }
                ?> -->


                <div class="acompanhamentos">
                    <h1>Histórico</h1>
                    <button id="acomp" class="btnAcompObs">Acompanhamento</button>

                    <div id="list_acomp" class="list-acomp"></div>
                    <button id="btnMostrarAcomps"><i class="fas fa-chevron-down"></i> Mostrar Todos</button>
                </div>
            </div>
        </div>


        <div id="filtro-colab" class="modal">
            <div class="modal-content" style="margin: auto; width: 80%; height: 80%;">

                <button id="mostrarLogsBtn">Mostrar Logs</button>

                <div class="labels">
                    <div>
                        <label for="dataInicio">Data Início:</label>
                        <input type="date" id="dataInicio">
                    </div>

                    <div>
                        <label for="dataFim">Data Fim:</label>
                        <input type="date" id="dataFim">
                    </div>

                    <div>
                        <label for="obra">Obra:</label>
                        <select name="obraSelect" id="obraSelect">
                            <option value="">Selecione:</option>
                            <?php foreach ($obras as $obra): ?>
                                <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="funcaoSelect">Função:</label>
                        <select id="funcaoSelect">
                            <option value="0">Selecione a Função:</option>
                            <?php foreach ($funcoes as $funcao): ?>
                                <option value="<?= htmlspecialchars($funcao['idfuncao']); ?>">
                                    <?= htmlspecialchars($funcao['nome_funcao']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
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
                    </div>
                </div>

                <div class="image-count">
                    <strong>Total de Imagens:</strong> <span id="totalImagens">0</span>
                </div>

                <div class="table-container" style="max-height: 60vh; overflow-y: auto; margin-top: 30px;">
                    <table id="tabela-colab" style="width: 100%;">
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
                </div>
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
        </div>


        <!-- <div class="graficos">
                        <canvas id="graficoProducao"></canvas>
                        <canvas id="graficoOrcamento"></canvas>
                        <canvas id="graficoPercentual"></canvas>
                    </div> -->
    </div>

    <script>
        const idColaborador = <?php echo json_encode($idcolaborador); ?>;
        localStorage.setItem('idcolaborador', idColaborador);
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../script/sidebar.js" defer></script>
    <script src="script.js" defer></script>
</body>

</html>