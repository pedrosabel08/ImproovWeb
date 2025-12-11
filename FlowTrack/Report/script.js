const api = 'report_data.php';

function el(q) { return document.querySelector(q); }

function makeTableForObra(obra, funcoesArray) {
    const tbl = document.createElement('table');
    const thead = document.createElement('thead');
    const trh = document.createElement('tr');
    trh.appendChild(document.createElement('th')).textContent = 'Tipo Imagem';
    // function columns
    const funcs = (funcoesArray || []).map(f => f.id);
    (funcoesArray || []).forEach(f => {
        const th = document.createElement('th');
        th.textContent = f.label;
        trh.appendChild(th);
    });
    // no etapa column; styles will be applied per-function cell
    thead.appendChild(trh);
    tbl.appendChild(thead);

    const tbody = document.createElement('tbody');
    obra.tipos.forEach(tipo => {
        const tr = document.createElement('tr');
        tr.appendChild(document.createElement('td')).textContent = tipo.tipo;
        funcs.forEach(fid => {
            const td = document.createElement('td');
            const val = (tipo.funcoes && (typeof tipo.funcoes[fid] !== 'undefined')) ? tipo.funcoes[fid] : '-';
            td.textContent = val;
            // map value to class: '-' -> not-allocated, 'Não iniciado' -> TO-DO, 'Finalizado' -> OK, 'Em andamento' -> TEA
            const mapClass = (v) => {
                if (v === '-') return 'not-allocated';
                if (v === 'Não iniciado') return 'status-TO-DO';
                if (v === 'Finalizado') return 'status-OK';
                if (v === 'Em andamento') return 'status-TEA';
                if (v === 'Em aprovação') return 'status-APR';
                if (v === 'HOLD') return 'status-HOLD';
                return '';
            };
            const cls = mapClass(val);
            if (cls) td.classList.add(cls);
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });
    tbl.appendChild(tbody);
    return tbl;
}

async function fetchJSON(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('Erro ao buscar dados');
    return res.json();
}

async function init() {
    const root = el('#reportRoot');
    try {
        const data = await fetchJSON(api);
        if (data.error) {
            root.textContent = 'Erro: ' + (data.message || '');
            return;
        }

        root.innerHTML = '';
        const funcoes = data.funcoes || [];
        data.obras.forEach(obra => {
            const h = document.createElement('h3');
            h.textContent = obra.obra_nome || ('Obra ' + obra.obra_id);
            root.appendChild(h);
            // show pending entregas above table
            if (Array.isArray(obra.entregas_pendentes) && obra.entregas_pendentes.length > 0) {
                const pendWrap = document.createElement('div');
                pendWrap.className = 'pending-list';
                const title = document.createElement('div');
                title.className = 'pending-title';
                title.textContent = 'Entregas pendentes:';
                pendWrap.appendChild(title);

                const list = document.createElement('div');
                list.className = 'pending-items';
                obra.entregas_pendentes.forEach(ent => {
                    const it = document.createElement('div');
                    it.className = 'pending-item';
                    const d = ent.data_prevista ? ent.data_prevista : null;
                    // compute due state
                    let dueClass = '';
                    let dueLabel = '';
                    if (d) {
                        const today = new Date();
                        // zero time part
                        today.setHours(0, 0, 0, 0);
                        const dt = new Date(d + 'T00:00:00');
                        const diffMs = dt - today;
                        const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));
                        if (diffDays < 0) { dueClass = 'pending-overdue'; dueLabel = 'Atrasada'; }
                        else if (diffDays === 0) { dueClass = 'pending-today'; dueLabel = 'Hoje'; }
                        else if (diffDays <= 3) { dueClass = 'pending-soon'; dueLabel = `Em ${diffDays} dia${diffDays > 1 ? 's' : ''}`; }
                        else { dueClass = 'pending-future'; dueLabel = `Em ${diffDays} dias`; }
                    } else {
                        dueClass = 'pending-no-date';
                        dueLabel = '-';
                    }

                    it.classList.add(dueClass);
                    const statusNome = ent.status_nome || ent.status || '';
                    const dateLabel = d ? d.split('-').reverse().join('/') : '-';
                    it.innerHTML = `<span class="pending-date">${dateLabel}</span> <span class="pending-status">${statusNome}</span> <span class="pending-due">${dueLabel}</span> <span class="pending-id">#${ent.id}</span>`;
                    list.appendChild(it);
                });
                pendWrap.appendChild(list);
                root.appendChild(pendWrap);
            }
            const tbl = makeTableForObra(obra, funcoes);
            root.appendChild(tbl);
            root.appendChild(document.createElement('br'));
        });
        // attach click handlers to pending items (open entrega details)
        document.querySelectorAll('.pending-item').forEach(item => {
            item.style.cursor = 'pointer';
            item.addEventListener('click', async () => {
                const text = item.querySelector('.pending-id')?.textContent || '';
                const m = text.match(/#(\d+)/);
                if (!m) return;
                const entrega_id = m[1];
                try {
                    const resp = await fetch(`../../Entregas/get_entrega_item.php?id=${entrega_id}`, { credentials: 'same-origin' });
                    if (!resp.ok) throw new Error('Erro ao buscar entrega');
                    const data = await resp.json();
                    if (data && data.error) throw new Error(data.error || 'erro');
                    showEntregaModal(data);
                } catch (err) {
                    console.error('Falha ao carregar entrega:', err);
                    alert('Falha ao carregar entrega (ver console)');
                }
            });
        });

        // helper to build and show entrega modal
        function showEntregaModal(entregaData) {
            // remove existing modal if any
            const existing = document.getElementById('rt-entrega-modal');
            if (existing) existing.remove();

            const modal = document.createElement('div');
            modal.id = 'rt-entrega-modal';
            modal.className = 'rt-modal';

            // build header and containers similar to Entregas modal
            const headerHtml = `
                <div class="rt-modal-header">
                    <strong>${entregaData.nomenclatura} - ${entregaData.nome_etapa || ''}</strong>
                    <button id="rt-close-modal">×</button>
                </div>`;

            const body = document.createElement('div');
            body.className = 'rt-modal-body';

            const prazoP = document.createElement('p');
            prazoP.innerHTML = `<strong>Prazo:</strong> ${entregaData.data_prevista || '-'}`;
            body.appendChild(prazoP);

            const statusP = document.createElement('p');
            statusP.innerHTML = `<strong>Status entrega:</strong> ${entregaData.status || entregaData.nome_etapa || ''}`;
            body.appendChild(statusP);

            // progresso
            const progressoP = document.createElement('p');
            progressoP.id = 'rt-modal-progresso';
            body.appendChild(progressoP);

            // imagens container
            const imagensContainer = document.createElement('div');
            imagensContainer.id = 'rt-modal-imagens';
            imagensContainer.className = 'rt-itens-list';

            // master checkbox
            const masterDiv = document.createElement('div');
            masterDiv.className = 'modal-imagem-item select-all-item';
            masterDiv.innerHTML = `
                <input type="checkbox" id="rt-selectAllImagens">
                <label for="rt-selectAllImagens" class="imagem_nome">Selecionar todos</label>`;
            imagensContainer.appendChild(masterDiv);

            // build item rows with checkbox + label + status badge (mimic Entregas)
            if (Array.isArray(entregaData.itens) && entregaData.itens.length) {
                entregaData.itens.forEach(it => {
                    const div = document.createElement('div');
                    div.className = 'modal-imagem-item';

                    const statusStr = (it.status || '').toString().trim();
                    const substatusStr = (it.nome_substatus || '').toString().trim();
                    const ns = statusStr.toLowerCase();
                    const nsub = substatusStr.toLowerCase();

                    const STATUS_PENDENTE = new Set(['entrega pendente']);
                    const STATUS_ENTREGUE = new Set(['entregue no prazo', 'entrega antecipada', 'entregue com atraso']);
                    const SUBSTATUS_PENDENTE = new Set(['rvw', 'drv']);

                    const isPendente = STATUS_PENDENTE.has(ns) || (SUBSTATUS_PENDENTE.has(nsub) && !STATUS_ENTREGUE.has(ns));
                    const isEntregue = STATUS_ENTREGUE.has(ns) && !isPendente;
                    const isEmAndamento = !isPendente && !isEntregue;

                    const checked = isEntregue ? 'checked' : '';
                    const disabled = isEntregue ? 'disabled' : '';

                    div.innerHTML = `
                        <input type="checkbox" id="rt-img-item-${it.id}" value="${it.id}" ${checked} ${disabled} data-imagem-id="${it.imagem_id}">
                        <label class="imagem_nome" data-imagem-id="${it.imagem_id}">${(it.nome || '')}</label>
                        <span class="entregue">${isEntregue ? '📦 Entregue' : isPendente ? '✅ Pendente' : '⏳ Em andamento'}</span>
                    `;
                    imagensContainer.appendChild(div);
                });
            } else {
                const none = document.createElement('p');
                none.textContent = 'Nenhum item encontrado nesta entrega.';
                imagensContainer.appendChild(none);
            }

            body.appendChild(imagensContainer);


            const content = document.createElement('div');
            content.className = 'rt-modal-content';
            content.innerHTML = headerHtml;
            content.appendChild(body);

            modal.appendChild(content);
            document.body.appendChild(modal);

            // wire close buttons (guard against missing elements)
            const btnCloseModal = document.getElementById('rt-close-modal');
            if (btnCloseModal) btnCloseModal.addEventListener('click', () => modal.remove());
            const btnCloseOk = document.getElementById('rt-close-ok');
            if (btnCloseOk) btnCloseOk.addEventListener('click', () => modal.remove());
            // close when background clicked
            modal.addEventListener('click', (ev) => { if (ev.target === modal) modal.remove(); });

            // update progresso count
            const updateProgresso = () => {
                const items = Array.from(imagensContainer.querySelectorAll('.modal-imagem-item:not(.select-all-item)'));
                const total = items.length;
                const entregues = items.filter(d => d.querySelector('input[type="checkbox"]').disabled).length;
                document.getElementById('rt-modal-progresso').textContent = `${entregues} / ${total} finalizadas`;
            };
            updateProgresso();

            // label click -> fetch imagem funcao and show mini panel (reusing Entregas endpoint)
            imagensContainer.addEventListener('click', async (e) => {
                const label = e.target.closest('label.imagem_nome');
                if (!label) return;
                const imagemId = label.dataset && label.dataset.imagemId ? label.dataset.imagemId : null;
                if (!imagemId) return;
                try {
                    const resp = await fetch(`../../Entregas/get_imagem_funcao.php?imagem_id=${imagemId}`, { credentials: 'same-origin' });
                    if (!resp.ok) throw new Error('Erro ao buscar função da imagem');
                    const json = await resp.json();
                    if (json && json.success && json.data) {
                        // reuse the global helper from Entregas is not available here, so create a lightweight mini panel
                        showMiniImagePanel(json.data, imagemId, label);
                    } else {
                        showMiniImagePanel(null, imagemId, label);
                    }
                } catch (err) {
                    console.error('Erro ao buscar função da imagem:', err);
                    showMiniImagePanel(null, imagemId, label);
                }
            });

            // master checkbox behavior
            const master = document.getElementById('rt-selectAllImagens');
            if (master) {
                const selectableSelector = 'input[type="checkbox"]:not([disabled])';
                const getSelectable = () => Array.from(imagensContainer.querySelectorAll(selectableSelector)).filter(cb => cb.id !== 'rt-selectAllImagens');

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

            // lightweight mini panel implementation (local version)
            function showMiniImagePanel(data, imagemId, anchorEl) {
                let panel = document.getElementById('rt-miniImagePanel');
                if (!panel) {
                    panel = document.createElement('div');
                    panel.id = 'rt-miniImagePanel';
                    panel.className = 'mini-image-panel';
                    panel.innerHTML = `
                        <div class="mini-header"><strong>Imagem #<span id="rt-miniImgId"></span></strong><button id="rt-miniClose">×</button></div>
                        <div id="rt-miniContent">Carregando...</div>`;
                    panel.style.visibility = 'hidden';
                    panel.style.display = 'block';
                    document.body.appendChild(panel);
                    document.getElementById('rt-miniClose').addEventListener('click', () => panel.remove());
                }
                document.getElementById('rt-miniImgId').textContent = imagemId;
                const content = document.getElementById('rt-miniContent');
                if (!data) {
                    content.innerHTML = '<p>Sem histórico de função para esta imagem.</p>';
                    positionPanelNearAnchor(panel, anchorEl);
                    return;
                }
                const funcao = data.nome_funcao || '—';
                const status = data.status || '—';
                const colaborador = data.nome_colaborador || '—';
                const prazo = data.prazo ? formatDateBR(data.prazo) : '-';
                content.innerHTML = `<p><strong>Função:</strong> ${funcao}</p><p><strong>Status:</strong> ${status}</p><p><strong>Colaborador:</strong> ${colaborador}</p><p><strong>Prazo:</strong> ${prazo}</p>`;
                positionPanelNearAnchor(panel, anchorEl);
            }

            function positionPanelNearAnchor(panel, anchorEl) {
                if (!panel) return;
                const panelWidth = panel.offsetWidth || 300;
                const panelHeight = panel.offsetHeight || 150;
                if (anchorEl && anchorEl.getBoundingClientRect) {
                    const rect = anchorEl.getBoundingClientRect();
                    let left = rect.right + 8 + window.scrollX;
                    let top = rect.top + (rect.height / 2) + window.scrollY - (panelHeight / 2);
                    if (left + panelWidth > window.innerWidth - 10) {
                        left = rect.left + window.scrollX - panelWidth - 8;
                    }
                    if (top + panelHeight > window.scrollY + window.innerHeight - 10) {
                        top = Math.max(window.scrollY + 10, window.scrollY + window.innerHeight - panelHeight - 10);
                    }
                    if (top < window.scrollY + 10) top = window.scrollY + 10;
                    panel.style.position = 'absolute';
                    panel.style.left = `${Math.max(10, left)}px`;
                    panel.style.top = `${Math.max(10, top)}px`;
                    panel.style.visibility = 'visible';
                } else {
                    panel.style.position = 'fixed';
                    panel.style.right = '20px';
                    panel.style.top = '20%';
                    panel.style.visibility = 'visible';
                }
            }

            function formatDateBR(d) {
                if (!d) return '-';
                const parts = d.split('-');
                return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : d;
            }
        }
        if (!data.obras || data.obras.length === 0) root.textContent = 'Nenhuma obra ativa encontrada.';
    } catch (e) {
        root.textContent = 'Falha carregando relatório: ' + e.message;
        console.error(e);
    }
}

init();
