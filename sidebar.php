<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>Sidebar</title>
</head>

<body>
    <div class="sidebar mini">
        <button id="menuButton">
            <i class="fa-solid fa-bars"></i>
        </button>
        <ul>
            <ul class="division">
                <label for="">Insights</label>
                <li><a title="Página Principal" href="https://improov.com.br/sistema/inicio.php"><i class="fas fa-home"></i><span> Página Principal</span></a></li>
                <li><a title="Lista Pós-Produção" href="https://improov.com.br/sistema/Pos-Producao/index.php"><i class="fas fa-list"></i><span> Lista Pós-Produção</span></a></li>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
                    <li><a title="Acompanhamentos" href="https://improov.com.br/sistema/Acompanhamento/index.php"><i class="fas fa-chart-line"></i><span> Acompanhamentos</span></a></li>
                <?php endif; ?>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
                    <li><a title="Lista Animação" href="https://improov.com.br/sistema/Animacao/index.php"><i class="fas fa-film"></i><span> Lista Animação</span></a></li>
                <?php endif; ?>

                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
                    <li><a title="Pagamento" href="https://improov.com.br/sistema/Pagamento/index.php"><i class="fas fa-money-bill-wave"></i><span> Pagamento</span></a></li>
                    <li><a title="Obras" href="https://improov.com.br/sistema/Obras/index.php"><i class="fas fa-building"></i><span> Obras</span></a></li>
                <?php endif; ?>

            </ul>


            <ul class="division" id="favoritos">
                <label for="">Favoritos</label>
            </ul>

            <ul class="division">
                <label for="">Ferramentas</label>
                <li><a title="Calendário" href="https://improov.com.br/sistema/Calendario/index.php"><i class="fa-solid fa-calendar-days"></i><span>Calendário</span></a></li>
                <li><a title="Filtro Colaborador" href="https://improov.com.br/sistema/main.php#filtro-colab"><i class="fa-solid fa-user"></i><span>Filtro Colaborador</span></a></li>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
                    <li><a title="Adicionar cliente ou obra" href="https://improov.com.br/sistema/main.php#add-cliente"><i class="fa-solid fa-person"></i><span>Adicionar cliente ou obra</span></a></li>
                <?php endif; ?>

            </ul>
            <ul id="obras-list" class="division">
                <label for="">Obras</label>
                <?php foreach ($obras as $obra): ?>
                    <li class="obra">
                        <i class="fa fa-star favorite-icon" data-id="<?= $obra['idobra']; ?>" title="<?= htmlspecialchars($obra['nomenclatura']); ?>"></i>
                        <a title="<?= htmlspecialchars($obra['nomenclatura']); ?>" href="#" class="obra-item" data-id="<?= $obra['idobra']; ?>" data-name="<?= htmlspecialchars($obra['nome_obra']); ?>">
                            <span><?= htmlspecialchars($obra['nomenclatura']); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </ul>
    </div>
</body>

</html>