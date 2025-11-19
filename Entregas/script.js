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
    let entregaDados = null; // guarda dados retornados por get_entrega_item.php para uso posterior

    function formatarData(data) {
        const partes = data.split("-");
        const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
        return dataFormatada;
    }

    // fechar modal: single handler for all buttons with class .fecharModal
    // Instead of closing based only on existence of a modal element,
    // close the closest modal container to the clicked button so other
    // modals are unaffected.
    document.querySelectorAll('.fecharModal').forEach(btn => {
        btn.addEventListener('click', (e) => {
            // prevent accidental form submission or default button behaviour
            e.preventDefault();

            // try to find the closest modal container for this button
            // (covers the known modal IDs used in this file)
            const modalToClose = btn.closest('#modalSelecionarImagens, #modalAdicionarEntrega, #entregaModal');

            if (modalToClose) {
                modalToClose.style.display = 'none';
            } else {
                // fallback: hide any open known modal
                const selecionarModal = document.getElementById('modalSelecionarImagens');
                const addModal = document.getElementById('modalAdicionarEntrega');
                const entregaModal = document.getElementById('entregaModal');

                if (selecionarModal && selecionarModal.style.display !== 'none') selecionarModal.style.display = 'none';
                else if (addModal && addModal.style.display !== 'none') addModal.style.display = 'none';
                else if (entregaModal && entregaModal.style.display !== 'none') entregaModal.style.display = 'none';
            }

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
            // salvar dados para uso por outros handlers (ex: adicionar imagem por id)
            entregaDados = data;
            modalPrazo.textContent = formatarData(data.data_prevista) || '-';
            // Use the same delivered logic as the list below to compute finalized count
            const finalizedCount = data.itens.filter(i => {
                const statusStr = (i.status || '').toString().trim();
                const substatus = (i.nome_substatus || '').toString().trim().toUpperCase();
                const isPendente = (statusStr === 'Entrega pendente') || (substatus === 'RVW') || (substatus === 'DRV');
                const entregue = !isPendente && (/^entrega/i.test(statusStr) || /^entregue/i.test(statusStr));
                return entregue;
            }).length;
            modalProgresso.textContent = `${finalizedCount} / ${data.itens.length} finalizadas`;

            modalImagens.innerHTML = '';
            data.itens.forEach(img => {
                const div = document.createElement('div');
                div.classList.add('modal-imagem-item');

                // Marcar como Pendente quando o item está com status 'Entrega pendente'
                // OU quando o substatus da imagem é 'RVW' ou 'DRV'.
                // Nesses casos NÃO deve ser tratado como 'Entregue'.
                const statusStr = (img.status || '').toString().trim();
                const substatusStr = (img.nome_substatus || '').toString().trim().toUpperCase();

                const isPendente = (statusStr === 'Entrega pendente') || (substatusStr === 'RVW') || (substatusStr === 'DRV');

                // Considera entregue apenas quando não for um dos casos pendentes e o status indicar entrega
                const entregue = !isPendente && (/^entrega/i.test(statusStr) || /^entregue/i.test(statusStr));

                // Se estiver entregue, checkbox vem marcado e desabilitado
                const checked = entregue ? 'checked' : '';
                const disabled = entregue ? 'disabled' : '';

                div.innerHTML = `
                <input type="checkbox" id="img-${img.id}" value="${img.id}" ${checked} ${disabled}>
                <label for="img-${img.id}" class="imagem_nome">
                    ${img.nome}
                </label>
                <span class="entregue">${entregue ? '📦 Entregue' : isPendente ? '✅ Pendente' : '⏳ Em andamento'}</span>
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
    // --- ADICIONAR IMAGEM: abrir modal de seleção pré-filtrada ---
    const btnAdicionarImagem = document.getElementById('btnAdicionarImagem');
    const modalSelecionar = document.getElementById('modalSelecionarImagens');
    const selecionarContainer = document.getElementById('selecionar_imagens_container');
    const btnAdicionarSelecionadas = document.getElementById('btnAdicionarSelecionadas');

    async function carregarImagensParaSelecao(obraId, statusId, existingIds = [], limit = 1000) {
        if (!obraId || !statusId) {
            selecionarContainer.innerHTML = '<p>Obra ou status inválido.</p>';
            return;
        }
        selecionarContainer.innerHTML = '<p>Carregando imagens...</p>';
        try {
            const res = await fetch(`get_imagens.php?obra_id=${obraId}&status_id=${statusId}`);
            const imgs = await res.json();
            const container = selecionarContainer;
            container.innerHTML = '';

            // Filtrar imagens que já estão na entrega
            const existingSet = new Set(existingIds.map(id => Number(id)));
            const filtered = imgs.filter(img => !existingSet.has(Number(img.id)));

            if (!filtered.length) {
                container.innerHTML = '<p>Nenhuma imagem disponível para adicionar (todas já presentes ou não existem).</p>';
                return;
            }

            filtered.slice(0, limit).forEach(img => {
                const div = document.createElement('div');
                div.classList.add('checkbox-item');
                div.innerHTML = `\n                    <input type="checkbox" name="selecionar_imagem_ids[]" value="${img.id}" id="sel-img-${img.id}">\n                    <label for="sel-img-${img.id}"><span>${img.nome}</span></label>\n                `;
                container.appendChild(div);
            });
        } catch (err) {
            console.error('Erro ao carregar imagens para seleção:', err);
            selecionarContainer.innerHTML = '<p>Erro ao carregar imagens.</p>';
        }
    }

    if (btnAdicionarImagem) {
        btnAdicionarImagem.addEventListener('click', async function () {
            if (!entregaAtualId || !entregaDados) {
                alert('Abra primeiro uma entrega clicando no card.');
                return;
            }

            const obraId = entregaDados.obra_id || entregaDados.obraId || entregaDados.id_obra || null;
            const statusId = entregaDados.status_id || entregaDados.statusId || entregaDados.id_status || null;

            // construir lista de existing ids
            const existingIds = (entregaDados.itens || []).map(it => Number(it.imagem_id || it.imagemId || it.id));

            // abrir modal e carregar imagens
            if (modalSelecionar) modalSelecionar.style.display = 'flex';
            await carregarImagensParaSelecao(obraId, statusId, existingIds);
        });
    }

    // handler do botão 'Adicionar Selecionadas'
    if (btnAdicionarSelecionadas) {
        btnAdicionarSelecionadas.addEventListener('click', async function () {
            if (!entregaAtualId) { alert('Entrega não selecionada.'); return; }
            const checked = Array.from(document.querySelectorAll('#selecionar_imagens_container input[type="checkbox"]:checked'));
            if (checked.length === 0) { alert('Selecione ao menos uma imagem.'); return; }
            const ids = checked.map(cb => parseInt(cb.value));
            try {
                const res = await fetch('add_imagem_entrega_id.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ entrega_id: entregaAtualId, imagem_ids: ids })
                });
                const json = await res.json();
                if (json.success) {
                    alert('Imagens adicionadas: ' + (json.added_count || 0) + '\nPuladas: ' + (json.skipped_count || 0));
                    if (modalSelecionar) modalSelecionar.style.display = 'none';
                    // atualizar modal entrega e kanban
                    modal.style.display = 'none';
                    entregaAtualId = null;
                    entregaDados = null;
                    carregarKanban();
                } else {
                    alert('Erro ao adicionar: ' + (json.error || 'desconhecido'));
                }
            } catch (err) {
                console.error('Erro ao adicionar imagens selecionadas:', err);
                alert('Erro ao adicionar imagens (ver console)');
            }
        });
    }
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

                if (img.antecipada) {
                    div.classList.add('antecipada');
                }

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

// --- ADICIONAR IMAGEM POR ID (botão no modal de entrega) ---
const btnAdicionarImagem = document.getElementById('btnAdicionarImagem');
if (btnAdicionarImagem) {
    btnAdicionarImagem.addEventListener('click', async function () {
        if (!entregaAtualId || !entregaDados) {
            alert('Abra primeiro uma entrega clicando no card.');
            return;
        }

        // Sugestão: pedir ao usuário uma lista de ids separados por vírgula
        const raw = prompt('Digite o(s) id(s) de imagens (imagens_cliente_obra.idimagens_cliente_obra). Separe por vírgula para múltiplos:');
        if (!raw) return;
        const ids = raw.split(',').map(s => parseInt(s.trim())).filter(n => !isNaN(n) && n > 0);
        if (ids.length === 0) { alert('Nenhum id válido informado.'); return; }

        try {
            const res = await fetch('add_imagem_entrega_id.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ entrega_id: entregaAtualId, imagem_ids: ids })
            });
            const json = await res.json();
            if (json.success) {
                alert('Imagens adicionadas com sucesso: ' + (json.added_count || 0));
                // atualizar a view
                modal.style.display = 'none';
                entregaAtualId = null;
                entregaDados = null;
                carregarKanban();
            } else {
                alert('Erro: ' + (json.error || 'desconhecido'));
            }
        } catch (err) {
            console.error('Erro ao adicionar imagens:', err);
            alert('Erro ao adicionar imagens (ver console)');
        }
    });
}
