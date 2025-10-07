document.addEventListener('DOMContentLoaded', () => {

    const listaImagensEl = document.getElementById('listaImagens');
    let sortableInstance = null;
    const colunas = document.querySelectorAll('.coluna .col-cards');

    // Função para carregar cards via AJAX
    async function carregarKanban() {
        try {
            const res = await fetch('listar_entregas.php');
            const data = await res.json();

            colunas.forEach(col => col.innerHTML = ''); // limpar colunas

            data.forEach(item => {
                const colId = 'col-' + item.kanban_status.replace(' ', '-');
                const card = document.createElement('div');
                card.classList.add('card-entrega', 'status-' + item.kanban_status.replace(' ', '-'));
                card.dataset.id = item.id;
                card.innerHTML = `
                    <p><strong>Entrega ${item.id}</strong></p>
                    <div class="meta">
                        <span>${item.status}</span>
                        <span>${item.data_prevista}</span>
                    </div>
                    <div class="progress mt-1" style="height:6px;">
                        <div class="progress-bar" role="progressbar" style="width:${item.pct_entregue}%"></div>
                    </div>
                    <small>${item.entregues}/${item.total_itens} itens entregues</small>
                `;
                document.getElementById(colId)?.appendChild(card);
            });

        } catch (err) {
            console.error('Erro ao carregar Kanban:', err);
        }
    }

    carregarKanban();

    // Modal de detalhes
    document.addEventListener('click', e => {
        const card = e.target.closest('.card-entrega');
        if (!card) return;

        const id = card.dataset.id;
        fetch(`get_entrega_item.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('detalhesTitulo').innerText = `Entrega ${data.id}`;
                document.getElementById('detalhesStatus').innerText = data.status;
                document.getElementById('detalhesPrevista').innerText = data.data_prevista;
                document.getElementById('detalhesObs').innerText = data.observacoes || '-';
                document.getElementById('detalhesNomeEtapa').innerText = data.nome_etapa || '-';

                // Lista de imagens
                const detalhesImagens = document.getElementById('detalhesImagens');
                detalhesImagens.innerHTML = '';
                data.itens.forEach(img => {
                    const div = document.createElement('div');
                    div.textContent = `${img.nome} - ${img.status}`;
                    detalhesImagens.appendChild(div);
                });

                new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
            });
    });

    // Drag-and-drop para atualizar status
    colunas.forEach(col => {
        new Sortable(col, {
            group: 'kanban',
            animation: 150,
            onEnd: evt => {
                const itemId = evt.item.dataset.id;
                const newStatus = evt.to.closest('.coluna').querySelector('h5').innerText.toLowerCase();
                // atualizar visual imediatamente
                evt.item.querySelector('.meta span:first-child').innerText = evt.to.closest('.coluna').querySelector('h5').innerText;

                fetch('update_entrega_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: itemId, status: newStatus })
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) alert('Erro ao atualizar status');
                });
            }
        });
    });

    // Modal de nova entrega
    const modal = document.getElementById('modalEntrega');

    modal.addEventListener('show.bs.modal', async () => {
        await carregarImagensParaObra();
    });

    document.getElementById('obraSelect').addEventListener('change', () => carregarImagensParaObra());

    async function carregarImagensParaObra() {
        listaImagensEl.innerHTML = '<div class="text-center text-muted py-3">Carregando imagens...</div>';
        const obraId = document.getElementById('obraSelect').value;
        const url = obraId ? `get_imagens.php?obra_id=${encodeURIComponent(obraId)}` : `get_imagens.php`;

        try {
            const res = await fetch(url);
            const imagens = await res.json();

            if (!Array.isArray(imagens) || imagens.length === 0) {
                listaImagensEl.innerHTML = '<div class="text-center text-muted py-3">Nenhuma imagem encontrada para esta obra.</div>';
                return;
            }

            listaImagensEl.innerHTML = '';
            imagens.forEach(img => {
                const div = document.createElement('div');
                div.className = 'card-imagem';
                div.setAttribute('data-id', img.id);
                div.innerHTML = `
                    <div>
                        <input type="checkbox" name="imagens[]" value="${img.id}" id="img-${img.id}">
                        <label for="img-${img.id}" style="margin-left:8px">${img.nome}</label>
                    </div>
                    <small class="text-muted">⇅</small>
                `;
                listaImagensEl.appendChild(div);
            });

            // inicializar Sortable (uma única vez)
            if (sortableInstance) sortableInstance.destroy();
            sortableInstance = new Sortable(listaImagensEl, { animation: 150, ghostClass: 'sortable-ghost' });

        } catch (err) {
            console.error(err);
            listaImagensEl.innerHTML = '<div class="text-danger">Erro ao carregar imagens</div>';
        }
    }

    // Submit do formulário de nova entrega
    document.getElementById('formEntrega').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const form = ev.target;

        const formData = new FormData();
        formData.append('obra_id', form.obra_id.value);
        formData.append('data_prevista', form.data_prevista.value);
        formData.append('tipo', form.tipo.value);
        formData.append('observacoes', form.observacoes.value);

        const selectedIds = [];
        const nodes = Array.from(listaImagensEl.querySelectorAll('.card-imagem'));
        nodes.forEach(n => {
            const checkbox = n.querySelector('input[type="checkbox"]');
            if (checkbox && checkbox.checked && !selectedIds.includes(checkbox.value)) {
                formData.append('imagens[]', checkbox.value);
                selectedIds.push(checkbox.value);
            }
        });

        try {
            const res = await fetch('save_entrega.php', { method: 'POST', body: formData });
            const json = await res.json();

            if (json.success) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                bsModal.hide();
                form.reset();
                listaImagensEl.innerHTML = '<div class="text-center text-muted py-3">Nenhuma imagem selecionada</div>';
                await carregarKanban();
                alert('Entrega salva com sucesso! ID: ' + json.entrega_id);
            } else {
                alert('Erro ao salvar: ' + (json.error || 'Erro desconhecido'));
            }

        } catch (err) {
            console.error(err);
            alert('Erro ao salvar entrega (ver console).');
        }
    });
});
