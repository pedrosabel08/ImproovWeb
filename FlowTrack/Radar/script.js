const apiRadar = 'radar_data.php';

function $(q, ctx = document) { return ctx.querySelector(q); }
function el(tag, cls) { const n = document.createElement(tag); if (cls) n.className = cls; return n; }

function renderFunctionCard(fn) {
    const card = el('div', 'radar-card');
    const title = el('h2');
    title.textContent = fn.funcao_nome;
    const sub = el('p', 'subtitle');
    sub.textContent = fn.prev_funcao_id ? `Depende de ${fn.prev_funcao_nome || ('função ' + fn.prev_funcao_id)}` : 'Primeira função da cadeia';
    card.append(title, sub);

    // Tipo de imagem filter per card (derived from suggestions)
    const tipos = Array.from(new Set((fn.sugestoes || []).map(s => s.tipo_imagem || ''))).filter(Boolean);
    let currentTipo = '';
    const filterWrap = el('div', 'radar-type-filter');
    const lbl = el('span', 'small');
    lbl.textContent = 'Filtrar por tipo:';
    const select = el('select');
    const optAll = el('option'); optAll.value = ''; optAll.textContent = 'Todos'; select.appendChild(optAll);
    tipos.forEach(t => { const o = el('option'); o.value = t; o.textContent = t; select.appendChild(o); });
    select.addEventListener('change', () => { currentTipo = select.value; renderSuggestions(); });
    filterWrap.append(lbl, select);
    card.appendChild(filterWrap);

    // Obra filter per card (derived from suggestions)
    const obras = Array.from(new Set((fn.sugestoes || []).map(s => s.obra_nome || ''))).filter(Boolean);
    let currentObra = '';
    const obraFilterWrap = el('div', 'radar-type-filter');
    const lblObra = el('span', 'small');
    lblObra.textContent = 'Filtrar por obra:';
    const selectObra = el('select');
    const optAllObra = el('option'); optAllObra.value = ''; optAllObra.textContent = 'Todas'; selectObra.appendChild(optAllObra);
    obras.forEach(o => { const oo = el('option'); oo.value = o; oo.textContent = o; selectObra.appendChild(oo); });
    selectObra.addEventListener('change', () => { currentObra = selectObra.value; renderSuggestions(); });
    obraFilterWrap.append(lblObra, selectObra);
    card.appendChild(obraFilterWrap);

    // Colaboradores
    const collabBox = el('div', 'radar-collabs');
    const ch = el('h3'); ch.textContent = 'Colaboradores';
    collabBox.appendChild(ch);
    const collabWrap = el('div');
    if (fn.colaboradores && fn.colaboradores.length) {
        fn.colaboradores.forEach(c => {
            const pill = el('span', 'pill');
            pill.textContent = c.nome || ('Colab ' + c.id);
            collabWrap.appendChild(pill);
        });
    } else {
        const empty = el('div', 'empty');
        empty.textContent = 'Sem colaboradores ativos nesta função.';
        collabWrap.appendChild(empty);
    }
    collabBox.appendChild(collabWrap);
    card.appendChild(collabBox);

    // Allocation controls: collaborator select + allocate button
    const allocControls = el('div', 'radar-alloc-controls');
    const collabSelect = el('select');
    const collabDefault = el('option'); collabDefault.value = ''; collabDefault.textContent = 'Selecionar colaborador...'; collabSelect.appendChild(collabDefault);
    if (fn.colaboradores && fn.colaboradores.length) {
        fn.colaboradores.forEach(c => {
            const o = el('option'); o.value = String(c.id || c.idcolaborador || c.id); o.textContent = c.nome || c.nome_colaborador || c.nome;
            collabSelect.appendChild(o);
        });
    }
    const allocBtn = el('button', 'btn'); allocBtn.textContent = 'Alocar selecionados'; allocBtn.style.display = 'none'; allocBtn.disabled = true;
    allocControls.append(collabSelect, allocBtn);
    card.appendChild(allocControls);

    const selectedImgs = new Set();

    // Sugestões
    const sugBox = el('div', 'radar-suggest');
    const sh = el('h3'); sh.textContent = 'Sugestões para alocar (anterior já andou)';
    sugBox.appendChild(sh);

    function renderSuggestions() {
        // clear previous list content except header
        while (sugBox.children.length > 1) sugBox.removeChild(sugBox.lastChild);
        selectedImgs.clear();
        allocBtn.style.display = 'none';
        allocBtn.disabled = true;
        let list = (fn.sugestoes || []).filter(s =>
            (!currentTipo || s.tipo_imagem === currentTipo) &&
            (!currentObra || (s.obra_nome || '') === currentObra)
        );
        // Apply global 'previous started' filter from the index select if present
        try {
            const prevFilterEl = document.getElementById('filter-prev-started');
            if (prevFilterEl && prevFilterEl.value === 'started') {
                // keep only suggestions where previous status is not 'Não iniciado'
                list = list.filter(s => (s.prev_status || '').trim() !== 'Não iniciado');
            }
        } catch (e) {
            // ignore DOM errors
        }
        if (list.length) {
            list.forEach(s => {
                const it = el('div', 'suggest-item');
                const cb = el('input'); cb.type = 'checkbox'; cb.className = 'suggest-checkbox';
                cb.dataset.imagemId = String(s.imagem_id || '');
                cb.addEventListener('change', () => {
                    const id = cb.dataset.imagemId;
                    if (cb.checked) selectedImgs.add(id); else selectedImgs.delete(id);
                    allocBtn.style.display = selectedImgs.size ? '' : 'none';
                    allocBtn.disabled = !collabSelect.value;
                });
                const title = el('div');
                title.innerHTML = `<strong>${s.imagem_nome || 'Imagem ' + s.imagem_id}</strong> <span class="small">(${s.tipo_imagem || 'Tipo?'})</span>`;
                const obra = el('div', 'small'); obra.textContent = s.obra_nome || '';
                const st = el('div', 'small');
                st.innerHTML = `Anterior: <span class="status-prev">${s.prev_status}</span> | Atual: <span class="status-cur">${s.cur_status || '-'}</span>`;
                it.append(cb, title, obra, st);
                sugBox.appendChild(it);
            });

            // when collaborator changes, enable/disable allocBtn
            collabSelect.addEventListener('change', () => {
                allocBtn.disabled = !collabSelect.value || selectedImgs.size === 0;
                allocBtn.style.display = selectedImgs.size ? '' : 'none';
            });

            allocBtn.addEventListener('click', async () => {
                if (!collabSelect.value) return alert('Selecione um colaborador.');
                const ids = Array.from(selectedImgs).filter(Boolean);
                if (!ids.length) return alert('Selecione ao menos uma sugestão.');
                allocBtn.disabled = true;
                const promises = ids.map(id => {
                    const fd = new FormData();
                    fd.append('imagem_id', id);
                    fd.append('funcao_id', String(fn.funcao_id));
                    fd.append('colaborador_id', collabSelect.value);
                    return fetch('../../insereFuncao.php', { method: 'POST', body: fd }).then(r => r.json()).catch(e => ({ error: e.message }));
                });
                try {
                    const results = await Promise.all(promises);
                    const failed = results.filter(r => r && r.error);
                    if (failed.length === 0) {
                        // remove allocated images from suggestions
                        fn.sugestoes = (fn.sugestoes || []).filter(s => !ids.includes(String(s.imagem_id)));
                        renderSuggestions();
                        alert('Alocado com sucesso.');
                    } else {
                        console.error('Falhas ao alocar', failed);
                        alert('Algumas alocações falharam. Veja console.');
                    }
                } catch (e) {
                    console.error(e);
                    alert('Erro ao alocar: ' + e.message);
                } finally {
                    allocBtn.disabled = false;
                }
            });
        } else {
            const empty = el('div', 'empty');
            empty.textContent = 'Nenhuma sugestão encontrada.';
            sugBox.appendChild(empty);
        }
    }

    renderSuggestions();
    card.appendChild(sugBox);

    return card;
}

async function fetchRadar() {
    const res = await fetch(apiRadar, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('Falha ao consultar radar');
    return res.json();
}

async function initRadar() {
    const grid = document.getElementById('radar-grid');
    const btn = document.getElementById('radar-refresh');
    const filters = document.getElementById('radar-filters');

    let functionsCache = [];
    const selected = new Set();

    function renderGrid() {
        grid.innerHTML = '';
        const funcsToShow = functionsCache.filter(f => selected.has(String(f.funcao_id)));
        if (funcsToShow.length === 0) {
            grid.textContent = 'Selecione uma ou mais funções acima.';
            return;
        }
        funcsToShow.forEach(fn => grid.appendChild(renderFunctionCard(fn)));
    }

    function renderFilters(funcs) {
        filters.innerHTML = '';
        funcs.forEach(fn => {
            const b = el('button', 'filter-btn');
            b.textContent = fn.funcao_nome;
            b.dataset.fid = String(fn.funcao_id);
            b.addEventListener('click', () => {
                const fid = b.dataset.fid;
                if (selected.has(fid)) selected.delete(fid); else selected.add(fid);
                b.classList.toggle('active', selected.has(fid));
                renderGrid();
            });
            filters.appendChild(b);
        });
    }

    const load = async () => {
        grid.textContent = 'Varrendo radar...';
        try {
            const data = await fetchRadar();
            if (data.error) {
                grid.textContent = data.message || 'Erro no radar';
                return;
            }
            functionsCache = data.functions || [];
            renderFilters(functionsCache);
            renderGrid(); // starts empty until user clicks
        } catch (e) {
            grid.textContent = 'Erro: ' + e.message;
            console.error(e);
        }
    };

    // Re-render grid when the global 'previous started' select changes
    const globalPrevSelect = document.getElementById('filter-prev-started');
    if (globalPrevSelect) globalPrevSelect.addEventListener('change', () => renderGrid());

    btn && btn.addEventListener('click', load);
    load();
}

document.addEventListener('DOMContentLoaded', initRadar);