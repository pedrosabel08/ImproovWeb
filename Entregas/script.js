document.addEventListener('DOMContentLoaded', () => {
    const columns = document.querySelectorAll('.column');
    const modal = document.getElementById('entregaModal');
    const modalTitle = document.getElementById('modalTitulo');
    const modalEtapa = document.getElementById('modalEtapa');
    const modalPrazo = document.getElementById('modalPrazo');
    const modalProgresso = document.getElementById('modalProgresso');
    const modalImagens = document.getElementById('modalImagens');

    // global store of fetched entregas so we can filter client-side
    let entregasAll = [];

    // Helper: create card element for a entrega
    function createCard(entrega) {
        const card = document.createElement('div');
        card.classList.add('card-entrega');
        card.dataset.id = entrega.id;

        // add status-based class (use nome_etapa as canonical status)
        const statusRaw = String(entrega.nome_etapa || entrega.kanban_status || entrega.status || 'UNKNOWN');
        const statusCode = statusRaw.trim().replace(/\s+/g, '-').replace(/[^\w-]/g, '').toUpperCase() || 'UNKNOWN';
        card.classList.add('status-' + statusCode);

        const readyCount = parseInt(entrega.ready_count || 0, 10);

        card.innerHTML = `
                <div class="card-header">
                    <h4>${entrega.nomenclatura || ''} - ${entrega.nome_etapa || ''}</h4>
                    ${readyCount > 0 ? `<div class="entrega-badge" title="Imagens prontas para entrega">${readyCount}</div>` : ''}
                </div>
                <p><strong>Status:</strong> ${entrega.nome_etapa || entrega.status || entrega.kanban_status || ''}</p>
                <p><strong>Prazo:</strong> ${entrega.data_prevista ? formatarData(entrega.data_prevista) : '-'}</p>
                <div class="progress">
                    <div class="progress-bar" style="width:${entrega.pct_entregue || 0}%"></div>
                </div>
                <small>${entrega.entregues || 0}/${entrega.total_itens || 0} imagens entregues</small>
            `;

        return card;
    }

    // Render a list of entregas into the columns
    function renderEntregas(list) {
        // clear existing cards
        columns.forEach(col => col.querySelectorAll('.card-entrega').forEach(card => card.remove()));

        list.forEach(entrega => {
            // find column based on dataset statuses
            const col = Array.from(columns).find(c => {
                const statuses = (c.dataset.status || '').split(',').map(s => s.trim().toLowerCase());
                // try to match using kanban_status first, then status, then nome_etapa
                const entStatus = String(entrega.kanban_status || entrega.status || entrega.nome_etapa || '').trim().toLowerCase();
                return entStatus && statuses.includes(entStatus);
            });

            if (!col) return;
            const card = createCard(entrega);
            col.appendChild(card);
        });
    }

    // Populate filter selects (obra/status) from the fetched entregas
    function populateFiltersFrom(entregas) {
        try {
            const obraSelect = document.getElementById('filterObra');
            const statusSelect = document.getElementById('filterStatus');
            if (!obraSelect || !statusSelect) return;

            // derive unique obras and statuses present in entregas
            const obras = new Map();
            const statuses = new Map();
            entregas.forEach(e => {
                const obraId = e.obra_id || e.obraId || e.id_obra || null;
                const obraLabel = e.nomenclatura || (obraId ? `Obra ${obraId}` : '');
                if (obraId) obras.set(String(obraId), obraLabel);

                // derive status code from the same fields used in filtering/rendering
                const st = String(e.nome_etapa || '').trim();
                if (st) statuses.set(st, st);
            });

            // clear and fill obra select (keep first option) - sort by label ascending
            const obraDefault = obraSelect.querySelector('option');
            obraSelect.innerHTML = '';
            obraSelect.appendChild(obraDefault.cloneNode(true));
            // convert map to array and sort by label
            const obraArr = Array.from(obras.entries()).sort((a, b) => a[1].localeCompare(b[1], 'pt', { sensitivity: 'base' }));
            obraArr.forEach(([id, label]) => {
                const opt = document.createElement('option');
                opt.value = id;
                opt.textContent = label;
                obraSelect.appendChild(opt);
            });

            // clear and fill status select - sort alphabetically
            const statusDefault = statusSelect.querySelector('option');
            statusSelect.innerHTML = '';
            statusSelect.appendChild(statusDefault.cloneNode(true));
            const statusArr = Array.from(statuses.values()).sort((a, b) => a.localeCompare(b, 'pt', { sensitivity: 'base' }));
            statusArr.forEach(label => {
                const opt = document.createElement('option');
                opt.value = label;
                opt.textContent = label;
                statusSelect.appendChild(opt);
            });
        } catch (err) {
            console.error('Erro ao popular filtros:', err);
        }
    }

    // Apply current filter selections to the global entregasAll and render
    function applyFilters() {
        const obraVal = (document.getElementById('filterObra') || {}).value || '';
        const statusVal = (document.getElementById('filterStatus') || {}).value || '';

        const filtered = entregasAll.filter(e => {
            let okObra = true;
            let okStatus = true;

            if (obraVal) {
                const oid = String(e.obra_id || e.obraId || e.id_obra || '');
                okObra = oid === String(obraVal);
            }
            if (statusVal) {
                const st = String(e.nome_etapa || e.kanban_status || e.status || '').trim();
                // compare normalized (case-insensitive)
                okStatus = st.toLowerCase() === String(statusVal).trim().toLowerCase();
            }
            return okObra && okStatus;
        });

        renderEntregas(filtered);
    }

    // Clear filters UI and render all
    function clearFilters() {
        const obraSelect = document.getElementById('filterObra');
        const statusSelect = document.getElementById('filterStatus');
        if (obraSelect) obraSelect.value = '';
        if (statusSelect) statusSelect.value = '';
        renderEntregas(entregasAll);
    }

    // Conjuntos de classificação de status/substatus (normalizados em lowercase)
    const STATUS_PENDENTE = new Set(['entrega pendente']);
    const STATUS_ENTREGUE = new Set(['entregue no prazo', 'entrega antecipada', 'entregue com atraso']);
    const SUBSTATUS_PENDENTE = new Set(['rvw', 'drv']);

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
            carregarKanban();
            // remover painel lateral se existir
            const mini = document.getElementById('miniImagePanel');
            if (mini) mini.remove();
        });
    });

    // Create and manage mini image info panel
    function showMiniImagePanel(data, imagemId, anchorEl) {
        let panel = document.getElementById('miniImagePanel');
        if (!panel) {
            panel = document.createElement('div');
            panel.id = 'miniImagePanel';
            panel.className = 'mini-image-panel';
            panel.innerHTML = `
                <div class="mini-header">
                    <strong>Imagem #<span id="miniImgId"></span></strong>
                    <button id="miniCloseBtn" class="fecharMini">×</button>
                </div>
                <div id="miniContent">Carregando...</div>
            `;
            // append hidden first so we can measure and position
            panel.style.visibility = 'hidden';
            panel.style.display = 'block';
            document.body.appendChild(panel);
            document.getElementById('miniCloseBtn').addEventListener('click', () => panel.remove());
        }

        document.getElementById('miniImgId').textContent = imagemId;
        const content = document.getElementById('miniContent');
        if (!data) {
            content.innerHTML = '<p>Sem histórico de função para esta imagem.</p>';
            // position near anchor even when empty
            positionPanelNearAnchor(panel, anchorEl);
            return;
        }

        const funcao = data.nome_funcao || '—';
        const status = data.status || '—';
        const colaborador = data.nome_colaborador || '—';
        const prazo = data.prazo ? formatarData(data.prazo) : '-';

        content.innerHTML = `
            <p><strong>Função:</strong> ${funcao}</p>
            <p><strong>Status:</strong> ${status}</p>
            <p><strong>Colaborador:</strong> ${colaborador}</p>
            <p><strong>Prazo:</strong> ${prazo}</p>
        `;

        // After filling content, position panel near the clicked label
        positionPanelNearAnchor(panel, anchorEl);
    }

    function positionPanelNearAnchor(panel, anchorEl) {
        if (!panel) return;
        // default width (should match CSS) — measure if possible
        const panelWidth = panel.offsetWidth || 300;
        const panelHeight = panel.offsetHeight || 150;

        // If we have an anchor element, position next to it; otherwise keep to right
        if (anchorEl && anchorEl.getBoundingClientRect) {
            const rect = anchorEl.getBoundingClientRect();
            // preferred position: to the right of the anchor
            let left = rect.right + 8;
            // center vertically relative to the anchor row
            let top = rect.top + (rect.height / 2) + window.scrollY - (panelHeight / 2);

            // if overflowing right edge, place to the left
            if (left + panelWidth > window.innerWidth - 10) {
                left = rect.left + window.scrollX - panelWidth - 8;
            }
            // ensure top is within viewport
            if (top + panelHeight > window.scrollY + window.innerHeight - 10) {
                top = Math.max(window.scrollY + 10, window.scrollY + window.innerHeight - panelHeight - 10);
            }
            // avoid going above the viewport
            if (top < window.scrollY + 10) {
                top = window.scrollY + 10;
            }

            panel.style.position = 'absolute';
            panel.style.left = `${Math.max(10, left)}px`;
            panel.style.top = `${Math.max(10, top)}px`;
            panel.style.visibility = 'visible';
        } else {
            // fallback: fixed to right side
            panel.style.position = 'fixed';
            panel.style.right = '20px';
            panel.style.top = '20%';
            panel.style.visibility = 'visible';
        }
    }

    // --- FUNÇÃO PRINCIPAL PARA CARREGAR O KANBAN ---
    async function carregarKanban() {
        try {
            const res = await fetch('listar_entregas.php');
            const entregas = await res.json();

            entregasAll = Array.isArray(entregas) ? entregas : [];

            populateFiltersFrom(entregasAll);
            renderEntregas(entregasAll);
        } catch (err) {
            console.error('Erro ao carregar o Kanban:', err);
        }
    }


    carregarKanban();

    // wire filter UI events (after initial load will populate options)
    const obraSelectEl = document.getElementById('filterObra');
    const statusSelectEl = document.getElementById('filterStatus');

    if (obraSelectEl) obraSelectEl.addEventListener('change', () => applyFilters());
    if (statusSelectEl) statusSelectEl.addEventListener('change', () => applyFilters());

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
            // Contabiliza somente itens realmente entregues conforme regras solicitadas
            const finalizedCount = data.itens.filter(i => {
                const statusStr = (i.status || '').toString().trim();
                const substatus = (i.nome_substatus || '').toString().trim();
                // normalizar para comparação
                const ns = statusStr.toLowerCase();
                const nsub = substatus.toLowerCase();
                const isPendente = STATUS_PENDENTE.has(ns) || (SUBSTATUS_PENDENTE.has(nsub) && !STATUS_ENTREGUE.has(ns));
                const isEntregue = STATUS_ENTREGUE.has(ns) && !isPendente;
                return isEntregue;
            }).length;
            modalProgresso.textContent = `${finalizedCount} / ${data.itens.length} finalizadas`;

            modalImagens.innerHTML = '';

            // adiciona checkbox mestre para ações em batch (selecionar todos)
            const masterDiv = document.createElement('div');
            masterDiv.classList.add('modal-imagem-item', 'select-all-item');
            masterDiv.innerHTML = `
                <input type="checkbox" id="selectAllImagens">
                <label for="selectAllImagens" class="imagem_nome">Selecionar todos</label>
            `;
            modalImagens.appendChild(masterDiv);

            data.itens.forEach(img => {
                const div = document.createElement('div');
                div.classList.add('modal-imagem-item');

                const statusStr = (img.status || '').toString().trim();
                const substatusStr = (img.nome_substatus || '').toString().trim();

                const ns = statusStr.toLowerCase();
                const nsub = substatusStr.toLowerCase();

                const isPendente = STATUS_PENDENTE.has(ns) || (SUBSTATUS_PENDENTE.has(nsub) && !STATUS_ENTREGUE.has(ns));
                const isEntregue = STATUS_ENTREGUE.has(ns) && !isPendente;
                const isEmAndamento = !isPendente && !isEntregue;

                const checked = isEntregue ? 'checked' : '';
                const disabled = isEntregue ? 'disabled' : '';

                div.innerHTML = `
                <input type="checkbox" id="img-item-${img.id}" value="${img.id}" ${checked} ${disabled} data-imagem-id="${img.imagem_id}">
                <label class="imagem_nome" data-imagem-id="${img.imagem_id}">${img.nome}</label>
                <span class="entregue">${isEntregue ? '📦 Entregue' : isPendente ? '✅ Pendente' : '⏳ Em andamento'}</span>
            `;
                modalImagens.appendChild(div);
            });

            // Click on image name to open mini info panel (last função / status / colaborador)
            modalImagens.addEventListener('click', async (e) => {
                const label = e.target.closest('label.imagem_nome');
                if (!label) return;
                // get imagem_id from the label's data attribute (set from server data)
                const imagemId = label.dataset && label.dataset.imagemId ? label.dataset.imagemId : null;
                if (!imagemId) return;

                try {
                    const resp = await fetch(`get_imagem_funcao.php?imagem_id=${imagemId}`);
                    const json = await resp.json();
                    if (json && json.success && json.data) {
                        showMiniImagePanel(json.data, imagemId, label);
                    } else {
                        showMiniImagePanel(null, imagemId, label);
                    }
                } catch (err) {
                    console.error('Erro ao buscar função da imagem:', err);
                    showMiniImagePanel(null, imagemId, label);
                }
            });

            // configurar comportamento do checkbox mestre e sincronização
            const master = document.getElementById('selectAllImagens');
            if (master) {
                const selectableSelector = 'input[type="checkbox"]:not([disabled])';
                const getSelectable = () => Array.from(modalImagens.querySelectorAll(selectableSelector)).filter(cb => cb.id !== 'selectAllImagens');

                const updateMasterState = () => {
                    const selectable = getSelectable();
                    const total = selectable.length;
                    const checkedCount = selectable.filter(cb => cb.checked).length;
                    master.checked = total > 0 && checkedCount === total;
                    master.indeterminate = checkedCount > 0 && checkedCount < total;
                };

                master.addEventListener('change', () => {
                    const selectable = getSelectable();
                    selectable.forEach(cb => cb.checked = master.checked);
                    master.indeterminate = false;
                });

                // adicionar listener para cada checkbox selecionável para manter o mestre em sincronia
                const attachIndividualListeners = () => {
                    const selectable = getSelectable();
                    selectable.forEach(cb => {
                        cb.removeEventListener('change', updateMasterState);
                        cb.addEventListener('change', updateMasterState);
                    });
                };

                attachIndividualListeners();
                // inicializar estado do mestre
                updateMasterState();
            }

            modal.style.display = 'flex';
        } catch (err) {
            console.error('Erro ao carregar detalhes da entrega:', err);
        }
    });

    // --- REMOVER IMAGEM COM CLIQUE DIREITO ---
    modalImagens.addEventListener('contextmenu', async (e) => {
        const item = e.target.closest('.modal-imagem-item');
        if (!item || item.classList.contains('select-all-item')) return;
        if (!entregaAtualId) return;
        e.preventDefault();
        const checkbox = item.querySelector('input[type="checkbox"]');
        if (!checkbox) return;
        const itemId = parseInt(checkbox.value, 10); // este é o id do registro em entregas_itens
        // obter também a imagem_id armazenada nos dados, se disponível
        let imagemId = null;
        if (entregaDados && Array.isArray(entregaDados.itens)) {
            const found = entregaDados.itens.find(it => parseInt(it.id, 10) === itemId);
            if (found) imagemId = found.imagem_id;
        }
        const nomeLabel = item.querySelector('label.imagem_nome');
        const nomeImagem = nomeLabel ? nomeLabel.textContent.trim() : ('Item ' + itemId);
        const confirmar = confirm(`Remover a imagem "${nomeImagem}" desta entrega?`);
        if (!confirmar) return;
        try {
            const payload = { entrega_id: entregaAtualId, item_id: itemId };
            const res = await fetch('remove_imagem_entrega.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (json.success) {
                item.remove();
                if (entregaDados && Array.isArray(entregaDados.itens)) {
                    entregaDados.itens = entregaDados.itens.filter(it => parseInt(it.id, 10) !== itemId);
                }
                const total = modalImagens.querySelectorAll('.modal-imagem-item:not(.select-all-item)').length;
                const entregues = Array.from(modalImagens.querySelectorAll('.modal-imagem-item:not(.select-all-item) input[disabled]')).length;
                modalProgresso.textContent = `${entregues} / ${total} finalizadas`;
                const master = document.getElementById('selectAllImagens');
                if (master) {
                    const selectable = Array.from(modalImagens.querySelectorAll('input[type="checkbox"]:not([disabled])')).filter(cb => cb.id !== 'selectAllImagens');
                    const checkedCount = selectable.filter(cb => cb.checked).length;
                    master.checked = selectable.length > 0 && checkedCount === selectable.length;
                    master.indeterminate = checkedCount > 0 && checkedCount < selectable.length;
                }
            } else {
                alert('Não foi possível remover: ' + (json.error || 'erro desconhecido'));
            }
        } catch (err) {
            console.error('Erro ao remover imagem da entrega:', err);
            alert('Falha ao remover imagem (ver console)');
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

            // adicionar checkbox mestre para seleção em lote dentro do container principal
            const masterDiv = document.createElement('div');
            masterDiv.classList.add('checkbox-item', 'select-all-item');
            masterDiv.innerHTML = `
                <input type="checkbox" id="selectAllImagens_list">
                <label for="selectAllImagens_list"><strong>Selecionar todos</strong></label>
            `;
            container.appendChild(masterDiv);

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
            <input type="checkbox" name="imagem_ids[]" value="${img.id}" class="img-selectable" id="lista-img-${img.id}">
            <label for="lista-img-${img.id}"><span>${img.nome}</span></label>
        `;
                container.appendChild(div);
            });

            // configurar comportamento do checkbox mestre no container
            const master = document.getElementById('selectAllImagens_list');
            if (master) {
                const getSelectable = () => Array.from(container.querySelectorAll('input[type="checkbox"].img-selectable:not([disabled])'));

                const updateMasterState = () => {
                    const selectable = getSelectable();
                    const total = selectable.length;
                    const checkedCount = selectable.filter(cb => cb.checked).length;
                    master.checked = total > 0 && checkedCount === total;
                    master.indeterminate = checkedCount > 0 && checkedCount < total;
                };

                master.addEventListener('change', () => {
                    const selectable = getSelectable();
                    selectable.forEach(cb => cb.checked = master.checked);
                    master.indeterminate = false;
                });

                const attachIndividualListeners = () => {
                    const selectable = getSelectable();
                    selectable.forEach(cb => {
                        cb.removeEventListener('change', updateMasterState);
                        cb.addEventListener('change', updateMasterState);
                    });
                };

                attachIndividualListeners();
                updateMasterState();
            }
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
