// Helper date functions
function daysBetween(a, b) {
    return Math.round((new Date(b) - new Date(a)) / (1000 * 60 * 60 * 24));
}

function formatDate(d) {
    const dt = new Date(d);
    return dt.toISOString().slice(0, 10);
}

async function carregarMetricas() {
    try {
        const resposta = await fetch('getMetrics.php');
        const data = await resposta.json();

        // --- CARD 1: Função mais finalizada ---
        const card1 = document.getElementById('pct-completed');
        const funcoes = data.funcoes_finalizadas_mes_atual || [];
        let topFuncao = null;
        if (funcoes.length > 0) {
            topFuncao = funcoes[0];
            card1.textContent = `${topFuncao.nome_funcao} (${topFuncao.total_finalizadas})`;
        } else {
            card1.textContent = '--';
        }

        // Container de detalhes (expandir lista)
        let detalhes1 = document.createElement('div');
        detalhes1.style.display = 'none';
        detalhes1.style.padding = '4px 8px';
        detalhes1.style.fontSize = '14px';
        card1.parentElement.appendChild(detalhes1);

        card1.style.cursor = 'pointer';
        card1.addEventListener('click', () => {
            detalhes1.style.display = detalhes1.style.display === 'none' ? 'block' : 'none';
        });
        detalhes1.innerHTML = funcoes.map(f => `${f.nome_funcao}: ${f.total_finalizadas}`).join('<br>');

        // --- CARD 2: Média de tempo da função top ---
        const card2 = document.getElementById('avg-time');
        const tempos = data.tempo_medio_conclusao || [];

        if (tempos.length) {
            // função top = primeira do array
            const top = tempos[0];
            const diasTop = (top.tempo_medio_horas / 24).toFixed(1);
            card2.textContent = `${diasTop} dias`;

            // detalhes de todas as funções
            let detalhes2 = document.createElement('div');
            detalhes2.style.display = 'none';
            detalhes2.style.padding = '4px 8px';
            detalhes2.style.fontSize = '14px';

            tempos.forEach(t => {
                const d = document.createElement('div');
                d.textContent = `${t.nome_funcao}: ${(t.tempo_medio_horas / 24).toFixed(1)} dias`;
                detalhes2.appendChild(d);
            });

            card2.parentElement.appendChild(detalhes2);

            card2.style.cursor = 'pointer';
            card2.addEventListener('click', () => {
                detalhes2.style.display = detalhes2.style.display === 'none' ? 'block' : 'none';
            });
        } else {
            card2.textContent = '--';
        }


        // --- CARD 3: Taxa de aprovação ---
        const card3 = document.getElementById('approval-rate');
        const taxa = data.taxa_aprovacao || {};
        card3.textContent = taxa.pct_aprovadas_de_primeira !== undefined
            ? `${taxa.pct_aprovadas_de_primeira}%`
            : '--';

        let detalhes3 = document.createElement('div');
        detalhes3.style.display = 'none';
        detalhes3.style.padding = '4px 8px';
        detalhes3.style.fontSize = '14px';
        card3.parentElement.appendChild(detalhes3);

        card3.style.cursor = 'pointer';
        card3.addEventListener('click', () => {
            detalhes3.style.display = detalhes3.style.display === 'none' ? 'block' : 'none';
        });

        // detalhamento
        detalhes3.innerHTML = `
            Total com histórico: ${taxa.total_com_historico || 0}<br>
            Aprovadas de primeira: ${taxa.total_aprovadas_de_primeira || 0} (${taxa.pct_aprovadas_de_primeira || 0}%)<br>
            Aprovadas com ajustes: ${taxa.total_aprovadas_com_ajustes_de_primeira || 0} (${taxa.pct_aprovadas_com_ajustes_de_primeira || 0}%)<br>
            Tiveram ajuste: ${taxa.total_que_tiveram_ajuste || 0} (${taxa.pct_que_tiveram_ajuste || 0}%)
        `;

        // --- CARD 4: inventada, aqui total de funções finalizadas ---
        const card4 = document.getElementById('due-today');
        const totalFinalizadas = funcoes.reduce((acc, f) => acc + parseInt(f.total_finalizadas || 0), 0);
        card4.textContent = totalFinalizadas;

    } catch (erro) {
        console.error('Erro ao carregar métricas:', erro);
    }
}


// --- Função para buscar atualizações do servidor ---
async function fetchUpdates(filter = 'all') {
    try {
        const res = await fetch('getAtualizacoes.php');
        const data = await res.json();

        // Atualiza a lista global (se quiser manter fora)
        updates = data.atualizacoes || [];

        // Renderiza
        renderUpdates(filter);
    } catch (err) {
        console.error('Erro ao buscar atualizações:', err);
    }
}

// --- Render updates feed ---
function renderUpdates(filter = 'all') {
    const el = document.getElementById('updates-list');
    el.innerHTML = '';

    const list = updates.filter(u => {
        if (filter === 'all') return true;
        if (filter === 'my') return u.body.includes('@Pedro') || u.taskId;
        if (filter === 'action') return u.actionNeeded;
        return true;
    });

    list.forEach(u => {
        const node = document.createElement('div');
        node.className = 'update card';
        node.setAttribute('data-id', u.id);

        // Ajusta valores de u conforme os campos do PHP
        node.innerHTML = `
            <div class="meta">
                <div class="left">
                    <div class="badge">${u.tipo_evento || 'Info'}</div>
                    <div style="display:flex;flex-direction:column">
                        <div class="title">${u.descricao || ''}</div>
                        <div class="tiny">${u.data_evento || ''} • ${u.lido ? 'Lido' : 'Novo'}</div>
                    </div>
                </div>
                <div class="pill">${u.actionNeeded ? 'Ação' : 'Info'}</div>
            </div>
            <div class="body">${u.descricao || ''}</div>
            <div class="actions">
                <button class="btn" onclick="openComments('${u.id}')">Comentar</button>
                <button class="btn" onclick="goToTaskForUpdate(${u.taskId || 'null'})">Ir para</button>
                <button class="btn" onclick="markRead('${u.id}', this)">${u.lido ? 'Marcar não lido' : 'Marcar lido'}</button>
            </div>
        `;
        el.appendChild(node);
    });
}

// --- Inicializa ---
let updates = [];

async function loadFeedbacks() {
    try {
        const res = await fetch('getFeedbacks.php');
        const data = await res.json();
        window.feedbacks = data.feedbacks || [];
        renderFeedbacks();
    } catch (err) {
        console.error('Erro ao carregar feedbacks:', err);
    }
}

// --- Render feedbacks ---
function renderFeedbacks() {
    const el = document.getElementById('feedback-list');
    el.innerHTML = '';

    feedbacks.forEach(f => {
        const node = document.createElement('div');
        node.className = 'feedback-item';
        node.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
            <div style="font-weight:700">${f.author || 'Colaborador'} • <span style="font-weight:600;color:var(--muted)">${f.type}</span></div>
            <div class="tiny">${f.excerpt || 'Sem comentários'}</div>
            <div class="tiny" style="margin-top:6px">Tarefa: <strong>${f.taskTitle}</strong></div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px">
            <button class="btn" onclick="goToTask(${f.taskId})">Ir para</button>
        </div>
    </div>
    `;
        el.appendChild(node);
    });
}

carregarMetricas();
fetchUpdates();
loadFeedbacks();


