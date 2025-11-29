document.addEventListener('DOMContentLoaded', () => {
    const groupsEl = document.getElementById('groups');
    const titleEl = document.getElementById('detail-title');
    const prazoEl = document.getElementById('detail-prazo');
    const statusEl = document.getElementById('detail-status');
    const progressoEl = document.getElementById('detail-progresso');
    const imagesListEl = document.getElementById('images-list');
    const filterRespEl = document.getElementById('filter-responsavel');
    const filterEtapaEl = document.getElementById('filter-etapa');
    const filtersSection = document.querySelector('.filters');

    let currentEntrega = null;
    let currentDetalhe = null;
    let hideDelivered = false; // estado do toggle

    const fmtDateBR = (ymd) => {
        if (!ymd) return '-';
        const [y, m, d] = ymd.split('-');
        return `${d}/${m}/${y}`;
    };

    const getBadgeClass = (entrega, hoje) => {
        const prev = entrega.data_prevista;
        if (prev < hoje && entrega.kanban_status !== 'concluida') return 'red';
        if (prev === hoje) return 'yellow';
        return entrega.kanban_status === 'concluida' ? 'green' : 'blue';
    };

    const groupByPriority = (items) => {
        const hoje = new Date();
        const fmt = (d) => d.toISOString().slice(0, 10);
        const todayStr = fmt(hoje);
        const tomorrowStr = fmt(new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate() + 1));
        const in7 = fmt(new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate() + 7));

        const groups = {
            'Atrasadas': [],
            'Entrega hoje': [],
            'Entrega amanhã': [],
            'Próximas (até 7 dias)': [],
            'Futuras': [],
        };

        items.forEach(e => {
            const d = e.data_prevista;
            if (d < todayStr && e.kanban_status !== 'concluida') groups['Atrasadas'].push(e);
            else if (d === todayStr) groups['Entrega hoje'].push(e);
            else if (d === tomorrowStr) groups['Entrega amanhã'].push(e);
            else if (d > tomorrowStr && d <= in7) groups['Próximas (até 7 dias)'].push(e);
            else groups['Futuras'].push(e);
        });

        // sort inside groups: atrasadas -> mais recentes primeiro; demais por data asc
        groups['Atrasadas'].sort((a, b) => b.data_prevista.localeCompare(a.data_prevista));
        const asc = (a, b) => a.data_prevista.localeCompare(b.data_prevista);
        groups['Entrega hoje'].sort(asc);
        groups['Entrega amanhã'].sort(asc);
        groups['Próximas (até 7 dias)'].sort(asc);
        groups['Futuras'].sort(asc);

        return { groups, todayStr };
    };

    const renderGroups = (groups, todayStr) => {
        groupsEl.innerHTML = '';
        const order = ['Atrasadas', 'Entrega hoje', 'Entrega amanhã', 'Próximas (até 7 dias)', 'Futuras'];
        order.forEach(name => {
            const arr = groups[name];
            if (!arr || arr.length === 0) return;
            const g = document.createElement('div');
            g.className = 'group';
            g.innerHTML = `<div class="group-title">${name}</div>`;
            const list = document.createElement('div');
            list.className = 'cards';
            arr.forEach(e => {
                const badge = getBadgeClass(e, todayStr);
                const card = document.createElement('div');
                card.className = 'card';
                card.dataset.id = e.id;
                card.innerHTML = `
          <div class="card-header">
            <div class="card-title">${e.nomenclatura} — ${e.nome_etapa}</div>
            <span class="badge ${badge}"></span>
          </div>
          <div class="card-row"><strong>Código:</strong> ${e.nome_etapa}</div>
          <div class="card-row"><strong>Prazo:</strong> ${fmtDateBR(e.data_prevista)}</div>
          <div class="progress"><div class="progress-bar" style="width:${e.pct_entregue}%"></div></div>
          <div class="card-row">${e.entregues}/${e.total_itens} imagens</div>
        `;
                card.addEventListener('click', () => loadDetail(e));
                list.appendChild(card);
            });
            g.appendChild(list);
            groupsEl.appendChild(g);
        });
    };

    const loadDetail = async (entrega) => {
        currentEntrega = entrega;
        titleEl.textContent = `${entrega.nomenclatura} — ${entrega.nome_etapa}`;
        prazoEl.textContent = fmtDateBR(entrega.data_prevista);
        const badge = getBadgeClass(entrega, new Date().toISOString().slice(0, 10));
        statusEl.textContent = (badge === 'red' ? 'Atrasada' : badge === 'yellow' ? 'Hoje' : (badge === 'green' ? 'Concluída' : 'Em andamento'));
        progressoEl.textContent = `${entrega.entregues}/${entrega.total_itens} imagens`;

        // fetch detalhe
        try {
            const res = await fetch(`${API_DETALHE}?entrega_id=${encodeURIComponent(entrega.id)}`);
            const json = await res.json();
            currentDetalhe = json;
            renderFilters(json.itens);
            // decidir default do hideDelivered (se >30% entregues)
            const deliveredCount = json.itens.filter(i => i.entregue).length;
            const ratio = json.itens.length ? deliveredCount / json.itens.length : 0;
            hideDelivered = ratio >= 0.3; // oculta por padrão se muitos entregues
            ensureHideDeliveredToggle();
            updateHideDeliveredUI();
            renderImages(json.itens);
        } catch (err) {
            console.error('Erro ao carregar detalhe:', err);
            imagesListEl.innerHTML = '<p>Erro ao carregar imagens.</p>';
        }
    };

    const renderFilters = (items) => {
        const responsaveis = Array.from(new Set(items.map(i => i.responsavel).filter(Boolean)));
        filterRespEl.innerHTML = '<option value="">Todos</option>' + responsaveis.map(r => `<option value="${r}">${r}</option>`).join('');
    };

    const etapaClass = (etapa) => {
        if (!etapa) return 'etapa-Aguardando aprovação';
        const map = {
            'Render': 'etapa-Render',
            'Em render': 'etapa-Em-render',
            'Pós-produção': 'etapa-Pós',
            'Finalização': 'etapa-Finalização',
            'Em aprovação': 'etapa-Em-aprovacao',
            'Aguardando aprovação': 'etapa-Aguardando aprovação',
            'Entregue': 'etapa-Entregue'
        };
        return map[etapa] || 'etapa-Aguardando aprovação';
    };

    const deriveEtapa = (item) => {
        if (item.entregue) return 'Entregue';
        // substatus 7 => Em render
        if (item.substatus_id === 7) return 'Em render';
        if (item.funcao_status === 'Em andamento') return 'Finalização';
        if (item.funcao_status === 'Em aprovação') return 'Em aprovação';
        return item.etapa || 'Aguardando aprovação';
    };

    const renderImages = (items) => {
        const resp = filterRespEl.value || '';
        const etapa = filterEtapaEl.value || '';
        const filtered = items.filter(i => {
            if (resp && i.responsavel !== resp) return false;
            if (etapa && i.etapa !== etapa) return false;
            if (hideDelivered && i.entregue) return false;
            return true;
        });
        imagesListEl.innerHTML = '';
        filtered.forEach(i => {
            const el = document.createElement('div');
            el.className = 'image-item' + (i.entregue ? ' entregue' : '');
            const etapaDerivada = deriveEtapa(i);
            el.innerHTML = `
                                <div class="image-line">
                                    <div class="image-name">${i.nome_imagem}</div>
                                    <span class="etapa-pill ${etapaClass(etapaDerivada)}">${etapaDerivada}</span>
                                </div>
                                <div class="image-meta">
                                    <div><strong>Responsável:</strong> ${i.responsavel || '-'}</div>
                                </div>
                        `;
            imagesListEl.appendChild(el);
        });
    };

    // Toggle para ocultar entregues
    function ensureHideDeliveredToggle() {
        if (!filtersSection) return;
        if (filtersSection.querySelector('#toggleHideDelivered')) return; // já existe
        const wrapper = document.createElement('div');
        wrapper.className = 'filter hide-delivered-filter';
        wrapper.innerHTML = `
            <label class="toggle-delivered">
                <input type="checkbox" id="toggleHideDelivered" /> Ocultar entregues
            </label>
        `;
        filtersSection.appendChild(wrapper);
        const cb = wrapper.querySelector('#toggleHideDelivered');
        cb.addEventListener('change', () => {
            hideDelivered = cb.checked;
            if (currentDetalhe) renderImages(currentDetalhe.itens);
        });
    }
    function updateHideDeliveredUI() {
        const cb = document.getElementById('toggleHideDelivered');
        if (cb) cb.checked = hideDelivered;
    }

    filterRespEl.addEventListener('change', () => {
        if (currentDetalhe) renderImages(currentDetalhe.itens);
    });
    filterEtapaEl.addEventListener('change', () => {
        if (currentDetalhe) renderImages(currentDetalhe.itens);
    });

    const init = async () => {
        try {
            const res = await fetch(API_LISTAR);
            const entregas = await res.json();
            const { groups, todayStr } = groupByPriority(entregas);
            renderGroups(groups, todayStr);
            // auto-select first available
            const firstGroup = ['Atrasadas', 'Entrega hoje', 'Entrega amanhã', 'Próximas (até 7 dias)', 'Futuras'].find(g => groups[g] && groups[g].length);
            if (firstGroup) loadDetail(groups[firstGroup][0]);
        } catch (err) {
            console.error('Erro ao carregar entregas:', err);
        }
    };

    init();
});
