// document.getElementById('showMenu').addEventListener('click', function () {
//     const menu2 = document.getElementById('menu2');
//     menu2.classList.toggle('hidden');
// });

// window.addEventListener('click', function (event) {
//     const menu2 = document.getElementById('menu2');
//     const button = document.getElementById('showMenu');

//     if (!button.contains(event.target) && !menu2.contains(event.target)) {
//         menu2.classList.add('hidden');
//     }
// });
carregarDados();

carregarEventosEntrega();

const data = new Date();

// Pega o mês abreviado em pt-BR (ex: set, out, nov...)
let mes = data.toLocaleDateString('pt-BR', { month: 'short' });
mes = mes.charAt(0).toUpperCase() + mes.slice(1).replace('.', ''); // Capitaliza e remove ponto

const dia = data.getDate();
const ano = data.getFullYear();

const formatted = `${mes} ${dia}, ${ano}`;

document.querySelector('#date span').textContent = formatted;

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

            const colaboradorId = localStorage.getItem("idcolaborador");
            if (colaboradorId === '1' || colaboradorId === '9' || colaboradorId === '21') {
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
    const { id, descricao, tipo_evento } = event;
    const normalizedTitle = (descricao || '').toUpperCase().trim();

    if (normalizedTitle.includes('R00')) {
        return { backgroundColor: '#1cf4ff', color: '#000000' };
    }
    if (normalizedTitle.includes('R01')) {
        return { backgroundColor: '#ff6200', color: '#000000' };
    }
    if (normalizedTitle.includes('R02')) {
        return { backgroundColor: '#ff3c00', color: '#000000' };
    }
    if (normalizedTitle.includes('R02')) {
        return { backgroundColor: '#ff0000', color: '#000000' };
    }
    if (normalizedTitle.includes('EF')) {
        return { backgroundColor: '#0dff00', color: '#000000' };
    }

    // Se não encontrou no título, usa o tipoEvento
    switch (tipo_evento) {
        case 'Reunião':
            return { backgroundColor: '#ffd700', color: '#000000' };
        case 'Entrega':
            return { backgroundColor: '#ff9f89', color: '#000000' };
        case 'Arquivos':
            return { backgroundColor: '#90ee90', color: '#000000' };
        case 'Outro':
            return { backgroundColor: '#87ceeb', color: '#000000' };
        case 'P00':
            return { backgroundColor: '#ffc21c', color: '#000000' };
        case 'R00':
            return { backgroundColor: '#1cf4ff', color: '#000000' };
        case 'R01':
            return { backgroundColor: '#ff6200', color: '#ffffff' };
        case 'R02':
            return { backgroundColor: '#ff3c00', color: '#ffffff' };
        case 'R03':
            return { backgroundColor: '#ff0000', color: '#ffffff' };
        case 'EF':
            return { backgroundColor: '#0dff00', color: '#000000' };
        case 'HOLD':
            return { backgroundColor: '#ff0000', color: '#ffffff' };
        case 'TEA':
            return { backgroundColor: '#f7eb07', color: '#000000' };
        case 'REN':
            return { backgroundColor: '#0c9ef2', color: '#ffffff' };
        case 'APR':
            return { backgroundColor: '#0c45f2', color: '#ffffff' };
        case 'APP':
            return { backgroundColor: '#7d36f7', color: '#ffffff' };
        case 'RVW':
            return { backgroundColor: 'green', color: '#ffffff' };
        case 'OK':
            return { backgroundColor: 'cornflowerblue', color: '#ffffff' };
        case 'Pós-Produção':
            return { backgroundColor: '#e3f2fd', color: '#000000' };
        case 'Finalização':
            return { backgroundColor: '#e8f5e9', color: '#000000' };
        case 'Modelagem':
            return { backgroundColor: '#fff3e0', color: '#000000' };
        case 'Caderno':
            return { backgroundColor: '#fce4ec', color: '#000000' };
        case 'Composição':
            return { backgroundColor: '#f9ffc6', color: '#000000' };
        default:
            return { backgroundColor: '#d3d3d3', color: '#000000' };
    }
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
            events: events, // Usa os eventos já formatados corretamente
            eventDidMount: function (info) {
                const eventProps = {
                    id: info.event.id,
                    descricao: info.event.title || '', // título do evento (pode ser usado como descrição)
                    tipo_evento: info.event.extendedProps.tipo_evento || ''
                };

                const colors = getEventColors(eventProps);

                info.el.style.backgroundColor = colors.backgroundColor;
                info.el.style.color = colors.color;
                info.el.style.borderColor = colors.backgroundColor;
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

                document.getElementById('eventId').value = '';
                document.getElementById('eventTitle').value = '';
                document.getElementById('eventDate').value = formattedDate;
                document.getElementById('eventModal').style.display = 'flex';

            },

            eventClick: function (info) {
                const clickedDate = new Date(info.event.start);
                const formattedDate = clickedDate.toISOString().split('T')[0];


                document.getElementById('eventId').value = info.event.id;
                document.getElementById('eventTitle').value = info.event.title;
                document.getElementById('eventDate').value = formattedDate;
                document.getElementById('eventModal').style.display = 'flex';
            },

            eventDrop: function (info) {
                const event = info.event;
                updateEvent(event);
            }
        });

        fullCalendar.render();
    } else {
        fullCalendar.refetchEvents();
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

document.getElementById('eventForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const id = document.getElementById('eventId').value;
    const title = document.getElementById('eventTitle').value;
    const start = document.getElementById('eventDate').value;
    const type = document.getElementById('eventType').value;
    const obraId = document.getElementById('obra_calendar').value;

    if (id) {
        fetch('./Dashboard/Calendario/eventoController.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, title, start, type })
        })
            .then(res => res.json())
            .then(res => {
                if (res.error) throw new Error(res.message);
                closeEventModal(); // ✅ fecha o modal após excluir
                showToast(res.message, 'update'); // para PUT
            })
            .catch(err => showToast(err.message, 'error'));
    } else {
        fetch('./Dashboard/Calendario/eventoController.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, start, type, obra_id: obraId })
        })
            .then(res => res.json())
            .then(res => {
                if (res.error) throw new Error(res.message);
                closeEventModal(); // ✅ fecha o modal após excluir
                showToast(res.message, 'create'); // para POST
            })
            .catch(err => showToast(err.message, 'error'));
    }
});

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

['click', 'touchstart', 'keydown'].forEach(eventType => {
    window.addEventListener(eventType, function (event) {
        if (event.target == eventModal || (eventType === 'keydown' && event.key === 'Escape')) {
            eventModal.style.display = "none";
        }
    });
});



// const idusuario = 1;
// const idcolaborador = 1;
function carregarDados(colaboradorId = 14) {

    let url = `getFuncoesPorColaborador.php?colaborador_id=${colaboradorId}`;

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

                    // Chama o tratamento
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
        else if (status === 'HOLD')
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

        if (tipo === 'imagem') {
            // lógica específica para imagem
            if (status === "Não iniciado") {
                const statusAnterior = item.status_funcao_anterior || "";
                if (["Aprovado", "Finalizado", "Aprovado com ajustes"].includes(statusAnterior)) {
                    bolinhaHTML = `<span class="bolinha verde"></span>`;
                    liberado = "1";
                } else {
                    bolinhaHTML = `<span class="bolinha vermelho"></span>`;
                    liberado = "0";
                }
            }
        } else {
            // lógica para tarefas criadas
            bolinhaHTML = '';
            liberado = '1'; // sempre liberado
        }

        // Cria card
        const card = document.createElement('div');
        card.className = `kanban-card ${tipoClasse}`; // só a classe base

        card.setAttribute('data-id', `${item.idfuncao_imagem}`);
        card.setAttribute('data-id-imagem', `${item.imagem_id}`);
        card.setAttribute('liberado', liberado);

        // adiciona bloqueado se necessário
        if (liberado === "0") {
            card.classList.add("bloqueado");
        }

        card.innerHTML = `
                    <div class="header-kanban">
                        <span class="priority ${item.prioridade || 'medium'}">
                            ${item.prioridade || 'Medium'}
                        </span>
                        ${bolinhaHTML}
                    </div>
                        <h5>${titulo || '-'}</h5>
                        <p>${subtitulo || '-'}</p>
                    <div class="card-footer">
                        <span class="date"><i class="fa-regular fa-calendar"></i> ${item.prazo ? formatarData(item.prazo) : '-'}</span>
                    </div>
                    <div class="card-log">
                        <span class="date tooltip ${getTempoClass(item.tempo_calculado, mediaFuncao)}" data-tooltip="${formatarDuracao(mediaFuncao)}">
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


        // Evento de clique
        card.addEventListener('click', () => {

            if (tipo === 'imagem') {
                // remove seleção dos outros
                document.querySelectorAll('.kanban-card.selected').forEach(c => {
                    c.classList.remove('selected');
                });

                // adiciona ao clicado
                card.classList.add('selected');
            } else {
                console.log("Tarefa selecionada:", item.id);
                // aqui pode abrir outro modal para editar tarefa
            }
        });

        // Atributos para filtros
        card.dataset.obra_nome = item.nomenclatura || '';      // nome da obra
        card.dataset.funcao_nome = item.nome_funcao || '';  // nome da função
        card.dataset.status = status;                       // status normalizado


        coluna.appendChild(card);
    }


    // Adiciona tarefas criadas
    if (data.tarefas) {
        data.tarefas.forEach(item => criarCard(item, 'criada', {}));
    }

    // Adiciona funções (tarefas de imagem)
    if (data.funcoes) {
        data.funcoes.forEach(item => criarCard(item, 'imagem', data.media_tempo_em_andamento));
    }

    // Atualiza contagem de tarefas
    Object.keys(statusMap).forEach(status => {
        const col = document.getElementById(statusMap[status]);
        const count = col.querySelectorAll('.kanban-card').length;
        col.querySelector('.task-count').textContent = count;
    });

    preencherFiltros()

}

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

// Aplica os filtros selecionados
function aplicarFiltros() {
    const obrasSelecionadas = Array.from(document.querySelectorAll('#filtroObra input:checked')).map(el => el.value).filter(v => v);
    const funcoesSelecionadas = Array.from(document.querySelectorAll('#filtroFuncao input:checked')).map(el => el.value).filter(v => v);
    const statusSelecionados = Array.from(document.querySelectorAll('#filtroStatus input:checked')).map(el => el.value).filter(v => v);

    document.querySelectorAll('.kanban-card').forEach(card => {
        let mostrar = true;

        if (obrasSelecionadas.length && !obrasSelecionadas.includes(card.dataset.obra_nome)) mostrar = false;
        if (funcoesSelecionadas.length && !funcoesSelecionadas.includes(card.dataset.funcao_nome)) mostrar = false;
        if (statusSelecionados.length && !statusSelecionados.includes(card.dataset.status)) mostrar = false;

        card.style.display = mostrar ? 'block' : 'none';
    });
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

    fetch('addTask.php', {
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
                carregarDados();
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

    cardSelecionado.dataset.prazo = modalPrazo.value;
    cardSelecionado.dataset.observacao = modalObs.value;

    console.log('Salvo:', {
        prazo: modalPrazo.value,
        observacao: modalObs.value,
        cardId: cardSelecionado.dataset.id,
        novaColuna: cardSelecionado.closest('.kanban-box').id
    });

    // Aqui você pode enviar via fetch/AJAX para atualizar no banco
    cardModal.classList.remove('active');
    cardSelecionado = null;
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
const dropArea = document.getElementById('drop-area');
const fileInput = document.getElementById('fileElem');
const fileList = document.getElementById('fileList');
let arquivosFinais = [];
let dataIdFuncoes = [];


// Inicializa Sortable nas colunas
const colunas = document.querySelectorAll('.kanban-box .content');
colunas.forEach(col => {
    new Sortable(col, {
        group: 'kanban',
        animation: 150,
        ghostClass: 'sortable-ghost',
        filter: ".bloqueado",      // não deixa arrastar cards bloqueados
        onMove: function (evt) {
            // Impede mover bloqueados
            if (evt.related && evt.dragged.classList.contains("bloqueado")) {
                return false;
            }

            // Impede soltar dentro da coluna #ajuste
            if (evt.to.closest('.kanban-box')?.id === "ajuste" || evt.from.closest('.kanban-box')?.id === "to-do") {
                return false;
            }
        },
        onEnd: (evt) => {
            const card = evt.item;                                // Card movido
            const deColuna = evt.from.closest('.kanban-box');     // coluna origem
            const novaColuna = evt.to.closest('.kanban-box');     // coluna destino
            const novoIndex = evt.newIndex;

            if (card.dataset.liberado === "0") {
                // Se por algum motivo tentou, não deixa abrir modal
                evt.from.appendChild(card); // devolve pra origem
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

                // Preenche os campos comuns
                modalPrazo.value = card.dataset.prazo || '';
                modalObs.value = card.dataset.observacao || '';

                // Reset modal: mostra tudo inicialmente
                document.querySelector('.modalPrazo').style.display = 'flex';
                document.querySelector('.modalObs').style.display = 'flex';
                document.querySelector('.modalUploads').style.display = 'flex';

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
                        break;
                    case 'in-progress':
                        // Apenas observação e botões
                        document.querySelector('.modalUploads').style.display = 'none';
                        break;
                    case 'in-review': // "Em aprovação"
                        // Mostra ambos inputs de arquivo (prévia e arquivo final)
                        document.querySelector('.modalPrazo').style.display = 'none';
                        document.querySelector('.modalObs').style.display = 'none';
                        document.querySelector('.modalUploads').style.display = 'flex';
                        break;
                    case 'done': // "Finalizado"
                        // Mostra prazo, observação e botões
                        document.querySelector('.modalPrazo').style.display = 'flex';
                        document.querySelector('.modalObs').style.display = 'flex';
                        document.querySelector('.modalUploads').style.display = 'flex';
                        break;
                    default:
                        // padrão: tudo visível
                        break;
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

let imagensSelecionadas = [];


// ENVIO DA PRÉVIA
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
    const campoNomeImagem = titulo;
    formData.append('nome_imagem', campoNomeImagem);

    // Extrai o número inicial antes do ponto
    const numeroImagem = campoNomeImagem.match(/^\d+/)?.[0] || '';
    formData.append('numeroImagem', numeroImagem);

    // Extrai a nomenclatura (primeira palavra com "_", depois do número e ponto)
    const nomenclatura = obra;
    formData.append('nomenclatura', nomenclatura);

    // Extrai a primeira palavra da descrição (depois da nomenclatura)
    const descricaoMatch = campoNomeImagem.match(/^\d+\.\s*[A-Z_]+\s+([^\s]+)/);
    const primeiraPalavra = descricaoMatch ? descricaoMatch[1] : '';
    formData.append('primeiraPalavra', primeiraPalavra);


    for (let [key, value] of formData.entries()) {
        console.log(key, value);
    }

    // const statusSelect = document.getElementById('opcao_status');
    // const statusNome = statusSelect.options[statusSelect.selectedIndex].text.trim();

    // formData.append('status_nome', statusNome);

    // fetch('../uploadArquivos.php', {
    //     method: 'POST',
    //     body: formData
    // })
    //     .then(resp => resp.json())
    //     .then(res => {
    //         Toastify({
    //             text: "Prévia enviada com sucesso!",
    //             duration: 3000,
    //             gravity: "top",
    //             backgroundColor: "#4caf50"
    //         }).showToast();

    //         // Avança para próxima etapa
    //         document.getElementById('etapaPrevia').style.display = 'none';
    //         document.getElementById('etapaFinal').style.display = 'block';
    //         document.getElementById('etapaTitulo').textContent = "2. Envio do Arquivo Final";

    //         Swal.fire({
    //             position: "center",
    //             icon: "success",
    //             title: "Agora adicione o arquivo final",
    //             showConfirmButton: false,
    //             timer: 1500,
    //             didOpen: () => {
    //                 const title = Swal.getTitle();
    //                 if (title) title.style.fontSize = "18px";
    //             }
    //         });


    //     })
    //     .catch(err => {
    //         Toastify({
    //             text: "Erro ao enviar prévia",
    //             duration: 3000,
    //             gravity: "top",
    //             backgroundColor: "#f44336"
    //         }).showToast();
    //     });
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

// Fecha o filtro ao clicar fora
document.addEventListener('click', function (e) {
    if (modalFilter.classList.contains('active') && !modalFilter.contains(e.target) && e.target !== btnFilter) {
        modalFilter.classList.remove('active');
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

// Fecha ao clicar fora
document.addEventListener('click', (e) => {
    // Se o clique NÃO for dentro de um .dropdown, fecha todos
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-content').forEach(dc => dc.classList.remove('show'));
    }
});
