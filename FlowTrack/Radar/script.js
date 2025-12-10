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

    // Sugestões
    const sugBox = el('div', 'radar-suggest');
    const sh = el('h3'); sh.textContent = 'Sugestões para alocar (anterior já andou)';
    sugBox.appendChild(sh);

    function renderSuggestions() {
        // clear previous list content except header
        while (sugBox.children.length > 1) sugBox.removeChild(sugBox.lastChild);
        const list = (fn.sugestoes || []).filter(s => !currentTipo || s.tipo_imagem === currentTipo);
        if (list.length) {
            list.forEach(s => {
                const it = el('div', 'suggest-item');
                const title = el('div');
                title.innerHTML = `<strong>${s.imagem_nome || 'Imagem ' + s.imagem_id}</strong> <span class="small">(${s.tipo_imagem || 'Tipo?'})</span>`;
                const obra = el('div', 'small'); obra.textContent = s.obra_nome || '';
                const st = el('div', 'small');
                st.innerHTML = `Anterior: <span class="status-prev">${s.prev_status}</span> | Atual: <span class="status-cur">${s.cur_status || '-'}</span>`;
                it.append(title, obra, st);
                sugBox.appendChild(it);
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

    btn && btn.addEventListener('click', load);
    load();
}

document.addEventListener('DOMContentLoaded', initRadar);