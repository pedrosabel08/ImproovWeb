async function fetchData(start_status, tipo) {
    const params = new URLSearchParams();
    if (start_status) params.set('start_status', start_status);
    if (tipo) params.set('tipo_imagem', tipo);
    const res = await fetch(`status_tipo_api.php?${params.toString()}`);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return await res.json();
}

function hoursToDaysLabel(h) {
    if (h === null || h === undefined || h === '') return '-';
    const num = Number(h);
    if (isNaN(num)) return h;
    const days = (num / 24);
    return `${num} h <span class="days">(${days.toFixed(1)} d)</span>`;
}

function createTable(rows) {
    const table = document.createElement('table');
    table.className = 'table';
    const thead = document.createElement('thead');
    thead.innerHTML = `<tr><th>Tipo</th><th>Total</th><th>Média (h / d)</th><th>Mediana P50 (h / d)</th><th>P75 (h / d)</th><th>P90 (h / d)</th></tr>`;
    table.appendChild(thead);
    const tbody = document.createElement('tbody');
    rows.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${r.tipo_imagem}</td><td>${r.total_imagens}</td><td>${hoursToDaysLabel(r.media_horas)}</td><td>${hoursToDaysLabel(r.mediana_p50)}</td><td>${hoursToDaysLabel(r.p75)}</td><td>${hoursToDaysLabel(r.p90)}</td>`;
        tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    return table;
}

function renderDashboard(data) {
    const container = document.getElementById('cards');
    container.innerHTML = '';
    data.forEach(block => {
        const card = document.createElement('div');
        card.className = 'card';
        const title = document.createElement('h3');
        title.textContent = `${block.status_name} → ${block.end_substatus_name}`;
        card.appendChild(title);
        if (block.rows && block.rows.length) {
            card.appendChild(createTable(block.rows));
        } else if (block.rows && block.rows.error) {
            const err = document.createElement('div'); err.className = 'small'; err.textContent = 'Erro: ' + block.rows.error; card.appendChild(err);
        } else {
            const none = document.createElement('div'); none.className = 'small'; none.textContent = 'Nenhum registro'; card.appendChild(none);
        }
        container.appendChild(card);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('refresh');
    const select = document.getElementById('status_select');
    const tipo = document.getElementById('tipo_input');

    async function load() {
        try {
            btn.disabled = true; btn.textContent = 'Carregando...';
            const statusVal = select.value || null;
            const tipoVal = tipo.value || '';
            const data = await fetchData(statusVal, tipoVal);
            renderDashboard(data);
        } catch (e) {
            document.getElementById('cards').innerHTML = '<div style="color:red">Erro ao carregar: ' + e.message + '</div>';
        } finally {
            btn.disabled = false; btn.textContent = 'Atualizar';
        }
    }

    btn.addEventListener('click', load);
    load();
});
