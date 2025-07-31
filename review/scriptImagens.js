const usuario_externo = parseInt(localStorage.getItem('usuario_externo')); // Obtém o idusuario do localStorage

// Função para salvar com tempo de expiração
function salvarUsuario(nome, email) {
    const agora = new Date().getTime();
    const expiraEm = agora + 2 * 60 * 60 * 1000; // 2 horas em milissegundos

    const dados = {
        nome: nome,
        email: email,
        expiracao: expiraEm
    };

    localStorage.setItem("usuario_externo", JSON.stringify(dados));
}

// Função para recuperar e validar expiração
function obterUsuario() {
    const item = localStorage.getItem("usuario_externo");

    if (!item) return null;

    const dados = JSON.parse(item);
    const agora = new Date().getTime();

    if (agora > dados.expiracao) {
        localStorage.removeItem("usuario_externo");
        return null;
    }

    return dados;
}

function mostrarModal() {
    document.getElementById("modalLogin").style.display = "flex";
}

function fecharModal() {
    document.getElementById("modalLogin").style.display = "none";
}


const usuario = obterUsuario();

if (usuario) {
    console.log("Usuário válido:", usuario.nome, usuario.email);
    // Pode permitir acesso direto
    fecharModal();
} else {
    console.log("Solicitar nome e email.");
    // Exibir formulário para pedir nome/email
    mostrarModal();
}

// Captura envio do formulário
document.getElementById("formLogin").addEventListener("submit", function (e) {
    e.preventDefault();
    const nome = document.getElementById("nome").value;
    const email = document.getElementById("email").value;
    salvarUsuario(nome, email);
    fecharModal();
    console.log("Usuário salvo:", nome, email);
});


async function carregarImagensPublicas() {
    const obraId = 1;
    try {
        const res = await fetch(`getImagensPublicas.php?obraId=${obraId}`);
        const text = await res.text();
        let data = {};
        try {
            data = JSON.parse(text);
        } catch (e) {
            document.getElementById('wrapper').innerHTML = `<p class="text-red-600">Erro ao processar resposta do servidor.</p>`;
            console.error('Resposta inválida:', text);
            return;
        }

        const wrapper = document.getElementById('wrapper');
        wrapper.innerHTML = '';

        if (data.error) {
            wrapper.innerHTML = `<p class="text-red-600">${data.error}</p>`;
            return;
        }

        if (!data.imagens || data.imagens.length === 0) {
            wrapper.innerHTML = `<p class="text-gray-500">Nenhuma imagem encontrada.</p>`;
            return;
        }

        data.imagens.forEach(imagem => {
            const div = document.createElement('div');
            div.className = 'card';

            const src = `../uploads/imagens/${imagem.nome_arquivo}`;
            const id = imagem.imagem_id;
            const imagem_nome = imagem.imagem_nome;

            div.innerHTML = `
        <img src="${src}" alt="${id}">
        <div class="overlay">
            <p id='nome_imagem'>${imagem_nome}</p>
        </div>
    `;

            wrapper.appendChild(div);

            div.addEventListener('click', () => {
                mostrarImagemCompleta(src, id);
            });
        });

    } catch (err) {
        document.getElementById('wrapper').innerHTML = `<p class="text-red-600">Erro ao carregar imagens.</p>`;
        console.error(err);
    }
}

carregarImagensPublicas();


let imagem_id = null; // Variável para armazenar o ID da imagem atual

const imagemId = localStorage.getItem('imagem_id_selecionada');
const imagemSrc = localStorage.getItem('imagem_src_selecionada');
if (imagemSrc && imagemId) {
    mostrarImagemCompleta(imagemSrc, imagemId)
}
// Mostra imagem e abre modal
function mostrarImagemCompleta(src, id) {
    imagem_id = id;

    const imageWrapper = document.getElementById("image_wrapper");

    const imagens = document.querySelector('.imagens');
    imagens.className = 'imagens somente-imagem';

    document.getElementById('wrapper_btn').style.display = "block";
    document.getElementById('comment_btn').style.display = "block";

    expandirImagem();


    while (imageWrapper.firstChild) {
        imageWrapper.removeChild(imageWrapper.firstChild);
    }

    const imgElement = document.createElement("img");
    imgElement.id = "imagem_atual";
    imgElement.src = src;
    imgElement.style.width = "100%";

    imageWrapper.appendChild(imgElement);
    document.querySelector('#imagem_atual').scrollIntoView({ behavior: 'smooth' });
    renderComments(id);

    imgElement.addEventListener('click', function (event) {
        if (dragMoved) {
            return;
        }
        if (![1, 2, 9, 20, 3].includes(usuario_externo)) return;

        const rect = imgElement.getBoundingClientRect();
        relativeX = ((event.clientX - rect.left) / rect.width) * 100;
        relativeY = ((event.clientY - rect.top) / rect.height) * 100;

        document.getElementById('comentarioTexto').value = '';
        document.getElementById('imagemComentario').value = '';
        document.getElementById('comentarioModal').style.display = 'flex';

    });
}


function showWrapper() {
    const wrapper = document.getElementById('wrapper_container');
    wrapper.classList.toggle('hidden');
}

function showComment() {
    const sidebar = document.getElementById('sidebar_direita');
    sidebar.classList.toggle('hidden');
}

function expandirImagem() {
    // Oculta os painéis laterais
    document.getElementById('wrapper_container').classList.add('hidden');
    document.getElementById('sidebar_direita').classList.add('hidden');
}


// let zoomLevel = 1;

// document.addEventListener('wheel', function (e) {
//     const isCtrlPressed = e.ctrlKey;

//     if (isCtrlPressed) {
//         e.preventDefault(); // Impede o zoom da página

//         const zoomStep = 0.1;

//         if (e.deltaY < 0) {
//             zoomLevel += zoomStep; // Zoom in
//         } else {
//             zoomLevel = Math.max(0.1, zoomLevel - zoomStep); // Zoom out
//         }

//         const imageWrapper = document.getElementById('image_wrapper');
//         imageWrapper.style.transform = `scale(${zoomLevel})`;
//         imageWrapper.style.transformOrigin = 'center center';
//     }
// }, { passive: false });

// Capturar colagem de imagem no campo de texto
document.getElementById('comentarioTexto').addEventListener('paste', function (event) {
    const items = (event.clipboardData || event.originalEvent.clipboardData).items;

    for (let index in items) {
        const item = items[index];
        if (item.kind === 'file') {
            const blob = item.getAsFile();
            if (blob && blob.type.startsWith('image/')) {
                const fileInput = document.getElementById('imagemComentario');

                // Cria um objeto DataTransfer para injetar o arquivo no input
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(new File([blob], 'imagem_colada.png', { type: blob.type }));

                fileInput.files = dataTransfer.files;

                Toastify({
                    text: 'Imagem colada com sucesso!',
                    duration: 3000,
                    backgroundColor: 'linear-gradient(to right, #00b09b, #96c93d)',
                    close: true,
                    gravity: "top",
                    position: "right"
                }).showToast();
            }
        }
    }
});

const imageWrapper = document.getElementById('image_wrapper');
const comments = document.querySelectorAll('.comment');
let currentZoom = 1;
const zoomStep = 0.1;
const maxZoom = 3;
const minZoom = 0.5;

// Pan variables
let isDragging = false;
let startX;
let startY;
let currentTranslateX = 0;
let currentTranslateY = 0;
let dragMoved = false;

// Function to apply transforms (zoom and pan)
function applyTransforms() {
    imageWrapper.style.transform = `scale(${currentZoom}) translate(${currentTranslateX}px, ${currentTranslateY}px)`;

    // Adjust comment scaling based on the new currentZoom
    comments.forEach(comment => {

        comment.style.transform = `scale(${1 / currentZoom})`;
    });
}

// --- Zoom functionality ---
document.addEventListener('wheel', function (event) {
    if (event.ctrlKey) {
        event.preventDefault(); // Prevent default browser zoom/scroll

        const oldZoom = currentZoom; // Store old zoom for potential pan adjustment (not used in your current code but good practice)

        if (event.deltaY < 0) {
            currentZoom += zoomStep;
        } else {
            currentZoom -= zoomStep;
        }

        currentZoom = Math.max(minZoom, Math.min(maxZoom, currentZoom));

        if (currentZoom === minZoom) {
            // When zoomed out completely, reset pan to origin
            currentTranslateX = 0;
            currentTranslateY = 0;
        }

        applyTransforms();
    }
}, { passive: false });

imageWrapper.addEventListener('mousedown', (e) => {
    if (e.button === 0 && !e.ctrlKey) {
        isDragging = true;
        dragMoved = false; // reset

        imageWrapper.classList.add('grabbing');
        startX = e.clientX - currentTranslateX;
        startY = e.clientY - currentTranslateY;
        imageWrapper.style.transition = 'none';
    }
});

document.addEventListener('mousemove', (e) => {
    if (!isDragging) return;

    e.preventDefault();

    const dx = e.clientX - startX;
    const dy = e.clientY - startY;

    // Marcar que houve movimento significativo
    if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
        dragMoved = true;
    }

    currentTranslateX = dx;
    currentTranslateY = dy;

    applyTransforms();
});

document.addEventListener('mouseup', (e) => {
    if (isDragging) {
        isDragging = false;
        imageWrapper.classList.remove('grabbing');
        imageWrapper.style.transition = 'transform 0.1s ease-out';
    }
});

// Initialize transforms
applyTransforms();
// Função para enviar o comentário
document.getElementById('enviarComentario').onclick = async () => {
    const texto = document.getElementById('comentarioTexto').value.trim();
    const imagemFile = document.getElementById('imagemComentario').files[0];

    if (!texto && !imagemFile) {
        Toastify({
            text: 'Escreva um comentário ou anexe uma imagem!',
            duration: 3000,
            backgroundColor: 'orange',
            close: true,
            gravity: "top",
            position: "right"
        }).showToast();
        return;
    }

    const idusuario_externo = parseInt(localStorage.getItem('usuario_externo')); // Obtém o idusuario do localStorage

    const formData = new FormData();
    formData.append('imagem_id', imagem_id);
    formData.append('x', relativeX);
    formData.append('y', relativeY);
    formData.append('texto', texto);
    formData.append('usuario_id', idusuario_externo);

    if (imagemFile) {
        formData.append('imagem', imagemFile);
    }

    try {
        const response = await fetch('salvar_comentario.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        document.getElementById('comentarioModal').style.display = 'none';

        if (result.sucesso) {
            Toastify({
                text: 'Comentário adicionado com sucesso!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();

            // Atualiza comentários
            renderComments(imagem_id);
        } else {
            Toastify({
                text: result.mensagem || 'Erro ao salvar comentário!',
                duration: 3000,
                backgroundColor: 'red',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
        }


    } catch (error) {
        console.error('Erro na requisição:', error);
        Toastify({
            text: 'Erro de conexão! Tente novamente.',
            duration: 3000,
            backgroundColor: 'red',
            close: true,
            gravity: "top",
            position: "left"
        }).showToast();
    }
};

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


// ---- CONFIGURAÇÃO ---------------------------------------------------------
const USERS_PERMITIDOS = [1, 2, 3, 9, 20];   // quem pode editar / excluir
// --------------------------------------------------------------------------

async function renderComments(id) {
    const comentariosDiv = document.querySelector(".comentarios");
    const imagemCompletaDiv = document.getElementById("image_wrapper");
    const response = await fetch(`buscar_comentarios.php?id=${id}`);
    const comentarios = await response.json();

    comentariosDiv.innerHTML = '';
    imagemCompletaDiv.querySelectorAll('.comment').forEach(c => c.remove());


    comentarios.forEach(comentario => {
        const commentCard = document.createElement('div');
        commentCard.classList.add('comment-card');
        commentCard.setAttribute('data-id', comentario.id);

        const header = document.createElement('div');
        header.classList.add('comment-header');
        header.innerHTML = `
            <div class="comment-number">${comentario.numero_comentario}</div>
            <div class="comment-user">${comentario.nome_responsavel}</div>
        `;

        const commentBody = document.createElement('div');
        commentBody.classList.add('comment-body');

        const p = document.createElement('p');
        p.classList.add('comment-input');
        p.textContent = comentario.texto;

        commentBody.appendChild(p);

        const footer = document.createElement('div');
        footer.classList.add('comment-footer');
        footer.innerHTML = `
            <div class="comment-date">${comentario.data}</div>
            <div class="comment-actions">
                <button class="comment-resp">&#8617</button>
                <button class="comment-edit">✏️</button>
                <button class="comment-delete" onclick="deleteComment(${comentario.id})">🗑️</button>
            </div>
        `;

        const respostas = document.createElement('div');
        respostas.classList.add('respostas-container');
        respostas.id = `respostas-${comentario.id}`;

        commentCard.appendChild(header);
        if (comentario.imagem) {
            const imagemDiv = document.createElement('div');
            imagemDiv.classList.add('comment-image');
            imagemDiv.innerHTML = `
                <img src="${comentario.imagem}" class="comment-img-thumb" onclick="abrirImagemModal('${comentario.imagem}')">
            `;
            commentCard.appendChild(imagemDiv);
        }
        commentCard.appendChild(commentBody);
        commentCard.appendChild(footer);
        commentCard.appendChild(respostas);

        // Permissões
        if (!USERS_PERMITIDOS.includes(usuario_externo)) {
            footer.querySelector('.comment-delete').style.display = 'none';
            footer.querySelector('.comment-edit').style.display = 'none';
        }

        const editButton = footer.querySelector('.comment-edit');

        editButton.addEventListener('click', () => {
            p.contentEditable = true;
            p.focus();

            const handleKeyDown = async function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();

                    const novoTexto = p.textContent.trim();

                    p.contentEditable = false;

                    updateComment(comentario.id, novoTexto);

                    // Remove o listener pra não acumular
                    p.removeEventListener('keydown', handleKeyDown);
                }
            };

            p.addEventListener('keydown', handleKeyDown);
        });

        const commentDiv = document.createElement('div');
        commentDiv.classList.add('comment');
        commentDiv.setAttribute('data-id', comentario.id);
        commentDiv.innerText = comentario.numero_comentario;
        commentDiv.style.left = `${comentario.x}%`;
        commentDiv.style.top = `${comentario.y}%`;

        commentDiv.addEventListener('click', () => {
            document.querySelectorAll('.comment-number').forEach(n => n.classList.remove('highlight'));
            const number = document.querySelector(`.comment-card[data-id="${comentario.id}"] .comment-number`);
            if (number) {
                number.classList.add('highlight');
                number.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        const respButton = commentCard.querySelector('.comment-resp');

        respButton.addEventListener('click', async () => {
            const textoResposta = prompt("Digite sua resposta:");
            if (textoResposta && textoResposta.trim() !== '') {
                const respostaSalva = await salvarResposta(comentario.id, textoResposta);
                if (respostaSalva) {
                    adicionarRespostaDOM(comentario.id, respostaSalva);

                    const mencoes = textoResposta.match(/@(\w+)/g);
                    if (mencoes) {
                        for (const mencao of mencoes) {
                            const nome = mencao.replace('@', '');
                            const colaborador = users.find(u => u.nome_colaborador === nome);
                            if (colaborador) {
                                await fetch('registrar_mencao.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        comentario_id: comentario.id,
                                        mencionado_id: colaborador.idcolaborador
                                    })
                                });
                            }
                        }
                    }
                }
            }
        });

        imagemCompletaDiv.appendChild(commentDiv);
        comentariosDiv.appendChild(commentCard);

        if (comentario.respostas && comentario.respostas.length > 0) {
            comentario.respostas.forEach(resposta => {
                adicionarRespostaDOM(comentario.id, resposta);
            });
        }
    });
}

// Função para enviar resposta pro backend
async function salvarResposta(comentarioId, texto) {
    const response = await fetch('responder_comentario.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            comentario_id: comentarioId,
            texto: texto
        })
    });
    return await response.json();
}

// Função pra adicionar resposta no DOM
function adicionarRespostaDOM(comentarioId, resposta) {
    const container = document.getElementById(`respostas-${comentarioId}`);
    const respostaDiv = document.createElement('div');
    respostaDiv.classList.add('resposta');
    respostaDiv.innerHTML = `
        <div class="resposta-nome"><span class="reply-icon">&#8617;</span>  ${resposta.nome_responsavel}</div>
        <div class="corpo-resposta">
            <div class="resposta-texto">${resposta.texto}</div>
            <div class="resposta-data">${resposta.data}</div>
        </div>
    `;
    container.appendChild(respostaDiv);
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
            renderComments(imagem_id); // Atualiza a lista de comentários
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

function abrirImagemModal(src) {
    const modal = document.getElementById('modal-imagem');
    const imagem = document.getElementById('imagem-ampliada');
    imagem.src = src;
    modal.style.display = 'flex';
}

function fecharImagemModal() {
    const modal = document.getElementById('modal-imagem');
    modal.style.display = 'none';
}

// Modal: fechar
document.getElementById('fecharComentarioModal').onclick = () => {
    document.getElementById('comentarioModal').style.display = 'none';
};


// const btnBack = document.getElementById('btnBack');
// btnBack.addEventListener('click', function () {
//     const main = document.querySelector('.main');
//     main.classList.remove('hidden');

//     const container_aprovacao = document.querySelector('.container-aprovacao');
//     container_aprovacao.classList.add('hidden');

//     const imagemWrapperDiv = document.querySelector(".image_wrapper");
//     imagemWrapperDiv.innerHTML = '';

//     const comentariosDiv = document.querySelector(".comentarios");
//     comentariosDiv.innerHTML = '';
// });

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        const comentarioModal = document.getElementById("comentarioModal");

        if (comentarioModal.style.display === 'flex') {
            comentarioModal.style.display = 'none';
            return; // Interrompe aqui se o modal estava visível
        }

        const main = document.querySelector('.main');
        main.classList.remove('hidden');

        const container_aprovacao = document.querySelector('.container-aprovacao');
        container_aprovacao.classList.add('hidden');

        const imagemWrapperDiv = document.querySelector(".image_wrapper");
        imagemWrapperDiv.innerHTML = '';

        const comentariosDiv = document.querySelector(".comentarios");
        comentariosDiv.innerHTML = '';
    }
});


$(document).ready(function () {
    $('#apr_imagem').select2({
        width: '100%',
        minimumResultsForSearch: Infinity
    });
});

// Mostra o botão quando selecionar uma opção válida
$('#apr_imagem').on('change', function () {
    if ($(this).val()) {
        $('#confirmar_aprovacao').show();
    } else {
        $('#confirmar_aprovacao').hide();
    }
});

// Ao clicar no botão, pede confirmação e envia via fetch
$('#confirmar_aprovacao').on('click', async function () {
    const opcao = $('#apr_imagem').val();
    const usuario = localStorage.getItem('usuario_externo');
    const imagemId = imagem_id; // imagem_id já está global no seu script

    if (!opcao || !usuario || !imagemId) {
        alert('Dados insuficientes para aprovar.');
        return;
    }

    if (confirm('Tem certeza que deseja confirmar esta aprovação?')) {
        try {
            const response = await fetch('aprovar_imagem.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    usuario: usuario,
                    imagem_id: imagemId,
                    aprovacao: opcao
                })
            });
            const result = await response.json();
            if (result.sucesso) {
                alert('Aprovação registrada com sucesso!');
                $('#confirmar_aprovacao').hide();
            } else {
                alert(result.mensagem || 'Erro ao registrar aprovação.');
            }
        } catch (e) {
            alert('Erro de conexão ao aprovar.');
        }
    }
});