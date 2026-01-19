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

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhamento</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <script src="<?php echo asset_url('script.js'); ?>" defer></script>
</head>

<body>
    <header>

        <button id="menuButton">
            <i class="fa-solid fa-bars"></i>
        </button>


        <div id="menu" class="hidden">
            <a href="../inicio.php" id="tab-imagens">Página Principal</a>
            <a href="../main.php" id="tab-imagens">Visualizar tabela com imagens</a>
            <a href="../Pos-Producao/index.php">Lista Pós-Produção</a>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
                <a href="../infoCliente/index.php">Informações clientes</a>
                <a href="Acompanhamento/index.php">Acompanhamentos</a>
            <?php endif; ?>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
                <a href="../Animacao/index.php">Lista Animação</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
                <a href="../Imagens/index.php">Lista Imagens</a>
                <a href="../Pagamento/index.php">Pagamento</a>
                <a href="../Obras/index.php">Obras</a>
            <?php endif; ?>

            <a href="../Metas/index.php">Metas e progresso</a>

            <a id="calendar" class="calendar-btn" href="../Calendario/index.php">
                <i class="fa-solid fa-calendar-days"></i>
            </a>
        </div>
        <img src="../gif/assinatura_branco.gif" alt="" style="width: 200px;">
    </header>

    <main>
        <div class="coluna">
            <h2>Tabela de Acompanhamento</h2>
            <table id="tabela-acomp">
                <thead>
                    <tr>
                        <th>Obra</th>
                        <th>Colaborador</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>

        <div class="coluna">

            <div id="filtro">
                <h1>Filtro</h1>
                <div id="filtros">
                    <select id="colunaFiltro">
                        <option value="0">Obra</option>
                        <option value="1">Colaborador</option>
                        <option value="2">Assunto</option>
                        <option value="3">Data</option>
                    </select>
                    <input type="text" id="pesquisa" onkeyup="filtrarTabela()" placeholder="Buscar...">
                </div>
            </div>
            <h2>Tabela de Acompanhamento por Email</h2>
            <table id="tabela-acomp-email">
                <thead>
                    <tr>
                        <th>Obra</th>
                        <th>Colaborador</th>
                        <th>Assunto Email</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </main>
</body>

</html>