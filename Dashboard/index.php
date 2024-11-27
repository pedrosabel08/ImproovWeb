<?php
session_start();

if (!isset($_SESSION['logado']) || !$_SESSION['logado'] || !isset($_SESSION['nivel_acesso']) || $_SESSION['nivel_acesso'] != 1) {
    header("Location: ../index.html"); // Redireciona para a página de login
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gráfico de Custos por Obra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.css" />

</head>

<body>
    <main>
        <div class="sidebar">
            <div class="top">
                <p>+</p>
            </div>
            <div class="content">
                <div class="nav">
                    <a href="index.php" id="dashboard" class="tooltip"><i class="fa-solid fa-chart-line"></i><span class="tooltiptext">Dashboard</span></a>
                    <a href="projetos.html" id="projects" class="tooltip"><i class="fa-solid fa-list-check"></i><span class="tooltiptext">Projetos</span></a>
                    <a href="#" id="colabs" class="tooltip"><i class="fa-solid fa-users"></i><span class="tooltiptext">Colaboradores</span></a>
                    <a href="controle_comercial.html" id="controle_comercial" class="tooltip"><i class="fa-solid fa-dollar-sign"></i><span class="tooltiptext">Controle Comercial</span></a>
                </div>
            </div>
        </div>

        <div class="main-content">
            <!-- Cabeçalho do Dashboard -->
            <div class="dashboard-header">
                <img src="../gif/assinatura_preto.gif" alt="">
            </div>

            <!-- Seção de Estatísticas -->
            <div class="stats-container">
                <!-- Total da Empresa -->
                <div class="stat-card active">
                    <h2>Total da Empresa ($)</h2>
                    <p id="total_orcamentos"></p>
                    <div class="lucro">
                        <p>Lucro: </p>
                        <span id="lucro_percentual"></span>
                    </div>
                </div>
                <div class="stat-card">
                    <h2>Total da Empresa - Produção ($)</h2>
                    <p id="total_producao"></p>
                </div>

                <!-- Obras Ativas -->
                <div class="stat-card">
                    <h2>Obras Ativas</h2>
                    <p id="obras_ativas"></p>
                </div>

            </div>
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
            </div>
            <div id="painel">

            </div>

            <div class="modalInfos" id="modalInfos">
                <div id="infos-obra">
                    <!-- <button id="follow-up">Follow-up</button> -->

                    <div class="obra-identificacao">
                        <h3 id="nomenclatura"></h3>
                        <h4 id="data_inicio"></h4>
                        <h4 id="prazo"></h4>
                        <h4 id="dias_trabalhados"></h4>
                    </div>

                    <div class="obra-acompanhamento">
                        <!-- <button id="acompanhamento">Acompanhamento</button> -->
                        <button id="orcamento">Orçamento</button>
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

                </div>
            </div>


            <!-- <div class="graficos">
                <canvas id="graficoProducao"></canvas>
                <canvas id="graficoOrcamento"></canvas>
                <canvas id="graficoPercentual"></canvas>
            </div> -->
        </div>
    </main>


    <script src="script.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>

</html>