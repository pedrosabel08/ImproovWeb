<?php
session_start();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <title>Tabela Imagens</title>
</head>

<body>
    <div id="menu" class="hidden">
        <a href="../inicio.php" id="tab-imagens">Página Principal</a>
        <a href="../main.php" id="tab-imagens">Visualizar tabela com imagens</a>
        <a href="../Pos-Producao/index.php">Lista Pós-Produção</a>

        <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
            <a href="../infoCliente/index.php">Informações clientes</a>
            <a href="../Acompanhamento/index.html">Acompanhamentos</a>
        <?php endif; ?>

        <a href="../Metas/index.php">Metas e progresso</a>

        <a id="calendar" class="calendar-btn" href="../Calendario/index.php">
            <i class="fa-solid fa-calendar-days"></i>
        </a>
    </div>
    <header>
        <button id="menuButton">
            <i class="fa-solid fa-bars"></i>
        </button>

        <h1>Tabela imagens</h1>

        <img src="../gif/assinatura_branco.gif" alt="">
    </header>

    <main>

        <div id="filtro">
            <h1>Filtro</h1>
            <div id="filtros">
                <select id="colunaFiltro">
                    <option value="0">Cliente</option>
                    <option value="1">Obra</option>
                    <option value="2">Imagem</option>
                    <option value="3">Recebimento de arquivos</option>
                    <option value="4">Data Inicio</option>
                    <option value="5">Prazo</option>
                    <option value="6">Status</option>
                    <option value="7">Tipo imagem</option>
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
        </div>

        <div class="atualizarImagens">
            <select id="colunaFiltro">
                <option value="0">Cliente</option>
                <option value="1">Obra</option>
                <option value="2">Imagem</option>
                <option value="3">Recebimento de arquivos</option>
                <option value="4">Data Inicio</option>
                <option value="5">Prazo</option>
                <option value="6">Status</option>
                <option value="7">Tipo imagem</option>
            </select>
            <input type="text" name="" id="">
        </div>

        <div class="tabelaClientes">

            <table id="tabelaClientes">
                <thead>
                    <tr>
                        <th></th>
                        <th>Cliente</th>
                        <th>Obra</th>
                        <th>Imagem</th>
                        <th>Recebimento de arquivos</th>
                        <th>Data Inicio</th>
                        <th>Prazo</th>
                        <th>Status</th>
                        <th>Tipo Imagem</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </main>

    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal()">&times;</span>
            <h2>Formulário de Informações</h2>
            <form id="formularioModal">
                <label for="cliente">Cliente:</label>
                <input type="text" id="nome_cliente" name="nome_cliente" required readonly>

                <label for="obra">Obra:</label>
                <input type="text" id="nome_obra" name="nome_obra" required readonly>

                <label for="imagem">Imagem:</label>
                <input type="text" id="imagem_nome" name="imagem_nome" required>

                <label for="recebimento_arquivos">Recebimento de arquivos:</label>
                <input type="text" id="recebimento_arquivos" name="recebimento_arquivos" required>

                <label for="data_inicio">Data inicio:</label>
                <input type="text" id="data_inicio" name="data_inicio" required>

                <label for="imagem">Prazo:</label>
                <input type="text" id="prazo" name="prazo" required>

                <label for="nome_status">Status:</label>
                <input type="text" id="nome_status" name="nome_status" required readonly>

                <label for="tipo_imagem">Tipo Imagem:</label>
                <input type="text" id="tipo_imagem" name="tipo_imagem" required>

                <input type="text" name="idimagens_cliente_obra" id="idimagens_cliente_obra" hidden>

                <button type="submit">Salvar</button>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</body>

</html>