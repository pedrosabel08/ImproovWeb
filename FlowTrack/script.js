const apiUrl = 'finalizacao_data.php';

const els = {
    obra: document.getElementById('filterObra'),
    tipoImagem: document.getElementById('filterTipoImagem'),
    finalizador: document.getElementById('filterFinalizador'),
    etapa: document.getElementById('filterEtapa'),
    status: document.getElementById('filterStatusFuncao'),
    limpar: document.getElementById('btnLimpar'),
    colP00: document.getElementById('colP00'),
    colR00: document.getElementById('colR00'),
    tpl: document.getElementById('cardTemplate'),
    kpiTotal: document.getElementById('kpiTotal'),
    kpiP00: document.getElementById('kpiP00'),
    kpiR00: document.getElementById('kpiR00'),
    kpiStatus: document.getElementById('kpiStatus')
};

let allItems = [];

async function fetchJSON(url, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(`${url}?${qs}`, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('Falha ao carregar dados');
    return res.json();
}

function fillSelect(select, items, getValue, getLabel, preserve = true) {
    const cur = select.value;
    select.options.length = 1;
    items.forEach((item) => {
        const opt = document.createElement('option');
        opt.value = getValue(item);
        opt.textContent = getLabel(item);
        select.appendChild(opt);
    });
    if (preserve && cur) {
        const found = Array.from(select.options).some(o => o.value === cur);
        if (found) select.value = cur; else select.value = '';
    }
}

function cardFromData(d) {
    const node = els.tpl.content.cloneNode(true);
    node.querySelector('.ft-imagem-nome').textContent = d.imagem_nome || '-';
    node.querySelector('.ft-finalizador').textContent = d.finalizador_nome || '-';
    node.querySelector('.ft-prazo').textContent = d.prazo || '-';
    node.querySelector('.ft-status').textContent = d.status_funcao || '-';
    node.querySelector('.ft-observacao').textContent = d.observacao || '';
    const article = node.querySelector('.ft-card');
    if (d.etapa) article.classList.add('etapa-' + String(d.etapa).toLowerCase());
    if (d.status_funcao) article.classList.add('status-' + String(d.status_funcao).toLowerCase().replace(/[^a-z0-9]/g, '-'));
    return node;
}

function renderColumns(items) {
    els.colP00.innerHTML = '';
    els.colR00.innerHTML = '';
    items.forEach((d) => {
        const frag = cardFromData(d);
        if (String(d.etapa).toUpperCase() === 'P00') els.colP00.appendChild(frag);
        else if (String(d.etapa).toUpperCase() === 'R00') els.colR00.appendChild(frag);
    });
}

function computeKPIs(items) {
    const k = { total: 0, p00: 0, r00: 0, by_status: {} };
    items.forEach(it => {
        k.total++;
        const et = String(it.etapa || '').toUpperCase();
        if (et === 'P00') k.p00++;
        if (et === 'R00') k.r00++;
        const st = it.status_funcao || '';
        if (st) k.by_status[st] = (k.by_status[st] || 0) + 1;
    });
    return k;
}

function updateKPIsFromItems(items) {
    const k = computeKPIs(items);
    els.kpiTotal.textContent = k.total || 0;
    els.kpiP00.textContent = k.p00 || 0;
    els.kpiR00.textContent = k.r00 || 0;
    if (k.by_status) {
        const parts = Object.keys(k.by_status).map(s => `${s}: ${k.by_status[s]}`);
        els.kpiStatus.textContent = parts.join(' | ');
    } else {
        els.kpiStatus.textContent = '-';
    }
}

function deriveListsFromItems(items) {
    const obras = {};
    const finalizadores = {};
    const tipos = {};
    const statuses = {};
    items.forEach(it => {
        if (it.obra_id) obras[it.obra_id] = { obra_id: it.obra_id, obra_nome: it.obra_nome || it.obra_id };
        if (it.usuario_id) finalizadores[it.usuario_id] = { usuario_id: it.usuario_id, usuario_nome: it.finalizador_nome || '' };
        if (it.tipo_imagem) tipos[it.tipo_imagem] = { tipo: it.tipo_imagem, label: it.tipo_imagem };
        if (it.status_funcao) statuses[it.status_funcao] = { key: it.status_funcao, label: it.status_funcao };
    });
    return {
        obras: Object.values(obras),
        finalizadores: Object.values(finalizadores),
        tipo_imagens: Object.values(tipos),
        statuses: Object.values(statuses)
    };
}

function applyLocalFilters() {
    const obra = els.obra.value;
    const tipo = els.tipoImagem ? els.tipoImagem.value : '';
    const finalizador = els.finalizador.value;
    const etapa = els.etapa.value;
    const status = els.status.value;

    let filtered = allItems.filter(it => {
        if (obra && String(it.obra_id) !== String(obra)) return false;
        if (tipo && String(it.tipo_imagem) !== String(tipo)) return false;
        if (finalizador && String(it.usuario_id) !== String(finalizador)) return false;
        if (etapa && String(it.etapa) !== String(etapa)) return false;
        if (status && String(it.status_funcao) !== String(status)) return false;
        return true;
    });

    // Update dependent filter options (finalizadores, statuses, tipo_imagens) based on the currently filtered set,
    // but preserve user selection if still present.
    const lists = deriveListsFromItems(filtered.length ? filtered : allItems);
    fillSelect(els.finalizador, lists.finalizadores, x => x.usuario_id, x => x.usuario_nome, true);
    if (els.tipoImagem) fillSelect(els.tipoImagem, lists.tipo_imagens, x => x.tipo, x => x.label, true);
    // Do not repopulate obra or etapa (they are broader)

    renderColumns(filtered);
    updateKPIsFromItems(filtered);
}

function bindEventsLocal() {
    let tmr = null;
    const debounced = () => { clearTimeout(tmr); tmr = setTimeout(applyLocalFilters, 150); };
    [els.obra, els.tipoImagem, els.finalizador, els.etapa, els.status].forEach(el => {
        if (!el) return;
        el.addEventListener('change', debounced);
    });
    els.limpar.addEventListener('click', () => {
        els.obra.value = '';
        if (els.tipoImagem) els.tipoImagem.value = '';
        els.finalizador.value = '';
        els.etapa.value = '';
        els.status.value = '';
        applyLocalFilters();
    });
}

async function fetchAllAndInit() {
    // fetch full dataset once
    const data = await fetchJSON(apiUrl, { action: 'list' });
    allItems = data.items || [];
    // derive initial lists from allItems
    const lists = deriveListsFromItems(allItems);
    fillSelect(els.obra, lists.obras, x => x.obra_id, x => x.obra_nome, false);
    fillSelect(els.finalizador, lists.finalizadores, x => x.usuario_id, x => x.usuario_nome, false);
    if (els.tipoImagem) fillSelect(els.tipoImagem, lists.tipo_imagens, x => x.tipo, x => x.label, false);
    // status select: prefer statuses from server via filters endpoint for completeness
    try {
        const f = await fetchJSON(apiUrl, { action: 'filters' });
        if (f.statuses) {
            fillSelect(els.status, f.statuses, x => x.key, x => x.label, false);
        }
    } catch (e) {
        // fallback: derive from items
        fillSelect(els.status, lists.statuses, x => x.key, x => x.label, false);
    }

    // initial render
    renderColumns(allItems);
    updateKPIsFromItems(allItems);
    bindEventsLocal();
}

(async function init() {
    try {
        await fetchAllAndInit();
    } catch (e) {
        console.error(e);
        alert('Erro inicializando a página.');
    }
})();
