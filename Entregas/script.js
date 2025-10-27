document.addEventListener('DOMContentLoaded', () => {
    const columns = document.querySelectorAll('.column');
    const modal = document.getElementById('entregaModal');
    const modalTitle = document.getElementById('modalTitulo');
    const modalEtapa = document.getElementById('modalEtapa');
    const modalPrazo = document.getElementById('modalPrazo');
    const modalProgresso = document.getElementById('modalProgresso');
    const modalImagens = document.getElementById('modalImagens');

    // botão de registrar entrega
    const btnRegistrarEntrega = document.createElement('button');
    btnRegistrarEntrega.textContent = 'Registrar Entrega';
    btnRegistrarEntrega.classList.add('btn-salvar');
    modal.querySelector('#entregaModal .buttons').appendChild(btnRegistrarEntrega);

    let entregaAtualId = null;

    function formatarData(data) {
        const partes = data.split("-");
        const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
        return dataFormatada;
    }

    // fechar modal: single handler for all buttons with class .fecharModal
    document.querySelectorAll('.fecharModal').forEach(btn => {
        btn.addEventListener('click', (e) => {
            // prevent accidental form submission or default button behaviour
            e.preventDefault();

            const addModal = document.getElementById('modalAdicionarEntrega');
            const entregaModal = document.getElementById('entregaModal');

            if (addModal) addModal.style.display = 'none';
            if (entregaModal) entregaModal.style.display = 'none';

            entregaAtualId = null;
        });
    });

    // --- FUNÇÃO PRINCIPAL PARA CARREGAR O KANBAN ---
    async function carregarKanban() {
        try {
            const res = await fetch('listar_entregas.php');
            const entregas = await res.json();

            // Limpa colunas antes de preencher
            columns.forEach(col => col.querySelectorAll('.card-entrega').forEach(card => card.remove()));

            entregas.forEach(entrega => {
                // Busca a coluna cujo data-status contém o status da entrega
                const col = Array.from(document.querySelectorAll('.column')).find(c => {
                    const statuses = c.dataset.status.split(',').map(s => s.trim());
                    return statuses.includes(entrega.kanban_status);
                });

                if (!col) return;

                const card = document.createElement('div');
                card.classList.add('card-entrega');
                card.dataset.id = entrega.id;
                // Badge de imagens prontas para entrega (somente exibida no Kanban)
                const readyCount = parseInt(entrega.ready_count || 0, 10);

                card.innerHTML = `
                <div class="card-header">
                    <h4>${entrega.nomenclatura} - ${entrega.nome_etapa}</h4>
                    ${readyCount > 0 ? `<div class="entrega-badge" title="Imagens prontas para entrega">${readyCount}</div>` : ''}
                </div>
                <p><strong>Status:</strong> ${entrega.status}</p>
                <p><strong>Prazo:</strong> ${formatarData(entrega.data_prevista)}</p>
                <div class="progress">
                    <div class="progress-bar" style="width:${entrega.pct_entregue}%"></div>
                </div>
                <small>${entrega.entregues}/${entrega.total_itens} imagens entregues</small>
            `;
                col.appendChild(card);
            });
        } catch (err) {
            console.error('Erro ao carregar o Kanban:', err);
        }
    }


    carregarKanban();

    // --- ABRIR MODAL AO CLICAR EM UM CARD ---
    document.addEventListener('click', async e => {
        const card = e.target.closest('.card-entrega');
        if (!card) return;

        entregaAtualId = card.dataset.id;

        try {
            const res = await fetch(`get_entrega_item.php?id=${entregaAtualId}`);
            const data = await res.json();

            modalTitle.textContent = `${data.nomenclatura || 'Entrega'} - ${data.nome_etapa || data.id}`;
            modalPrazo.textContent = formatarData(data.data_prevista) || '-';
            modalProgresso.textContent = `${data.itens.filter(i => i.nome_substatus === 'RVW' || i.nome_substatus === 'DRV').length} / ${data.itens.length} finalizadas`;

            modalImagens.innerHTML = '';
            data.itens.forEach(img => {
                const div = document.createElement('div');
                div.classList.add('modal-imagem-item');

                const finalizada = (img.nome_substatus === 'RVW' || img.nome_substatus === 'DRV');
                const entregue = /^entrega/i.test(img.status) || /^entregue/i.test(img.status);
                // regex para pegar status que começam com "Entrega" ou "Entregue" (sem case sensitive)

                // Se estiver entregue, checkbox vem marcado e desabilitado
                const checked = entregue ? 'checked' : '';
                const disabled = entregue ? 'disabled' : '';

                div.innerHTML = `
                <input type="checkbox" id="img-${img.id}" value="${img.id}" ${checked} ${disabled}>
                <label for="img-${img.id}" class="imagem_nome">
                    ${img.nome}
                </label>
                <span class="entregue">${entregue ? '📦 Entregue' : finalizada ? '✅ Finalizada' : '⏳ Em andamento'}</span>
            `;
                modalImagens.appendChild(div);
            });

            modal.style.display = 'flex';
        } catch (err) {
            console.error('Erro ao carregar detalhes da entrega:', err);
        }
    });


    // --- REGISTRAR ENTREGA ---
    btnRegistrarEntrega.addEventListener('click', async () => {
        if (!entregaAtualId) return;

        const checkboxes = modalImagens.querySelectorAll('input[type="checkbox"]:checked:not([disabled])');
        if (checkboxes.length === 0) {
            alert('Nenhuma imagem selecionada para entrega.');
            return;
        }

        const imagens = Array.from(checkboxes).map(cb => cb.value);

        try {
            const res = await fetch('registrar_entrega.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ entrega_id: entregaAtualId, imagens_entregues: imagens })
            });
            const json = await res.json();

            if (json.success) {
                alert(`Entrega registrada! Status: ${json.novo_status}`);
                modal.style.display = 'none';
                entregaAtualId = null;
                carregarKanban();
            } else {
                alert('Erro ao registrar entrega: ' + (json.error || 'desconhecido'));
            }
        } catch (err) {
            console.error('Erro ao registrar entrega:', err);
            alert('Erro ao registrar entrega (ver console)');
        }
    });

    // --- DRAG AND DROP ---
    columns.forEach(col => {
        col.addEventListener('dragover', e => e.preventDefault());
        col.addEventListener('drop', async e => {
            e.preventDefault();
            const cardId = e.dataTransfer.getData('text/plain');
            const card = document.querySelector(`.card-entrega[data-id="${cardId}"]`);
            if (!card) return;
            col.appendChild(card);

            const newStatus = col.dataset.status;

            try {
                const res = await fetch('update_entrega_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: cardId, status: newStatus })
                });
                const result = await res.json();
                if (!result.success) alert('Erro ao atualizar status!');
            } catch (err) {
                console.error('Erro ao mover card:', err);
            }
        });
    });

    // --- Habilitar drag nos cards ---
    document.addEventListener('dragstart', e => {
        if (e.target.classList.contains('card-entrega')) {
            e.dataTransfer.setData('text/plain', e.target.dataset.id);
        }
    });
});

document.getElementById('adicionar_entrega').addEventListener('click', function () {
    document.getElementById('modalAdicionarEntrega').style.display = 'flex';
})

document.getElementById('obra_id').addEventListener('change', carregarImagens);
document.getElementById('status_id').addEventListener('change', carregarImagens);

function carregarImagens() {
    const obraId = document.getElementById('obra_id').value;
    const statusId = document.getElementById('status_id').value;

    if (!obraId || !statusId) {
        document.getElementById('imagens_container').innerHTML = '<p>Selecione uma obra e um status.</p>';
        return;
    }

    fetch(`get_imagens.php?obra_id=${obraId}&status_id=${statusId}`)
        .then(res => res.json())
        .then(imagens => {
            const container = document.getElementById('imagens_container');
            container.innerHTML = '';

            if (!imagens.length) {
                container.innerHTML = '<p>Nenhuma imagem encontrada para esses critérios.</p>';
                return;
            }

            imagens.forEach(img => {
                const div = document.createElement('div');
                div.classList.add('checkbox-item');
                div.innerHTML = `
            <input type="checkbox" name="imagem_ids[]" value="${img.id}">
            <span>${img.nome}</span>
        `;
                container.appendChild(div);
            });
        })
        .catch(err => {
            console.error('Erro ao carregar imagens:', err);
        });
}

// enviar form via AJAX
document.getElementById('formAdicionarEntrega').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('save_entrega.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Entrega adicionada com sucesso!');
                // Aqui você pode atualizar a tabela, fechar modal, etc.
                document.getElementById('formAdicionarEntrega').reset();
                document.getElementById('imagens_container').innerHTML = '<p>Selecione uma obra e status.</p>';
            } else {
                alert('Erro: ' + data.msg);
            }
        })
        .catch(err => console.error('Erro:', err));
});
