<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />

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
                <li><a title="Lista Pós-Produção" href="https://improov.com.br/sistema/Pos-Producao"><i class="fas fa-list"></i><span> Lista Pós-Produção</span></a></li>
                <li><a title="Lista Alteração" href="https://improov.com.br/sistema/Alteracao"><i class="fa-solid fa-user-pen"></i><span> Lista Alteração</span></a></li>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
                    <!-- <li><a title="Infos Cliente" href="https://improov.com.br/sistema/infoCliente"><i class="fas fa-chart-line"></i><span> Infos Cliente</span></a></li> -->
                <?php endif; ?>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
                    <li><a title="Lista Animação" href="https://improov.com.br/sistema/Animacao"><i class="fas fa-film"></i><span> Lista Animação</span></a></li>
                <?php endif; ?>

                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
                    <li><a title="Pagamento" href="https://improov.com.br/sistema/Pagamento"><i class="fas fa-money-bill-wave"></i><span> Pagamento</span></a></li>
                    <!-- <li><a title="Obras" href="https://improov.com.br/sistema/Obras"><i class="fas fa-building"></i><span> Obras</span></a></li> -->
                    <li><a title="Dashboard" href="https://improov.com.br/sistema/Dashboard"><i class="fa-solid fa-chart-line"></i><span> Dashboard</span></a></li>
                    <li><a title="Projetos" href="https://improov.com.br/sistema/Projetos"><i class="fa-solid fa-diagram-project"></i><span> Projetos</span></a></li>
                <?php endif; ?>
            </ul>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>

                <ul class="division">
                    <label for="">Comercial</label>
                    <li><a title="Tela Gerencial" href="https://improov.com.br/sistema/TelaGerencial"><i class="fa-solid fa-desktop"></i><span> Tela Gerencial</span></a></li>
                    <li><a title="Tela de custos" href="https://improov.com.br/sistema/Custos"><i class="fa-solid fa-desktop"></i><span> Tela Custos</span></a></li>
                </ul>
            <?php endif; ?>



            <ul class="division" id="favoritos">
                <label for="">Favoritos</label>
            </ul>

            <ul class="division">
                <label for="">Ferramentas</label>
                <li><a title="Filtro Colaborador" href="https://improov.com.br/sistema/main.php#filtro-colab"><i class="fa-solid fa-user"></i><span>Filtro Colaborador</span></a></li>
                <li><a title="Lista Render" href="https://improov.com.br/sistema/Render"><i class="fas fa-list"></i><span> Lista Render</span></a></li>
                <li><a title="Flow Review" href="https://improov.com.br/sistema/Revisao"><i class="fas fa-check"></i><span> Flow Review</span></a></li>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
                    <li><a title="Adicionar cliente ou obra" href="https://improov.com.br/sistema/main.php#add-cliente"><i class="fa-solid fa-person"></i><span>Adicionar cliente ou obra</span></a></li>
                    <li><a title="Gerenciar prioridades" href="https://improov.com.br/sistema/Prioridade"><i class="fa-solid fa-user-plus"></i><span>Gerenciar prioridades</span></a></li>
                    <li><a title="Quadro TEA" href="https://improov.com.br/sistema/Quadro"><i class="fa-solid fa-columns"></i><span>Quadro TEA</span></a></li>


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