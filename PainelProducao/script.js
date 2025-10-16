/* escape para evitar XSS */
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/* normalize para encontrar o section id igual ao seu HTML */
function normalizeId(tipo) {
    return tipo.toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // remove acentos
        .replace(/\s+/g, '-')
        .replace(/[^a-z0-9\-]/g, '');
}

function formatarDiaMes(dataStr) {
    if (!dataStr) return '—';

    const data = new Date(dataStr);
    if (isNaN(data)) return '—';

    const dia = String(data.getDate()).padStart(2, '0');
    const mes = String(data.getMonth() + 1).padStart(2, '0'); // Janeiro = 0
    return `${dia}/${mes}`;
}

function formatarData(data) {
    const partes = data.split("-");
    const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
    return dataFormatada;
}


fetch('getImagens.php')
    .then(res => res.json())
    .then(data => {
        // Agrupar por tipo_imagem
        const grupos = data.reduce((acc, item) => {
            const tipo = item.tipo_imagem || 'outros';
            if (!acc[tipo]) acc[tipo] = [];
            acc[tipo].push(item);
            return acc;
        }, {});

        const tipoParaId = {
            'Fachada': 'fachada',
            'Imagem Externa': 'externas',
            'Imagem Interna': 'internas',
            'Unidade': 'unidades',
            'Planta Humanizada': 'plantas'
        };


        Object.entries(grupos).forEach(([tipo, itens]) => {
            const sectionId = tipoParaId[tipo];
            const section = document.getElementById(sectionId);
            if (!section) return;

            // pegar divs
            const pessoasDiv = section.querySelector('.pessoas');
            const contentDiv = section.querySelector('.content');
            contentDiv.innerHTML = '';

            // agrupar por colaborador dentro desse tipo
            const porCol = itens.reduce((acc, it) => {
                const col = it.colaborador ? it.colaborador : 'Sem responsável';
                if (!acc[col]) acc[col] = [];
                acc[col].push(it);
                return acc;
            }, {});

            // preencher .pessoas com os colaboradores como badges clicáveis
            const colaboradores = Object.keys(porCol);

            // calcular contagens: alocadas vs não alocadas (disponíveis)
            const totalNoTipo = itens.length;
            const naoAlocadas = porCol['Sem responsável'] ? porCol['Sem responsável'].length : 0;
            const alocadas = totalNoTipo - naoAlocadas;

            // atualizar título com contagens
            const titleNome = section.querySelector('.title .nome_tipo');
            if (titleNome) {
                titleNome.textContent = `${tipo} • ${alocadas} alocadas • ${naoAlocadas} disponíveis`;
            }
            pessoasDiv.innerHTML = '';

            // estado de seleção por section (armazenado no elemento DOM via dataset)
            if (!section._selectedCols) section._selectedCols = new Set();

            function renderPessoas() {
                pessoasDiv.innerHTML = '';

                colaboradores.forEach(col => {
                    const span = document.createElement('span');
                    span.className = 'colaborador-badge';
                    // mostrar nome + contagem entre parênteses
                    const countForCol = porCol[col] ? porCol[col].length : 0;
                    span.textContent = `${col} (${countForCol})`;
                    span.title = 'Clique para filtrar por ' + col;

                    // mostrar 'x' quando selecionado
                    if (section._selectedCols.has(col)) {
                        span.classList.add('selected');
                        const x = document.createElement('em');
                        x.className = 'remove-x';
                        x.textContent = ' ×';
                        span.appendChild(x);
                    }

                    span.addEventListener('click', (e) => {
                        // clique normal alterna seleção
                        if (section._selectedCols.has(col)) {
                            section._selectedCols.delete(col);
                        } else {
                            section._selectedCols.add(col);
                        }
                        renderPessoas();
                        renderContent();
                    });

                    pessoasDiv.appendChild(span);
                });

                // botão para limpar seleção quando houver alguma
                if (section._selectedCols.size > 0) {
                    const limpar = document.createElement('button');
                    limpar.className = 'limpar-filtro';
                    limpar.textContent = 'Limpar filtros';
                    limpar.addEventListener('click', () => {
                        section._selectedCols.clear();
                        renderPessoas();
                        renderContent();
                    });
                    pessoasDiv.appendChild(limpar);
                }
            }

            // função que renderiza conteúdo filtrado conforme seleção
            function renderContent() {
                contentDiv.innerHTML = '';

                // filtrar colaboradores por seleção (se houver)
                const selected = section._selectedCols.size > 0 ? Array.from(section._selectedCols) : null;

                // construir lista de pares (colaborador -> imagens) aplicando filtro
                const filteredEntries = Object.entries(porCol).filter(([col, imgs]) => {
                    if (!selected) return true; // sem seleção mostra todos
                    return selected.includes(col);
                });

                // se nenhum após filtro
                if (filteredEntries.length === 0) {
                    contentDiv.innerHTML = '<div class="empty-msg">Nenhuma imagem ativa</div>';
                    return;
                }

                const wrapper = document.createElement('div');
                wrapper.className = 'table-wrapper';

                const table = document.createElement('table');
                table.className = 'card-table';

                // header
                const thead = document.createElement('thead');
                thead.innerHTML = `
        <tr>
          <th>Colaborador</th>
          <th>Imagem</th>
          <th>Etapa</th>
          <th>Status</th>
          <th>Prazo Colaborador</th>
          <th>Prazo Imagem</th>
        </tr>`;
                table.appendChild(thead);

                const tbody = document.createElement('tbody');

                // construir tbody com rowspan para cada colaborador
                filteredEntries.forEach(([col, imgs]) => {
                    const rowspan = imgs.length;

                    imgs.forEach((imgObj, index) => {
                        const tr = document.createElement('tr');

                        if (index === 0) {
                            // coluna do colaborador com rowspan
                            const tdCol = document.createElement('td');
                            tdCol.className = 'col-colaborador';
                            tdCol.setAttribute('rowspan', String(rowspan));
                            tdCol.innerText = col;
                            tr.appendChild(tdCol);
                        }

                        // imagem (mostramos como badge — se quiser conter mais dados, ajuste aqui)
                        const tdImg = document.createElement('td');
                        // se esta imagem não tem colaborador, marcamos como disponível
                        const disponivel = !imgObj.colaborador || imgObj.colaborador === '';
                        tdImg.innerHTML = `<span class="col-imagem-badge" data-imagem-id="${imgObj.imagem_id}" data-tipo="${escapeHtml(tipo)}">${escapeHtml(imgObj.imagem_nome)}</span>` +
                            (disponivel ? ' <small class="disponivel-badge">Disponível</small>' : '');
                        tr.appendChild(tdImg);

                        // adicionar listener de click na badge da imagem (delegação simples)
                        const badge = tdImg.querySelector('.col-imagem-badge');
                        badge.addEventListener('click', () => openAssignModal(imgObj.imagem_id, tipo));

                        // prazo do colaborador (você pode preencher com imgObj.prazo_colaborador quando existir)
                        const tdEtapa = document.createElement('td');
                        tdEtapa.className = 'col-etapa';
                        tdEtapa.innerText = imgObj.etapa;
                        tr.appendChild(tdEtapa);

                        const tdStatus = document.createElement('td');
                        tdStatus.className = 'col-status';
                        tdStatus.innerText = imgObj.status;
                        tr.appendChild(tdStatus);
                        
                        const tdPrazoCol = document.createElement('td');
                        tdPrazoCol.className = 'col-prazo';
                        const inicioFormatado = formatarDiaMes(imgObj.data_inicio);
                        const fimFormatado = formatarDiaMes(imgObj.data_fim);
                        tdPrazoCol.innerText = `${inicioFormatado} - ${fimFormatado}`;
                        tr.appendChild(tdPrazoCol);

                        // prazo da imagem (preencher com imgObj.prazo_imagem quando existir)
                        const tdPrazoImg = document.createElement('td');
                        tdPrazoImg.className = 'col-prazo';
                        tdPrazoImg.innerText = formatarData(imgObj.prazo_imagem) || '—';
                        tr.appendChild(tdPrazoImg);

                        tbody.appendChild(tr);
                    });
                });

                table.appendChild(tbody);
                wrapper.appendChild(table);
                contentDiv.appendChild(wrapper);
            }

            // inicializar renderizadores
            renderPessoas();
            renderContent();
        });
    })
    .catch(err => {
        console.error('Erro ao carregar dados:', err);
    });

// --- Modal and assign helpers ---
(function createModal() {
    if (document.getElementById('assignModal')) return;
    const modal = document.createElement('div');
    modal.id = 'assignModal';
    modal.className = 'assign-modal';
    modal.innerHTML = `
        <div class="assign-modal-content">
            <button class="assign-close">×</button>
            <h3 class="assign-title">Alocar colaborador</h3>
            <div class="assign-body">Carregando...</div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.querySelector('.assign-close').addEventListener('click', closeAssignModal);
})();

function openAssignModal(imagemId, tipo) {
    const modal = document.getElementById('assignModal');
    modal.classList.add('open');
    modal.querySelector('.assign-title').innerText = `Alocar - ${tipo}`;
    const body = modal.querySelector('.assign-body');
    body.innerHTML = 'Carregando métricas...';

    fetch('getColabMetrics.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tipo })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            body.innerText = 'Erro ao carregar métricas';
            return;
        }
        renderMetrics(body, data.colaboradores, imagemId);
    })
    .catch(err => {
        body.innerText = 'Erro ao carregar métricas';
        console.error(err);
    });
}

function closeAssignModal() {
    const modal = document.getElementById('assignModal');
    modal.classList.remove('open');
}

function renderMetrics(container, colaboradores, imagemId) {
    if (!colaboradores || colaboradores.length === 0) {
        container.innerHTML = '<div>Nenhum colaborador encontrado.</div>';
        return;
    }
    const list = document.createElement('div');
    list.className = 'assign-list';

    colaboradores.forEach(c => {
        const row = document.createElement('div');
        row.className = 'assign-row';
        row.innerHTML = `<div class="assign-nome">${escapeHtml(c.nome)}</div>
                         <div class="assign-meta">Taxa de Aprovação: ${c.pct_aprovadas_de_primeira}% • Tarefas: ${c.tarefas_alocadas} • Última: ${c.ultima_entrega || '—'}</div>
                         <button class="assign-btn">Alocar</button>`;
        const btn = row.querySelector('.assign-btn');
        btn.addEventListener('click', () => {
            btn.disabled = true;
            btn.textContent = 'Atribuindo...';
            fetch('assignColaborador.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ imagem_id: imagemId, colaborador_id: c.idcolaborador })
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.ok) {
                    btn.textContent = 'OK';
                    setTimeout(() => closeAssignModal(), 600);
                    // refresh a página para ver alteração ou re-fetch do painel
                    setTimeout(() => location.reload(), 900);
                } else {
                    btn.textContent = 'Erro';
                    btn.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                btn.textContent = 'Erro';
                btn.disabled = false;
            });
        });
        list.appendChild(row);
    });

    container.innerHTML = '';
    container.appendChild(list);
}