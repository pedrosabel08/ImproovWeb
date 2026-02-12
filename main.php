<?php
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/config/session_bootstrap.php';

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
    <link href="./css/styleSidebar.css" rel="stylesheet">
    <link href="./css/modalSessao.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <title>Improov+Flow</title>
</head>


<header>

    <img style="position: absolute; right: 15px; top: 15px" src="gif/assinatura_preto.gif" alt="Logo Improov + Flow">

</header>


<button class="nav-toggle" aria-label="Toggle navigation" onclick="toggleNav()">
    &#9776;
</button>

<nav class="nav-menu" style="display: none;">
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
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$imagens = obterImagens($conn);

$conn->close();
?>

<?php

include 'sidebar.php';

?>


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

            <select id="imagem" onchange="filtrarTabela()">
                <option value="">Todos as imagens</option>
                <option value="Antecipada">Antecipada</option>
            </select>
        </div>

        <div class="tabelaClientes">
            <div class="image-count">
                <strong>Total de Imagens:</strong> <span id="total-imagens">0</span>
            </div>
            <div class="image-count">
                <strong>Total de Imagens antecipadas:</strong> <span id="total-imagens-antecipada">0</span>
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
                </tbody>
            </table>
        </div>
    </div>
    <div class="form-edicao" id="form-edicao">
        <form id="form-add" method="post" action="insereFuncao.php">
            <div class="titulo-funcoes">
                <span id="campoNomeImagem"></span>
            </div> <input type="hidden" id="imagem_id" name="imagem_id">
            <div class="modal-funcoes">
                <span id="mood"></span>
                <div class="funcao_comp">
                    <div class="funcao">
                        <div class="titulo">
                            <p id="caderno">Caderno</p>
                            <i class="fas fa-chevron-down toggle-options"></i>
                        </div>
                        <div class="opcoes" style="display: none;">
                            <select name="caderno_id" id="opcao_caderno">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status_caderno" id="status_caderno">
                                <option value="Não iniciado">Não iniciado</option>
                                <option value="Em andamento">Em andamento</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="HOLD">HOLD</option>
                                <option value="Não se aplica">Não se aplica</option>
                                <option value="Em aprovação">Em aprovação</option>
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_caderno" id="prazo_caderno">
                            <input type="text" name="obs_caderno" id="obs_caderno" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_caderno">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">
                    <div class="funcao">
                        <div class="titulo">
                            <p id="filtro">Filtro de assets</p>
                            <i class="fas fa-chevron-down" id="toggle-options"></i>
                        </div>
                        <div class="opcoes" id="opcoes" style="display: none;">
                            <select name="filtro_id" id="opcao_filtro">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status_filtro" id="status_filtro">
                                <option value="Não iniciado">Não iniciado</option>
                                <option value="Em andamento">Em andamento</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="HOLD">HOLD</option>
                                <option value="Não se aplica">Não se aplica</option>
                                <option value="Em aprovação">Em aprovação</option>
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_filtro" id="prazo_filtro" placeholder="Data">
                            <input type="text" name="obs_filtro" id="obs_filtro" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_filtro">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">
                    <div class="funcao">
                        <div class="titulo">
                            <p id="modelagem">Modelagem</p>
                            <i class="fas fa-chevron-down" id="toggle-options"></i>
                        </div>
                        <div class="opcoes" style="display: none;">
                            <select name="model_id" id="opcao_model">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status_modelagem" id="status_modelagem">
                                <option value="Não iniciado">Não iniciado</option>
                                <option value="Em andamento">Em andamento</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="HOLD">HOLD</option>
                                <option value="Não se aplica">Não se aplica</option>
                                <option value="Em aprovação">Em aprovação</option>
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_modelagem" id="prazo_modelagem">
                            <input type="text" name="obs_modelagem" id="obs_modelagem" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_model">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">
                    <div class="funcao">
                        <div class="titulo">
                            <p id="comp">Composição</p>
                            <i class="fas fa-chevron-down" id="toggle-options"></i>
                        </div>
                        <div class="opcoes" id="opcoes" style="display: none;">
                            <select name="comp_id" id="opcao_comp">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status_comp" id="status_comp">
                                <option value="Não iniciado">Não iniciado</option>
                                <option value="Em andamento">Em andamento</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="HOLD">HOLD</option>
                                <option value="Não se aplica">Não se aplica</option>
                                <option value="Em aprovação">Em aprovação</option>
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_comp" id="prazo_comp">
                            <input type="text" name="obs_comp" id="obs_comp" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_comp">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">
                    <div class="funcao">
                        <div class="titulo">
                            <p id="pre">Pré-Finalização</p>
                            <i class="fas fa-chevron-down" id="toggle-options"></i>
                        </div>
                        <div class="opcoes" style="display: none;">
                            <select name="opcao_pre" id="opcao_pre">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status_pre" id="status_pre">
                                <option value="Não iniciado">Não iniciado</option>
                                <option value="Em andamento">Em andamento</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="HOLD">HOLD</option>
                                <option value="Não se aplica">Não se aplica</option>
                                <option value="Em aprovação">Em aprovação</option>
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_pre" id="prazo_pre">
                            <input type="text" name="obs_pre" id="obs_pre" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_pre">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">
                    <div class="funcao">
                        <div class="titulo">
                            <p id="final">Finalização</p>
                            <i class="fas fa-chevron-down" id="toggle-options"></i>
                        </div>
                        <div class="opcoes" id="opcoes" style="display: none;">
                            <select name="final_id" id="opcao_final">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status_finalizacao" id="status_finalizacao">
                                <option value="Não iniciado">Não iniciado</option>
                                <option value="Em andamento">Em andamento</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="HOLD">HOLD</option>
                                <option value="Não se aplica">Não se aplica</option>
                                <option value="Em aprovação">Em aprovação</option>
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_finalizacao" id="prazo_finalizacao">
                            <input type="text" name="obs_finalizacao" id="obs_finalizacao" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_final">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">

                    <div class="funcao">
                        <div class="titulo">
                            <p id="pos">Pós-Produção</p>
                            <i class="fas fa-chevron-down" id="toggle-options"></i>
                        </div>
                        <div class="opcoes" id="opcoes" style="display: none;">
                            <select name="pos_id" id="opcao_pos">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="status_pos" id="status_pos">
                                <option value="Não iniciado">Não iniciado</option>
                                <option value="Em andamento">Em andamento</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="HOLD">HOLD</option>
                                <option value="Não se aplica">Não se aplica</option>
                                <option value="Em aprovação">Em aprovação</option>
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_pos" id="prazo_pos">
                            <input type="text" name="obs_pos" id="obs_pos" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_pos">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">
                    <div class="funcao">
                        <div class="titulo">
                            <p id="alteracao">Alteração</p>
                            <i class="fas fa-chevron-down" id="toggle-options"></i>
                        </div>
                        <div class="opcoes" id="opcoes" style="display: none;">
                            <select name="alteracao_id" id="opcao_alteracao">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="status_alteracao" id="status_alteracao">
                                <option value="Não iniciado">Não iniciado</option>
                                <option value="Em andamento">Em andamento</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="HOLD">HOLD</option>
                                <option value="Não se aplica">Não se aplica</option>
                                <option value="Em aprovação">Em aprovação</option>
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_alteracao" id="prazo_alteracao">
                            <input type="text" name="obs_alteracao" id="obs_alteracao" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_alt">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao_comp">
                    <div class="funcao">
                        <div class="titulo">
                            <p id="planta">Planta Humanizada</p>
                            <i class="fas fa-chevron-down" id="toggle-options"></i>
                        </div>
                        <div class="opcoes" id="opcoes" style="display: none;">
                            <select name="planta_id" id="opcao_planta">
                                <?php foreach ($colaboradores as $colab): ?>
                                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="status_planta" id="status_planta">
                                <option value="Não iniciado">Não iniciado</option>
                                <option value="Em andamento">Em andamento</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="HOLD">HOLD</option>
                                <option value="Não se aplica">Não se aplica</option>
                                <option value="Em aprovação">Em aprovação</option>
                                <option value="Aprovado">Aprovado</option>
                                <option value="Ajuste">Ajuste</option>
                                <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                            </select>
                            <input type="date" name="prazo_planta" id="prazo_planta">
                            <input type="text" name="obs_planta" id="obs_planta" placeholder="Caminho arquivo">
                            <div class="revisao_imagem" style="display: none;">
                                <button type="button" onclick="abrirModal(this)" id="revisao_imagem_ph">Adicionar Imagens</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funcao" id="status_funcao" style="margin-bottom: 15px;width: max-content;">
                    <p id="status">Status</p>
                    <select name="status_id" id="opcao_status">
                        <?php foreach ($status_imagens as $status): ?>
                            <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                                <?= htmlspecialchars($status['nome_status']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status_hold" id="status_hold" multiple style="width: 200px; display: none;">
                        <option value="Paisagismo">Paisagismo</option>
                        <option value="Mood">Mood</option>
                        <option value="Interiores">Interiores</option>
                        <option value="Luminotécnico">Luminotécnico</option>
                        <option value="Arquitetônico">Arquitetônico</option>
                        <option value="Definição de unidade">Definição de unidade</option>
                        <option value="Aguardando aprovações">Aguardando aprovações</option>
                        <option value="Aguardando arquivos">Aguardando arquivos</option>
                    </select>
                </div>
                <div class="funcao" id="status_funcao" style="width: 200px; margin-bottom: 15px;">
                    <div class="render">
                        <p id="render_alta">Render</p>
                        <button id="addRender" type="button" class="buttons-form-add" style=" padding: 3px 10px; font-size: 13px; background-color: steelblue;">Adicionar render</button>
                        <label class="switch" style="display: none;">
                            <input type="checkbox" id="notificar">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="funcao" id="status_funcao" style="width: 200px; margin-bottom: 15px;">
                    <div class="revisao">
                        <p id="revisao">Revisao</p>
                        <button id="addRevisao" class="buttons-form-add" style=" padding: 3px 10px; font-size: 13px; background-color: steelgreen;">Adicionar revisão</button>
                    </div>
                </div>
                <div class="buttons">
                    <button type="button" id="btnAnterior" style="background: white; color: black"><i class="fa-solid fa-angle-left"></i></button>
                    <div>
                        <button type="submit" id="salvar_funcoes" class="buttons-form-add">Salvar</button>
                        <div id="loadingBar" style="display: none;">
                            <div class="progress"></div>
                        </div>
                    </div>
                    <button type="button" id="btnProximo" style="background: white; color: black"><i class="fa-solid fa-angle-right"></i></button>
                </div>
            </div>

        </form>
    </div>

    <div id="filtro-colab" class="modal">
        <div class="header">
            <h1>Filtro colaboradores</h1>
        </div>
        <button id="mostrarLogsBtn" disabled style="display: none;">Mostrar Logs</button>

        <div class="filtro-container">
            <div class="filtro-item" id="div-colab">
                <label for="colaboradorSelect">Colaborador:</label>
                <select id="colaboradorSelect">
                    <option value="0">Selecione:</option>
                    <?php foreach ($colaboradores as $colab): ?>
                        <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                            <?= htmlspecialchars($colab['nome_colaborador']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-item">
                <label for="mes">Data:</label>
                <input type="month" id="mes" name="mes">

            </div>

            <div class="filtro-item">
                <label for="obraSelect">Obra:</label>
                <select id="obraSelect">
                    <option value="">Selecione:</option>
                    <?php foreach ($obras as $obra): ?>
                        <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nomenclatura']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-item">
                <label for="funcaoSelect">Função:</label>
                <select id="funcaoSelect" multiple size="10" style="overflow: hidden;">
                    <option value="0">Selecione a Função:</option>
                    <?php foreach ($funcoes as $funcao): ?>
                        <option value="<?= htmlspecialchars($funcao['idfuncao']); ?>">
                            <?= htmlspecialchars($funcao['nome_funcao']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-item">
                <label for="statusSelect">Status:</label>
                <select id="statusSelect" multiple size="10" style="overflow: hidden;">
                    <option value="0">Selecione um status:</option>
                    <option value="Não iniciado">Não iniciado</option>
                    <option value="Em andamento">Em andamento</option>
                    <option value="Finalizado">Finalizado</option>
                    <option value="HOLD">HOLD</option>
                    <option value="Não se aplica">Não se aplica</option>
                    <option value="Em aprovação">Em aprovação</option>
                    <option value="Ajuste">Ajuste</option>
                    <option value="Aprovado">Aprovado</option>
                    <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                </select>
            </div>
        </div>

        <div class="image-count" id="image-count" style="display: none;">
            <strong>Total de Imagens:</strong> <span id="totalImagens">0</span>
        </div>

        <button id="copyColumnColab" style="display: none;">
            <i class="fas fa-copy"></i>
        </button>

        <div id="legenda" class="legenda" style="display: none;">
            <div class="legenda-item">
                <div class="cor" style="background-color: red;"></div>
                <span>Esperando concluir função anterior</span>
            </div>
            <div class="legenda-item">
                <div class="cor" style="background-color: green;"></div>
                <span>Função liberada</span>
            </div>
        </div>

        <div class="kanban-board" id="kanban-board" style="display: none;">
            <div class="kanban-column" id="kanban-nãoiniciado">
                <div class="kanban-title">Não iniciado</div>
            </div>
            <div class="kanban-column" id="kanban-emandamento">
                <div class="kanban-title">Em andamento</div>
            </div>
            <div class="kanban-column" id="kanban-emaprovação">
                <div class="kanban-title">Em aprovação</div>
            </div>
            <div class="kanban-column" id="kanban-finalizado">
                <div class="kanban-title">Finalizado</div>
            </div>
        </div>

        <!-- <table id="tabela-colab">
            <thead>
                <tr>
                    <th>Prioridade</th>
                    <th id="nome">Nome da Imagem</th>
                    <th>Função</th>
                    <th>Status</th>
                    <th>Prazo</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table> -->

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

        <select id="antecipada_obra">
            <option value="">Todos as imagens</option>
            <option value="Antecipada">Antecipada</option>
        </select>

        <div class="legenda">
            <span class="legenda-item">
                <span class="circulo antecipada"></span> Antecipada
            </span>
        </div>

        <button id="copyColumn">
            <i class="fas fa-copy"></i>
        </button>


        <table id="tabela-obra">
            <thead>
                <th>Nome da Imagem</th>
                <th style="width: 150px;">Tipo</th>
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

        <select id="antecipada_follow">
            <option value="">Todos as imagens</option>
            <option value="Antecipada">Antecipada</option>
        </select>

        <button id="generate-pdf">Gerar PDF</button>

        <div class="legenda">
            <span class="legenda-item">
                <span class="circulo antecipada"></span> Antecipada
            </span>
        </div>

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
            <div id="data-email" style="display: none">
                <label for="">Data:</label>

                <input type="date" name="data" id="data">
            </div>

            <div class="buttons">
                <button type="submit" id="salvar">Salvar</button>
                <button type="button" onclick="closeModal('add-acomp', this)" id="fechar">Fechar</button>
            </div>
        </form>
    </div>

    <div id="modal_pos" class="modal hidden">
        <div class="modal-content" style="width: 40%; max-height: 80%; overflow-y: auto; margin: 100px auto;">
            <span class="close">&times;</span>
            <button id="deleteButton">Excluir</button>
            <div id="form-inserir">
                <h2>Formulário de Dados</h2>
                <form id="formPosProducao">
                    <div>
                        <label for="nomeFinalizador">Nome Finalizador</label>
                        <select name="final_id" id="opcao_finalizador" required>
                            <option value="0">Selecione um colaborador:</option>
                            <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                    <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="nomeObra">Nome Obra</label>
                        <select name="obra_id" id="opcao_obra_pos" required>
                            <option value="0">Selecione uma obra:</option>
                            <?php foreach ($obras as $obra): ?>
                                <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="imagem_id_pos">Nome Imagem</label>
                        <select id="imagem_id_pos" name="imagem_id_pos" required>
                            <option value="">Selecione uma imagem:</option>
                            <?php foreach ($imagens as $imagem): ?>
                                <option value="<?= $imagem['idimagens_cliente_obra']; ?>">
                                    <?= htmlspecialchars($imagem['imagem_nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="caminhoPasta">Caminho Pasta</label>
                        <input type="text" id="caminhoPasta" name="caminho_pasta">
                    </div>

                    <div>
                        <label for="numeroBG">Número BG</label>
                        <input type="text" id="numeroBG" name="numero_bg">
                    </div>

                    <div>
                        <label for="referenciasCaminho">Referências/Caminho</label>
                        <input type="text" id="referenciasCaminho" name="refs">
                    </div>

                    <div>
                        <label for="observacao">Observação</label>
                        <textarea id="observacao" name="obs" rows="3"></textarea>
                    </div>

                    <div>
                        <label for="status">Revisão</label>
                        <select name="status_id" id="opcao_status_pos">
                            <?php foreach ($status_imagens as $status): ?>
                                <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                                    <?= htmlspecialchars($status['nome_status']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="text" name="id-pos" id="id-pos" hidden>
                    <input type="hidden" id="alterar_imagem" name="alterar_imagem" value="false">

                    <div>
                        <label for="status_pos">Status</label>
                        <input type="checkbox" name="status_pos" id="status_pos" disabled>
                    </div>

                    <div>
                        <label for="nome_responsavel">Nome Responsável</label>
                        <select name="responsavel_id" id="responsavel_id">
                            <option value="14" id="Adriana">Adriana</option>
                            <option value="28" id="Eduardo">Eduardo</option>

                        </select>
                    </div>

                    <input type="hidden" id="render_id_pos" name="render_id_pos">


                    <div>
                        <button type="submit">Enviar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</main>

<!-- Popover unificado -->
<div id="popover-tarefas" class="popover oculto">
    <!-- Tarefas -->
    <div class="secao">
        <div class="secao-titulo secao-tarefas" onclick="toggleSecao('tarefas')">
            <strong>Tarefas</strong>
            <span id="badge-tarefas" class="badge-interna"></span>
        </div>
        <div id="conteudo-tarefas" class="secao-conteudo"></div>
    </div>

    <!-- Notificações -->
    <div class="secao">
        <div class="secao-titulo secao-notificacoes">
            <strong>Notificações</strong>
            <span id="badge-notificacoes" class="badge-interna"></span>
        </div>
        <div id="conteudo-notificacoes" class="secao-conteudo">
        </div>
    </div>
    <button id="btn-ir-revisao">Ir para Revisão</button>
</div>

<div id="modalSessao" class="modal-sessao">
    <div class="modal-conteudo">
        <h2>Sessão Expirada</h2>
        <p>Sua sessão expirou. Deseja continuar?</p>
        <button onclick="renovarSessao()">Continuar Sessão</button>
        <button onclick="sair()">Sair</button>
    </div>
</div>

<!-- <div id="modalUpload" style="display: none;">
    <div id="overlay" onclick="fecharModal()" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9;"></div>
    <input type="hidden" id="funcao_id_revisao">
    <input type="hidden" id="nome_funcao_upload">
    <div style="position: fixed; top: 50%; left: 50%; width: 600px; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 10px; z-index: 10;">
        <h2>Upload de Imagens</h2>

        <div id="drop-area">
            Arraste suas imagens aqui ou clique para selecionar
            <input type="file" id="fileElem" accept="image/*" multiple style="display:none;">
        </div>

        <ul class="file-list" id="fileList"></ul>

        <div class="buttons-upload">
            <button onclick="fecharModal()" id="cancelar" style="background-color: red;">Cancelar</button>
            <button class="upload" onclick="enviarImagens()" id="enviar" style="background-color: green;">Enviar</button>
        </div>
    </div>
</div> 
-->

<!-- MODAL ÚNICO -->
<div id="modalUpload" style="display: none;">
    <div id="overlay" onclick="fecharModal()" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9;"></div>
    <input type="hidden" id="funcao_id_revisao">
    <input type="hidden" id="nome_funcao_upload">

    <div style="position: fixed; top: 50%; left: 50%; width: 600px; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 10px; z-index: 10;">
        <h2 id="etapaTitulo">1. Envio de Prévia</h2>

        <!-- Conteúdo da etapa 1 -->
        <div id="etapaPrevia">
            <div id="drop-area-previa" class="drop-area">
                Arraste suas imagens aqui ou clique para selecionar
                <input type="file" id="fileElemPrevia" accept="image/*" multiple style="display:none;">
            </div>
            <ul class="file-list" id="fileListPrevia"></ul>
            <div class="buttons-upload">
                <button onclick="fecharModal()" style="background-color: red;">Cancelar</button>
                <button onclick="enviarImagens()" style="background-color: green;">Enviar Prévia</button>
            </div>
        </div>

        <!-- Conteúdo da etapa 2 -->
        <div id="etapaFinal" style="display: none;">
            <div id="drop-area-final" class="drop-area">
                Arraste o arquivo final aqui ou clique para selecionar
                <input type="file" id="fileElemFinal" multiple style="display:none;">
            </div>
            <ul class="file-list" id="fileListFinal"></ul>
            <div class="buttons-upload">
                <button onclick="fecharModal()" style="background-color: red;">Cancelar</button>
                <button onclick="enviarArquivo()" style="background-color: green;">Enviar Arquivo Final</button>
            </div>
        </div>

    </div>
</div>

<?php if (isset($_SESSION['idusuario']) && ($_SESSION['idusuario'] == 1 || $_SESSION['idusuario'] == 2 || $_SESSION['idusuario'] == 9)): ?>
    <div id="notificacao-sino" class="notificacao-sino">
        <i class="fas fa-bell sino" id="icone-sino"></i>
        <span id="contador-tarefas" class="contador-tarefas">0</span>
    </div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">

<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

<script src="<?php echo asset_url('script/notificacoes.js'); ?>"></script>
<script src="<?php echo asset_url('./script/script.js'); ?>"></script>
<script src="<?php echo asset_url('./script/sidebar.js'); ?>"></script>
<script src="<?php echo asset_url('./script/controleSessao.js'); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>



</body>

</html>