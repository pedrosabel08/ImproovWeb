document.addEventListener('DOMContentLoaded', () => {
    const columns = document.querySelectorAll('.column');
    const modal = document.getElementById('entregaModal');
    const modalTitle = document.getElementById('modalTitulo');
    const modalEtapa = document.getElementById('modalEtapa');
    const modalPrazo = document.getElementById('modalPrazo');
    const modalProgresso = document.getElementById('modalProgresso');
    const modalImagens = document.getElementById('modalImagens');
    const fecharModalBtn = document.getElementById('fecharModal');

    // botão de registrar entrega
    const btnRegistrarEntrega = document.createElement('button');
    btnRegistrarEntrega.textContent = 'Registrar Entrega';
    btnRegistrarEntrega.style.marginTop = '1rem';
    modal.querySelector('.modal-content').appendChild(btnRegistrarEntrega);

    let entregaAtualId = null;

    function formatarData(data) {
        const partes = data.split("-");
        const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
        return dataFormatada;
    }

    // fechar modal
    fecharModalBtn.addEventListener('click', () => {
        modal.style.display = 'none';
        entregaAtualId = null;
    });

    // --- FUNÇÃO PRINCIPAL PARA CARREGAR O KANBAN ---
    async function carregarKanban() {
        try {
            const res = await fetch('listar_entregas.php');
            const entregas = await res.json();

            // Limpa colunas antes de preencher
            columns.forEach(col => col.querySelectorAll('.card-entrega').forEach(card => card.remove()));

            entregas.forEach(entrega => {
                const col = document.querySelector(`.column[data-status="${entrega.kanban_status}"]`);
                if (!col) return;

                const card = document.createElement('div');
                card.classList.add('card-entrega');
                card.dataset.id = entrega.id;
                card.innerHTML = `
                    <h4>${entrega.nomenclatura} - ${entrega.nome_etapa}</h4>
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
            modalEtapa.textContent = data.nome_etapa || '-';
            modalPrazo.textContent = formatarData(data.data_prevista) || '-';
            modalProgresso.textContent = `${data.itens.filter(i => i.nome_substatus === 'RVW' || i.nome_substatus === 'DRV').length} / ${data.itens.length} finalizadas`;

            modalImagens.innerHTML = '';
            data.itens.forEach(img => {
                const div = document.createElement('div');
                div.classList.add('modal-imagem-item');
                const finalizada = (img.nome_substatus === 'RVW' || img.nome_substatus === 'DRV');
                div.innerHTML = `
                    <input type="checkbox" id="img-${img.id}" value="${img.id}">
                    <label for="img-${img.id}">${img.nome} - ${finalizada ? '✅ Finalizada' : '⏳ Em andamento'}</label>
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
