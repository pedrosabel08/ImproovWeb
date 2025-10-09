<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kanban de Entregas</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header>📦 Kanban de Entregas</header>

    <main id="kanban">
        <div class="column" data-status="pendente">
            <h2>A entregar</h2>
        </div>
        <div class="column" data-status="concluida">
            <h2>Enviado / Aguardando feedback</h2>
        </div>
        <div class="column" data-status="atrasadas">
            <h2>Atrasadas</h2>
        </div>
        <div class="column" data-status="aprovadas">
            <h2>Aprovadas</h2>
        </div>
    </main>

    <!-- Modal -->
    <div class="modal" id="entregaModal">
        <div class="modal-content">
            <h3 id="modalTitulo"></h3>
            <p><strong>Etapa:</strong> <span id="modalEtapa"></span></p>
            <p><strong>Prazo:</strong> <span id="modalPrazo"></span></p>
            <p><strong>Conclusão geral:</strong> <span id="modalProgresso"></span></p>
            <h4 style="margin-top:1rem; color: var(--accent-color);">📸 Imagens</h4>
            <div id="modalImagens"></div>
            <button id="fecharModal">Fechar</button>
        </div>
    </div>

    <script src="script.js"></script>
</body>

</html>