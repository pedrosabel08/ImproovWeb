<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kanban de Entregas</title>
    <style>
        :root {
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --column-bg: #181818;
            --text-color: #f5f5f5;
            --accent-color: #00adb5;
            --border-color: #2a2a2a;
            --hover-bg: #222;
            --modal-bg: #1a1a1a;
        }

        body {
            margin: 0;
            font-family: "Inter", sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        header {
            padding: 1rem 2rem;
            background: var(--column-bg);
            border-bottom: 1px solid var(--border-color);
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--accent-color);
        }

        main {
            display: flex;
            flex: 1;
            overflow-x: auto;
            padding: 1rem;
            gap: 1rem;
        }

        .column {
            background: var(--column-bg);
            flex: 1;
            min-width: 260px;
            border-radius: 12px;
            padding: 0.8rem;
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            border: 1px solid var(--border-color);
        }

        .column h2 {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--accent-color);
            margin: 0;
            margin-bottom: 0.4rem;
            text-align: center;
        }

        /* CARD */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.8rem;
            cursor: grab;
            transition: all 0.2s ease;
        }

        .card:hover {
            background: var(--hover-bg);
            transform: scale(1.02);
        }

        .card .obra {
            font-weight: 600;
            color: var(--accent-color);
        }

        .card .etapa {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .card .prazo {
            font-size: 0.85rem;
            color: #ccc;
        }

        .progress-bar {
            background: var(--border-color);
            border-radius: 6px;
            overflow: hidden;
            margin-top: 6px;
            height: 8px;
        }

        .progress {
            background: var(--accent-color);
            height: 100%;
            transition: width 0.3s ease;
        }

        .column.drag-over {
            background: #202020;
            border: 1px dashed var(--accent-color);
        }

        ::-webkit-scrollbar {
            height: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent-color);
            border-radius: 4px;
        }

        /* ===== Modal ===== */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--modal-bg);
            padding: 1.5rem;
            border-radius: 12px;
            width: 450px;
            box-shadow: 0 0 10px #000;
            animation: fadeIn 0.2s ease;
        }

        .modal-content h3 {
            margin-top: 0;
            color: var(--accent-color);
        }

        .modal-content p {
            margin: 0.5rem 0;
        }

        .modal button {
            margin-top: 1rem;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.2rem;
            cursor: pointer;
            transition: 0.2s;
        }

        .modal button:hover {
            background: #00c1c9;
        }

        .image-item {
            background: #1f1f1f;
            padding: 0.5rem 0.8rem;
            border-radius: 8px;
            margin-top: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            border: 1px solid #2b2b2b;
        }

        @keyframes fadeIn {
            from {
                transform: scale(0.95);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
</head>

<body>
    <header>📦 Kanban de Entregas</header>

    <main id="kanban">
        <div class="column" data-status="a_entregar">
            <h2>A entregar</h2>
        </div>
        <div class="column" data-status="aguardando_feedback">
            <h2>Enviado / Aguardando feedback</h2>
        </div>
        <div class="column" data-status="revisao">
            <h2>Revisão necessária</h2>
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

    <script>
        // ===== Dados ilusórios =====
        const entregas = [{
                id: 1,
                obra: "Residencial Harmonia",
                etapa: "R00",
                prazo: "2025-10-10",
                imagens: [{
                        nome: "Living",
                        pct: 70
                    },
                    {
                        nome: "Fachada",
                        pct: 100
                    },
                    {
                        nome: "Garagem",
                        pct: 50
                    }
                ],
                status: "a_entregar"
            },
            {
                id: 2,
                obra: "Edifício Solaris",
                etapa: "EF",
                prazo: "2025-10-05",
                imagens: [{
                        nome: "Piscina",
                        pct: 100
                    },
                    {
                        nome: "Churrasqueira",
                        pct: 100
                    }
                ],
                status: "aguardando_feedback"
            },
            {
                id: 3,
                obra: "Condomínio Bela Vista",
                etapa: "R02",
                prazo: "2025-09-28",
                imagens: [{
                        nome: "Playground",
                        pct: 50
                    },
                    {
                        nome: "Jardim",
                        pct: 40
                    },
                    {
                        nome: "Portaria",
                        pct: 30
                    }
                ],
                status: "atrasadas"
            }
        ];

        // ===== Renderização inicial =====
        const kanban = document.getElementById('kanban');

        function calcularMedia(imagens) {
            const soma = imagens.reduce((acc, img) => acc + img.pct, 0);
            return Math.round(soma / imagens.length);
        }

        function renderKanban() {
            document.querySelectorAll('.column').forEach(col => col.innerHTML = `<h2>${col.querySelector('h2').innerText}</h2>`);
            entregas.forEach(ent => {
                const col = document.querySelector(`[data-status="${ent.status}"]`);
                if (!col) return;

                const media = calcularMedia(ent.imagens);

                const card = document.createElement('div');
                card.className = "card";
                card.dataset.id = ent.id;
                card.innerHTML = `
      <div class="obra">${ent.obra}</div>
      <div class="etapa">Etapa: ${ent.etapa}</div>
      <div class="prazo">Prazo: ${ent.prazo}</div>
      <div class="progress-bar"><div class="progress" style="width:${media}%;"></div></div>
      <small>${media}% concluído</small>
    `;
                card.addEventListener('click', () => abrirModal(ent.id));
                col.appendChild(card);
            });
        }

        renderKanban();

        // ===== Modal =====
        const modal = document.getElementById('entregaModal');
        const fecharModal = document.getElementById('fecharModal');

        function abrirModal(id) {
            const ent = entregas.find(e => e.id == id);
            if (!ent) return;

            const media = calcularMedia(ent.imagens);
            document.getElementById('modalTitulo').textContent = ent.obra;
            document.getElementById('modalEtapa').textContent = ent.etapa;
            document.getElementById('modalPrazo').textContent = ent.prazo;
            document.getElementById('modalProgresso').textContent = `${media}%`;

            const imagensEl = document.getElementById('modalImagens');
            imagensEl.innerHTML = "";
            ent.imagens.forEach(img => {
                const div = document.createElement('div');
                div.className = "image-item";
                div.innerHTML = `<span>${img.nome}</span> <span>${img.pct}%</span>`;
                imagensEl.appendChild(div);
            });

            modal.style.display = 'flex';
        }

        fecharModal.addEventListener('click', () => modal.style.display = 'none');
        modal.addEventListener('click', e => {
            if (e.target === modal) modal.style.display = 'none';
        });
    </script>
</body>

</html>