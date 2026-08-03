document.addEventListener('DOMContentLoaded', () => {
    const STATUS = {
        nao_gerado: { label: 'Não gerado', icon: 'fa-circle-minus' },
        gerado: { label: 'Gerado', icon: 'fa-file-lines' },
        enviado: { label: 'Enviado', icon: 'fa-paper-plane' },
        visualizado: { label: 'Visualizado', icon: 'fa-eye' },
        assinado: { label: 'Assinado', icon: 'fa-signature' },
        expirado: { label: 'Expirado', icon: 'fa-clock' },
        recusado: { label: 'Recusado', icon: 'fa-circle-xmark' },
    };
    const STAGES = ['gerado', 'enviado', 'visualizado', 'assinado'];
    const state = { items: [], competencia: '', competenciaAtual: '', selected: new Set(), sort: { key: 'colaborador', direction: 'asc' } };

    const elements = {
        competencia: document.getElementById('competencia'),
        pesquisa: document.getElementById('pesquisa'),
        status: document.getElementById('filtro-status'),
        pendentes: document.getElementById('filtro-pendentes'),
        body: document.getElementById('contratos-body'),
        selectAll: document.getElementById('select-all'),
        tableCount: document.getElementById('table-count'),
        total: document.getElementById('colaboradores-total'),
        menu: document.getElementById('menu-acoes'),
        bulk: document.getElementById('bulk-actions'),
        bulkCount: document.getElementById('bulk-count'),
        history: document.getElementById('history-modal'),
        historyContent: document.getElementById('history-content'),
        historyTitle: document.getElementById('history-title'),
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
    const statusInfo = (status) => STATUS[status] || STATUS.nao_gerado;
    const toast = (message, type = 'info') => {
        if (window.Toastify) {
            Toastify({ text: message, duration: 3500, gravity: 'top', position: 'right', className: `contract-toast ${type}` }).showToast();
            return;
        }
        window.alert(message);
    };

    function formatCompetencia(competencia) {
        if (!/^\d{4}-\d{2}$/.test(competencia || '')) return '—';
        const [year, month] = competencia.split('-');
        return new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' })
            .format(new Date(Number(year), Number(month) - 1, 1))
            .replace(' de ', ' / ')
            .replace(/^./, (letter) => letter.toUpperCase());
    }

    function formatDate(value, relative = false) {
        if (!value || value === '0000-00-00 00:00:00') return '—';
        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return value;
        if (relative) {
            const diff = Date.now() - date.getTime();
            const minutes = Math.floor(diff / 60000);
            if (minutes < 1) return 'agora';
            if (minutes < 60) return `há ${minutes} min`;
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return `há ${hours} h`;
            if (hours < 48) return 'ontem';
        }
        return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(date);
    }

    function statusBadge(status) {
        const info = statusInfo(status);
        return `<span class="status-badge status-${escapeHtml(status)}"><i class="fa-solid ${info.icon}"></i>${info.label}</span>`;
    }

    function progressMarkup(status) {
        if (status === 'nao_gerado') return '<span class="progress-empty">Aguardando geração</span>';
        if (['expirado', 'recusado'].includes(status)) return '<span class="progress-empty">Fluxo encerrado</span>';
        const activeIndex = STAGES.indexOf(status);
        return `<div class="contract-progress" aria-label="Etapa atual: ${statusInfo(status).label}">${STAGES.map((stage, index) => {
            const completed = index <= activeIndex;
            return `<span class="progress-step ${completed ? 'is-complete' : ''} ${index === activeIndex ? 'is-current' : ''}" title="${statusInfo(stage).label}"><i class="fa-solid ${statusInfo(stage).icon}"></i></span>${index < STAGES.length - 1 ? `<span class="progress-line ${index < activeIndex ? 'is-complete' : ''}"></span>` : ''}`;
        }).join('')}</div>`;
    }

    function visibleItems() {
        const term = elements.pesquisa.value.trim().toLocaleLowerCase('pt-BR');
        const status = elements.status.value;
        return state.items.filter((item) => {
            const matchesText = !term || item.colaborador_nome.toLocaleLowerCase('pt-BR').includes(term);
            const matchesStatus = !status || item.status === status;
            const pending = ['nao_gerado', 'gerado', 'enviado', 'visualizado'].includes(item.status);
            return matchesText && matchesStatus && (!elements.pendentes.checked || pending);
        }).sort(compareItems);
    }

    function compareItems(a, b) {
        const { key, direction } = state.sort;
        const values = {
            colaborador: [a.colaborador_nome, b.colaborador_nome],
            competencia: [a.competencia, b.competencia],
            status: [statusInfo(a.status).label, statusInfo(b.status).label],
            atualizacao: [a.ultima_atualizacao || '', b.ultima_atualizacao || ''],
        };
        const [left, right] = values[key] || values.colaborador;
        return String(left).localeCompare(String(right), 'pt-BR', { numeric: true }) * (direction === 'asc' ? 1 : -1);
    }

    function renderTable() {
        closeMenu();
        const items = visibleItems();
        elements.tableCount.textContent = `${items.length} de ${state.items.length}`;
        elements.selectAll.checked = items.length > 0 && items.every((item) => state.selected.has(item.colaborador_id));
        elements.selectAll.indeterminate = items.some((item) => state.selected.has(item.colaborador_id)) && !elements.selectAll.checked;

        if (!items.length) {
            elements.body.innerHTML = '<tr><td colspan="6" class="table-empty">Nenhum contrato encontrado com estes filtros.</td></tr>';
            updateBulkActions();
            return;
        }
        elements.body.innerHTML = items.map((item) => {
            const action = primaryAction(item);
            return `<tr data-colaborador-id="${item.colaborador_id}">
                <td class="select-column"><input class="row-select" type="checkbox" data-id="${item.colaborador_id}" aria-label="Selecionar ${escapeHtml(item.colaborador_nome)}" ${state.selected.has(item.colaborador_id) ? 'checked' : ''}></td>
                <td><strong class="collaborator-name">${escapeHtml(item.colaborador_nome)}</strong></td>
                <td><span class="competencia-value">${formatCompetencia(item.competencia)}</span>${item.is_competencia_atual ? '<span class="current-pill">Atual</span>' : ''}</td>
                <td><div class="status-cell">${statusBadge(item.status)}${progressMarkup(item.status)}</div></td>
                <td><span class="updated-date" title="${escapeHtml(formatDate(item.ultima_atualizacao))}">${formatDate(item.ultima_atualizacao, true)}</span></td>
                <td class="actions-cell">${action}<button class="more-actions" data-id="${item.colaborador_id}" aria-label="Mais ações para ${escapeHtml(item.colaborador_nome)}" title="Mais ações"><i class="fa-solid fa-ellipsis"></i></button></td>
            </tr>`;
        }).join('');
        updateBulkActions();
    }

    function primaryAction(item) {
        if (item.can_generate) return `<button class="row-action primary generate-contract" data-id="${item.colaborador_id}"><i class="fa-solid fa-file-circle-plus"></i> Gerar contrato</button>`;
        if (item.can_download) return `<button class="row-action download-contract" data-id="${item.colaborador_id}"><i class="fa-solid fa-download"></i> Baixar</button>`;
        if (item.can_history) return `<button class="row-action history-contract" data-id="${item.colaborador_id}"><i class="fa-solid fa-timeline"></i> Acompanhamento</button>`;
        return '<span class="no-action">Sem ação disponível</span>';
    }

    function updateSummary(resumo) {
        elements.total.textContent = `${resumo.total} colaboradores`;
        document.getElementById('resumo-assinado').textContent = resumo.assinado;
        document.getElementById('resumo-pendente').textContent = resumo.pendente_assinatura;
        document.getElementById('resumo-expirado').textContent = resumo.expirado + resumo.recusado;
        document.getElementById('resumo-nao-gerado').textContent = resumo.nao_gerado;
    }

    function updateCompetencias(competencias) {
        const selected = state.competencia;
        elements.competencia.innerHTML = competencias.map((item) => `<option value="${item}" ${item === selected ? 'selected' : ''}>${formatCompetencia(item)}</option>`).join('');
    }

    async function loadDashboard(competencia = '') {
        elements.body.innerHTML = '<tr><td colspan="6" class="table-empty">Atualizando contratos…</td></tr>';
        try {
            const selectedCompetencia = competencia || state.competencia || elements.competencia.value;
            const query = selectedCompetencia ? `&competencia=${encodeURIComponent(selectedCompetencia)}` : '';
            const response = await fetch(`./status.php?mode=dashboard${query}`, { credentials: 'same-origin' });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Não foi possível carregar os contratos.');
            state.items = data.items || [];
            state.competencia = data.competencia;
            state.competenciaAtual = data.competencia_atual;
            state.selected.clear();
            updateCompetencias(data.competencias || [data.competencia]);
            updateSummary(data.resumo);
            renderTable();
        } catch (error) {
            elements.body.innerHTML = `<tr><td colspan="6" class="table-empty table-error">${escapeHtml(error.message)}</td></tr>`;
            toast(error.message, 'error');
        }
    }

    function findItem(id) {
        return state.items.find((item) => item.colaborador_id === Number(id));
    }

    async function generateContract(item) {
        if (!item || (!item.can_generate && !item.can_regenerate)) return;
        const isRegeneration = Boolean(item.contrato_id);
        if (isRegeneration && !window.confirm(`Gerar uma nova versão do contrato de ${item.colaborador_nome} para ${formatCompetencia(item.competencia)}?`)) return;
        try {
            const response = await fetch('./gerar_contrato.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ colaborador_id: item.colaborador_id, competencia: state.competencia }),
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Não foi possível gerar o contrato.');
            toast(isRegeneration ? 'Nova versão do contrato gerada.' : 'Contrato gerado com sucesso.', 'success');
            await loadDashboard(state.competencia);
        } catch (error) {
            toast(error.message || 'Erro de rede ao gerar contrato.', 'error');
        }
    }

    function closeMenu() {
        elements.menu.hidden = true;
        elements.menu.innerHTML = '';
    }

    function openMenu(button, item) {
        if (!item) return;
        const actions = [];
        if (item.can_download) actions.push(`<button class="menu-download" data-id="${item.colaborador_id}"><i class="fa-solid fa-download"></i> Baixar</button>`);
        if (item.can_history) actions.push(`<button class="menu-history" data-id="${item.colaborador_id}"><i class="fa-solid fa-timeline"></i> Histórico</button>`);
        if (item.sign_url && ['enviado', 'visualizado'].includes(item.status)) actions.push(`<a href="${escapeHtml(item.sign_url)}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir assinatura</a>`);
        if (item.can_regenerate) actions.push(`<button class="menu-regenerate" data-id="${item.colaborador_id}"><i class="fa-solid fa-rotate"></i> Gerar novamente</button>`);
        if (!actions.length) actions.push('<span class="menu-empty">Nenhuma ação disponível</span>');
        elements.menu.innerHTML = actions.join('');
        const rect = button.getBoundingClientRect();
        elements.menu.style.top = `${rect.bottom + window.scrollY + 6}px`;
        elements.menu.style.left = `${Math.max(12, rect.right + window.scrollX - 190)}px`;
        elements.menu.hidden = false;
    }

    async function openHistory(item) {
        if (!item?.contrato_id) return;
        elements.history.hidden = false;
        elements.historyTitle.textContent = `Histórico · ${item.colaborador_nome}`;
        elements.historyContent.innerHTML = '<p class="history-loading">Carregando acompanhamento…</p>';
        try {
            const response = await fetch(`./status.php?mode=historico&contrato_id=${item.contrato_id}`, { credentials: 'same-origin' });
            const data = await response.json();
            if (!response.ok || !data.success || !data.contrato) throw new Error(data.message || 'Histórico indisponível.');
            renderHistory(data.contrato, data.historico || []);
        } catch (error) {
            elements.historyContent.innerHTML = `<p class="history-loading table-error">${escapeHtml(error.message)}</p>`;
        }
    }

    function renderHistory(contract, history) {
        const events = history.length ? history : syntheticHistory(contract);
        elements.historyContent.innerHTML = `
            <div class="history-meta"><div><span>Competência</span><strong>${formatCompetencia(contract.competencia)}</strong></div><div><span>Status atual</span>${statusBadge(contract.status)}</div></div>
            <div class="history-timeline">${events.length ? events.map((event) => {
                const info = statusInfo(event.status || event.acao);
                return `<article class="timeline-event"><span class="timeline-dot status-${escapeHtml(event.status || 'nao_gerado')}"><i class="fa-solid ${info.icon}"></i></span><div><strong>${info.label}</strong><p>${escapeHtml(event.acao ? event.acao.replaceAll('_', ' ') : 'Atualização do contrato')}</p><time>${formatDate(event.ocorrido_em || event.created_at)}</time></div></article>`;
            }).join('') : '<p class="history-loading">Sem eventos registrados.</p>'}</div>`;
    }

    function syntheticHistory(contract) {
        const events = [];
        if (contract.data_geracao) events.push({ status: 'gerado', acao: 'Contrato gerado', ocorrido_em: contract.data_geracao });
        if (contract.data_envio && contract.status !== 'gerado') events.push({ status: 'enviado', acao: 'Contrato enviado', ocorrido_em: contract.data_envio });
        if (contract.assinado_em) events.push({ status: 'assinado', acao: 'Contrato assinado', ocorrido_em: contract.assinado_em });
        return events;
    }

    function updateBulkActions() {
        const selected = state.items.filter((item) => state.selected.has(item.colaborador_id));
        elements.bulk.hidden = selected.length === 0;
        elements.bulkCount.textContent = `${selected.length} contrato${selected.length === 1 ? '' : 's'} selecionado${selected.length === 1 ? '' : 's'}`;
        document.getElementById('bulk-gerar').disabled = !selected.some((item) => item.can_generate);
        document.getElementById('bulk-zip').disabled = !selected.some((item) => item.can_download);
    }

    async function bulkGenerate() {
        const items = state.items.filter((item) => state.selected.has(item.colaborador_id) && item.can_generate);
        if (!items.length) return;
        if (!window.confirm(`Gerar ${items.length} contrato${items.length === 1 ? '' : 's'} da competência atual?`)) return;
        for (const item of items) await generateContract(item);
    }

    function bulkZip() {
        const ids = state.items.filter((item) => state.selected.has(item.colaborador_id) && item.can_download).map((item) => item.colaborador_id);
        if (!ids.length) return;
        window.open(`./lote.php?competencia=${encodeURIComponent(state.competencia)}&colaborador_ids=${ids.join(',')}`, '_blank', 'noopener');
    }

    function bulkExport() {
        const items = state.items.filter((item) => state.selected.has(item.colaborador_id));
        if (!items.length) return;
        const rows = [['Colaborador', 'Competência', 'Status', 'Gerado em', 'Assinado em', 'Última atualização'], ...items.map((item) => [item.colaborador_nome, formatCompetencia(item.competencia), statusInfo(item.status).label, formatDate(item.data_geracao), formatDate(item.assinado_em), formatDate(item.ultima_atualizacao)])];
        const csv = rows.map((row) => row.map((cell) => `"${String(cell).replaceAll('"', '""')}"`).join(';')).join('\n');
        const url = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `contratos_${state.competencia}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    }

    elements.competencia.addEventListener('change', () => loadDashboard(elements.competencia.value));
    [elements.pesquisa, elements.status, elements.pendentes].forEach((element) => element.addEventListener(element === elements.pesquisa ? 'input' : 'change', renderTable));
    elements.selectAll.addEventListener('change', () => {
        visibleItems().forEach((item) => elements.selectAll.checked ? state.selected.add(item.colaborador_id) : state.selected.delete(item.colaborador_id));
        renderTable();
    });
    document.querySelectorAll('.sort-button').forEach((button) => button.addEventListener('click', () => {
        const key = button.dataset.sort;
        state.sort.direction = state.sort.key === key && state.sort.direction === 'asc' ? 'desc' : 'asc';
        state.sort.key = key;
        renderTable();
    }));
    elements.body.addEventListener('change', (event) => {
        const checkbox = event.target.closest('.row-select');
        if (!checkbox) return;
        checkbox.checked ? state.selected.add(Number(checkbox.dataset.id)) : state.selected.delete(Number(checkbox.dataset.id));
        renderTable();
    });
    elements.body.addEventListener('click', (event) => {
        const id = event.target.closest('[data-id]')?.dataset.id;
        if (!id) return;
        const item = findItem(id);
        if (event.target.closest('.generate-contract')) generateContract(item);
        else if (event.target.closest('.download-contract')) window.open(item.download_url, '_blank', 'noopener');
        else if (event.target.closest('.history-contract')) openHistory(item);
        else if (event.target.closest('.more-actions')) openMenu(event.target.closest('.more-actions'), item);
    });
    elements.menu.addEventListener('click', (event) => {
        const id = event.target.closest('[data-id]')?.dataset.id;
        if (!id) return;
        const item = findItem(id);
        if (event.target.closest('.menu-download')) window.open(item.download_url, '_blank', 'noopener');
        if (event.target.closest('.menu-history')) openHistory(item);
        if (event.target.closest('.menu-regenerate')) generateContract(item);
        closeMenu();
    });
    document.addEventListener('click', (event) => { if (!event.target.closest('.more-actions, .actions-menu')) closeMenu(); });
    document.getElementById('bulk-limpar').addEventListener('click', () => { state.selected.clear(); renderTable(); });
    document.getElementById('bulk-gerar').addEventListener('click', bulkGenerate);
    document.getElementById('bulk-zip').addEventListener('click', bulkZip);
    document.getElementById('bulk-exportar').addEventListener('click', bulkExport);
    document.getElementById('history-close').addEventListener('click', () => { elements.history.hidden = true; });
    elements.history.addEventListener('click', (event) => { if (event.target === elements.history) elements.history.hidden = true; });

    loadDashboard();
});
