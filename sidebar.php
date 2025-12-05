<?php /* atualiza_log_tela.php removed from server-side include to avoid duplicate/incorrect entries; client-side fetch below records the title/url. */ ?>
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
        <!-- <button id="menuButton">
            <i class="fa-solid fa-bars"></i>
        </button> -->
        <img id="gif" src="https://improov.com.br/flow/ImproovWeb/gif/assinatura_preto.gif" alt="">
        <ul>
            <ul class="division">
                <label for="">Insights</label>
                <li><a title="Página Principal" href="https://improov.com.br/flow/ImproovWeb/inicio.php"><i class="fas fa-home"></i><span> Página Principal</span></a></li>
                <li><a title="Lista Pós-Produção" href="https://improov.com.br/flow/ImproovWeb/Pos-Producao"><i class="fas fa-list"></i><span> Lista Pós-Produção</span></a></li>
                <!-- <li><a title="Lista Alteração" href="https://improov.com.br/flow/ImproovWeb/Alteracao"><i class="fa-solid fa-user-pen"></i><span> Lista Alteração</span></a></li> -->
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
                    <li><a title="Recebimento de arquivos" href="https://improov.com.br/flow/ImproovWeb/Arquivos"><i class="fas fa-file"></i><span> Arquivos</span></a></li>
                    <!-- <li><a title="Infos Cliente" href="https://improov.com.br/flow/ImproovWeb/infoCliente"><i class="fas fa-chart-line"></i><span> Infos Cliente</span></a></li> -->
                <?php endif; ?>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
                    <!-- <li><a title="Lista Animação" href="https://improov.com.br/flow/ImproovWeb/Animacao"><i class="fas fa-film"></i><span> Lista Animação</span></a></li> -->
                <?php endif; ?>

                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
                    <!-- <li><a title="Obras" href="https://improov.com.br/flow/ImproovWeb/Obras"><i class="fas fa-building"></i><span> Obras</span></a></li> -->
                    <li><a title="Dashboard" href="https://improov.com.br/flow/ImproovWeb/Dashboard"><i class="fa-solid fa-chart-line"></i><span> Dashboard</span></a></li>
                    <li><a title="Projetos" href="https://improov.com.br/flow/ImproovWeb/Projetos"><i class="fa-solid fa-diagram-project"></i><span> Projetos</span></a></li>
                    <li><a title="Entregas" href="https://improov.com.br/flow/ImproovWeb/Entregas"><i class="fa-solid fa-diagram-project"></i><span> Entregas</span></a></li>
                    <li><a title="Gestão" href="https://improov.com.br/flow/ImproovWeb/Gestao"><i class="fa-solid fa-diagram-project"></i><span> Gestão</span></a></li>
                <?php endif; ?>
            </ul>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>

                <ul class="division">
                    <label for="">Comercial</label>
                    <li><a title="Tela Gerencial" href="https://improov.com.br/flow/ImproovWeb/TelaGerencial"><i class="fa-solid fa-desktop"></i><span> Tela Gerencial</span></a></li>
                </ul>
            <?php endif; ?>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>

                <ul class="division">
                    <label for="">Financeiro</label>
                    <li><a title="Tela de custos" href="https://improov.com.br/flow/ImproovWeb/Custos"><i class="fa-solid fa-desktop"></i><span> Tela Custos</span></a></li>
                    <li><a title="Pagamento" href="https://improov.com.br/flow/ImproovWeb/Pagamento"><i class="fas fa-money-bill-wave"></i><span> Pagamento</span></a></li>

                </ul>
            <?php endif; ?>



            <ul class="division" id="favoritos">
                <label for="">Favoritos</label>
            </ul>

            <ul class="division">
                <label for="">Ferramentas</label>
                <!-- <li><a title="Filtro Colaborador" href="https://improov.com.br/flow/ImproovWeb/main.php#filtro-colab"><i class="fa-solid fa-user"></i><span>Filtro Colaborador</span></a></li> -->
                <li><a title="Lista Render" href="https://improov.com.br/flow/ImproovWeb/Render"><i class="fas fa-list"></i><span> Lista Render</span></a></li>
                <li><a title="Flow Review" href="https://improov.com.br/flow/ImproovWeb/Revisao"><i class="fas fa-check"></i><span> Flow Review</span></a></li>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
                    <li><a title="Adicionar cliente ou obra" href="https://improov.com.br/flow/ImproovWeb/main.php#add-cliente"><i class="fa-solid fa-person"></i><span>Adicionar cliente ou obra</span></a></li>
                    <li><a title="Gerenciar prioridades" href="https://improov.com.br/flow/ImproovWeb/Prioridade"><i class="fa-solid fa-user-plus"></i><span>Gerenciar prioridades</span></a></li>
                    <li><a title="Quadro TEA" href="https://improov.com.br/flow/ImproovWeb/Quadro"><i class="fa-solid fa-columns"></i><span>Quadro TEA</span></a></li>

                <?php endif; ?>

            </ul>
            <ul id="obras-list" class="division">
                <label for="">Obras</label>
                <?php foreach ($obras as $obra): ?>
                    <li class="obra">
                        <i class="fa fa-star favorite-icon" data-id="<?= $obra['idobra']; ?>" title="<?= htmlspecialchars($obra['nomenclatura']); ?>"></i>
                        <a title="<?= htmlspecialchars($obra['nomenclatura']); ?>" href="#" class="obra-item" data-id="<?= $obra['idobra']; ?>" data-name="<?= htmlspecialchars($obra['nomenclatura']); ?>">
                            <span><?= htmlspecialchars($obra['nomenclatura']); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <ul id="obras-list" class="division">
                <label for="">Usuário</label>
                <li><a title="Informações do Usuário" href="https://improov.com.br/flow/ImproovWeb/infos.php"><i class="fa-solid fa-user"></i><span>Informações</span></a></li>
            </ul>
        </ul>
    </div>
</body>

</html>
 
<script src="/flow/ImproovWeb/assets/js/upload-ws.js"></script>

<script>
    // Envia o title e url ao servidor para registrar histórico com título amigável
    (function() {
        try {
            if (!window.fetch) return;
            const payload = new FormData();
            payload.append('title', document.title || 'Página');
            payload.append('url', window.location.href);

            // envia de forma assíncrona; o endpoint ignora se usuário não autenticado
            // usa o caminho absoluto para evitar requests relativos incorretos
            const endpoint = window.location.origin + '/flow/ImproovWeb/atualiza_log_tela.php';
            fetch(endpoint, {
                method: 'POST',
                body: payload,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(resp => resp.json().then(js => {
                if (!js.ok) console.debug('atualiza_log_tela response:', js);
            })).catch(err => { console.debug('atualiza_log_tela fetch error', err); });
        } catch (e) {
            // noop
        }
    })();
</script>