let idImagemSelecionada = null;
let sortableInstances = [];
const selectedCards = new Set();

const STATUS_COLUMNS = [
    { label: 'Não iniciado', key: 'nao-iniciado' },
    { label: 'Em andamento', key: 'em-andamento' },
    { label: 'Em aprovação', key: 'em-aprovacao' },
    { label: 'Finalizado', key: 'finalizado' }
];

function normalizarStatus(status) {
    return (status || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

function getStatusKey(status) {
    const normalizado = normalizarStatus(status);
    if (normalizado === 'nao iniciado') return 'nao-iniciado';
    if (normalizado === 'em andamento') return 'em-andamento';
    if (normalizado === 'em aprovacao') return 'em-aprovacao';
    if (normalizado === 'finalizado') return 'finalizado';
    return 'nao-iniciado';
}

function getFilters() {
    return {
        status: document.getElementById('filtro-status').value,
        obra_id: document.getElementById('filtro-obra').value,
        colaborador_id: document.getElementById('filtro-colaborador').value,
        busca: document.getElementById('filtro-busca').value.trim()
    };
}

function montarQueryString(filtros) {
    const params = new URLSearchParams();
    Object.entries(filtros).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            params.append(key, value);
        }
    });
    return params.toString();
}

function limparSelecao() {
    selectedCards.clear();
    document.querySelectorAll('.imagem-card.selected').forEach(card => card.classList.remove('selected'));
}

function preencherFiltros(filtros) {
    const selectObra = document.getElementById('filtro-obra');
    const selectColab = document.getElementById('filtro-colaborador');
    const obraAtual = selectObra.value;
    const colabAtual = selectColab.value;

    selectObra.innerHTML = '<option value="">Todas</option>';
    (filtros?.obras || []).forEach(obra => {
        const option = document.createElement('option');
        option.value = obra.id;
        option.textContent = obra.nome;
        selectObra.appendChild(option);
    });
    if (obraAtual) {
        selectObra.value = obraAtual;
    }

    selectColab.innerHTML = '<option value="">Todos</option>';
    (filtros?.colaboradores || []).forEach(colab => {
        const option = document.createElement('option');
        option.value = colab.id;
        option.textContent = colab.nome;
        selectColab.appendChild(option);
    });
    if (colabAtual) {
        selectColab.value = colabAtual;
    }
}

function agruparPorObra(items) {
    return items.reduce((acc, item) => {
        const key = `${item.obra_id}`;
        if (!acc[key]) {
            acc[key] = { obra_nome: item.obra_nome, items: [] };
        }
        acc[key].items.push(item);
        return acc;
    }, {});
}

function criarCard(item) {
    const card = document.createElement('div');
    card.className = 'imagem-card';
    card.dataset.imagemId = item.imagem_id;

    if (!item.colaborador_id) {
        card.classList.add('sem-colaborador');
    }

    card.innerHTML = `
        <div class="card-title">${item.imagem_nome}</div>
        <div class="card-sub">${item.obra_nome}</div>
        <div class="card-meta">Colab: ${item.colaborador_nome || '—'}</div>
        <div class="card-meta">Prazo: ${item.prazo || '—'}</div>
    `;

    card.addEventListener('click', (event) => {
        const imagemId = String(item.imagem_id);
        if (event.ctrlKey || event.metaKey) {
            event.preventDefault();
            if (selectedCards.has(imagemId)) {
                selectedCards.delete(imagemId);
                card.classList.remove('selected');
            } else {
                selectedCards.add(imagemId);
                card.classList.add('selected');
            }
            return;
        }

        limparSelecao();
        abrirModal(item.imagem_id);
    });

    return card;
}

function renderKanban(items) {
    STATUS_COLUMNS.forEach(({ key }) => {
        const container = document.getElementById(`kanban-${key}`);
        if (container) {
            container.innerHTML = '';
        }
    });

    const obraFiltrada = document.getElementById('filtro-obra').value !== '';

    STATUS_COLUMNS.forEach(({ label, key }) => {
        const container = document.getElementById(`kanban-${key}`);
        if (!container) return;

        const itensColuna = items.filter(item => getStatusKey(item.status_funcao) === key);
        const countId = `count-${key}`;
        const countElement = document.getElementById(countId);
        if (countElement) {
            countElement.textContent = String(itensColuna.length);
        }

        if (obraFiltrada) {
            const grupos = agruparPorObra(itensColuna);
            Object.values(grupos).forEach(grupo => {
                const groupContainer = document.createElement('div');
                groupContainer.className = 'obra-group';
                groupContainer.innerHTML = `<div class="obra-title">${grupo.obra_nome}</div>`;

                const list = document.createElement('div');
                list.className = 'cards-list';
                grupo.items.forEach(item => list.appendChild(criarCard(item)));
                groupContainer.appendChild(list);
                container.appendChild(groupContainer);
            });
            return;
        }

        const list = document.createElement('div');
        list.className = 'cards-list';
        itensColuna.forEach(item => list.appendChild(criarCard(item)));
        container.appendChild(list);
    });

    inicializarDragAndDrop();
}

function inicializarDragAndDrop() {
    sortableInstances.forEach(instance => instance.destroy());
    sortableInstances = [];

    document.querySelectorAll('.cards-list').forEach(list => {
        const instance = new Sortable(list, {
            group: 'alteracao-kanban',
            animation: 120,
            ghostClass: 'drag-ghost',
            onStart: (evt) => {
                const draggedId = evt.item?.dataset?.imagemId;
                if (draggedId && !selectedCards.has(draggedId)) {
                    limparSelecao();
                    selectedCards.add(draggedId);
                    evt.item.classList.add('selected');
                }
            },
            onEnd: (evt) => {
                const coluna = evt.to.closest('.kanban-column');
                const statusDestino = coluna?.dataset?.status;
                if (!statusDestino) {
                    recarregarAlteracao();
                    return;
                }

                const draggedId = evt.item?.dataset?.imagemId;
                const ids = selectedCards.size > 0 && draggedId && selectedCards.has(draggedId)
                    ? Array.from(selectedCards)
                    : draggedId ? [draggedId] : [];

                if (ids.length === 0) {
                    recarregarAlteracao();
                    return;
                }

                atualizarStatusLote(ids, statusDestino);
            }
        });

        sortableInstances.push(instance);
    });
}

function atualizarStatusLote(ids, statusDestino) {
    const atribuirLogado = normalizarStatus(statusDestino) === 'em andamento';

    fetch('updateStatusLote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            imagem_ids: ids,
            status: statusDestino,
            atribuir_logado: atribuirLogado
        })
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                Toastify({
                    text: data.message || 'Erro ao atualizar status.',
                    duration: 3200,
                    gravity: 'top',
                    position: 'left',
                    backgroundColor: 'red'
                }).showToast();
                recarregarAlteracao();
                return;
            }

            Toastify({
                text: 'Status atualizado com sucesso!',
                duration: 2500,
                gravity: 'top',
                position: 'left',
                backgroundColor: 'green'
            }).showToast();

            recarregarAlteracao();
        })
        .catch(() => {
            Toastify({
                text: 'Erro ao atualizar status.',
                duration: 3200,
                gravity: 'top',
                position: 'left',
                backgroundColor: 'red'
            }).showToast();
            recarregarAlteracao();
        });
}

function recarregarAlteracao() {
    limparSelecao();
    const filtros = getFilters();
    const query = montarQueryString(filtros);

    fetch(`getAlteracao.php${query ? `?${query}` : ''}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Erro ao carregar dados.');
            }

            preencherFiltros(data.filtros);
            renderKanban(data.items || []);
        })
        .catch(error => {
            console.error('Erro ao carregar Kanban:', error);
        });
}

function abrirModal(idimagem) {
    document.getElementById('form-edicao').style.display = 'flex';
    atualizarModal(idimagem);
    idImagemSelecionada = idimagem;
}

function limparCampos() {
    document.getElementById('campoNomeImagem').textContent = '';
    document.getElementById('status_alteracao').value = '';
    document.getElementById('prazo_alteracao').value = '';
    document.getElementById('obs_alteracao').value = '';
    document.getElementById('opcao_alteracao').value = '';
}

function atualizarModal(idImagem) {
    limparCampos();

    fetch(`../buscaLinhaAJAX.php?ajid=${idImagem}`)
        .then(response => response.json())
        .then(response => {
            document.getElementById('form-edicao').style.display = 'flex';

            if (response.funcoes && response.funcoes.length > 0) {
                document.getElementById('campoNomeImagem').textContent = response.funcoes[0].imagem_nome;

                response.funcoes.forEach(function (funcao) {
                    if (funcao.nome_funcao === 'Alteração') {
                        document.getElementById('opcao_alteracao').value = funcao.colaborador_id || '';
                        document.getElementById('status_alteracao').value = funcao.status || 'Não iniciado';
                        document.getElementById('prazo_alteracao').value = funcao.prazo || '';
                        document.getElementById('obs_alteracao').value = funcao.observacao || '';
                    }
                });
            }
        })
        .catch(error => console.error('Erro ao buscar dados da linha:', error));
}

document.getElementById('salvar_funcoes').addEventListener('click', function (event) {
    event.preventDefault();

    if (!idImagemSelecionada) {
        Toastify({
            text: 'Nenhuma imagem selecionada',
            duration: 3000,
            close: true,
            gravity: 'top',
            position: 'left',
            backgroundColor: 'red',
            stopOnFocus: true,
        }).showToast();
        return;
    }

    const dados = {
        imagem_id: idImagemSelecionada,
        funcao_id: 6,
        colaborador_id: document.getElementById('opcao_alteracao').value || '',
        status: document.getElementById('status_alteracao').value || '',
        prazo: document.getElementById('prazo_alteracao').value || '',
        observacao: document.getElementById('obs_alteracao').value || '',
    };

    $.ajax({
        type: 'POST',
        url: '../insereFuncao.php',
        data: dados,
        success: function () {
            Toastify({
                text: 'Dados salvos com sucesso!',
                duration: 3000,
                close: true,
                gravity: 'top',
                position: 'left',
                backgroundColor: 'green',
                stopOnFocus: true,
            }).showToast();
            document.getElementById('form-edicao').style.display = 'none';
            recarregarAlteracao();
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('Erro ao salvar dados: ' + textStatus, errorThrown);
            Toastify({
                text: 'Erro ao salvar dados.',
                duration: 3000,
                close: true,
                gravity: 'top',
                position: 'left',
                backgroundColor: 'red',
                stopOnFocus: true,
            }).showToast();
        },
    });
});

document.getElementById('btn-aplicar-filtros').addEventListener('click', recarregarAlteracao);
document.getElementById('btn-limpar-filtros').addEventListener('click', () => {
    document.getElementById('filtro-status').value = '';
    document.getElementById('filtro-obra').value = '';
    document.getElementById('filtro-colaborador').value = '';
    document.getElementById('filtro-busca').value = '';
    recarregarAlteracao();
});

const form_edicao = document.getElementById('form-edicao');

window.addEventListener('touchstart', function (event) {
    if (event.target == form_edicao) {
        form_edicao.style.display = 'none';
    }
});

['click', 'touchstart', 'keydown'].forEach(eventType => {
    window.addEventListener(eventType, function (event) {
        if (eventType === 'keydown' && event.key !== 'Escape') return;

        if (event.target == form_edicao || (eventType === 'keydown' && event.key === 'Escape')) {
            form_edicao.style.display = 'none';
        }
    });
});

recarregarAlteracao();