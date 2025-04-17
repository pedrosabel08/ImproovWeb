document.addEventListener("DOMContentLoaded", function () {

    fetchObrasETarefas();

});

function revisarTarefa(idfuncao_imagem, nome_colaborador, imagem_nome, nome_funcao, colaborador_id, isChecked) {
    const actionText = isChecked
        ? "marcar esta tarefa como revisada"
        : "indicar que esta tarefa precisa de alterações";

    const idcolaborador = localStorage.getItem('idcolaborador');

    if (confirm(`Você tem certeza de que deseja ${actionText}?`)) {
        fetch('revisarTarefa.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                idfuncao_imagem: idfuncao_imagem,
                nome_colaborador: nome_colaborador,
                imagem_nome: imagem_nome,
                nome_funcao: nome_funcao,
                isChecked: isChecked,
                responsavel: idcolaborador,
                colaborador_id: colaborador_id
            }),
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error("Erro ao atualizar a tarefa.");
                }
                return response.json();
            })
            .then(data => {
                console.log("Resposta do servidor:", data);

                const message = isChecked
                    ? "Tarefa marcada como revisada com sucesso!"
                    : "Tarefa marcada como necessitando de alterações!";
                const bgColor = isChecked ? "green" : "orange";

                if (data.success) {
                    Toastify({
                        text: message,
                        duration: 3000,
                        backgroundColor: bgColor,
                        close: true,
                        gravity: "top",
                        position: "right"
                    }).showToast();

                    const filtroAtual = document.getElementById('nome_funcao').value;
                    fetchTarefas(filtroAtual);
                } else {
                    Toastify({
                        text: "Falha ao atualizar a tarefa: " + data.message,
                        duration: 3000,
                        backgroundColor: "red",
                        close: true,
                        gravity: "top",
                        position: "right"
                    }).showToast();
                }
            })
            .catch(error => {
                console.error("Erro:", error);
                Toastify({
                    text: "Ocorreu um erro ao processar a solicitação." + error.message,
                    duration: 3000,
                    backgroundColor: "red",
                    close: true,
                    gravity: "top",
                    position: "right"
                }).showToast();
            });
    }
    event.stopPropagation();
}


// Função para alternar a visibilidade dos detalhes da tarefa
function toggleTaskDetails(taskElement) {
    taskElement.classList.toggle('open');
}

let dadosTarefas = [];
let todasAsObras = new Set();
let todosOsColaboradores = new Set();

async function fetchObrasETarefas(status = 'Em aprovação') {
    try {
        const response = await fetch(`atualizar.php?status=${status}`);
        if (!response.ok) throw new Error("Erro ao buscar tarefas");

        dadosTarefas = await response.json();

        todasAsObras = new Set(dadosTarefas.map(t => t.nome_obra));
        todosOsColaboradores = new Set(dadosTarefas.map(t => t.nome_colaborador));

        exibirCardsDeObra(dadosTarefas); // Mostra os cards

    } catch (error) {
        console.error(error);
    }
}

function exibirCardsDeObra(tarefas) {
    const container = document.querySelector('.containerObra');
    container.innerHTML = '';

    // Agrupar tarefas por nome_obra
    const obrasMap = new Map();
    tarefas.forEach(tarefa => {
        if (!obrasMap.has(tarefa.nome_obra)) {
            obrasMap.set(tarefa.nome_obra, []);
        }
        obrasMap.get(tarefa.nome_obra).push(tarefa);
    });

    obrasMap.forEach((tarefasDaObra, nome_obra) => {
        tarefasDaObra.sort((a, b) => new Date(b.data_aprovacao) - new Date(a.data_aprovacao));

        const tarefaComImagem = tarefasDaObra.find(t => t.imagem);
        const imagemPreview = tarefaComImagem ? `../${tarefaComImagem.imagem}` : null;

        const card = document.createElement('div');
        card.classList.add('obra-card');

        // Monta o conteúdo do card com ou sem imagem
        card.innerHTML = `
            ${imagemPreview ? `
            <div class="obra-img-preview">
                <img src="${imagemPreview}" alt="Imagem da obra ${nome_obra}">
            </div>` : ''}
            <div class="obra-info">
                <h3>${nome_obra}</h3>
                <p>${tarefasDaObra.length} tarefas</p>
            </div>
        `;

        card.addEventListener('click', () => {
            filtrarTarefasPorObra(nome_obra);
        });

        container.appendChild(card);
    });
}

function filtrarTarefasPorObra(obraSelecionada) {

    document.getElementById('filtro_obra').value = obraSelecionada;

    // Filtra todas as tarefas da obra
    const tarefasDaObra = dadosTarefas.filter(t => t.nome_obra === obraSelecionada);

    // Atualiza os filtros dinamicamente com base nessa obra
    atualizarFiltrosDinamicos(tarefasDaObra);

    // Captura os novos valores dos selects após atualização
    const colaboradorSelecionado = document.getElementById('filtro_colaborador').value;
    const funcaoSelecionada = document.getElementById('nome_funcao').value;

    // Aplica os filtros adicionais (colaborador e função)
    const tarefasFiltradas = tarefasDaObra.filter(t => {
        const matchColaborador = !colaboradorSelecionado || t.nome_colaborador === colaboradorSelecionado;
        const matchFuncao = funcaoSelecionada === 'Todos' || t.nome_funcao === funcaoSelecionada;
        return matchColaborador && matchFuncao;
    });

    // Exibe as tarefas filtradas
    exibirTarefas(tarefasFiltradas);
}
// Eventos para os filtros
function atualizarFiltrosDinamicos(tarefas) {
    const selectColaborador = document.getElementById('filtro_colaborador');
    const selectFuncao = document.getElementById('nome_funcao');

    // Salva os valores antes de atualizar
    const valorAnteriorColaborador = selectColaborador.value;
    const valorAnteriorFuncao = selectFuncao.value;

    const colaboradores = [...new Set(tarefas.map(t => t.nome_colaborador))];
    const funcoes = [...new Set(tarefas.map(t => t.nome_funcao))];

    // Atualiza select de colaborador
    selectColaborador.innerHTML = '<option value="">Todos</option>';
    colaboradores.forEach(colaborador => {
        const option = document.createElement('option');
        option.value = colaborador;
        option.textContent = colaborador;
        selectColaborador.appendChild(option);
    });

    // Atualiza select de função
    selectFuncao.innerHTML = '<option value="Todos">Todos</option>';
    funcoes.forEach(funcao => {
        const option = document.createElement('option');
        option.value = funcao;
        option.textContent = funcao;
        selectFuncao.appendChild(option);
    });

    // Reatribui os valores anteriores (se ainda existirem nas opções)
    if ([...selectColaborador.options].some(o => o.value === valorAnteriorColaborador)) {
        selectColaborador.value = valorAnteriorColaborador;
    }

    if ([...selectFuncao.options].some(o => o.value === valorAnteriorFuncao)) {
        selectFuncao.value = valorAnteriorFuncao;
    }
}

document.getElementById('filtro_colaborador').addEventListener('change', () => {
    const obraSelecionada = document.getElementById('filtro_obra').value;
    filtrarTarefasPorObra(obraSelecionada);
});

document.getElementById('nome_funcao').addEventListener('change', () => {
    const obraSelecionada = document.getElementById('filtro_obra').value;
    filtrarTarefasPorObra(obraSelecionada);
});

// Função para exibir as tarefas e abastecer os filtros
function exibirTarefas(tarefas) {
    const container = document.querySelector('.containerObra');
    const containerMain = document.querySelector('.container-main');
    containerMain.classList.add('expanded');

    const tarefasObra = document.querySelector('.tarefasObra');
    tarefasObra.classList.remove('hidden');

    const tarefasImagensObra = document.querySelector('.tarefasImagensObra');

    tarefasImagensObra.innerHTML = ''; // Limpa as tarefas anteriores

    if (tarefas.length > 0) {
        tarefas.forEach(tarefa => {
            const taskItem = document.createElement('div');
            taskItem.classList.add('task-item');
            taskItem.setAttribute('onclick', `historyAJAX(${tarefa.idfuncao_imagem}, '${tarefa.nome_funcao}', '${tarefa.imagem_nome}', '${tarefa.nome_colaborador}')`);

            taskItem.innerHTML = `
                <div class="task-info">
                    <h3>${tarefa.nome_funcao}</h3><span>${tarefa.nome_colaborador}</span>
                    <p data-obra="${tarefa.nome_obra}">${tarefa.imagem_nome}</p>
                    <p>${tarefa.status_novo}</p>
                    <p>${formatarDataHora(tarefa.data_aprovacao)}</p>       
                </div>
            `;

            tarefasImagensObra.appendChild(taskItem);
        });
    } else {
        container.innerHTML = '<p style="text-align: center; color: #888;">Não há tarefas de revisão no momento.</p>';
    }
}


function formatarData(data) {
    const [ano, mes, dia] = data.split('-'); // Divide a string no formato 'YYYY-MM-DD'
    return `${dia}/${mes}/${ano}`; // Retorna o formato 'DD/MM/YYYY'
}

function formatarDataHora(data) {
    const date = new Date(data); // Cria um objeto Date a partir da string datetime

    const dia = String(date.getDate()).padStart(2, '0'); // Pega o dia e formata com 2 dígitos
    const mes = String(date.getMonth() + 1).padStart(2, '0'); // Pega o mês e formata com 2 dígitos (mes começa do 0)
    const ano = date.getFullYear(); // Pega o ano
    const horas = String(date.getHours()).padStart(2, '0'); // Pega a hora e formata com 2 dígitos
    const minutos = String(date.getMinutes()).padStart(2, '0'); // Pega os minutos e formata com 2 dígitos

    return `${dia}/${mes}/${ano} ${horas}:${minutos}`; // Retorna o formato desejado
}



const modalComment = document.getElementById('modalComment');

const idusuario = parseInt(localStorage.getItem('idusuario')); // Obtém o idusuario do localStorage


function historyAJAX(idfuncao_imagem, funcao_nome, imagem_nome, colaborador_nome) {
    fetch(`historico.php?ajid=${idfuncao_imagem}`)
        .then(response => response.json())
        .then(response => {

            const titulo = document.getElementById('funcao_nome');
            titulo.textContent = `${colaborador_nome} - ${funcao_nome}`;
            // document.getElementById("id_funcao").value = idfuncao_imagem;
            document.getElementById("imagem_nome").textContent = imagem_nome;
            // document.getElementById("funcao_nome").textContent = funcao_nome;
            // document.getElementById("colaborador_nome").textContent = colaborador_nome;

            // Exibir o modal
            // const modal = document.getElementById('historico_modal');
            // modal.style.display = 'grid';
            const main = document.querySelector('.main');
            main.classList.add('hidden');

            const container_aprovacao = document.querySelector('.container-aprovacao');
            container_aprovacao.classList.remove('hidden');


            const { historico, imagens } = response;

            historico.forEach(historico => {

                if ([1, 2, 9, 20, 3].includes(idusuario)) { // Verifica se o idusuario é 1, 2 ou 9
                    document.getElementById('buttons-task').innerHTML = `
                        <button class="action-btn tooltip" id="add_obs" onclick="addObservacao(${historico.id})" data-tooltip="Adicionar Observação">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                        <button class="action-btn tooltip" id="check" data-tooltip="Aprovar" onclick="revisarTarefa(${historico.funcao_imagem_id}, '${historico.colaborador_nome}', '${historico.imagem_nome}', '${historico.nome_funcao}', '${historico.colaborador_id}', true)">
                            <i class="fa-solid fa-check"></i>
                        </button>
                        <button class="action-btn tooltip" id="xmark" data-tooltip="Rejeitar" onclick="revisarTarefa(${historico.funcao_imagem_id}, '${historico.colaborador_nome}', '${historico.imagem_nome}', '${historico.nome_funcao}', '${historico.colaborador_id}', false)">
                            <i class="fa-solid fa-xmark"></i>
                        </button>`;
                } else {
                    document.getElementById('buttons-task').innerHTML = ''; // Não exibe os botões para outros usuários
                }
            });
            // Renderizar as imagens
            const imageContainer = document.getElementById('imagens');
            imageContainer.innerHTML = ''; // Limpa as imagens anteriores
            const imagemCompletaDiv = document.getElementById("imagem_completa");
            imagemCompletaDiv.innerHTML = '';
            const commentDiv = document.querySelector('.sidebar-direita');
            commentDiv.style.display = 'none';


            const indiceSelect = document.getElementById('indiceSelect');

            // 1. Agrupar imagens por indice_envio
            const imagensAgrupadas = imagens.reduce((acc, img) => {
                if (!acc[img.indice_envio]) {
                    acc[img.indice_envio] = [];
                }
                acc[img.indice_envio].push(img);
                return acc;
            }, {});

            // 2. Popular o <select> com os índices de envio (ordenado desc)
            const indicesOrdenados = Object.keys(imagensAgrupadas).sort((a, b) => b - a);

            // Preenche o select
            indicesOrdenados.forEach(indice => {
                const option = document.createElement('option');
                option.value = indice;
                option.textContent = `Envio ${indice}`;
                indiceSelect.appendChild(option);
            });

            // 3. Evento de mudança no select
            indiceSelect.addEventListener('change', () => {
                const indiceSelecionado = indiceSelect.value;

                // Limpa as imagens anteriores
                imageContainer.innerHTML = '';

                if (indiceSelecionado && imagensAgrupadas[indiceSelecionado]) {
                    imagensAgrupadas[indiceSelecionado].forEach(img => {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'imageWrapper';

                        const imgElement = document.createElement('img');
                        imgElement.src = `../${img.imagem}`;
                        imgElement.alt = img.imagem;
                        imgElement.className = 'image';
                        imgElement.setAttribute('data-id', img.id);

                        imgElement.addEventListener('click', () => {
                            mostrarImagemCompleta(imgElement.src, img.id);
                        });

                        imgElement.addEventListener('contextmenu', (event) => {
                            event.preventDefault();
                            abrirMenuContexto(event.pageX, event.pageY, img.id, imgElement.src);
                        });

                        if (img.has_comments == "1" || img.has_comments === 1) {
                            const notificationDot = document.createElement('div');
                            notificationDot.className = 'notification-dot';
                            notificationDot.textContent = `${img.comment_count}`;
                            wrapper.appendChild(notificationDot);
                        }

                        wrapper.appendChild(imgElement);
                        imageContainer.appendChild(wrapper);
                    });
                }
            });

            // Já seleciona o mais recente e mostra as imagens
            if (indicesOrdenados.length > 0) {
                indiceSelect.value = indicesOrdenados[0]; // pega o mais recente
                indiceSelect.dispatchEvent(new Event('change')); // já mostra as imagens
            }

        })
        .catch(error => console.error("Erro ao buscar dados:", error));
}

function abrirMenuContexto(x, y, id, src) {
    const menu = document.getElementById('menuContexto');

    // Coloca info da imagem (caso precise usar depois)
    menu.setAttribute('data-id', id);
    menu.setAttribute('data-src', src);

    menu.style.top = `${y}px`;
    menu.style.left = `${x}px`;
    menu.style.display = 'block';
}

function excluirImagem() {
    const menu = document.getElementById('menuContexto');
    const idImagem = menu.getAttribute('data-id');

    if (!idImagem) {
        alert("ID da imagem não encontrado!");
        return;
    }

    if (confirm("Tem certeza que deseja excluir esta imagem?")) {
        fetch('excluir_imagem.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${idImagem}`
        })
            .then(response => response.text())
            .then(data => {
                console.log(data);
                // Remove a imagem da tela também, se quiser
                const imgElement = document.querySelector(`img[data-id='${idImagem}']`);
                if (imgElement) {
                    imgElement.parentElement.remove(); // Remove o wrapper da imagem
                }
                // Esconde o menu
                menu.style.display = 'none';
            })
            .catch(error => {
                console.error('Erro ao excluir imagem:', error);
                alert("Erro ao excluir imagem.");
            });
    } else {
        // Fecha o menu caso cancele
        menu.style.display = 'none';
    }
}

document.addEventListener('click', (e) => {
    const menu = document.getElementById('menuContexto');
    if (!menu.contains(e.target)) {
        menu.style.display = 'none';
    }
});



let ap_imagem_id = null; // Variável para armazenar o ID da imagem atual

function mostrarImagemCompleta(src, id) {
    ap_imagem_id = id; // Armazena o id da imagem clicada
    const imagemCompletaDiv = document.getElementById("imagem_completa");
    imagemCompletaDiv.innerHTML = '';
    const commentDiv = document.querySelector('.sidebar-direita');
    commentDiv.style.display = 'block';

    const imgElement = document.createElement("img");
    imgElement.src = src;
    imgElement.style.width = "100%";
    imgElement.style.borderRadius = "10px";
    imgElement.id = "imagem_atual";

    imagemCompletaDiv.appendChild(imgElement);

    renderComments(id);


    imgElement.addEventListener('click', async function (event) {

        if (![1, 2, 9, 20, 3].includes(idusuario)) {
            return;
        }
        const rect = imgElement.getBoundingClientRect();
        const relativeX = ((event.clientX - rect.left) / rect.width) * 100;
        const relativeY = ((event.clientY - rect.top) / rect.height) * 100;

        const commentText = prompt("Digite seu comentário:");
        if (commentText) {

            const comentario = { ap_imagem_id, x: relativeX, y: relativeY, texto: commentText };

            console.log('Comentário:', comentario);

            try {
                const response = await fetch('salvar_comentario.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(comentario)
                });

                const result = await response.json();

                if (result.sucesso) {
                    addComment(relativeX, relativeY);

                    Toastify({
                        text: 'Comentário adicionado com sucesso!',
                        duration: 3000,
                        backgroundColor: 'green',
                        close: true,
                        gravity: "top",
                        position: "left"
                    }).showToast();
                    renderComments(ap_imagem_id); // Atualiza a lista de comentários

                } else {
                    Toastify({
                        text: 'Erro ao salvar comentário!',
                        duration: 3000,
                        backgroundColor: 'red',
                        close: true,
                        gravity: "top",
                        position: "left"
                    }).showToast();
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
                alert('Ocorreu um erro ao tentar salvar o comentário.');
            }
        }
    });
}

function addComment(x, y) {
    const imagemCompletaDiv = document.getElementById("imagem_completa");

    // Cria o div do comentário
    const commentDiv = document.createElement('div');
    commentDiv.classList.add('comment');
    commentDiv.style.left = `${x}%`;
    commentDiv.style.top = `${y}%`;

    imagemCompletaDiv.appendChild(commentDiv);
}

const image = document.getElementById("imagem_atual");


async function renderComments(id) {
    const comentariosDiv = document.querySelector(".comentarios");
    const imagemCompletaDiv = document.getElementById("imagem_completa");
    const response = await fetch(`buscar_comentarios.php?id=${id}`);
    const comentarios = await response.json();


    // Limpa os comentários antigos
    comentariosDiv.innerHTML = '';
    // Limpa os comentários antigos
    imagemCompletaDiv.querySelectorAll('.comment').forEach(comment => comment.remove());

    comentarios.forEach(comentario => {

        // Cria o card do comentário
        const commentCard = document.createElement('div');
        commentCard.classList.add('comment-card');

        commentCard.innerHTML = `
             <div class="comment-header">
                 <div class="comment-number">${comentario.numero_comentario}</div>
                 <div class="comment-user">${comentario.nome_responsavel}</div>
             </div>
        <div class="comment-body" contenteditable="false">${comentario.texto}</div>
             <div class="comment-footer">
                 <div class="comment-date">${comentario.data}</div>
                 <div class="comment-actions">
                     <button class="comment-edit">✏️</button>
                     <button class="comment-delete" onclick='deleteComment(${comentario.id})'>🗑️</button>
                 </div>
             </div>
         `;

        // Adiciona evento ao botão "edit"
        const editButton = commentCard.querySelector('.comment-edit');
        const commentBody = commentCard.querySelector('.comment-body');

        editButton.addEventListener('click', () => {
            // Torna o comment-body editável
            commentBody.setAttribute('contenteditable', 'true');
            commentBody.focus(); // Foca no elemento para edição
        });

        // Adiciona evento para salvar ao perder o foco
        commentBody.addEventListener('blur', () => {
            // Torna o comment-body não editável novamente
            commentBody.setAttribute('contenteditable', 'false');

            // Chama a função updateComment para salvar as alterações
            const novoTexto = commentBody.textContent.trim();
            updateComment(comentario.id, novoTexto);
        });

        // Cria um novo div para cada comentário
        const commentDiv = document.createElement('div');
        commentDiv.classList.add('comment');
        commentDiv.classList.add('tooltip');
        commentDiv.innerText = comentario.numero_comentario;
        commentDiv.style.left = `${comentario.x}%`;
        commentDiv.style.top = `${comentario.y}%`;

        // Adiciona o texto do comentário ao atributo data-tooltip
        commentDiv.setAttribute('data-tooltip', comentario.texto);

        // Adiciona um event listener para detectar o clique no comentário
        commentDiv.addEventListener('click', function () {

            document.querySelectorAll('.comment-number').forEach(number => {
                number.classList.remove('highlight');
            });

            // Adiciona o destaque ao card correspondente
            const commentNumber = document.querySelector(`.comment-card[data-id="${comentario.id}"] .comment-number`);
            if (commentNumber) {
                commentNumber.classList.add('highlight');
                commentNumber.scrollIntoView({ behavior: 'smooth', block: 'center' }); // Rola até o card

            }

        });

        // Adiciona o comentário à imagem
        imagemCompletaDiv.appendChild(commentDiv);
        comentariosDiv.appendChild(commentCard);
        commentCard.setAttribute('data-id', comentario.id);

    });
}

// Função para atualizar o comentário no banco de dados
async function updateComment(commentId, novoTexto) {
    try {
        const response = await fetch('atualizar_comentario.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: commentId, texto: novoTexto })
        });

        const result = await response.json();
        if (result.sucesso) {
            Toastify({
                text: 'Comentário atualizado com sucesso!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
        } else {
            Toastify({
                text: 'Erro ao atualizar comentário!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
        }
    } catch (error) {
        console.error('Erro ao atualizar comentário:', error);
        alert('Ocorreu um erro ao tentar atualizar o comentário.');
    }
}

// Função para excluir o comentário do banco de dados
async function deleteComment(commentId) {
    try {
        const response = await fetch('excluir_comentario.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: commentId })
        });

        const result = await response.json();
        if (result.sucesso) {
            Toastify({
                text: 'Comentário excluído com sucesso!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
            renderComments(ap_imagem_id); // Atualiza a lista de comentários
        } else {
            Toastify({
                text: 'Erro ao excluir comentário!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
        }
    } catch (error) {
        console.error('Erro ao excluir comentário:', error);
        alert('Ocorreu um erro ao tentar excluir o comentário.');
    }
}


const btnBack = document.getElementById('btnBack');
btnBack.addEventListener('click', function () {
    const main = document.querySelector('.main');
    main.classList.remove('hidden');

    const container_aprovacao = document.querySelector('.container-aprovacao');
    container_aprovacao.classList.add('hidden');

    const imagemCompletaDiv = document.getElementById("imagem_completa");
    imagemCompletaDiv.innerHTML = '';

    const comentariosDiv = document.querySelector(".comentarios");
    comentariosDiv.innerHTML = '';
});

// Adiciona o evento para a tecla Esc
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') { // Verifica se a tecla pressionada é Esc
        const main = document.querySelector('.main');
        main.classList.remove('hidden');

        const container_aprovacao = document.querySelector('.container-aprovacao');
        container_aprovacao.classList.add('hidden');

        const imagemCompletaDiv = document.getElementById("imagem_completa");
        imagemCompletaDiv.innerHTML = '';

        const comentariosDiv = document.querySelector(".comentarios");
        comentariosDiv.innerHTML = '';
    }
});

const id_revisao = document.getElementById('id_revisao');

// function addObservacao(id) {
//     const modal = document.getElementById('historico_modal');
//     const idRevisao = document.getElementById('id_revisao');
//     const historicoAdd = modal.querySelector('.historico-add');

//     historicoAdd.classList.toggle('hidden');

//     if (historicoAdd.classList.contains('hidden')) {
//         modal.classList.remove('complete');
//     } else {
//         modal.classList.add('complete');
//     }

//     idRevisao.innerText = `${id}`;
// }

// Inicializa o editor Quill
// var quill = new Quill('#text_obs', {
//     theme: 'snow',  // Tema claro
//     modules: {
//         toolbar: [
//             ['bold', 'italic', 'underline'], // Negrito, itálico, sublinhado
//             [{ 'header': 1 }, { 'header': 2 }], // Títulos
//             [{ 'list': 'ordered' }, { 'list': 'bullet' }], // Listas
//             [{ 'color': [] }, { 'background': [] }], // Cores
//             ['clean'] // Limpar formatação
//         ]
//     }
// });


// const historico_modal = document.getElementById('historico_modal');
// const historicoAdd = historico_modal.querySelector('.historico-add');

// window.addEventListener('click', function (event) {
//     if (event.target == historico_modal) {
//         historico_modal.style.display = "none"
//         historico_modal.classList.remove('complete');
//         historicoAdd.classList.add('hidden');
//     }
// });

// window.addEventListener('touchstart', function (event) {
//     if (event.target == historico_modal) {
//         historico_modal.style.display = "none"
//         historico_modal.classList.remove('complete');
//         historicoAdd.classList.add('hidden');

//     }
// });


// Captura o evento de envio do formulário
// document.getElementById('adicionar_obs').addEventListener('submit', function (event) {
//     event.preventDefault(); // Previne o comportamento padrão do envio do formulário

//     // Exibe um prompt para o usuário digitar o número da revisão
//     const numeroRevisao = document.getElementById('id_revisao').textContent;
//     const idfuncao_imagem = document.getElementById("id_funcao").value;

//     if (numeroRevisao) {
//         // Captura o conteúdo do editor Quill
//         const observacao = quill.root.innerHTML;

//         // Exibe os valores no console (você pode remover esta parte depois)
//         console.log("Número da Revisão: " + numeroRevisao);
//         console.log("Observação: " + observacao);

//         // Envia os dados para o servidor via fetch
//         fetch('atualizar_historico.php', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json'
//             },
//             body: JSON.stringify({
//                 revisao: numeroRevisao,
//                 observacao: observacao
//             })
//         })
//             .then(response => response.json())
//             .then(data => {
//                 // Verifica se a atualização foi bem-sucedida
//                 if (data.success) {
//                     Toastify({
//                         text: 'Observação adicionada com sucesso!',
//                         duration: 3000,
//                         backgroundColor: 'green',
//                         close: true,
//                         gravity: "top",
//                         position: "right"
//                     }).showToast();

//                     historico_modal.classList.remove('complete');
//                     historicoAdd.classList.toggle('hidden');
//                     historyAJAX(idfuncao_imagem)
//                 } else {
//                     Toastify({
//                         text: "Falha ao atualizar a tarefa: " + data.message,
//                         duration: 3000,
//                         backgroundColor: "red",
//                         close: true,
//                         gravity: "top",
//                         position: "right"
//                     }).showToast();
//                 }
//             })
//             .catch(error => {
//                 console.error("Erro ao enviar dados para o servidor:", error);
//                 alert("Ocorreu um erro ao tentar adicionar a observação.");
//             });
//     } else {
//         alert("Número de revisão é obrigatório!");
//     }
// });

