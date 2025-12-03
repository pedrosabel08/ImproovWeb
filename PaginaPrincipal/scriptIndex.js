
if (colaborador_id === 9 || colaborador_id === 21) {
    document.getElementById('idcolab').style.display = 'flex'; // libera
} else {
    document.getElementById('idcolab').style.display = 'none'; // esconde
}
// const idusuario = 1;

document.getElementById('idcolab').addEventListener('change', function () {

    const idcolab = parseInt(this.value, 10);
    carregarDados(idcolab);

});


// Converte um caminho SFTP/servidor para a URL pública onde os JPGs ficam acessíveis
// Ex: /mnt/clientes/2025/TES_TES/05.Exchange/01.Input/Angulo_definido/Fachada/IMG/teste2/file.jpg
// => https://improov.com.br/uploads/angulo_definido/Fachada/IMG/teste2/file.jpg
function sftpToPublicUrl(rawPath) {
    if (!rawPath) return null;
    // normaliza barras
    const p = rawPath.replace(/\\/g, '/');
    // Primeira tentativa: detectar caminho completo com nomenclatura
    // /mnt/clientes/<ano>/<nomenclatura>/05.Exchange/01.Input/<rest>
    const mFull = p.match(/\/mnt\/clientes\/\d+\/([^\/]+)\/05\.Exchange\/01\.Input\/(.*)/i);
    if (mFull && mFull[1] && mFull[2]) {
        const nomen = mFull[1];
        const rest = mFull[2];
        // Monta com a nomenclatura logo após angulo_definido conforme solicitado
        return 'https://improov.com.br/flow/ImproovWeb/uploads/angulo_definido/' + nomen + '/' + rest;
    }

    // Segunda tentativa: localizar Angulo_definido no caminho e usar o que vem depois
    const m = p.match(/\/Angulo_definido\/(.*)/i);
    if (m && m[1]) {
        return 'https://improov.com.br/flow/ImproovWeb/uploads/angulo_definido/' + m[1];
    }

    // Terceira tentativa: pega tudo depois de /05.Exchange/01.Input/
    const idx = p.indexOf('/05.Exchange/01.Input/');
    if (idx >= 0) {
        const after = p.substring(idx + '/05.Exchange/01.Input/'.length);
        return 'https://improov.com.br/flow/ImproovWeb/uploads/' + after;
    }

    return null;
}


function carregarDados(colaborador_id) {

    let url = `PaginaPrincipal/getFuncoesPorColaborador.php?colaborador_id=${colaborador_id}`;

    const xhr = new XMLHttpRequest();

    // Mostra loading quando iniciar a requisição
    xhr.addEventListener("loadstart", () => {
        document.getElementById("loading").style.display = "block";
    });

    // Esconde loading quando terminar
    xhr.addEventListener("loadend", () => {
        document.getElementById("loading").style.display = "none";
    });

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const data = JSON.parse(xhr.responseText);

                    // Atualiza mini-calendar (se implementado)
                    if (window.updateMiniCalendarWithData) {
                        try { window.updateMiniCalendarWithData(data); } catch (e) { console.error('mini-calendar update error', e); }
                    }

                    // Chama o tratamento do kanban
                    processarDados(data);

                } catch (err) {
                    console.error("Erro ao parsear JSON:", err);
                }
            } else {
                console.error("Erro na requisição:", xhr.status);
            }
        }
    };

    xhr.open("GET", url, true);
    xhr.send();
}
// extrai a lógica do fetch para uma função reutilizável
function processarDados(data) {
    const statusMap = {
        'Não iniciado': 'to-do',
        'Em andamento': 'in-progress',
        'Em aprovação': 'in-review',
        'Ajuste': 'ajuste',
        'Finalizado': 'done',
        'HOLD': 'hold'
    };

    Object.values(statusMap).forEach(colId => {
        const col = document.getElementById(colId);
        if (col) col.querySelector('.content').innerHTML = '';
    });
    // Função auxiliar para criar cards
    function criarCard(item, tipo, media) {
        // Define status real
        let status = item.status || 'Não iniciado';
        if (status === 'Ajuste') status = 'Ajuste';
        else if (status === 'Em aprovação')
            status = 'Em aprovação';
        else if (status === 'Em andamento')
            status = 'Em andamento';
        else if (['Aprovado', 'Aprovado com ajustes', 'Finalizado'].includes(status))
            status = 'Finalizado';
        else if (status === 'Não iniciado')
            status = 'Não iniciado';
        else if (status === 'HOLD' || status === 'Hold')
            status = 'HOLD';
        else
            status = 'Não iniciado';

        const colunaId = statusMap[status];
        const coluna = document.getElementById(colunaId)?.querySelector('.content');
        if (!coluna) return;

        // Define a classe da tarefa (criada ou imagem)
        const tipoClasse = tipo === 'imagem' ? 'tarefa-imagem' : 'tarefa-criada';

        // Normaliza prioridade (número ou string)
        if (item.prioridade == 3 || item.prioridade === 'baixa') {
            item.prioridade = 'baixa';
        } else if (item.prioridade == 2 || item.prioridade === 'media' || item.prioridade === 'média') {
            item.prioridade = 'media';
        } else {
            item.prioridade = 'alta';
        }


        // Nome a exibir
        const titulo = tipo === 'imagem' ? item.imagem_nome : item.titulo;
        const subtitulo = tipo === 'imagem' ? item.nome_funcao : item.descricao;

        function getTempoClass(tempo, media) {
            if (!tempo || tempo === 0) return ""; // sem tempo registrado

            if (tempo <= media) {
                return "tempo-bom"; // verde
            } else if (tempo <= media * 1.3) {
                return "tempo-atenção"; // amarelo
            } else {
                return "tempo-ruim"; // vermelho
            }
        }


        // Pega a média da função específica
        const mediaFuncao = media[item.funcao_id] || 0;

        // Bolinha só no "Não iniciado"
        let bolinhaHTML = "";
        let liberado = "1"; // padrão liberado

        // Cria card
        const card = document.createElement('div');
        card.className = `kanban-card ${tipoClasse}`; // só a classe base

        if (tipo === 'imagem') {
            // lógica específica para imagem
            if (status === "Não iniciado") {
                const statusAnterior = item.status_funcao_anterior || "";
                if (["Aprovado", "Finalizado", "Aprovado com ajustes"].includes(statusAnterior)) {
                    bolinhaHTML = `<span class="bolinha verde" data-status-anterior="${statusAnterior}"></span>`;
                    liberado = "1";
                } else if (item.liberada) {
                    bolinhaHTML = `<span class="bolinha verde" data-status-anterior="${statusAnterior || ''}"></span>`;
                    liberado = "1";
                } else if (item.nome_funcao === "Filtro de assets") {
                    bolinhaHTML = `<span class="bolinha verde" data-status-anterior="${statusAnterior || ''}"></span>`;
                    liberado = "1";
                } else {
                    bolinhaHTML = `<span class="bolinha vermelho" data-status-anterior="${statusAnterior || ''}"></span>`;
                    liberado = "0";
                }

            }


            // store original previous status (comes from getFuncoesPorColaborador -> status_funcao_anterior)
            const statusAnteriorFull = item.status_funcao_anterior || '';

            card.setAttribute('data-id', `${item.idfuncao_imagem}`);
            card.setAttribute('data-status-anterior', statusAnteriorFull);
            card.setAttribute('data-id-imagem', `${item.imagem_id}`);
            card.setAttribute('data-id-funcao', `${item.funcao_id}`);
            card.setAttribute('liberado', liberado);
            card.setAttribute('data-nome_status', `${item.nome_status}`); // para filtro
            card.setAttribute('data-prazo', `${item.prazo}`); // para filtro

        } else {
            // lógica para tarefas criadas
            bolinhaHTML = '';
            // 🟢 Lógica para tarefas criadas
            card.dataset.id = item.id;                   // apenas id simples
            card.dataset.titulo = item.titulo;   // se precisar para modal
            card.dataset.descricao = item.descricao;
            card.dataset.prazo = item.prazo;
            card.dataset.status = item.status;
            card.dataset.prioridade = item.prioridade;
            card.setAttribute('liberado', '1');  // sempre liberado
        }


        // adiciona bloqueado se necessário
        if (liberado === "0") {
            card.classList.add("bloqueado");
        }

        function isAtrasada(prazoStr) {
            // Divide a string 'YYYY-MM-DD'
            const [ano, mes, dia] = prazoStr.split('-').map(Number);
            const prazo = new Date(ano, mes - 1, dia);

            const hoje = new Date();
            const hojeLimpo = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate());

            return prazo < hojeLimpo;
        }

        // Marca como atrasada apenas se estiver 'Em andamento' e o prazo já passou
        const atrasada = (status === 'Em andamento' && item.prazo) ? isAtrasada(item.prazo) : false;

        // Normalize ultima_imagem: if it's an SFTP server path (/mnt/clientes/...), convert to public URL
        const ultimaImagemRaw = item.ultima_imagem || '';
        let ultimaImagemPublic = ultimaImagemRaw;
        try {
            if (typeof ultimaImagemPublic === 'string' && ultimaImagemPublic.startsWith('/mnt/clientes')) {
                ultimaImagemPublic = sftpToPublicUrl(ultimaImagemPublic);
            }
        } catch (e) {
            // if conversion fails, fallback to raw path
            console.error('sftpToPublicUrl error for', ultimaImagemRaw, e);
            ultimaImagemPublic = ultimaImagemRaw;
        }

        // Decide image src: if we have an http(s) public URL, use it directly; otherwise use thumb.php to generate a thumbnail
        let imgSrc = '';

        // Special override: se for o colaborador Marcio (id 8) ou nome 'Marcio', usar a imagem local fixa
        try {
            // const nomeColl = String(item.nome_colaborador || '').trim();
            // if ((typeof colaborador_id !== 'undefined' && Number(colaborador_id) === 8) || nomeColl === 'Marcio') {
            //     imgSrc = 'assets/marcio_cafezinho.jpg';
            // } else {
            if (ultimaImagemPublic) {
                if (ultimaImagemPublic.startsWith('http://') || ultimaImagemPublic.startsWith('https://')) {
                    imgSrc = ultimaImagemPublic;
                } else {
                    imgSrc = `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(ultimaImagemPublic)}&w=360&q=70`;
                }
            } else {
                imgSrc = `https://improov.com.br/flow/ImproovWeb/${ultimaImagemPublic || ''}`;
            }
            // }
        } catch (e) {
            // fallback to original logic if anything falhar
            if (ultimaImagemPublic) {
                if (ultimaImagemPublic.startsWith('http://') || ultimaImagemPublic.startsWith('https://')) {
                    imgSrc = ultimaImagemPublic;
                } else {
                    imgSrc = `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(ultimaImagemPublic)}&w=360&q=70`;
                }
            } else {
                imgSrc = `https://improov.com.br/flow/ImproovWeb/${ultimaImagemPublic || ''}`;
            }
        }

        card.innerHTML = `
                    <div class="header-kanban">
                        <span class="priority ${item.prioridade || 'medium'}">
                            ${item.prioridade || 'Medium'}
                        </span>
                        ${bolinhaHTML}
                        ${(item.notificacoes_nao_lidas && Number(item.notificacoes_nao_lidas) > 0) ? `
                            <span class="notif-icon" title="${item.notificacoes_nao_lidas} notificação(s)">
                                <i class="ri-notification-3-line"></i>
                                <small class="notif-count">${item.notificacoes_nao_lidas}</small>
                            </span>
                        ` : ''}
                    </div>
                        <h5>${titulo || '-'}</h5>
                        <!-- Use server-side thumb generator to reduce weight for thumbnails -->
                        <img loading="lazy" src="${imgSrc}" alt="" style="max-width: 100%; height: auto; margin-bottom: 8px;">
                        <p>${subtitulo || '-'}</p>
                    <div class="card-footer">
                        <span class="date ${atrasada ? 'atrasada' : ''}">
                            <i class="fa-regular fa-calendar"></i>
                            ${item.prazo ? formatarData(item.prazo) : '-'}
                        </span>
                    </div>
                    <div class="card-log">
                            <span 
                                class="date tooltip ${getTempoClass(item.tempo_calculado, mediaFuncao)}" 
                                data-tooltip="${formatarDuracao(mediaFuncao)}"
                                data-inicio="${item.tempo_calculado || ''}">
                                <i class="ri-time-line"></i> 
                                ${item.tempo_calculado ? formatarDuracao(item.tempo_calculado) : '-'}
                                </span>
                    <div class="comments">
                        ${item.indice_envio_atual ? `<span class="indice_envio"><i class="ri-file-line"></i> ${item.indice_envio_atual} |</span>` : ''}
                        ${item.indice_envio_atual ?
                (item.comentarios_ultima_versao > 0 ?
                    `<span class="numero_comments"><i class="ri-chat-3-line"></i> ${item.comentarios_ultima_versao}</span>`
                    : `<span class="numero_comments">0</span>`)
                : ''
            }
                    </div>

                    </div>
                `;

        // Atributos para filtros
        card.dataset.obra_nome = item.nomenclatura || '';      // nome da obra
        card.dataset.funcao_nome = item.nome_funcao || '';  // nome da função
        card.dataset.status = status;                       // status normalizado

        card.addEventListener('click', () => {
            document.querySelectorAll('.kanban-card.selected').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            if (card.classList.contains('tarefa-criada')) {
                const idTarefa = card.dataset.id;
                abrirSidebarTarefaCriada(idTarefa);

            } else if (card.classList.contains('tarefa-imagem')) {
                const idFuncao = card.dataset.id;
                const idImagem = card.dataset.idImagem;
                abrirSidebar(idFuncao, idImagem);

            }

        });


        if (liberado === "1") {
            // Inserir no topo da coluna, antes dos bloqueados
            const primeiroBloqueado = coluna.querySelector('.kanban-card.bloqueado');
            if (primeiroBloqueado) {
                coluna.insertBefore(card, primeiroBloqueado);
            } else {
                coluna.appendChild(card);
            }
        } else {
            // Bloqueados vão no final
            coluna.appendChild(card);
        }
    }


    // Adiciona tarefas criadas
    if (data.tarefas) {
        data.tarefas.forEach(item => criarCard(item, 'criada', {}));
    }

    // Adiciona funções (tarefas de imagem)
    if (data.funcoes) {
        data.funcoes.forEach(item => criarCard(item, 'imagem', data.media_tempo_em_andamento));
    }

    atualizarTaskCount();

    preencherFiltros()



}




document.getElementById('modalDaily').style.display = 'none';

// checkDailyAccess agora retorna uma Promise
function checkDailyAccess() {
    return new Promise((resolve, reject) => {
        fetch('verifica_respostas.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `idcolaborador=${idColaborador}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.hasResponses) {
                    // Se já respondeu, segue para checkRender
                    resolve();
                } else {
                    // Se não respondeu, exibe modal e interrompe fluxo (não resolve ainda)
                    document.getElementById('modalDaily').style.display = 'flex';
                    // Resolve apenas após o envio do formulário
                    document.getElementById('dailyForm').addEventListener('submit', function onSubmit(e) {
                        e.preventDefault();
                        this.removeEventListener('submit', onSubmit); // evita múltiplas submissões

                        const formData = new FormData(this);

                        fetch('submit_respostas.php', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    document.getElementById('modalDaily').style.display = 'none';
                                    Swal.fire({
                                        icon: 'success',
                                        text: 'Respostas enviadas com sucesso!',
                                        showConfirmButton: false,
                                        timer: 1200
                                    }).then(() => {
                                        // Após fechar o toast, primeiro verifica funções em andamento
                                        // para garantir que o prompt de HOLD seja exibido imediatamente
                                        // mesmo que o resumo abra em seguida.
                                        if (typeof checkFuncoesEmAndamento === 'function') {
                                            checkFuncoesEmAndamento(idColaborador)
                                                .catch(err => console.error('Erro ao checar funções em andamento após Daily:', err))
                                            // .finally(() => {
                                            //     mostrarResumoInteligente().then(() => resolve()).catch(() => resolve());
                                            // });
                                        } else {
                                            // fallback se a função não existir por algum motivo
                                            // mostrarResumoInteligente().then(() => resolve()).catch(() => resolve());
                                        }
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        text: 'Erro ao enviar as tarefas, tente novamente!',
                                        showConfirmButton: false,
                                        timer: 2000
                                    });
                                    reject(); // interrompe a sequência
                                }
                            })
                            .catch(error => {
                                console.error('Erro:', error);
                                reject();
                            });
                    });
                }
            })
            .catch(error => {
                console.error('Erro ao verificar respostas:', error);
                reject();
            });
    });
}

function checkFuncoesSomentePrimeiroAcesso() {
    const hoje = new Date().toISOString().split('T')[0]; // ex: 2025-09-25
    const chave = "funcoes_visto_" + hoje;

    if (!localStorage.getItem(chave)) {
        // Primeira vez no dia → chama a verificação primeiro.
        // Só marca como visto após a verificação completar, assim falhas não impedem
        // novas tentativas durante o dia.
        if (typeof checkFuncoesEmAndamento === 'function') {
            return checkFuncoesEmAndamento(idColaborador)
                .then(() => {
                    try {
                        localStorage.setItem(chave, "1");
                    } catch (e) {
                        console.error('Não foi possível salvar funcoes_visto no localStorage:', e);
                    }
                })
                .catch(err => {
                    console.error('Erro ao checar funções em andamento:', err);
                    // resolvemos para não travar a sequência principal
                });
        }

        // Se a função não existir, apenas resolve para seguir o fluxo
        return Promise.resolve();
    } else {
        // Já viu hoje → não faz nada
        return Promise.resolve();
    }
}


// checkRenderItems também retorna uma Promise
function checkRenderItems(idColaborador) {
    return new Promise((resolve, reject) => {
        fetch('verifica_render.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `idcolaborador=${idColaborador}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.total > 0) {
                    Swal.fire({
                        title: `Você tem ${data.total} item(ns) na sua lista de render!`,
                        text: "Deseja ver agora ou depois?",
                        icon: "info",
                        showCancelButton: true,
                        confirmButtonText: "Ver agora",
                        cancelButtonText: "Ver depois",
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "./Render/";
                        } else {
                            resolve(); // segue o fluxo
                        }
                    });
                } else {
                    resolve(); // segue o fluxo mesmo sem render
                }
            })
            .catch(error => {
                console.error('Erro ao verificar itens de render:', error);
                reject();
            });
    });
}

// --- Resumo inteligente & nav toggles ---
function mostrarResumoInteligente() {
    return new Promise((resolve, reject) => {
        const resumoModal = document.getElementById('resumoModal');
        const resumoContent = document.getElementById('resumo-content');

        resumoContent.innerHTML = '<p>Carregando resumo...</p>';

        fetch('PaginaPrincipal/Overview/getResumo.php')
            .then(r => r.ok ? r.json() : Promise.reject('Erro na resposta'))
            .then(data => {
                if (data.error) {
                    resumoContent.innerHTML = `<p style="color:red">${data.error}</p>`;
                    resumoModal.style.display = 'flex';
                    resolve();
                    return;
                }

                const parts = [];

                // Tarefas do dia
                parts.push('<h3>Tarefas do dia</h3>');
                if (data.tarefasHoje && data.tarefasHoje.length) {
                    parts.push('<ul>');
                    data.tarefasHoje.forEach(t => {
                        parts.push(`<li><strong>${t.nome_funcao || 'Função'}</strong> — ${t.imagem_nome || ''} <small style="color:#64748b">(${t.prazo ? t.prazo.split(' ')[0] : ''})</small></li>`);
                    });
                    parts.push('</ul>');
                } else {
                    parts.push('<p>Nenhuma tarefa com prazo para hoje.</p>');
                }

                // Tarefas atrasadas
                parts.push('<h3>Tarefas atrasadas</h3>');
                if (data.tarefasAtrasadas && data.tarefasAtrasadas.length) {
                    parts.push('<ul>');
                    data.tarefasAtrasadas.forEach(t => {
                        parts.push(`<li><strong>${t.nome_funcao || 'Função'}</strong> — ${t.imagem_nome || ''} <span style="color:#ef4444">(${t.prazo ? t.prazo.split(' ')[0] : ''})</span></li>`);
                    });
                    parts.push('</ul>');
                } else {
                    parts.push('<p>Sem tarefas atrasadas.</p>');
                }

                // Tarefas próximas
                parts.push('<h3>Tarefas próximas (7 dias)</h3>');
                if (data.tarefasProximas && data.tarefasProximas.length) {
                    parts.push('<ul>');
                    data.tarefasProximas.forEach(t => {
                        parts.push(`<li><strong>${t.nome_funcao || 'Função'}</strong> — ${t.imagem_nome || ''} <small style="color:#64748b">(${t.prazo ? t.prazo.split(' ')[0] : ''})</small></li>`);
                    });
                    parts.push('</ul>');
                } else {
                    parts.push('<p>Sem tarefas próximas nos próximos 7 dias.</p>');
                }

                // Últimos ajustes
                parts.push('<h3>Últimos ajustes</h3>');
                if (data.ultimosAjustes && data.ultimosAjustes.length) {
                    parts.push('<ul>');
                    data.ultimosAjustes.forEach(t => {
                        parts.push(`<li><strong>${t.nome_funcao || 'Função'}</strong> — ${t.imagem_nome || ''} <small style="color:#64748b">${t.status || ''} ${t.updated_at ? '• ' + t.updated_at.split(' ')[0] : ''}</small></li>`);
                    });
                    parts.push('</ul>');
                } else {
                    parts.push('<p>Sem ajustes recentes.</p>');
                }

                resumoContent.innerHTML = parts.join('');
                resumoModal.style.display = 'flex';
                resolve();
            })
            .catch(err => {
                console.error('Erro ao obter resumo:', err);
                resumoContent.innerHTML = '<p>Erro ao carregar resumo.</p>';
                resumoModal.style.display = 'flex';
                resolve();
            });
    });
}

// Busca os dados do painel diário e exibe modal com as informações (se necessário)
function fetchDailyPanel() {
    fetch('PaginaPrincipal/get_daily_panel.php')
        .then(r => r.ok ? r.json() : Promise.reject('Erro na resposta'))
        .then(data => {
            if (!data || data.error) return;
            if (!data.show) return; // não mostrar hoje

            // Preenche contadores
            document.getElementById('daily_renders').textContent = data.renders ?? 0;
            document.getElementById('daily_ajustes').textContent = data.ajustes ?? 0;
            document.getElementById('daily_atrasadas').textContent = data.atrasadas ?? 0;
            document.getElementById('daily_hoje').textContent = data.hoje ?? 0;

            // Preenche últimas páginas (apenas 3) como botões com o título
            const container = document.getElementById('daily_recent_pages');
            container.innerHTML = '';
            if (Array.isArray(data.recent_pages) && data.recent_pages.length) {
                // pega até 3 e adiciona botões soltos (sem <li>)
                data.recent_pages.slice(0, 3).forEach(p => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'recent-page-btn';

                    // label text + arrow icon to the right
                    let label = p.tela || p.url || 'Página';
                    try {
                        // If the label is exactly 'Detalhes da obra', append obraName from localStorage when available
                        if (String(label).trim() === 'Detalhes da Obra') {
                            const obraNome = localStorage.getItem('obraNome') || '';
                            if (obraNome) label = `${label} (${obraNome})`;
                        }
                    } catch (e) {
                        // localStorage might be unavailable in some contexts; ignore silently
                    }

                    const labelSpan = document.createElement('span');
                    labelSpan.className = 'recent-page-label';
                    labelSpan.textContent = label;
                    btn.appendChild(labelSpan);

                    const icon = document.createElement('i');
                    icon.className = 'fa-solid fa-circle-arrow-right recent-page-icon';
                    btn.appendChild(icon);

                    btn.addEventListener('click', () => {
                        const url = p.url || '#';
                        if (url === '#') return;
                        window.open(url, '_blank');
                    });

                    container.appendChild(btn);
                });
            } else {
                const span = document.createElement('span');
                span.textContent = 'Nenhuma página registrada.';
                container.appendChild(span);
            }

            const modal = document.getElementById('dailyPanelModal');
            if (modal) modal.style.display = 'flex';

            // Bind buttons (only once)
            const goTasks = document.getElementById('daily_go_tasks');

            function markSeenAndClose(redirect) {
                fetch('PaginaPrincipal/mark_daily_panel_seen.php', { method: 'POST' })
                    .then(r => r.json())
                    .finally(() => {
                        const m = document.getElementById('dailyPanelModal');
                        if (m) m.style.display = 'none';
                    });
            }

            if (goTasks) {
                goTasks.onclick = () => {
                    markSeenAndClose(true);
                    // alternativamente direcionar para o Kanban principal
                    modal.style.display = 'none';
                };
            }
        })
        .catch(err => console.error('Erro ao buscar painel diário:', err));
}

// Nav button handlers
const btnOverview = document.getElementById('overview');
const btnKanban = document.getElementById('kanban');
const overviewSection = document.getElementById('overview-section');
const kanbanSection = document.getElementById('kanban-section');

function setActive(button) {
    [btnOverview, btnKanban].forEach(b => b.classList.remove('active'));
    if (button) button.classList.add('active');
}

if (btnOverview) btnOverview.addEventListener('click', () => {
    overviewSection.style.display = 'flex';
    kanbanSection.style.display = 'none';
    setActive(btnOverview);
});

if (btnKanban) btnKanban.addEventListener('click', () => {
    overviewSection.style.display = 'none';
    kanbanSection.style.display = 'flex';
    setActive(btnKanban);
});

// Resumo modal button handlers
document.getElementById('resumo-overview').addEventListener('click', () => {
    document.getElementById('resumoModal').style.display = 'none';
    btnOverview.click();
});

document.getElementById('resumo-kanban').addEventListener('click', () => {
    document.getElementById('resumoModal').style.display = 'none';
    btnKanban.click();
});

document.getElementById('resumo-close').addEventListener('click', () => {
    document.getElementById('resumoModal').style.display = 'none';
});

function checkFuncoesEmAndamento(idColaborador) {
    return new Promise((resolve, reject) => {
        fetch('getFuncoesEmAndamento.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `idcolaborador=${idColaborador}`
        })
            .then(res => res.json())
            .then(funcoes => {
                if (!funcoes || funcoes.length === 0) {
                    resolve(); // nada em andamento, segue fluxo
                    return;
                }

                // Processa em sequência cada função
                let index = 0;

                function perguntarProximo() {
                    if (index >= funcoes.length) {
                        resolve(); // terminou todas
                        return;
                    }

                    const funcao = funcoes[index];
                    Swal.fire({
                        title: `Você ainda está trabalhando em ${funcao.imagem_nome}?`,
                        text: `Função: ${funcao.nome_funcao}`,
                        icon: "question",
                        showCancelButton: true,
                        confirmButtonText: "Sim, estou fazendo",
                        cancelButtonText: "Não, colocar em HOLD"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // continua sem alterar
                            index++;
                            perguntarProximo();
                        } else {
                            // pede observação
                            Swal.fire({
                                title: "Observação",
                                input: "text",
                                inputPlaceholder: "Por que não está fazendo?",
                                showCancelButton: false,
                                confirmButtonText: "Salvar"
                            }).then((obsResult) => {
                                const obs = obsResult.value || "";

                                fetch('atualizarFuncao.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    },
                                    body: `idfuncao_imagem=${funcao.idfuncao_imagem}&observacao=${encodeURIComponent(obs)}`
                                }).finally(() => {
                                    index++;
                                    perguntarProximo();
                                    carregarDados(idColaborador); // atualiza o kanban
                                });
                            });
                        }
                    });
                }

                perguntarProximo();
            })
            .catch(err => {
                console.error("Erro ao verificar funções em andamento:", err);
                reject();
            });
    });
}

// const MODO_TESTE = true;

// if (MODO_TESTE) {
//     checkFuncoesEmAndamento(idColaborador);
// } else {
checkDailyAccess()
    .then(() => checkRenderItems(idColaborador))
    .then(() => checkFuncoesSomentePrimeiroAcesso()) // ✅ só na 1ª vez do dia
    .then(() => {
        buscarTarefas();
        mostrarChangelogSeNecessario();
        try { fetchDailyPanel(); } catch (e) { console.error(e); }
        // mostrarResumoInteligente();
    })
    .catch(() => console.log('Fluxo interrompido'));

// }


carregarDados(colaborador_id);

carregarEventosEntrega();

const data = new Date();

// Pega o mês abreviado em pt-BR (ex: set, out, nov...)
let mes = data.toLocaleDateString('pt-BR', { month: 'short' });
mes = mes.charAt(0).toUpperCase() + mes.slice(1).replace('.', ''); // Capitaliza e remove ponto

const dia = data.getDate();
const ano = data.getFullYear();

const formatted = `${mes} ${dia}, ${ano}`;

document.querySelector('#date span').textContent = formatted;

// Inicializa mini FullCalendar (visão semanal) e integra com o calendário full (modal)
(function initMiniCalendar() {
    const miniEl = document.getElementById('mini-calendar');
    if (!miniEl || typeof FullCalendar === 'undefined') return;

    const miniCalendar = new FullCalendar.Calendar(miniEl, {
        initialView: 'dayGridWeek',
        headerToolbar: false,
        height: 110,
        locale: 'pt-br',
        displayEventTime: false,
        selectable: false,
        events: [],
        dateClick: function (info) {
            // abre modal do calendário expandido e vai para o dia
            document.getElementById('calendarFullModal').style.display = 'flex';
            openFullCalendar();
            setTimeout(() => {
                if (fullCalendar) {
                    fullCalendar.gotoDate(info.date);
                    fullCalendar.changeView('dayGridMonth');
                }
            }, 250);
        },
        eventClick: function (info) {
            // prevent the native click from bubbling to the global window handler
            // which would immediately hide the modal we open on this click
            try { info.jsEvent?.stopPropagation(); } catch (e) { /* ignore */ }

            const ev = info.event;
            if (ev.id && ev.id.startsWith('t_')) {
                abrirSidebarTarefaCriada(ev.id.replace('t_', ''));
            } else if (ev.id && ev.id.startsWith('f_')) {
                abrirSidebar(ev.id.replace('f_', ''), ev.extendedProps?.imagem_id || '');
            }
        }
    });

    miniCalendar.render();
    window.miniCalendar = miniCalendar;

    // Atualizador a ser chamado por carregarDados (passando o JSON já parseado)
    window.updateMiniCalendarWithData = function (data) {
        try {
            const evs = [];
            if (data && data.funcoes) {
                data.funcoes.forEach(f => {
                    const date = f.prazo || f.imagem_prazo;
                    if (date) {
                        evs.push({
                            id: `f_${f.idfuncao_imagem}`,
                            title: `${f.nome_funcao} — ${f.imagem_nome}`,
                            start: date,
                            allDay: true,
                            extendedProps: { tipo: 'funcao', status: f.status, imagem_id: f.imagem_id }
                        });
                    }
                });
            }
            if (data && data.tarefas) {
                data.tarefas.forEach(t => {
                    if (t.prazo) {
                        evs.push({
                            id: `t_${t.id}`,
                            title: t.titulo,
                            start: t.prazo,
                            allDay: true,
                            extendedProps: { tipo: 'tarefa', status: t.status }
                        });
                    }
                });
            }

            // keep latest mini events available globally so fullCalendar can reuse them
            window.miniCalendarEvents = evs;

            miniCalendar.removeAllEvents();
            if (evs.length) miniCalendar.addEventSource(evs);

            // if the full calendar modal is open, refresh its events to include these
            if (typeof fullCalendar !== 'undefined' && fullCalendar) {
                try {
                    fullCalendar.removeAllEvents();
                    if (Array.isArray(events) && events.length) fullCalendar.addEventSource(events);
                    if (Array.isArray(window.miniCalendarEvents) && window.miniCalendarEvents.length) fullCalendar.addEventSource(window.miniCalendarEvents);
                } catch (err) {
                    console.error('Erro ao atualizar fullCalendar com eventos do mini:', err);
                }
            }
        } catch (e) {
            console.error('updateMiniCalendarWithData error', e);
        }
    };

    // Fechar modal do calendário full
    document.getElementById('closeFullCalendar')?.addEventListener('click', function () {
        document.getElementById('calendarFullModal').style.display = 'none';
    });

})();

let events = [];

function carregarEventosEntrega() {
    fetch(`./Dashboard/Calendario/getEventosEntrega.php`)
        .then(res => res.json())
        .then(data => {
            console.log("Eventos de entrega:", data);

            events = data.map(evento => {
                delete evento.eventDate;

                const colors = getEventColors(evento); // 👈 adiciona o título

                return {
                    id: evento.id,
                    title: evento.descricao,
                    start: evento.start,
                    end: evento.end && evento.end !== evento.start ? evento.end : null,
                    allDay: evento.end ? true : false,
                    tipo_evento: evento.tipo_evento,
                    backgroundColor: colors.backgroundColor,
                    color: colors.color
                };
            });
            if (!fullCalendar) {
                openFullCalendar();
            } else {
                fullCalendar.removeAllEvents();
                fullCalendar.addEventSource(events);
            }

            if (colaborador_id === 1 || colaborador_id === 9 || colaborador_id === 21) {
                notificarEventosDaSemana(events);
            }
        });
}
// 👇 Função que retorna eventos desta semana
function notificarEventosDaSemana(eventos) {
    const hoje = new Date();
    const inicioSemana = new Date(hoje);
    inicioSemana.setDate(hoje.getDate() - hoje.getDay()); // domingo
    const fimSemana = new Date(inicioSemana);
    fimSemana.setDate(inicioSemana.getDate() + 6); // sábado

    const eventosSemana = eventos.filter(evento => {
        const startDate = new Date(evento.start);
        return startDate >= inicioSemana && startDate <= fimSemana;
    });

    if (eventosSemana.length > 0) {
        const listaEventos = eventosSemana
            .map(ev => `<li><strong>${ev.title}</strong> em ${new Date(ev.start).toLocaleDateString()}</li>`)
            .join('');

        Swal.fire({
            icon: 'info',
            title: 'Eventos desta semana',
            html: `<ul style="text-align: left; padding: 0 20px">${listaEventos}</ul>`,
            confirmButtonText: 'Entendi'
        });
    }
}

// Função para definir as cores com base no tipo_evento
function getEventColors(event) {
    // Only differentiate colors when the event is a 'funcao' or 'tarefa'
    // prefer extendedProps.tipo, fall back to tipo or tipo_evento if present
    const tipo = event?.extendedProps?.tipo || event?.tipo || event?.tipo_evento || '';

    if (String(tipo).toLowerCase() === 'funcao') {
        return { backgroundColor: '#ff9f89', color: '#000000' };
    }

    if (String(tipo).toLowerCase() === 'tarefa') {
        return { backgroundColor: '#90ee90', color: '#000000' };
    }

    // default: no special color
    return { backgroundColor: '#d3d3d3', color: '#000000' };
}


let fullCalendar;

function openFullCalendar() {

    if (!fullCalendar) {
        fullCalendar = new FullCalendar.Calendar(document.getElementById('calendarFull'), {
            initialView: 'dayGridMonth',
            editable: true,
            selectable: true,
            locale: 'pt-br',
            displayEventTime: false,
            events: [], // we'll add event sources after render (delivery events + mini events)
            eventDidMount: function (info) {
                // Pass the real event object so getEventColors can read extendedProps.tipo
                try {
                    const colors = getEventColors(info.event);
                    if (colors && colors.backgroundColor) info.el.style.backgroundColor = colors.backgroundColor;
                    if (colors && colors.color) info.el.style.color = colors.color;
                    info.el.style.borderColor = colors.backgroundColor || '';
                } catch (e) {
                    console.error('eventDidMount color error', e);
                }
            },
            datesSet: function (info) {
                const tituloOriginal = info.view.title;
                const partes = tituloOriginal.replace('de ', '').split(' ');
                const mes = partes[0];
                const ano = partes[1];
                const mesCapitalizado = mes.charAt(0).toUpperCase() + mes.slice(1);
                document.querySelector('#calendarFull .fc-toolbar-title').textContent = `${mesCapitalizado} ${ano}`;
            },

            dateClick: function (info) {
                const clickedDate = new Date(info.date);
                const formattedDate = clickedDate.toISOString().split('T')[0];

                // document.getElementById('eventId').value = '';
                // document.getElementById('eventTitle').value = '';
                // document.getElementById('eventDate').value = formattedDate;
                // document.getElementById('eventModal').style.display = 'flex';

            },

            eventClick: function (info) {
                // prevent the native click from bubbling to the global window handler
                // which would immediately hide the modal we open on this click
                try { info.jsEvent?.stopPropagation(); } catch (e) { /* ignore */ }

                // Show a simple detail modal on event click (Nome da função, Nome da imagem, Status, Prazo)
                try {
                    showEventDetails(info.event, info.el);
                } catch (e) {
                    console.error('Erro ao mostrar detalhes do evento:', e);
                }
            },

            eventDrop: function (info) {
                const event = info.event;
                updateEvent(event);
            }
        });

        fullCalendar.render();

        // add both delivery events and mini-calendar events (if any)
        try {
            if (Array.isArray(events) && events.length) fullCalendar.addEventSource(events);
            if (Array.isArray(window.miniCalendarEvents) && window.miniCalendarEvents.length) fullCalendar.addEventSource(window.miniCalendarEvents);
        } catch (err) {
            console.error('Erro ao adicionar fontes de evento ao fullCalendar:', err);
        }
    } else {
        // refresh event lists so both delivery events and mini events are present
        try {
            fullCalendar.removeAllEvents();
            if (Array.isArray(events) && events.length) fullCalendar.addEventSource(events);
            if (Array.isArray(window.miniCalendarEvents) && window.miniCalendarEvents.length) fullCalendar.addEventSource(window.miniCalendarEvents);
        } catch (err) {
            console.error('Erro ao atualizar eventos do fullCalendar existente:', err);
            fullCalendar.refetchEvents();
        }
    }
}


function closeEventModal() {
    document.getElementById('eventModal').style.display = 'none';
    carregarEventosEntrega()
}

function showToast(message, type = 'success') {
    let backgroundColor;

    switch (type) {
        case 'create':
            backgroundColor = 'linear-gradient(to right, #00b09b, #96c93d)'; // verde limão
            break;
        case 'update':
            backgroundColor = 'linear-gradient(to right, #2193b0, #6dd5ed)'; // azul claro
            break;
        case 'delete':
            backgroundColor = 'linear-gradient(to right, #ff416c, #ff4b2b)'; // vermelho/rosa
            break;
        case 'error':
            backgroundColor = 'linear-gradient(to right, #e53935, #e35d5b)'; // vermelho
            break;
        default:
            backgroundColor = 'linear-gradient(to right, #00b09b, #96c93d)'; // sucesso padrão
    }

    Toastify({
        text: message,
        duration: 4000,
        gravity: "top",
        position: "right",
        backgroundColor: backgroundColor,
    }).showToast();
}

// document.getElementById('eventForm').addEventListener('submit', function (e) {
//     e.preventDefault();
//     const id = document.getElementById('eventId').value;
//     const title = document.getElementById('eventTitle').value;
//     const start = document.getElementById('eventDate').value;
//     const type = document.getElementById('eventType').value;
//     const obraId = document.getElementById('obra_calendar').value;

//     if (id) {
//         fetch('./Dashboard/Calendario/eventoController.php', {
//             method: 'PUT',
//             headers: { 'Content-Type': 'application/json' },
//             body: JSON.stringify({ id, title, start, type })
//         })
//             .then(res => res.json())
//             .then(res => {
//                 if (res.error) throw new Error(res.message);
//                 closeEventModal(); // ✅ fecha o modal após excluir
//                 showToast(res.message, 'update'); // para PUT
//             })
//             .catch(err => showToast(err.message, 'error'));
//     } else {
//         fetch('./Dashboard/Calendario/eventoController.php', {
//             method: 'POST',
//             headers: { 'Content-Type': 'application/json' },
//             body: JSON.stringify({ title, start, type, obra_id: obraId })
//         })
//             .then(res => res.json())
//             .then(res => {
//                 if (res.error) throw new Error(res.message);
//                 closeEventModal(); // ✅ fecha o modal após excluir
//                 showToast(res.message, 'create'); // para POST
//             })
//             .catch(err => showToast(err.message, 'error'));
//     }
// });

function deleteEvent() {
    const id = document.getElementById('eventId').value;
    if (!id) return;

    fetch('./Dashboard/Calendario/eventoController.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
        .then(res => res.json())
        .then(res => {
            if (res.error) throw new Error(res.message);
            closeEventModal(); // ✅ fecha o modal após excluir

            showToast(res.message, 'delete');
        })
        .catch(err => showToast(err.message, 'error'));
}

// Show a simple read-only detail view for a clicked event inside #eventModal
function showEventDetails(ev, el) {
    const modal = document.getElementById('eventModal');
    const detail = document.getElementById('eventDetail');
    const form = document.getElementById('eventForm');

    if (form) form.style.display = 'none';
    if (detail) detail.style.display = 'flex';

    const nomeFuncaoEl = document.getElementById('detailNomeFuncao');
    const nomeImagemEl = document.getElementById('detailNomeImagem');
    const statusEl = document.getElementById('detailStatus');
    const prazoEl = document.getElementById('detailPrazo');

    let nomeFuncao = '-';
    let nomeImagem = '-';
    let status = ev.extendedProps?.status || ev.extendedProps?.tipo || ev.tipo_evento || ev.extendedProps?.tipo_evento || '-';
    let prazo = ev.start ? (new Date(ev.start)).toISOString().split('T')[0] : (ev.startStr || '-');

    if (ev.id && ev.id.startsWith('f_')) {
        if (ev.title && ev.title.includes('—')) {
            const parts = ev.title.split('—').map(s => s.trim());
            nomeFuncao = parts[0] || '-';
            nomeImagem = parts[1] || '-';
        } else {
            nomeFuncao = ev.title || '-';
            nomeImagem = ev.extendedProps?.imagem_id ? String(ev.extendedProps.imagem_id) : '-';
        }
    } else if (ev.id && ev.id.startsWith('t_')) {
        nomeFuncao = ev.title || '-';
        nomeImagem = '-';
    } else {
        nomeFuncao = ev.title || '-';
        nomeImagem = ev.extendedProps?.obra_nome || '-';
    }

    nomeFuncaoEl.textContent = nomeFuncao;
    nomeImagemEl.textContent = nomeImagem;
    statusEl.textContent = status;
    prazoEl.textContent = prazo;

    // === posição dinâmica ===
    const rect = el.getBoundingClientRect();
    const offsetX = 10; // espaço entre evento e modal
    const offsetY = 0;

    // Ajusta posição (para ficar à direita do evento)
    modal.style.top = `${window.scrollY + rect.top + offsetY}px`;
    modal.style.left = `${window.scrollX + rect.right + offsetX}px`;

    modal.style.display = 'block';
    modal.classList.add('show');

    // Fecha ao clicar no botão
    document.getElementById('closeEventDetail')?.addEventListener('click', () => {
        modal.style.display = 'none';
    }, { once: true });
}


const eventModal = document.getElementById('eventModal');

// Safe global handler: only attach if the modal exists and protect against missing children
if (eventModal) {
    ['click', 'touchstart', 'keydown'].forEach(eventType => {
        window.addEventListener(eventType, function (event) {
            try {
                // If modal is not visible, ignore
                if (!eventModal.style || !eventModal.style.display || eventModal.style.display === 'none') return;

                const eventosDiv = eventModal.querySelector('.eventos');

                // Close on click/touch when clicking on overlay background (the modal element itself)
                // or when clicking outside the inner '.eventos' container (if it exists)
                if ((eventType === 'click' || eventType === 'touchstart')) {
                    if (event.target === eventModal) {
                        eventModal.style.display = 'none';
                        return;
                    }

                    if (eventosDiv && !eventosDiv.contains(event.target)) {
                        eventModal.style.display = 'none';
                        return;
                    }
                }

                // Close on Escape key
                if (eventType === 'keydown' && event.key === 'Escape') {
                    eventModal.style.display = 'none';
                }
            } catch (e) {
                console.error('modal global handler error', e);
            }
        });
    });
}


function updateEvent(event) {
    fetch('./Dashboard/Calendario/eventoController.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: event.id,
            title: event.title,
            start: event.start.toISOString().substring(0, 10),
            type: event.extendedProps?.tipo_evento // 👈 forma segura de acessar

        })
    })
        .then(res => res.json())
        .then(res => {
            if (res.error) throw new Error(res.message);
            showToast(res.message);
        })
        .catch(err => showToast(err.message, false));
}



function atualizarTemposEmAndamento() {
    const spans = document.querySelectorAll('.card-log .date[data-inicio]');

    spans.forEach(span => {
        // pega o card correto
        const card = span.closest('.kanban-card');
        if (!card || card.dataset.status !== 'Em andamento') return;

        // pega o valor de data-inicio (em minutos)
        let minutosIniciais = parseInt(span.dataset.inicio, 10);
        if (isNaN(minutosIniciais)) minutosIniciais = 0;

        // salva timestamp do carregamento da página
        if (!span.dataset.startTimestamp) {
            span.dataset.startTimestamp = Date.now();
        }

        const agora = Date.now();
        const diffMs = agora - span.dataset.startTimestamp; // ms desde que abriu
        const diffSeg = Math.floor(diffMs / 1000); // segundos decorridos desde que abriu

        // converte minutos iniciais em segundos e soma
        const totalSegundos = minutosIniciais * 60 + diffSeg;

        // calcula dias, horas, minutos e segundos
        const dias = Math.floor(totalSegundos / 86400); // 86400 = 24*60*60
        const horas = Math.floor((totalSegundos % 86400) / 3600);
        const minutos = Math.floor((totalSegundos % 3600) / 60);
        const segundos = totalSegundos % 60;

        // monta a string formatada
        let partes = [];
        if (dias > 0) partes.push(`${dias}d`);
        if (horas > 0) partes.push(`${horas}h`);
        if (minutos > 0) partes.push(`${minutos}min`);
        partes.push(`${segundos}s`);

        span.innerHTML = `<i class="ri-time-line"></i> ${partes.join(' ')}`;
    });
}

// Atualiza a cada segundo
setInterval(atualizarTemposEmAndamento, 1000);

// Atualiza imediatamente ao carregar
atualizarTemposEmAndamento();



const statusMap = {
    'Não iniciado': 'to-do',
    'Em andamento': 'in-progress',
    'Em aprovação': 'in-review',
    'Ajuste': 'ajuste',
    'Finalizado': 'done',
    'HOLD': 'hold'
};


// Atualiza contagem de tarefas
function atualizarTaskCount() {
    Object.keys(statusMap).forEach(status => {
        const col = document.getElementById(statusMap[status]);
        const count = col.querySelectorAll('.kanban-card:not([style*="display: none"])').length;

        col.querySelector('.task-count').textContent = count;

        // Se for a coluna "ajuste", esconde quando não houver tarefas
        if (statusMap[status] === 'ajuste') {
            col.style.display = count === 0 ? 'none' : '';
        }
    });
}


// remove seleção dos outros
const sidebarRight = document.getElementById('sidebar-right');
const sidebarContent = document.getElementById('sidebar-content');
const closeSidebar = document.getElementById('close-sidebar');

function abrirSidebarTarefaCriada(idTarefa) {
    fetch(`PaginaPrincipal/getInfosTarefaCriada.php?idtarefa=${idTarefa}`)
        .then(res => res.json())
        .then(data => {
            sidebarContent.innerHTML = '';

            // Acessa o primeiro item do array dentro de "tarefa"
            const t = data.tarefa && data.tarefa[0] ? data.tarefa[0] : {};

            const tarefaDiv = document.createElement('div');
            tarefaDiv.innerHTML = `
                <h3>${t.titulo || '-'}</h3>
                <p><strong>Descrição:</strong> ${t.descricao || '-'}</p>
                <p><strong>Prazo:</strong> ${t.prazo || '-'}</p>
                <p><strong>Status:</strong> ${t.status || '-'}</p>
                <p><strong>Prioridade:</strong> ${t.prioridade || '-'}</p>
                <p><strong>Data de Criação:</strong> ${t.data_criacao || '-'}</p>
            `;

            sidebarContent.appendChild(tarefaDiv);
        });

    sidebarRight.classList.add('active');
}

function abrirSidebar(idFuncao, idImagem) {

    return fetch(`PaginaPrincipal/getInfosCard.php?idfuncao=${idFuncao}&imagem_id=${idImagem}`)
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            // Limpa conteúdo antigo
            sidebarContent.innerHTML = '';

            const funcao = data.funcoes[0]; // pega o primeiro elemento do array

            if (data.notificacoes && data.notificacoes.length > 0) {
                // ensure styles for notification UI exist
                if (!document.getElementById('func-notif-styles')) {
                    const s = document.createElement('style');
                    s.id = 'func-notif-styles';
                    s.textContent = `
                        /* notifications panel styles */
                        
                    `;
                    document.head.appendChild(s);
                }

                const notificacoesDiv = document.createElement('div');
                notificacoesDiv.className = 'notificacoes-container';
                notificacoesDiv.innerHTML = `<h3>Notificações</h3>`;

                // mark sidebar as blurred except the notifications container
                sidebarContent.classList.add('sidebar-blurred-mode');

                data.notificacoes.forEach(notif => {
                    const notifEl = document.createElement('div');
                    notifEl.className = 'func-notif';
                    notifEl.dataset.notId = notif.id;

                    const msgSpan = document.createElement('div');
                    msgSpan.className = 'msg';
                    msgSpan.textContent = notif.mensagem;

                    const rightDiv = document.createElement('div');
                    rightDiv.style.display = 'flex';
                    rightDiv.style.alignItems = 'center';

                    const dataSpan = document.createElement('div');
                    dataSpan.className = 'data';
                    dataSpan.textContent = notif.data ? notif.data.split(' ')[0] : '';

                    const markBtn = document.createElement('button');
                    markBtn.className = 'mark-btn';
                    markBtn.textContent = 'Marcar lida';

                    // Click handler: mark as read via backend then remove element
                    function marcarLida() {
                        const id = notifEl.dataset.notId;
                        fetch('PaginaPrincipal/markNotificacao.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `id=${encodeURIComponent(id)}`
                        })
                            .then(r => r.json())
                            .then(res => {
                                if (res && res.success) {
                                    // remove from DOM
                                    notifEl.remove();

                                    // if there are no more notifications, remove sidebar blur
                                    try {
                                        if (!notificacoesDiv.querySelector('.func-notif')) {
                                            sidebarContent.classList.remove('sidebar-blurred-mode');
                                        }
                                    } catch (e) {
                                        console.error('Erro ao atualizar blur da sidebar:', e);
                                    }
                                    // update any card icon counts if present
                                    const card = document.querySelector(`.kanban-card[data-id="${notif.funcao_imagem_id || notif.funcao_imagem || ''}"]`);
                                    if (card) {
                                        const countEl = card.querySelector('.notif-count');
                                        if (countEl) {
                                            let n = Number(countEl.textContent || 0);
                                            n = Math.max(0, n - 1);
                                            if (n === 0) {
                                                const icon = card.querySelector('.notif-icon');
                                                if (icon) icon.remove();
                                                notificacoesDiv.remove();
                                            } else {
                                                countEl.textContent = n;
                                            }
                                        }
                                    }
                                    showToast('Notificação marcada como lida', 'update');
                                } else {
                                    showToast('Não foi possível marcar como lida', 'error');
                                }
                            })
                            .catch(err => {
                                console.error('Erro markNotificacao:', err);
                                showToast('Erro ao conectar com o servidor', 'error');
                            });
                    }

                    // clicking the whole element marks as read (manual reading)
                    notifEl.addEventListener('click', function (e) {
                        // avoid double-trigger when clicking the button
                        if (e.target === markBtn) return;
                        marcarLida();
                    });

                    markBtn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        marcarLida();
                    });

                    rightDiv.appendChild(dataSpan);
                    rightDiv.appendChild(markBtn);

                    notifEl.appendChild(msgSpan);
                    notifEl.appendChild(rightDiv);

                    notificacoesDiv.appendChild(notifEl);
                });

                sidebarContent.appendChild(notificacoesDiv);
            }

            const imagemDiv = document.createElement('p');
            imagemDiv.innerHTML = `<strong>Imagem: ${funcao.imagem_nome || '-'}</strong>`;
            sidebarContent.appendChild(imagemDiv);
            // Exibe status da imagem
            if (data.status_imagem) {

                const statusDiv = document.createElement('p');
                statusDiv.classList.add('status-imagem');
                statusDiv.innerHTML = `<strong>Status da Imagem:</strong> `;

                const nomestatusSpan = document.createElement('span');
                nomestatusSpan.textContent = data.status_imagem.nome_status;

                // Define a cor conforme o status
                let corStatus;
                switch (data.status_imagem.nome_status.toLowerCase()) {
                    case 'p00':
                        corStatus = '#c2ff1cff';
                        break;
                    case 'r00':
                        corStatus = '#1cf4ff';
                        break;
                    case 'r01':
                        corStatus = '#ff9800';
                        break;
                    case 'r02':
                        corStatus = '#ff3c00';
                        break;
                    case 'r03':
                    case 'r04':
                    case 'r05':
                        corStatus = '#dc3545';
                        break;
                    case 'ef':
                        corStatus = '#0dff00';
                        break;
                    default:
                        corStatus = '#777';
                }

                // Aplica estilo no span
                nomestatusSpan.style.backgroundColor = corStatus;
                nomestatusSpan.style.color = '#000';
                nomestatusSpan.style.padding = '2px 6px';
                nomestatusSpan.style.borderRadius = '5px';
                nomestatusSpan.style.marginLeft = '8px';
                nomestatusSpan.style.fontWeight = '500';

                // Adiciona o span ao p e depois o p à sidebar
                statusDiv.appendChild(nomestatusSpan);
                sidebarContent.appendChild(statusDiv);
            }


            // Exibe colaboradores e suas funções
            if (data.colaboradores && data.colaboradores.length > 0) {
                const colabDiv = document.createElement('div');
                colabDiv.innerHTML = `<strong>Colaboradores:</strong>`;
                const ul = document.createElement('ul');

                data.colaboradores.forEach(col => {
                    let funcoes = col.funcoes || '';
                    if (funcoes) {
                        const arr = funcoes.split(',').map(f => f.trim());
                        if (arr.length > 1) {
                            const last = arr.pop();
                            funcoes = arr.join(', ') + ' e ' + last;
                        }
                    }
                    const li = document.createElement('li');
                    li.textContent = `${col.nome_colaborador} - ${funcoes}`;
                    ul.appendChild(li);
                });

                colabDiv.appendChild(ul);
                sidebarContent.appendChild(colabDiv);
            }

            // Exibe funções da imagem
            if (data.funcoes && data.funcoes.length > 0) {
                const funcoesDiv = document.createElement('div');
                data.funcoes.forEach(f => {
                    const fDiv = document.createElement('div');
                    fDiv.classList.add('funcao-card');
                    fDiv.innerHTML = `
                                        <p><strong>Função:</strong> ${f.nome_funcao}</p>
                                        <p><strong>Prazo:</strong> ${f.prazo || '—'}</p>
                                        <p><strong>Status:</strong> ${f.status || '—'}</p>
                                        <p><strong>Observação:</strong> ${f.observacao || '—'}</p>
                                    `;
                    funcoesDiv.appendChild(fDiv);
                });
                sidebarContent.appendChild(funcoesDiv);
            }

            // Helper: group array of arquivos by categoria_nome -> tipo
            function groupArquivos(arr) {
                const grouped = {}; // { categoria: { tipo: [items] } }
                arr.forEach(a => {
                    const cat = a.categoria_nome || 'Sem categoria';
                    const tipo = a.tipo || a.nome_interno?.split('.').pop()?.toUpperCase() || 'Outros';
                    if (!grouped[cat]) grouped[cat] = {};
                    if (!grouped[cat][tipo]) grouped[cat][tipo] = [];
                    grouped[cat][tipo].push(a);
                });
                return grouped;
            }

            // normalize path: replace /mnt/clientes -> Z:, use backslashes, and trim
            function normalizePath(rawPath, isTipoLevel = false) {
                if (!rawPath) return '';
                let p = rawPath;
                // replace linux mount prefix with drive letter
                p = p.replace(/^\/\/*mnt\/clientes/i, 'Z:');
                // normalize slashes to backslashes
                p = p.replace(/\//g, '\\');
                // remove trailing backslashes
                p = p.replace(/\\+$/g, '');

                const parts = p.split('\\').filter(Boolean);
                if (parts.length === 0) return p;

                const TYPES = ['IMG', 'DWG', 'PDF', 'Outros', 'SKP'];
                let idx = -1;
                for (let i = 0; i < parts.length; i++) {
                    if (TYPES.includes(parts[i].toUpperCase())) {
                        idx = i;
                        break;
                    }
                }

                if (idx >= 0) {
                    // for type-level files, stop at the type folder
                    if (isTipoLevel) {
                        return parts.slice(0, idx + 1).join('\\');
                    }
                    // for image-specific files, keep the folder after the type as well (if present)
                    if (idx + 1 < parts.length) {
                        return parts.slice(0, idx + 2).join('\\');
                    }
                    return parts.slice(0, idx + 1).join('\\');
                }

                // fallback: if last segment looks like a filename (has an extension), drop it
                const last = parts[parts.length - 1];
                if (/\.[A-Za-z0-9]{1,6}$/.test(last)) {
                    return parts.slice(0, -1).join('\\');
                }

                // otherwise return full normalized path
                return parts.join('\\');
            }


            function renderGroupedArquivos(title, arr, isTipoLevel = false) {
                if (!arr || arr.length === 0) return;

                const section = document.createElement('div');
                section.classList.add('arquivos-section');

                const header = document.createElement('h3');
                header.innerHTML = `📁 ${title}`;
                section.appendChild(header);

                const grouped = groupArquivos(arr);

                Object.keys(grouped).forEach(cat => {
                    const catDiv = document.createElement('div');
                    catDiv.classList.add('arquivos-categoria');

                    // total por categoria
                    const totalCat = Object.values(grouped[cat]).reduce((s, arr) => s + arr.length, 0);

                    const catHeader = document.createElement('div');
                    catHeader.classList.add('cat-header');
                    catHeader.innerHTML = `🏗️ ${cat} <span class="count">(${totalCat})</span>`;
                    catDiv.appendChild(catHeader);

                    // tipos dentro da categoria
                    Object.keys(grouped[cat]).forEach(tipo => {
                        const tipoArr = grouped[cat][tipo];
                        const tipoDiv = document.createElement('div');
                        tipoDiv.classList.add('arquivos-tipo');

                        // Se a categoria contém itens com categoria_id === 7 (ex: JPGs),
                        // não adicionamos o cabeçalho de tipo. Isso evita repetir o tipo
                        // para entradas de imagem que têm apresentação especial abaixo.
                        const containsCategoria7 = tipoArr.some(it => parseInt(it.categoria_id, 10) === 7);
                        if (!containsCategoria7) {
                            tipoDiv.innerHTML = `\n                <div class="tipo-header">↳ ${tipo} <span class="count">(${tipoArr.length})</span></div>\n            `;
                        } else {
                            // Marca o elemento para estilos alternativos, se necessário
                            tipoDiv.classList.add('no-tipo-header');
                        }

                        const infoDiv = document.createElement('div');
                        infoDiv.classList.add('tipo-info');

                        // separate items where categoria_id == 7 (special: show JPG filename + observação)
                        const jpgItems = tipoArr.filter(it => parseInt(it.categoria_id, 10) === 7);
                        const otherItems = tipoArr.filter(it => parseInt(it.categoria_id, 10) !== 7);

                        // First render other items as folder paths (existing behavior)
                        const rawPaths = Array.from(new Set(otherItems.map(it => it.caminho).filter(Boolean)));
                        const paths = rawPaths.map(p => normalizePath(p, isTipoLevel));
                        const uniquePaths = Array.from(new Set(paths));

                        if (uniquePaths.length > 0) {
                            uniquePaths.forEach(p => {
                                // Linha do caminho da pasta
                                const pDiv = document.createElement('div');
                                pDiv.classList.add('path');
                                pDiv.innerHTML = `📂 ${p}`;
                                infoDiv.appendChild(pDiv);

                                // Arquivos que compõem este caminho
                                const filesForPath = otherItems.filter(it => {
                                    const np = normalizePath(it.caminho, isTipoLevel);
                                    return np === p;
                                });

                                if (filesForPath.length > 0) {
                                    const listDiv = document.createElement('div');
                                    listDiv.classList.add('path-files');

                                    filesForPath.forEach(it => {
                                        const fileEntry = document.createElement('div');
                                        fileEntry.classList.add('file-entry');

                                        // Nome do arquivo
                                        const nome = it.nome_interno || (it.nome_arquivo || '—');
                                        const titleDiv = document.createElement('div');
                                        titleDiv.classList.add('file-title');
                                        titleDiv.textContent = `↳ ${nome}`;
                                        listDiv.appendChild(titleDiv);

                                        // Metadados: data (recebido_em), sufixo, descricao
                                        const metaDiv = document.createElement('div');
                                        metaDiv.classList.add('file-meta');

                                        // Data recebido_em formatada dd/mm/aaaa
                                        let dataStr = '';
                                        const rawDate = it.recebido_em || it.data || it.data_recebimento || '';
                                        if (rawDate) {
                                            const d = new Date(rawDate);
                                            if (!isNaN(d.getTime())) {
                                                const dd = String(d.getDate()).padStart(2, '0');
                                                const mm = String(d.getMonth() + 1).padStart(2, '0');
                                                const yyyy = d.getFullYear();
                                                dataStr = `${dd}/${mm}/${yyyy}`;
                                            } else {
                                                // se não for parseável, mostra como veio
                                                dataStr = String(rawDate);
                                            }
                                        }

                                        const partes = [];
                                        if (dataStr) partes.push(`📅 ${dataStr}`);
                                        if (it.sufixo) partes.push(`📝 ${it.sufixo}`);
                                        if (it.descricao) partes.push(`⚠️ ${it.descricao}`);

                                        if (partes.length > 0) {
                                            metaDiv.textContent = partes.join(' | ');
                                            listDiv.appendChild(metaDiv);
                                        }
                                    });

                                    infoDiv.appendChild(listDiv);
                                }
                            });
                        } else if (otherItems.length === 0 && jpgItems.length === 0) {
                            const noneDiv = document.createElement('div');
                            noneDiv.classList.add('path');
                            noneDiv.textContent = 'Sem caminho';
                            infoDiv.appendChild(noneDiv);
                        }

                        // Then render jpg items: show filename (from nome_interno or from caminho) and observação if present
                        if (jpgItems.length > 0) {
                            jpgItems.forEach(it => {
                                const pDiv = document.createElement('div');
                                pDiv.classList.add('path', 'jpg-entry');

                                // extrai nome do arquivo
                                let filename = it.nome_interno || '';
                                if (!filename && it.caminho) {
                                    const parts = it.caminho.split(/[\\\/]/).filter(Boolean);
                                    filename = parts.length ? parts[parts.length - 1] : it.caminho;
                                }

                                // tenta construir URL pública
                                let url = null;
                                if (it.caminho) {
                                    url = sftpToPublicUrl(it.caminho);
                                }

                                console.log('URL pública para', filename, ':', url);

                                if (url) {
                                    const img = document.createElement('img');
                                    img.src = encodeURI(url);
                                    img.alt = filename;
                                    img.title = filename;
                                    img.classList.add('thumb');

                                    const filenameSpan = document.createElement('span');
                                    filenameSpan.textContent = filename;

                                    // adiciona o nome primeiro
                                    pDiv.appendChild(filenameSpan);
                                    // e depois a imagem
                                    pDiv.appendChild(img);
                                } else {
                                    pDiv.textContent = `🖼️ ${filename}`;
                                }


                                // adiciona observação
                                if (it.descricao) {
                                    const descDiv = document.createElement('div');
                                    descDiv.classList.add('arquivo-descricao');
                                    descDiv.textContent = `⚠️ ${it.descricao}`;
                                    pDiv.appendChild(descDiv);
                                }

                                infoDiv.appendChild(pDiv);
                            });
                        }


                        tipoDiv.appendChild(infoDiv);
                        catDiv.appendChild(tipoDiv);
                    });

                    section.appendChild(catDiv);
                });

                sidebarContent.appendChild(section);
            }

            // Render image-specific arquivos grouped (show one folder after type)
            renderGroupedArquivos('Arquivos da imagem', data.arquivos_imagem, false);

            // Render type-level arquivos grouped (trim to the type folder)
            renderGroupedArquivos('Arquivos do tipo de imagem', data.arquivos_tipo, true);

            // Render previous-task arquivos (logs) with custom layout:
            // - .cat-header = nome_funcao
            // - .arquivos-tipo will contain only .tipo-info with caminhos
            function renderArquivosAnteriores(title, arr) {
                if (!arr || arr.length === 0) return;

                const section = document.createElement('div');
                section.classList.add('arquivos-section');

                const header = document.createElement('h3');
                header.innerHTML = `📁 ${title}`;
                section.appendChild(header);

                // group by nome_funcao -> tipo -> items
                const groupedByFunc = {};
                arr.forEach(a => {
                    const func = a.nome_funcao || 'Sem função';
                    const tipo = a.tipo || a.nome_arquivo?.split('.').pop()?.toUpperCase() || 'Outros';
                    if (!groupedByFunc[func]) groupedByFunc[func] = {};
                    if (!groupedByFunc[func][tipo]) groupedByFunc[func][tipo] = [];
                    groupedByFunc[func][tipo].push(a);
                });

                Object.keys(groupedByFunc).forEach(funcName => {
                    const catDiv = document.createElement('div');
                    catDiv.classList.add('arquivos-categoria');

                    // total por função
                    const totalFunc = Object.values(groupedByFunc[funcName]).reduce((s, arr) => s + arr.length, 0);

                    const catHeader = document.createElement('div');
                    catHeader.classList.add('cat-header');
                    catHeader.innerHTML = `🏗️ ${funcName} <span class="count">(${totalFunc})</span>`;
                    catDiv.appendChild(catHeader);

                    // for each tipo, only show tipo-info (paths)
                    Object.keys(groupedByFunc[funcName]).forEach(tipo => {
                        const tipoArr = groupedByFunc[funcName][tipo];
                        const tipoDiv = document.createElement('div');
                        tipoDiv.classList.add('arquivos-tipo');

                        const rawPaths = Array.from(new Set(tipoArr.map(it => it.caminho).filter(Boolean)));
                        const paths = rawPaths.map(p => normalizePath(p, false));
                        const uniquePaths = Array.from(new Set(paths));

                        const infoDiv = document.createElement('div');
                        infoDiv.classList.add('tipo-info');

                        if (uniquePaths.length > 0) {
                            uniquePaths.forEach(p => {
                                const pDiv = document.createElement('div');
                                pDiv.classList.add('path');
                                pDiv.innerHTML = `📂 ${p}`;
                                infoDiv.appendChild(pDiv);
                            });
                        } else {
                            const noneDiv = document.createElement('div');
                            noneDiv.classList.add('path');
                            noneDiv.textContent = 'Sem caminho';
                            infoDiv.appendChild(noneDiv);
                        }

                        tipoDiv.appendChild(infoDiv);
                        catDiv.appendChild(tipoDiv);
                    });

                    section.appendChild(catDiv);
                });

                sidebarContent.appendChild(section);

            }

            if (data.arquivos_anteriores && data.arquivos_anteriores.length) {
                renderArquivosAnteriores('Processos anteriores', data.arquivos_anteriores);
            }

            // Exibe log de alterações
            if (data.log_alteracoes && data.log_alteracoes.length > 0) {
                const logDiv = document.createElement('div');
                logDiv.classList.add('log-alteracoes');
                logDiv.innerHTML = `<strong>Log de Alterações:</strong>`;

                data.log_alteracoes.forEach(log => {
                    const li = document.createElement('div');
                    li.classList.add('log-entry');

                    // Define a cor da borda conforme o status_novo
                    let corBorda;
                    switch (log.status_novo.toLowerCase()) {
                        case 'em aprovação':
                            corBorda = '#4a90e2'; // azul
                            break;
                        case 'finalizado':
                        case 'aprovado':
                            corBorda = '#28a745'; // verde
                            break;
                        case 'aprovado com ajustes':
                            corBorda = '#5e07ffff';
                            break;
                        case 'não iniciado':
                            corBorda = '#6c757d';
                            break;
                        case 'em andamento':
                            corBorda = '#ff9800'; // laranja
                            break;
                        case 'ajuste':
                        case 'hold':
                            corBorda = '#dc3545'; // vermelho
                            break;
                        default:
                            corBorda = '#777'; // cinza padrão
                    }

                    li.style.borderLeft = `3px solid ${corBorda}`;
                    li.style.paddingLeft = '10px';
                    li.style.marginBottom = '10px';

                    li.innerHTML = `<strong>${log.data}</strong> ${log.status_anterior} → <em>${log.status_novo}</em> (${log.responsavel})`;
                    logDiv.appendChild(li);
                });

                sidebarContent.appendChild(logDiv);
            }
            const pathEl = document.querySelectorAll('.path');
            pathEl.forEach(el => {
                // Não sobrescrever elementos que já contêm conteúdo HTML (ex: imagens)
                // ou que são entradas JPG específicas — isso removia o <img> criado acima.
                if (el.classList.contains('jpg-entry') || el.querySelector('img')) return;

                // Só aplica o word-break em paths que são texto puro
                el.innerHTML = el.textContent.replace(/[\\\/]/g, '$&<wbr>');
            });
            // Abre a sidebar
            sidebarRight.classList.add('active');
            return data; // expose fetched data to caller
        });
};

// Fechar sidebar
closeSidebar.addEventListener('click', () => {
    sidebarRight.classList.remove('active');
});


function formatarDuracao(minutos) {
    if (!minutos || minutos < 0) return "-";

    const dias = Math.floor(minutos / 1440); // 1440 = 60*24
    const horas = Math.floor((minutos % 1440) / 60);
    const mins = minutos % 60;

    let partes = [];
    if (dias > 0) partes.push(`${dias}d`);
    if (horas > 0) partes.push(`${horas}h`);
    if (mins > 0) partes.push(`${mins}min`);

    return partes.join(" ");
}



// Preenche os filtros dinâmicos
function preencherFiltros() {
    const obras = new Set();
    const funcoes = new Set();

    document.querySelectorAll('.kanban-card').forEach(card => {
        if (card.dataset.obra_nome) obras.add(card.dataset.obra_nome);
        if (card.dataset.funcao_nome) funcoes.add(card.dataset.funcao_nome);
    });

    const filtroObra = document.getElementById('filtroObra');
    const filtroFuncao = document.getElementById('filtroFuncao');

    filtroObra.innerHTML = '<label><input type="checkbox" value=""> Todas as obras</label>';
    filtroFuncao.innerHTML = '<label><input type="checkbox" value=""> Todas as funções</label>';

    obras.forEach(o => {
        filtroObra.innerHTML += `<label><input type="checkbox" value="${o}"> ${o}</label>`;
    });

    funcoes.forEach(f => {
        filtroFuncao.innerHTML += `<label><input type="checkbox" value="${f}"> ${f}</label>`;
    });

    // Reaplica os eventos de filtro
    document.querySelectorAll('#filtroObra input, #filtroFuncao input, #filtroStatus input')
        .forEach(chk => chk.addEventListener('change', aplicarFiltros));
}

const statusMapInvertido = {
    'to-do': 'Não iniciado',
    'in-progress': 'Em andamento',
    'in-review': 'Em aprovação',
    'done': 'Finalizado'
};

flatpickr("#prazoRange", {
    mode: "range",
    dateFormat: "Y-m-d",
    onChange: aplicarFiltros // Chama a função de filtro sempre que mudar
});

const prazoInput = document.getElementById('prazoRange');
const resetBtn = document.getElementById('resetPrazo');

// Inicialmente esconde o botão
resetBtn.style.display = 'none';

// Mostra/esconde o botão conforme o valor do input
prazoInput.addEventListener('input', () => {
    resetBtn.style.display = prazoInput.value ? 'inline-block' : 'none';
});

// Também mantém o botão escondido quando clicamos para resetar
resetBtn.addEventListener('click', () => {
    prazoInput.value = '';
    resetBtn.style.display = 'none';
    aplicarFiltros(); // reaplica os filtros sem considerar o prazo
});


// Aplica os filtros selecionados
function aplicarFiltros() {
    const obrasSelecionadas = Array.from(document.querySelectorAll('#filtroObra input:checked')).map(el => el.value).filter(v => v);
    const funcoesSelecionadas = Array.from(document.querySelectorAll('#filtroFuncao input:checked')).map(el => el.value).filter(v => v);
    const statusSelecionados = Array.from(document.querySelectorAll('#filtroStatus input:checked')).map(el => el.value).filter(v => v);

    const prazoRange = document.getElementById('prazoRange').value.split(" to "); // Flatpickr usa "to" para range
    const prazoInicio = prazoRange[0] ? new Date(prazoRange[0]) : null;
    const prazoFim = prazoRange[1] ? new Date(prazoRange[1]) : prazoInicio;

    document.querySelectorAll('.kanban-card').forEach(card => {
        let mostrar = true;

        if (obrasSelecionadas.length && !obrasSelecionadas.includes(card.dataset.obra_nome)) mostrar = false;
        if (funcoesSelecionadas.length && !funcoesSelecionadas.includes(card.dataset.funcao_nome)) mostrar = false;
        if (statusSelecionados.length && !statusSelecionados.includes(card.dataset.status)) mostrar = false;

        if (prazoInicio) {
            const cardPrazo = new Date(card.dataset.prazo);
            if (cardPrazo < prazoInicio || cardPrazo > prazoFim) mostrar = false;
        }

        card.style.display = mostrar ? 'block' : 'none';
    });

    atualizarTaskCount();

}

// Vincula eventos de mudança dos selects
['filtroObra', 'filtroFuncao', 'filtroStatus'].forEach(id => {
    document.getElementById(id)?.addEventListener('change', aplicarFiltros);
});

function formatarData(data) {
    const partes = data.split("-");
    const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
    return dataFormatada;
}



const buttons = document.querySelectorAll('.nav-left button');

buttons.forEach(btn => {
    btn.addEventListener('click', () => {
        // Remove active de todos
        buttons.forEach(b => b.classList.remove('active'));
        // Adiciona active no botão clicado
        btn.classList.add('active');
    });
});

const add_task = document.getElementById('add-task');
add_task.addEventListener('click', () => {
    const modal = document.getElementById('task-modal');
    modal.style.display = 'flex';
    modal.classList.add('active');

    // pega id do colaborador no localStorage
    const selectColab = document.getElementById("task-colab");
    console.log("colab id:", colaborador_id);
    if (Number(colaborador_id) === 9 || Number(colaborador_id) === 21) {
        selectColab.disabled = false; // libera
    } else {
        selectColab.disabled = true;  // bloqueia
        selectColab.classList.add('hidden');
    }
});



const form = document.getElementById('task-form');
const modal = document.getElementById('task-modal');
const closeBtn = document.getElementById('close-modal');

// Fecha o modal
closeBtn.addEventListener('click', () => {
    modal.style.display = 'none';
});

// Submit AJAX
form.addEventListener('submit', (e) => {
    e.preventDefault();

    const formData = new FormData(form);

    fetch('PaginaPrincipal/addTask.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                alert("✅ Tarefa adicionada com sucesso!");
                form.reset();
                modal.style.display = 'none';
                // aqui você pode recarregar o Kanban
                carregarDados(colaborador_id);
            } else {
                alert("❌ Erro: " + response.message);
            }
        })
        .catch(err => {
            console.error("Erro no fetch:", err);
            alert("Erro ao enviar tarefa.");
        });
});

const cardModal = document.getElementById('cardModal');
const modalPrazo = document.getElementById('modalPrazo');
const modalObs = document.getElementById('modalObs');
let cardSelecionado = null;

// Fechar modal
document.getElementById('fecharModal').addEventListener('click', () => {
    cardModal.classList.remove('active');
    cardSelecionado = null;
});

// Salvar alterações
document.getElementById('salvarModal').addEventListener('click', () => {
    if (!cardSelecionado) return;

    // Verifica se o prazo está vazio
    if (modalPrazo.offsetParent !== null && !modalPrazo.value) {

        Toastify({
            text: "Por favor, preencha o prazo antes de salvar.",
            duration: 3000,
            close: true,
            gravity: "top",
            position: "left",
            backgroundColor: "red",
        }).showToast();

        return; // interrompe o envio
    }

    cardSelecionado.dataset.prazo = modalPrazo.value;
    cardSelecionado.dataset.observacao = modalObs.value;

    // Mapeamento de IDs de coluna para status
    const statusMap = {
        'to-do': 'Não iniciado',
        'hold': 'HOLD',
        'in-progress': 'Em andamento',
        'in-review': 'Em aprovação',
        'ajuste': 'Ajuste',
        'done': 'Finalizado'
    };

    if (cardSelecionado.classList.contains('tarefa-criada')) {
        // Se for tarefa criada, atualiza via outro script
        const dadosTarefa = {
            tarefa_id: cardSelecionado.dataset.id, // ou outro atributo se necessário
            prazo: modalPrazo.value,
            observacao: modalObs.value,
            status: statusMap[cardSelecionado.closest('.kanban-box').id] || null
        };

        $.ajax({
            type: "POST",
            url: "PaginaPrincipal/atualizaTarefa.php",
            data: dadosTarefa,
            success: function (response) {
                Toastify({
                    text: "Tarefa atualizada com sucesso!",
                    duration: 3000,
                    close: true,
                    gravity: "top",
                    position: "left",
                    backgroundColor: "green",
                    stopOnFocus: true,
                }).showToast();
                cardModal.classList.remove('active');
                cardSelecionado = null;
                carregarDados(colaborador_id); // Recarrega o Kanban para refletir mudanças
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Erro ao atualizar tarefa: " + textStatus, errorThrown);
                Toastify({
                    text: "Erro ao atualizar tarefa.",
                    duration: 3000,
                    close: true,
                    gravity: "top",
                    position: "left",
                    backgroundColor: "red",
                    stopOnFocus: true,
                }).showToast();
            },
        });
    } else {
        // capture the original previous status from the card (provided by server as status_funcao_anterior)
        const previousStatus = (cardSelecionado.getAttribute('data-status-anterior') || cardSelecionado.getAttribute('data-nome_status') || cardSelecionado.dataset.status || '').toString();

        const dados = {
            imagem_id: cardSelecionado.dataset.idImagem,
            funcao_id: cardSelecionado.dataset.idFuncao,
            cardId: cardSelecionado.dataset.id,
            status: statusMap[cardSelecionado.closest('.kanban-box').id] || null,
            prazo: modalPrazo.value,
            observacao: modalObs.value,
        };

        $.ajax({
            type: "POST",
            url: "insereFuncao.php",
            data: dados,
            success: function (response) {
                Toastify({
                    text: "Dados salvos com sucesso!",
                    duration: 3000,
                    close: true,
                    gravity: "top",
                    position: "left",
                    backgroundColor: "green",
                    stopOnFocus: true,
                }).showToast();
                cardModal.classList.remove('active');
                cardSelecionado = null;
                carregarDados(colaborador_id); // Recarrega o Kanban para refletir mudanças

                try {
                    const novo = (dados.status || '').toString().toLowerCase();
                    const prev = (previousStatus || '').toString().toLowerCase();
                    if (novo === 'em andamento' && prev === 'aprovado com ajustes') {
                        // open sidebar and get data so we can show the previous function name
                        abrirSidebar(dados.cardId, dados.imagem_id)
                            .then((data) => {
                                // ensure notifications container exists
                                let notificacoesDiv = sidebarContent.querySelector('.notificacoes-container');
                                if (!notificacoesDiv) {
                                    notificacoesDiv = document.createElement('div');
                                    notificacoesDiv.className = 'notificacoes-container';
                                    notificacoesDiv.innerHTML = `<h3>Notificações</h3>`;
                                    sidebarContent.insertBefore(notificacoesDiv, sidebarContent.firstChild);
                                }

                                // enable blur mode so the notification stands out
                                sidebarContent.classList.add('sidebar-blurred-mode');

                                // build reminder message using function name from fetched data if available
                                const funcName = (data && data.funcoes && data.funcoes[0] && data.funcoes[0].nome_funcao) ? data.funcoes[0].nome_funcao : '';
                                const prevReadable = previousStatus || 'Aprovado com Ajustes';
                                const mensagem = funcName ? `Lembrete: Função "${funcName}" veio de \"${prevReadable}\". Verifique comentários/ajustes anteriores.` : `Lembrete: Função veio de \"${prevReadable}\". Verifique comentários/ajustes anteriores.`;

                                const reminder = document.createElement('div');
                                reminder.className = 'func-notif reminder';
                                reminder.dataset.notId = 'client-reminder-' + Date.now();

                                const msgSpan = document.createElement('div');
                                msgSpan.className = 'msg';
                                msgSpan.textContent = mensagem;

                                const rightDiv = document.createElement('div');
                                rightDiv.style.display = 'flex';
                                rightDiv.style.alignItems = 'center';

                                const dataSpan = document.createElement('div');
                                dataSpan.className = 'data';
                                dataSpan.textContent = (new Date()).toISOString().split('T')[0];

                                const markBtn = document.createElement('button');
                                markBtn.className = 'mark-btn';
                                markBtn.textContent = 'Fechar';

                                function dismissReminder() {
                                    try {
                                        reminder.remove();
                                        if (!notificacoesDiv.querySelector('.func-notif')) {
                                            sidebarContent.classList.remove('sidebar-blurred-mode');
                                            notificacoesDiv.remove();
                                        }
                                    } catch (e) { console.error('Erro ao remover lembrete:', e); }
                                }

                                reminder.addEventListener('click', function (e) { if (e.target === markBtn) return; dismissReminder(); });
                                markBtn.addEventListener('click', function (e) { e.stopPropagation(); dismissReminder(); });

                                rightDiv.appendChild(dataSpan);
                                rightDiv.appendChild(markBtn);
                                reminder.appendChild(msgSpan);
                                reminder.appendChild(rightDiv);

                                notificacoesDiv.insertBefore(reminder, notificacoesDiv.querySelector('.func-notif') || null);

                                // update card UI counter if present
                                try {
                                    const card = document.querySelector(`.kanban-card[data-id="${dados.cardId}"]`);
                                    if (card) {
                                        let countEl = card.querySelector('.notif-count');
                                        if (!countEl) {
                                            const icon = document.createElement('span');
                                            icon.className = 'notif-icon';
                                            icon.innerHTML = `<i class="ri-notification-3-line"></i><small class="notif-count">1</small>`;
                                            card.querySelector('.header-kanban')?.appendChild(icon);
                                        } else {
                                            let n = Number(countEl.textContent || 0);
                                            countEl.textContent = String(n + 1);
                                        }
                                    }
                                } catch (e) { console.error('Erro ao atualizar contador de notificação no card:', e); }

                            })
                            .catch(err => console.error('Erro ao abrir sidebar para mostrar lembrete:', err));
                    }
                } catch (e) {
                    console.error('Erro na lógica pós-salvar:', e);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Erro ao salvar dados: " + textStatus, errorThrown);
                Toastify({
                    text: "Erro ao salvar dados.",
                    duration: 3000,
                    close: true,
                    gravity: "top",
                    position: "left",
                    backgroundColor: "red",
                    stopOnFocus: true,
                }).showToast();
            },
        });
    }
});

function configurarDropzone(areaId, inputId, listaId, arquivosArray) {
    const dropArea = document.getElementById(areaId);
    const fileInput = document.getElementById(inputId);

    // Funções nomeadas para poder remover depois
    function handleDrop(e) {
        e.preventDefault();
        dropArea.classList.remove('highlight');
        for (let file of e.dataTransfer.files) arquivosArray.push(file);
        renderizarLista(arquivosArray, listaId);
    }
    function handleChange() {
        for (let file of fileInput.files) arquivosArray.push(file);
        renderizarLista(arquivosArray, listaId);
    }
    function handleClick() {
        fileInput.click();
    }
    function handleDragOver(e) {
        e.preventDefault();
        dropArea.classList.add('highlight');
    }
    function handleDragLeave() {
        dropArea.classList.remove('highlight');
    }

    // Remove listeners antigos
    dropArea.removeEventListener('click', dropArea._handleClick);
    dropArea.removeEventListener('dragover', dropArea._handleDragOver);
    dropArea.removeEventListener('dragleave', dropArea._handleDragLeave);
    dropArea.removeEventListener('drop', dropArea._handleDrop);
    fileInput.removeEventListener('change', fileInput._handleChange);

    // Adiciona listeners e guarda referência para remover depois
    dropArea.addEventListener('click', handleClick);
    dropArea.addEventListener('dragover', handleDragOver);
    dropArea.addEventListener('dragleave', handleDragLeave);
    dropArea.addEventListener('drop', handleDrop);
    fileInput.addEventListener('change', handleChange);

    // Guarda referência
    dropArea._handleClick = handleClick;
    dropArea._handleDragOver = handleDragOver;
    dropArea._handleDragLeave = handleDragLeave;
    dropArea._handleDrop = handleDrop;
    fileInput._handleChange = handleChange;
}

function renderizarLista(array, listaId) {
    const lista = document.getElementById(listaId);
    lista.innerHTML = '';
    array.forEach((file, i) => {
        // Calcula o tamanho em B, KB, MB ou GB
        let tamanho = file.size;
        let tamanhoStr = '';
        if (tamanho < 1024) {
            tamanhoStr = `${tamanho} B`;
        } else if (tamanho < 1024 * 1024) {
            tamanhoStr = `${(tamanho / 1024).toFixed(1)} KB`;
        } else if (tamanho < 1024 * 1024 * 1024) {
            tamanhoStr = `${(tamanho / (1024 * 1024)).toFixed(2)} MB`;
        } else {
            tamanhoStr = `${(tamanho / (1024 * 1024 * 1024)).toFixed(2)} GB`;
        }

        const li = document.createElement('li');
        li.innerHTML = `<div class="file-info">
            <span>${file.name} <small style="color:#888;">(${tamanhoStr})</small></span>
            <span onclick="removerArquivo(${i}, '${listaId}')" style="cursor:pointer;color: #c00;font-weight: bold;font-size: 1.2em;">×</span>
        </div>`;
        lista.appendChild(li);
    });
}

function removerArquivo(index, listaId) {
    if (listaId === 'fileListPrevia') {
        imagensSelecionadas.splice(index, 1);
        renderizarLista(imagensSelecionadas, listaId);
    } else {
        arquivosFinais.splice(index, 1);
        renderizarLista(arquivosFinais, listaId);
    }
}



var idfuncao_imagem = null;
var titulo = null;
var subtitulo = null;
var obra = null;
var idimagem = null;
var nome_status = null;
const dropArea = document.getElementById('drop-area');
const fileInput = document.getElementById('fileElem');
const fileList = document.getElementById('fileList');
let arquivosFinais = [];
let dataIdFuncoes = [];
let imagensSelecionadas = [];



// Inicializa Sortable nas colunas
const colunas = document.querySelectorAll('.kanban-box .content');
colunas.forEach(col => {
    new Sortable(col, {
        group: 'kanban',
        animation: 150,
        ghostClass: 'sortable-ghost',
        filter: ".bloqueado",      // não deixa arrastar cards bloqueados
        touchStartThreshold: 10, // move 10px antes de iniciar o drag
        onMove: function (evt) {
            const fromId = evt.from.closest('.kanban-box')?.id;
            const toId = evt.to.closest('.kanban-box')?.id;
            const dragged = evt.dragged;

            if (dragged.classList.contains("bloqueado")) return false;

            if (toId === "ajuste") return false;

            if (toId === "to-do" && fromId !== "to-do") return false;

            if (fromId === "em-andamento" && toId === "to-do") return false;

            return true; // caso contrário, libera o movimento
        }
        ,
        onEnd: (evt) => {
            const card = evt.item;
            const deColuna = evt.from.closest('.kanban-box');
            const novaColuna = evt.to.closest('.kanban-box');
            const novoIndex = evt.newIndex;

            if (card.dataset.liberado === "0") {
                evt.from.appendChild(card);
                alert("Esta função ainda não foi liberada.");
                return;
            }

            console.log(`Card movido de ${deColuna.id} para ${novaColuna.id}, índice: ${novoIndex}`);

            // Só abre modal se mudou de coluna
            if (deColuna.id !== novaColuna.id) {
                cardSelecionado = card;

                idfuncao_imagem = card.getAttribute("data-id");
                idimagem = card.getAttribute("data-id-imagem");
                titulo = card.querySelector("h5")?.innerText || "";
                subtitulo = card.getAttribute("data-funcao_nome");
                obra = card.getAttribute("data-obra_nome");
                nome_status = card.getAttribute("data-nome_status");

                // Preenche os campos comuns
                modalPrazo.value = card.dataset.prazo || '';
                modalObs.value = card.dataset.observacao || '';

                // Reset modal: mostra tudo inicialmente
                document.querySelector('.modalPrazo').style.display = 'flex';
                document.querySelector('.modalObs').style.display = 'flex';
                document.querySelector('.modalUploads').style.display = 'flex';
                document.querySelector('.buttons').style.display = 'flex';

                // Limpa listas de arquivos ao abrir o modal
                imagensSelecionadas = [];
                arquivosFinais = [];
                renderizarLista(imagensSelecionadas, 'fileListPrevia');
                renderizarLista(arquivosFinais, 'fileListFinal');

                // Ativar modal
                cardModal.classList.add('active');

                cardSelecionado.classList.add('selected');
                configurarDropzone("drop-area-previa", "fileElemPrevia", "fileListPrevia", imagensSelecionadas);
                configurarDropzone("drop-area-final", "fileElemFinal", "fileListFinal", arquivosFinais);


                // Ajusta modal de acordo com a coluna de destino
                switch (novaColuna.id) {
                    case 'hold':
                        // Apenas observação e botões
                        document.querySelector('.modalPrazo').style.display = 'none';
                        document.querySelector('.modalUploads').style.display = 'none';
                        document.querySelector('.statusAnterior').style.display = 'none';
                        break;
                    case 'in-progress':
                        // Apenas observação e botões
                        document.querySelector('.modalUploads').style.display = 'none';
                        document.querySelector('.statusAnterior').style.display = 'flex';
                        break;
                    case 'in-review': // "Em aprovação"
                        // Mostra ambos inputs de arquivo (prévia e arquivo final)
                        document.querySelector('.modalPrazo').style.display = 'none';
                        document.querySelector('.modalObs').style.display = 'none';
                        document.querySelector('.modalUploads').style.display = 'flex';
                        document.querySelector('.buttons').style.display = 'none';
                        document.querySelector('.statusAnterior').style.display = 'none';
                        break;
                    case 'done': // "Finalizado"
                        // Mostra prazo, observação e botões
                        document.querySelector('.modalPrazo').style.display = 'flex';
                        document.querySelector('.modalObs').style.display = 'flex';
                        document.querySelector('.modalUploads').style.display = 'flex';
                        document.querySelector('.statusAnterior').style.display = 'flex';
                        break;
                    default:
                        // padrão: tudo visível
                        break;
                }

                // ✅ Sobrescreve se for tarefa-criada (regra final)
                if (card.classList.contains('tarefa-criada')) {
                    document.querySelector('.modalPrazo').style.display = 'flex';
                    document.querySelector('.modalObs').style.display = 'flex';
                    document.querySelector('.buttons').style.display = 'flex';
                    document.querySelector('.modalUploads').style.display = 'none';
                    document.querySelector('.statusAnterior').style.display = 'none';
                }

                // Posicionar modal ao lado da coluna de destino
                const rect = novaColuna.getBoundingClientRect();
                const modalWidth = cardModal.offsetWidth;
                const modalHeight = cardModal.offsetHeight;

                let left = rect.right + 10;
                let top = rect.top + 10;

                if (left + modalWidth > window.innerWidth) {
                    left = rect.left - modalWidth - 10;
                }
                if (top + modalHeight > window.innerHeight) {
                    top = window.innerHeight - modalHeight - 10;
                    if (top < 10) top = 10;
                }

                cardModal.style.left = `${left}px`;
                cardModal.style.top = `${top}px`;
            }

        }
    });
});


function enviarImagens() {
    if (imagensSelecionadas.length === 0) {
        Toastify({
            text: "Selecione pelo menos uma imagem para enviar a prévia.",
            duration: 3000,
            gravity: "top",
            backgroundColor: "#f44336"
        }).showToast();
        return;
    }

    const formData = new FormData();
    imagensSelecionadas.forEach(file => formData.append('imagens[]', file));
    formData.append('dataIdFuncoes', idfuncao_imagem);
    formData.append('idimagem', idimagem);
    formData.append('nome_funcao', subtitulo);
    formData.append('nome_imagem', titulo);

    const numeroImagem = titulo.match(/^\d+/)?.[0] || '';
    formData.append('numeroImagem', numeroImagem);
    formData.append('nomenclatura', obra);

    // Extrai a primeira palavra da descrição (depois da nomenclatura)
    // aceita letras maiúsculas, underscores e dígitos na nomenclatura (ex: MEN_991)
    const descricaoMatch = titulo.match(/^\d+\.\s*[A-Z0-9_]+\s+([^\s]+)/i);
    const primeiraPalavra = descricaoMatch ? descricaoMatch[1] : '';
    formData.append('primeiraPalavra', primeiraPalavra);

    // Container de progresso
    const progressContainer = document.createElement('div');
    progressContainer.style.fontSize = '16px';
    progressContainer.innerHTML = `
        <progress id="uploadProgress" value="0" max="100" style="width:100%;height:20px;"></progress>
        <div id="uploadStatus">Enviando... 0%</div>
        <div id="uploadTempo">Tempo: 0s</div>
        <div id="uploadVelocidade">Velocidade: 0 MB/s</div>
        <div id="uploadEstimativa">Tempo restante: ...</div>
        <button id="cancelarUpload" style="margin-top:10px;padding:5px 10px;">Cancelar</button>
    `;

    Swal.fire({
        title: 'Enviando prévia...',
        html: progressContainer,
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            const xhr = new XMLHttpRequest();
            const startTime = Date.now();
            let uploadCancelado = false;

            xhr.open('POST', 'uploadArquivos.php');

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const now = Date.now();
                    const elapsed = (now - startTime) / 1000;
                    const uploadedMB = e.loaded / (1024 * 1024);
                    const totalMB = e.total / (1024 * 1024);
                    const percent = (e.loaded / e.total) * 100;
                    const speed = uploadedMB / elapsed;
                    const remainingMB = totalMB - uploadedMB;
                    const estimatedTime = remainingMB / (speed || 1);

                    document.getElementById('uploadProgress').value = percent;
                    document.getElementById('uploadStatus').innerText = `Enviando... ${percent.toFixed(2)}%`;
                    document.getElementById('uploadTempo').innerText = `Tempo: ${elapsed.toFixed(1)}s`;
                    document.getElementById('uploadVelocidade').innerText = `Velocidade: ${speed.toFixed(2)} MB/s`;
                    document.getElementById('uploadEstimativa').innerText = `Tempo restante: ${estimatedTime.toFixed(1)}s`;
                }
            });

            xhr.onreadystatechange = () => {
                if (xhr.readyState === 4 && !uploadCancelado) {
                    try {
                        const res = JSON.parse(xhr.responseText);

                        if (res.error) {
                            Toastify({
                                text: "Erro: " + res.error,
                                duration: 3000,
                                gravity: "top",
                                backgroundColor: "#f44336"
                            }).showToast();
                        } else {
                            Swal.fire({
                                position: "center",
                                icon: "success",
                                title: "Prévia enviada com sucesso!",
                                showConfirmButton: false,
                                timer: 2000
                            });
                        }
                    } catch (err) {
                        Toastify({
                            text: "Erro ao processar resposta do servidor",
                            duration: 3000,
                            gravity: "top",
                            backgroundColor: "#f44336"
                        }).showToast();
                        console.error(err);
                    }
                }
            };

            xhr.onerror = () => {
                if (!uploadCancelado) {
                    Toastify({
                        text: "Erro ao enviar prévia",
                        duration: 3000,
                        gravity: "top",
                        backgroundColor: "#f44336"
                    }).showToast();
                }
            };

            document.getElementById('cancelarUpload').addEventListener('click', () => {
                uploadCancelado = true;
                xhr.abort();
                Swal.fire({
                    icon: 'warning',
                    title: 'Upload cancelado',
                    showConfirmButton: false,
                    timer: 1500
                });
            });

            xhr.send(formData);
        }
    });
}


// ENVIO DO ARQUIVO FINAL
function enviarArquivo() {
    if (arquivosFinais.length === 0) {
        Toastify({
            text: "Selecione pelo menos um arquivo para enviar a prévia.",
            duration: 3000,
            gravity: "top",
            backgroundColor: "#f44336"
        }).showToast();
        return;
    }

    const formData = new FormData();
    arquivosFinais.forEach(file => formData.append('arquivo_final[]', file));
    formData.append('dataIdFuncoes', idfuncao_imagem);
    formData.append('idimagem', idimagem);
    formData.append('nome_funcao', subtitulo);
    const campoNomeImagem = titulo;
    formData.append('nome_imagem', campoNomeImagem);

    // Extrai o número inicial antes do ponto
    const numeroImagem = campoNomeImagem.match(/^\d+/)?.[0] || '';
    formData.append('numeroImagem', numeroImagem);

    // Extrai a nomenclatura (primeira palavra com "_", depois do número e ponto)
    const nomenclatura = obra;
    formData.append('nomenclatura', nomenclatura);

    // Extrai a primeira palavra da descrição (depois da nomenclatura)
    // aceita letras maiúsculas, underscores e dígitos na nomenclatura (ex: MEN_991)
    const descricaoMatch = campoNomeImagem.match(/^\d+\.\s*[A-Z0-9_]+\s+([^\s]+)/i);
    const primeiraPalavra = descricaoMatch ? descricaoMatch[1] : '';
    formData.append('primeiraPalavra', primeiraPalavra);


    const statusNome = nome_status;

    formData.append('status_nome', statusNome);

    // Criar container de progresso
    const progressContainer = document.createElement('div');
    progressContainer.style.fontSize = '16px';
    progressContainer.innerHTML = `
        <progress id="uploadProgress" value="0" max="100" style="width: 100%; height: 20px;"></progress>
        <div id="uploadStatus">Enviando... 0%</div>
        <div id="uploadTempo">Tempo: 0s</div>
        <div id="uploadVelocidade">Velocidade: 0 MB/s</div>
        <div id="uploadEstimativa">Tempo restante: ...</div>
        <button id="cancelarUpload" style="margin-top:10px;padding:5px 10px;">Cancelar</button>
    `;

    Swal.fire({
        title: 'Enviando arquivo...',
        html: progressContainer,
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            const xhr = new XMLHttpRequest();
            const startTime = Date.now();
            let uploadCancelado = false;

            xhr.open('POST', 'https://improov.com.br/flow/ImproovWeb/uploadFinal.php');

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const now = Date.now();
                    const elapsed = (now - startTime) / 1000; // em segundos
                    const uploadedMB = e.loaded / (1024 * 1024);
                    const totalMB = e.total / (1024 * 1024);
                    const percent = (e.loaded / e.total) * 100;
                    const speed = uploadedMB / elapsed; // MB/s
                    const remainingMB = totalMB - uploadedMB;
                    const estimatedTime = remainingMB / (speed || 1); // evita divisão por 0

                    document.getElementById('uploadProgress').value = percent;
                    document.getElementById('uploadStatus').innerText = `Enviando... ${percent.toFixed(2)}%`;
                    document.getElementById('uploadTempo').innerText = `Tempo: ${elapsed.toFixed(1)}s`;
                    document.getElementById('uploadVelocidade').innerText = `Velocidade: ${speed.toFixed(2)} MB/s`;
                    document.getElementById('uploadEstimativa').innerText = `Tempo restante: ${estimatedTime.toFixed(1)}s`;
                }
            });

            // onload executa para respostas HTTP (independente de status)
            xhr.onload = () => {
                if (uploadCancelado) return;
                // tenta parsear JSON, se possível
                let res = null;
                try {
                    res = JSON.parse(xhr.responseText || 'null');
                } catch (err) {
                    console.error('Resposta não-JSON do servidor:', xhr.responseText);
                }

                if (xhr.status >= 200 && xhr.status < 300) {
                    if (res && Array.isArray(res) && res.length > 0) {
                        const destino = res[0]?.destino || 'Caminho não encontrado';
                        Swal.fire({
                            position: "center",
                            icon: "success",
                            text: `Salvo em: ${destino}, como: ${res[0]?.nome_arquivo || 'Nome não encontrado'}`,
                            showConfirmButton: false,
                            timer: 2000
                        });
                    } else {
                        Swal.fire({
                            position: "center",
                            icon: "success",
                            text: 'Upload concluído.',
                            showConfirmButton: false,
                            timer: 1500
                        });
                    }
                    fecharModal();
                } else {
                    // status não-200: mostra erro com texto do servidor quando disponível
                    const serverMsg = xhr.responseText ? xhr.responseText : `Status ${xhr.status}`;
                    Swal.close();
                    Toastify({
                        text: "Erro no servidor: " + serverMsg,
                        duration: 6000,
                        gravity: "top",
                        backgroundColor: "#f44336"
                    }).showToast();
                }
            };

            xhr.onerror = () => {
                if (!uploadCancelado) {
                    Swal.close();
                    Toastify({
                        text: "Erro ao enviar arquivo final",
                        duration: 3000,
                        gravity: "top",
                        backgroundColor: "#f44336"
                    }).showToast();
                }
            };

            // Cancelar envio
            document.getElementById('cancelarUpload').addEventListener('click', () => {
                uploadCancelado = true;
                xhr.abort();
                Swal.fire({
                    icon: 'warning',
                    title: 'Upload cancelado',
                    showConfirmButton: false,
                    timer: 1500
                });
            });

            xhr.send(formData);
        }
    });
}


const btnFilter = document.getElementById('filter');
const modalFilter = document.getElementById('modalFilter');


btnFilter.addEventListener('click', function (e) {
    e.stopPropagation(); // impede que o clique no botão feche o modal
    modalFilter.classList.add('active');

    const rect = btnFilter.getBoundingClientRect();
    modalFilter.style.left = `${rect.left + (rect.width / 2) - (modalFilter.offsetWidth / 2)}px`;
    modalFilter.style.top = `${rect.bottom + 5}px`; // 5px de espaçamento

})

// Fecha modal ao clicar fora ou pressionar Esc
document.addEventListener('click', function (e) {
    if (modalFilter.classList.contains('active') && !modalFilter.contains(e.target) && e.target !== btnFilter) {
        modalFilter.classList.remove('active');
        // remove seleção dos outros
        document.querySelectorAll('.dropdown-content.show').forEach(c => {
            c.classList.remove('show');
        });
    }
});


['click', 'touchstart', 'keydown'].forEach(eventType => {
    window.addEventListener(eventType, function (event) {
        // Fecha os modais ao clicar fora ou pressionar Esc
        if (eventType === 'keydown' && event.key !== 'Escape') return;

        // if (event.target == cardModal || (eventType === 'keydown' && event.key === 'Escape')) {
        //     cardModal.classList.remove('active');
        // }
        // if (!cardModal.querySelector('.modal-content').contains(event.target)) {
        //     cardModal.classList.remove('active');
        // }
    });
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        if (modalFilter.classList.contains('active')) {
            modalFilter.classList.remove('active');
        }
        if (cardModal.classList.contains('active')) {
            cardModal.classList.remove('active');
        }
    }
});

document.querySelectorAll('.dropbtn').forEach(btn => {
    btn.addEventListener('click', function (e) {
        e.stopPropagation();

        // Fecha todos antes
        document.querySelectorAll('.dropdown-content').forEach(dc => dc.classList.remove('show'));

        // Pega o dropdown-content mais próximo do botão clicado
        const dropdown = this.closest('.dropdown').querySelector('.dropdown-content');
        dropdown.classList.toggle('show');
    });
});
