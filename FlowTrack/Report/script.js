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
            const tbl = makeTableForObra(obra, funcoes);
            root.appendChild(tbl);
            root.appendChild(document.createElement('br'));
        });
        if (!data.obras || data.obras.length === 0) root.textContent = 'Nenhuma obra ativa encontrada.';
    } catch (e) {
        root.textContent = 'Falha carregando relatório: ' + e.message;
        console.error(e);
    }
}

init();
