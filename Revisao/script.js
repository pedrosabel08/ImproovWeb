document.addEventListener("DOMContentLoaded", function () {

    fetchTarefas();

});

function revisarTarefa(idfuncao_imagem, nome_colaborador, imagem_nome, nome_funcao, colaborador_id, id_slack, isChecked) {
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
                colaborador_id: colaborador_id,
                id_slack: id_slack
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

function filtrarTarefas() {
    const select = document.getElementById('nome_funcao');
    const valorSelecionado = select.value; // Obtém o valor selecionado no select

    // Recarrega as tarefas e exibe apenas as que correspondem ao filtro
    fetchTarefas(valorSelecionado);
}


// Função para buscar tarefas de revisão
async function fetchTarefas(filtro = 'Todos', status = 'Em aprovação') {
    try {
        const response = await fetch(`atualizar.php?status=${status}`);
        if (!response.ok) {
            throw new Error("Erro ao buscar as tarefas.");
        }

        const data = await response.json();

        // Filtra as tarefas com base no filtro
        const tarefasFiltradas = data.filter(tarefa => {
            return filtro === 'Todos' || tarefa.nome_funcao === filtro;
        });

        // Exibe as tarefas
        exibirTarefas(tarefasFiltradas);

        // Conta o número de revisões por tipo de função
        const revisoesPorFuncao = {};

        tarefasFiltradas.forEach(tarefa => {
            const funcao = tarefa.nome_funcao;
            revisoesPorFuncao[funcao] = revisoesPorFuncao[funcao] ? revisoesPorFuncao[funcao] + 1 : 1;
        });

        // Exibe a contagem por função
        const contagemAltDiv = document.getElementById('contagem_alt');
        contagemAltDiv.innerHTML = ''; // Limpa o conteúdo anterior

        // Adiciona as contagens na div
        for (const funcao in revisoesPorFuncao) {
            const contagem = revisoesPorFuncao[funcao];
            contagemAltDiv.innerHTML += `<p>${funcao}: ${contagem}</p>`;
        }

    } catch (error) {
        console.error("Erro:", error);
        Toastify({
            text: "Ocorreu um erro ao carregar as tarefas.",
            duration: 3000,
            backgroundColor: "red",
            close: true,
            gravity: "top",
            position: "right"
        }).showToast();
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

// Função para exibir as tarefas
function exibirTarefas(tarefas) {
    const container = document.querySelector('.container');
    container.innerHTML = ''; // Limpa o conteúdo da página antes de exibir as novas tarefas

    if (tarefas.length > 0) {
        tarefas.forEach(tarefa => {
            const taskItem = document.createElement('div');
            taskItem.classList.add('task-item');
            taskItem.setAttribute('onclick', `historyAJAX(${tarefa.idfuncao_imagem}, '${tarefa.nome_funcao}', '${tarefa.imagem_nome}', '${tarefa.nome_colaborador}', '${tarefa.id_slack}')`);

            const imageContainer = document.getElementById('imagens');
            imageContainer.innerHTML = ''; // Limpa as imagens anteriores

            taskItem.innerHTML = `
                <div class="task-info">
                    <h3>${tarefa.nome_funcao}</h3><span>${tarefa.nome_colaborador}</span>
                    <p>${tarefa.imagem_nome}</p>
                    <p>${tarefa.status_novo}</p>
                    <p>${formatarDataHora(tarefa.data_aprovacao)}</p>       
                </div>
            `;
            container.appendChild(taskItem);
        });
    } else {
        container.innerHTML = '<p style="text-align: center; color: #888;">Não há tarefas de revisão no momento.</p>';
    }
}

document.getElementById('nome_funcao').addEventListener('change', filtrarTarefas);
const modalComment = document.getElementById('modalComment');

const idusuario = parseInt(localStorage.getItem('idusuario')); // Obtém o idusuario do localStorage
const idcolaborador = parseInt(localStorage.getItem('idcolaborador')); // Obtém o idusuario do localStorage


function historyAJAX(idfuncao_imagem, funcao_nome, imagem_nome, colaborador_nome, id_slack) {
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
            const sidebarDireita = document.querySelector(".sidebar-direita");

            sidebarDireita.classList.add('hidden');

            const { historico, imagens } = response;

            historico.forEach(historico => {

                if ([1, 2, 9].includes(idusuario)) { // Verifica se o idusuario é 1, 2 ou 9
                    document.getElementById('buttons-task').innerHTML = `
                        <button class="action-btn tooltip" id="add_obs" onclick="addObservacao(${historico.id})" data-tooltip="Adicionar Observação">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                        <button class="action-btn tooltip" id="check" data-tooltip="Aprovar" onclick="revisarTarefa(${historico.funcao_imagem_id}, '${historico.colaborador_nome}', '${historico.imagem_nome}', '${historico.nome_funcao}', '${historico.colaborador_id}', '${id_slack}', true)">
                            <i class="fa-solid fa-check"></i>
                        </button>
                        <button class="action-btn tooltip" id="xmark" data-tooltip="Rejeitar" onclick="revisarTarefa(${historico.funcao_imagem_id}, '${historico.colaborador_nome}', '${historico.imagem_nome}', '${historico.nome_funcao}', '${historico.colaborador_id}', '${id_slack}', false)">
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

            imagens.forEach(img => {
                const wrapper = document.createElement('div');
                wrapper.className = 'imageWrapper';

                const imgElement = document.createElement('img');
                imgElement.src = `../${img.imagem}`;
                imgElement.alt = img.imagem;
                imgElement.className = 'image';
                imgElement.setAttribute('data-id', img.id);

                imgElement.addEventListener('click', (event) => {
                    mostrarImagemCompleta(imgElement.src, img.id);
                });

                wrapper.appendChild(imgElement);
                imageContainer.appendChild(wrapper);

            });

        })
        .catch(error => console.error("Erro ao buscar dados:", error));
}


let ap_imagem_id = null; // Variável para armazenar o ID da imagem atual

function mostrarImagemCompleta(src, id) {
    ap_imagem_id = id; // Armazena o id da imagem clicada
    const imagemCompletaDiv = document.getElementById("imagem_completa");
    imagemCompletaDiv.innerHTML = '';

    const imgElement = document.createElement("img");
    imgElement.src = src;
    imgElement.style.maxWidth = "100%";
    imgElement.style.borderRadius = "10px";
    imgElement.id = "imagem_atual";

    imagemCompletaDiv.appendChild(imgElement);

    renderComments(id);


    imgElement.addEventListener('click', async function (event) {

        if (![1, 2, 9].includes(idusuario)) {
            return;
        }
        const rect = imgElement.getBoundingClientRect();
        const relativeX = ((event.clientX - rect.left) / rect.width) * 100;
        const relativeY = ((event.clientY - rect.top) / rect.height) * 100;

        const commentText = prompt("Digite seu comentário:");
        if (commentText) {
            const comentario = { ap_imagem_id, x: relativeX, y: relativeY, texto: commentText, responsavel: idcolaborador };

            console.log('Comentário:', comentario);

            try {
                const response = await fetch('salvar_comentario.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(comentario)
                });

                const result = await response.json();

                if (result.sucesso) {
                    addComment(relativeX, relativeY, commentText);

                    alert('Comentário salvo com sucesso!');
                } else {
                    alert('Erro ao salvar o comentário.');
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
                alert('Ocorreu um erro ao tentar salvar o comentário.');
            }
        }
    });
}

function addComment(x, y, text) {
    const imagemCompletaDiv = document.getElementById("imagem_completa");

    // Cria o div do comentário
    const commentDiv = document.createElement('div');
    commentDiv.classList.add('comment');
    commentDiv.innerText = text;
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
    
    const sidebarDireita = document.querySelector(".sidebar-direita");

    sidebarDireita.classList.remove('hidden');

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

        if (![1, 2, 9].includes(idusuario)) {
            const commentActions = commentCard.querySelector('.comment-actions');
            if (commentActions) {
                commentActions.classList.add('hidden'); // Adiciona a classe hidden
            }
        }


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
            alert('Comentário atualizado com sucesso!');
        } else {
            alert('Erro ao atualizar o comentário.');
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
            alert('Comentário excluído com sucesso!');

            renderComments(ap_imagem_id); // Atualiza a lista de comentários
        } else {
            alert('Erro ao excluir o comentário.');
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

