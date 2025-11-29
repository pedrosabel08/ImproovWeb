<?php
require_once __DIR__ . '/../conexao.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Planner — Entregas</title>
    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <div id="planner">
        <aside id="left-panel">
            <header class="panel-header">
                <h2>Entregas</h2>
                <small>Ordenadas por prioridade temporal</small>
            </header>
            <div id="groups">
                <!-- Grupos serão renderizados via JS -->
            </div>
        </aside>

        <main id="right-panel">
            <header class="detail-header">
                <h2 id="detail-title">Selecione uma entrega</h2>
                <div class="detail-meta">
                    <div><strong>Prazo:</strong> <span id="detail-prazo">-</span></div>
                    <div><strong>Status:</strong> <span id="detail-status">-</span></div>
                    <div><strong>Progresso:</strong> <span id="detail-progresso">-</span></div>
                </div>
            </header>

            <section class="filters">
                <div class="filter">
                    <label for="filter-responsavel">Responsável</label>
                    <select id="filter-responsavel">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="filter">
                    <label for="filter-etapa">Etapa</label>
                    <select id="filter-etapa">
                        <option value="">Todas</option>
                        <option value="Render">Render</option>
                        <option value="Em render">Em render</option>
                        <option value="Pós-produção">Pós-produção</option>
                        <option value="Finalização">Finalização</option>
                        <option value="Em aprovação">Em aprovação</option>
                        <option value="Aguardando aprovação">Aguardando aprovação</option>
                        <option value="Entregue">Entregue</option>
                    </select>
                </div>
            </section>

            <section id="images-list">
                <!-- Lista de imagens da entrega selecionada -->
            </section>
        </main>
    </div>

    <script>
        const API_LISTAR = '../Entregas/listar_entregas.php';
        const API_DETALHE = 'get_entrega_detalhe.php';
    </script>
    <script src="script.js"></script>
</body>

</html>