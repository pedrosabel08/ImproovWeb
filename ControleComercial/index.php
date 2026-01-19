<?php
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

session_start();

if (!isset($_SESSION['logado']) || !$_SESSION['logado'] || !isset($_SESSION['nivel_acesso']) || $_SESSION['nivel_acesso'] != 1) {
    header("Location: ../index.html"); // Redireciona para a página de login
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleComercial.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">

    <title>Document</title>
</head>

<body>
    <div class="header">
        <h1>Controle comercial</h1>
        <button id="openModalBtn">Adicionar orçamento</button>
    </div>

    <form id="process_csv" action="process_csv.php" method="POST" enctype="multipart/form-data">
        <label for="csvFile">Selecione o arquivo CSV com as imagens:</label>
        <input type="file" name="csvFile" accept=".csv">
        <button type="submit">Upload CSV</button>
    </form>

    <main>
        <div id="filtro">
            <label for="colunaFiltro">Filtrar por:</label>
            <select id="colunaFiltro">
                <option value="0">Responsável</option>
            </select>

            <select id="filtro-select" onchange="filtrarTabela()">
                <option value="">Todos</option>
                <option value="diogo">Diogo</option>
                <option value="carol">Carol</option>
            </select>
        </div>
        <div class="tabela-orcamentos">

            <table>
                <thead>
                    <tr>
                        <th>Responsável</th>
                        <th>Contato</th>
                        <th>Construtora</th>
                        <th>Obra</th>
                        <th>R$</th>
                        <th>Status</th>
                        <th>Mês fechamento</th>
                    </tr>
                </thead>
                <tr>
                    <tbody id="lista-orcamentos"></tbody>
                </tr>
            </table>
        </div>

    </main>

    <div class="modal" id="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="form-inserir">

                <form id="form_comercial">

                    <div>
                        <label for="">Responsável:</label>
                        <select name="resp" id="resp">
                            <option value="Diogo">Diogo</option>
                            <option value="Carol">Carol</option>
                        </select>

                        <label for="">Contato:</label>
                        <input type="text" name="contato" id="contato">

                        <label for="">Construtora:</label>
                        <input type="text" name="construtora" id="construtora">

                        <label for="">Obra:</label>
                        <input type="text" name="obra" id="obra">

                        <label for="">R$:</label>
                        <input type="text" name="valor" id="valor">

                        <label for="">Status:</label>
                        <select name="status" id="status">
                            <option value="Fechado">Fechado</option>
                            <option value="Aberto">Aberto</option>
                            <option value="Perdido">Perdido</option>
                        </select>

                        <label for="">Mês:</label>
                        <select id="mes" name="mes">
                            <option value="Janeiro">Janeiro</option>
                            <option value="Fevereiro">Fevereiro</option>
                            <option value="Março">Março</option>
                            <option value="Abril">Abril</option>
                            <option value="Maio">Maio</option>
                            <option value="Junho">Junho</option>
                            <option value="Julho">Julho</option>
                            <option value="Agosto">Agosto</option>
                            <option value="Setembro">Setembro</option>
                            <option value="Outubro">Outubro</option>
                            <option value="Novembro">Novembro</option>
                            <option value="Dezembro">Dezembro</option>
                        </select>
                    </div>
                    <input type="text" name="idcontrole" id="idcontrole" hidden>
                    <div id="button">
                        <button type="submit" id="enviar">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
</body>

</html>