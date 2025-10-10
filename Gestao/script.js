function gerarMetricas() {
    fetch('getMetrics.php')
        .then(response => response.json())
        .then(data => {
            const metricas = data.metricas_funcoes || {};
            const obrasAtivas = data.obras_ativas || 0;
            const imagensAtivas = data.imagens_ativas || 0;

            // Inicializa contadores
            let tarefasTodo = 0;
            let tarefasTea = 0;
            let tarefasConcluidas = 0;
            let tarefasHold = 0;
            let tarefasApr = 0;

            // Percorre todos os status retornados
            Object.entries(metricas).forEach(([status, qtd]) => {
                const q = Number(qtd) || 0;

                // TO-DO → tarefas ainda não iniciadas ou em andamento
                if (["Não iniciado"].includes(status)) tarefasTodo += q;

                // TEA → tarefas em aprovação, com ajustes, ou em hold
                if (["Em aprovação", "Aprovado com ajustes", "Ajuste"].includes(status)) tarefasApr += q;

                // TEA → tarefas em aprovação, com ajustes, ou em hold
                if (["Em andamento"].includes(status)) tarefasTea += q;
                // HOLD
                if (["HOLD"].includes(status)) tarefasHold += q;

                // Concluídas
                if (["Finalizado"].includes(status)) tarefasConcluidas += q;
            });

            // Atualiza os cards no HTML (seguindo a ordem original)
            const cards = document.querySelectorAll('.section-metrics .card-metric');
            if (cards.length >= 7) {
                cards[0].querySelector('strong').textContent = obrasAtivas;      // Projetos ativos
                cards[1].querySelector('strong').textContent = imagensAtivas;    // Total de imagens
                cards[2].querySelector('strong').textContent = tarefasTodo;      // Tarefas TO-DO
                cards[3].querySelector('strong').textContent = tarefasTea;       // Tarefas TEA
                cards[4].querySelector('strong').textContent = tarefasConcluidas;// Tarefas concluídas
                cards[5].querySelector('strong').textContent = tarefasHold;// Tarefas concluídas
                cards[6].querySelector('strong').textContent = tarefasApr;// Tarefas concluídas
            }
        })
        .catch(error => console.error('Erro ao buscar métricas:', error));
}



async function atualizarProgressoFuncoes() {
    try {
        const response = await fetch('getFuncoesDados.php');
        const data = await response.json();

        if (!data.metricas_funcoes || !data.colaboradores_funcoes) {
            console.error('Nenhuma métrica ou colaborador encontrada.');
            return;
        }

        const metricas = data.metricas_funcoes;
        const colaboradores = data.colaboradores_funcoes;

        document.querySelectorAll('.card-group').forEach(card => {
            // Permite múltiplas funções separadas por vírgula
            const funcoes = card.dataset.funcao.split(',').map(f => f.trim());

            let total = 0;
            let emAndamento = 0;

            // Soma métricas de todas as funções do card
            funcoes.forEach(funcao => {
                const dados = metricas[funcao];
                if (dados) {
                    total += dados.total;
                    emAndamento += dados.em_andamento;
                }
            });

            // Atualiza progress bar
            const porcentagem = total > 0 ? (emAndamento / total) * 100 : 0;
            const progressBar = card.querySelector('.progress-bar');
            progressBar.style.width = `${porcentagem.toFixed(1)}%`;
            progressBar.title = `${porcentagem.toFixed(1)}% concluído (${emAndamento}/${total})`;

            // Atualiza contagem de tarefas abaixo da barra
            let existingSmall = card.querySelector('small.progress-count');
            if (existingSmall) existingSmall.remove();
            const contagem = document.createElement('small');
            contagem.classList.add('progress-count');
            contagem.textContent = `${emAndamento}/${total} tarefas`;
            const progressContainer = card.querySelector('.progress');
            progressContainer.insertAdjacentElement('afterend', contagem);

            // Preenche a div colabs com os colaboradores
            const colabsDiv = card.querySelector('.colabs');
            colabsDiv.innerHTML = ''; // limpa antes
            funcoes.forEach(funcao => {
                const listaColabs = colaboradores[funcao] || [];
                listaColabs.forEach(nome => {
                    const circle = document.createElement('div');
                    circle.classList.add('colab-circle', 'tooltip');
                    circle.setAttribute('data-tooltip', nome)
                    circle.textContent = nome.charAt(0).toUpperCase();
                    colabsDiv.appendChild(circle);
                });
            });
        });

    } catch (error) {
        console.error('Erro ao atualizar progresso:', error);
    }
}


async function atualizarGantt(funcao = '') {
    const container = document.getElementById('gantt');
    container.style.position = 'relative';
    container.innerHTML = '<div style="padding:12px">Carregando...</div>';

    // fetch
    let res;
    try {
        res = await fetch(`getGanttDados.php?funcao=${encodeURIComponent(funcao)}`);
        if (!res.ok) throw new Error('Erro ao carregar dados: ' + res.status);
    } catch (err) {
        container.innerHTML = `<div style="padding:12px;color:#a00">Erro: ${err.message}</div>`;
        return;
    }

    const data = await res.json();

    // parseSafe (evita -1 dia)
    const parseSafe = s => {
        if (!s) return null;
        const parts = s.split('-');
        if (parts.length < 3) return null;
        const d = new Date(parts[0], parts[1] - 1, parts[2]);
        d.setHours(0, 0, 0, 0);
        return d;
    };

    const colaboradores = Object.keys(data);
    const validTasksByCol = {};
    const noDateTasksByCol = {};
    let minDate = null, maxDate = null;

    colaboradores.forEach(col => {
        validTasksByCol[col] = [];
        noDateTasksByCol[col] = [];
        (data[col] || []).forEach(task => {
            let s = parseSafe(task.start);
            let e = parseSafe(task.end);
            if (!s || !e) {
                noDateTasksByCol[col].push(task);
                return;
            }
            if (s.getTime() > e.getTime()) [s, e] = [e, s];
            validTasksByCol[col].push({ ...task, startDate: s, endDate: e });

            if (!minDate || s < minDate) minDate = new Date(s);
            if (!maxDate || e > maxDate) maxDate = new Date(e);
        });
    });

    if (!minDate || !maxDate) {
        const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
        minDate = new Date(hoje); minDate.setDate(minDate.getDate() - 3);
        maxDate = new Date(hoje); maxDate.setDate(maxDate.getDate() + 3);
    }

    // limitar para performance
    const MAX_DAYS = 240;
    const diffDays = Math.round((maxDate - minDate) / (24 * 3600 * 1000)) + 1;
    if (diffDays > MAX_DAYS) {
        const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
        const half = Math.floor(MAX_DAYS / 2);
        minDate = new Date(hoje); minDate.setDate(hoje.getDate() - half);
        maxDate = new Date(hoje); maxDate.setDate(hoje.getDate() + half);
    }

    // dias array
    const dias = [];
    const cur = new Date(minDate);
    while (cur <= maxDate) { dias.push(new Date(cur)); cur.setDate(cur.getDate() + 1); }

    // leitura de CSS vars (números)
    const colLeftRaw = getComputedStyle(document.documentElement).getPropertyValue('--col-left') || '220px';
    const baseCellHRaw = getComputedStyle(document.documentElement).getPropertyValue('--cell-h') || '50px';
    const colLeft = parseFloat(colLeftRaw);
    const baseCellH = parseFloat(baseCellHRaw);

    const hasNoDate = Object.values(noDateTasksByCol).some(arr => arr.length > 0);
    const noDateWidth = hasNoDate ? 140 : 0;

    // opções responsivas
    const minCellW = 80;   // mínimo prático por coluna (ajuste aqui)
    const gap = 0;         // se usar gap entre colunas, considere subtrair

    // helper para calcular cellW com base no tamanho atual do container
    function computeCellW() {
        const availW = Math.max(0, container.clientWidth - colLeft - noDateWidth - 8); // -8 padding
        const ideal = availW / Math.max(1, dias.length);
        const cw = Math.max(minCellW, Math.floor(ideal));
        return cw;
    }

    // render grid (header + linhas vazias) - usa cellW em pixels para grid
    function renderGrid(cellW) {
        container.innerHTML = '';
        // grid-template-columns usando pixel por coluna (assim dá controle exato)
        const repeatPart = `repeat(${dias.length}, ${cellW}px)`;
        const templateParts = [`${colLeft}px`, repeatPart];
        if (hasNoDate) templateParts.push(`${noDateWidth}px`);
        container.style.gridTemplateColumns = templateParts.join(' ');
        container.style.gridAutoRows = `${baseCellH}px`;

        // header
        const headCol = document.createElement('div');
        headCol.className = 'gantt-cell-header col-left';
        headCol.textContent = 'Colaborador';
        container.appendChild(headCol);

        dias.forEach(d => {
            const el = document.createElement('div');
            el.className = 'gantt-cell-header';
            el.textContent = d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            container.appendChild(el);
        });

        if (hasNoDate) {
            const el = document.createElement('div');
            el.className = 'gantt-cell-header';
            el.textContent = 'Sem data';
            container.appendChild(el);
        }

        // linhas (label + células vazias)
        colaboradores.forEach(col => {
            const colCell = document.createElement('div');
            colCell.className = 'gantt-cell col-left';
            colCell.textContent = col;
            container.appendChild(colCell);

            dias.forEach(() => {
                const dayCell = document.createElement('div');
                dayCell.className = 'gantt-cell';
                container.appendChild(dayCell);
            });

            if (hasNoDate) {
                const nd = document.createElement('div');
                nd.className = 'gantt-cell no-date';
                container.appendChild(nd);
            }
        });
    }

    // preparar dados de barras (lanes) — igual ao que você já tinha: evitar sobreposição
    const totalCols = 1 + dias.length + (hasNoDate ? 1 : 0);
    const headerCount = 1 + dias.length + (hasNoDate ? 1 : 0);
    const lanesByRow = [];
    let maxLanes = 1;

    colaboradores.forEach((col, rowIdx) => {
        const tasks = validTasksByCol[col] || [];
        const lanes = [];
        tasks.forEach(task => {
            const startIdx = dias.findIndex(d => d.getTime() === task.startDate.getTime());
            const endIdx = dias.findIndex(d => d.getTime() === task.endDate.getTime());
            if (startIdx === -1 || endIdx === -1) return;
            let placed = false;
            for (let li = 0; li < lanes.length; li++) {
                if (lanes[li].lastEnd < startIdx) {
                    lanes[li].lastEnd = endIdx;
                    lanes[li].items.push({ task, startIdx, endIdx });
                    placed = true;
                    break;
                }
            }
            if (!placed) lanes.push({ lastEnd: endIdx, items: [{ task, startIdx, endIdx }] });
        });
        lanesByRow[rowIdx] = lanes;
        if (lanes.length > maxLanes) maxLanes = lanes.length;
    });

    // ajustar altura da linha para comportar as lanes (uniforme)
    const taskBarHeight = 20;
    const laneGap = 6;
    const newCellH = baseCellH + (maxLanes - 1) * (taskBarHeight + laneGap);
    document.documentElement.style.setProperty('--cell-h', `${newCellH}px`);

    // dados para reposicionar barras no resize
    const placedBars = []; // [{el, rowIdx, laneIdx, startIdx, endIdx}]

    // função que cria as barras (baseada em cellW)
    // função que cria as barras (baseada em cellW) — substitua a versão anterior por esta
    function placeBars(cellW) {
        // remover barras anteriores
        placedBars.forEach(p => {
            if (p.el && p.el.parentNode === container) container.removeChild(p.el);
        });
        placedBars.length = 0;

        // medir header (caso precise)
        const headerCell = container.querySelector('.gantt-cell-header');
        const headerHeight = headerCell ? headerCell.offsetHeight : 36;

        colaboradores.forEach((col, rowIdx) => {
            const lanes = lanesByRow[rowIdx] || [];

            // calcular índice do primeiro elemento da linha no container.children
            // headerCount foi definido mais acima no seu código
            const rowStartIndex = headerCount + rowIdx * totalCols;
            const rowLabelCell = container.children[rowStartIndex]; // célula "col-left" desse colaborador

            // fallback se não encontrar a célula (segurança)
            const rowTopBase = (rowLabelCell && typeof rowLabelCell.offsetTop === 'number')
                ? rowLabelCell.offsetTop
                : (headerHeight + rowIdx * newCellH);

            lanes.forEach((lane, laneIdx) => {
                lane.items.forEach(item => {
                    const { task, startIdx, endIdx } = item;
                    const taskDiv = document.createElement('div');
                    taskDiv.className = 'gantt-task';
                    taskDiv.textContent = task.imagem;
                    taskDiv.setAttribute('data-tooltip', `${task.imagem} — ${task.start} → ${task.end}`);

                    const left = colLeft + (startIdx * cellW);
                    const width = (endIdx - startIdx + 1) * cellW;
                    // posicionamento vertical baseado no offsetTop da célula da linha
                    const top = rowTopBase + 6 + laneIdx * (taskBarHeight + laneGap);

                    taskDiv.style.position = 'absolute';
                    taskDiv.style.left = `${left}px`;
                    taskDiv.style.width = `${Math.max(30, width - 6)}px`;
                    taskDiv.style.height = `${taskBarHeight}px`;
                    taskDiv.style.lineHeight = `${taskBarHeight}px`;
                    taskDiv.style.top = `${top}px`;
                    taskDiv.style.zIndex = 9999;
                    // estilos básicos (pode ser deixado no CSS)
                    taskDiv.style.background = '#2b6cb0';
                    taskDiv.style.color = '#fff';
                    taskDiv.style.borderRadius = '6px';
                    taskDiv.style.padding = '0 8px';
                    taskDiv.style.overflow = 'hidden';
                    taskDiv.style.textOverflow = 'ellipsis';

                    container.appendChild(taskDiv);
                    placedBars.push({ el: taskDiv, rowIdx, laneIdx, startIdx, endIdx });
                });
            });

            // tarefas "sem data"
            if (hasNoDate && (noDateTasksByCol[col] || []).length) {
                const cellIndex = rowStartIndex + totalCols - 1;
                const cell = container.children[cellIndex];
                (noDateTasksByCol[col] || []).forEach(td => {
                    const t = document.createElement('div');
                    t.className = 'gantt-task';
                    t.textContent = td.imagem;
                    t.setAttribute('data-tooltip', `${td.imagem} — sem data válida`);
                    cell.appendChild(t);
                });
            }
        });
    }


    // render grid inicialmente com computed cellW e posiciona barras
    function renderAndPlace() {
        const cw = computeCellW();
        renderGrid(cw);
        placeBars(cw);

        // ajustar altura do container para acomodar as barras absolutas
        const headerCell = container.querySelector('.gantt-cell-header');
        const headerHeight = headerCell ? headerCell.offsetHeight : 36;
        // container.style.height = `${headerHeight + colaboradores.length * newCellH + 24}px`;
    }

    // primeira renderização
    renderAndPlace();

    // re-render / reposition on resize (debounced)
    let resizeTimer = null;
    function onResize() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            renderAndPlace();
        }, 120);
    }
    window.addEventListener('resize', onResize);

    // retornar objeto útil e função para remover listener se necessário
    return {
        dias,
        colaboradores,
        destroy: () => window.removeEventListener('resize', onResize)
    };
}

// Exemplo de clique
document.querySelectorAll('.card-group').forEach(card => {
    card.addEventListener('click', () => {
        const funcoes = card.dataset.funcao.split(',').map(f => f.trim());
        funcoes.forEach(f => atualizarGantt(f));
    });
});

gerarMetricas();
atualizarProgressoFuncoes();
