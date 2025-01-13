function instagramImproov() {
    window.location.href = 'https://www.instagram.com/improovbr/';
}

function visualizarTabela() {
    window.location.href = 'main.php'
}

function listaPos() {
    window.location.href = 'Pos-Producao/index.php'
}

function dashboard() {
    window.location.href = 'Dashboard/index.php'
}

function clientes() {
    window.location.href = 'infoCliente/index.php'
}

function animacao() {
    window.location.href = 'Animacao/index.php'
}

function arquitetura() {
    window.location.href = 'Arquitetura/index.php'
}
function metas() {
    window.location.href = 'Metas/index.php'
}
function acomp() {
    window.location.href = 'Acompanhamento/index.html'
}
function calendar() {
    window.location.href = 'Calendario/index.php'
}


function formatarDataAtual() {
    const opcoes = { weekday: 'long', day: 'numeric', month: 'long' };
    const dataAtual = new Date();
    return dataAtual.toLocaleDateString('pt-BR', opcoes);
}

document.getElementById('data').textContent = formatarDataAtual();

document.addEventListener("DOMContentLoaded", function () {

    document.getElementById('menuButton').addEventListener('click', function () {
        const menu = document.getElementById('menu');
        menu.classList.toggle('hidden');
    });

    window.addEventListener('click', function (event) {
        const menu = document.getElementById('menu');
        const button = document.getElementById('menuButton');

        if (!button.contains(event.target) && !menu.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });

    document.getElementById('showMenu').addEventListener('click', function () {
        const menu2 = document.getElementById('menu2');
        menu2.classList.toggle('hidden');
    });

    window.addEventListener('click', function (event) {
        const menu2 = document.getElementById('menu2');
        const button = document.getElementById('showMenu');

        if (!button.contains(event.target) && !menu2.contains(event.target)) {
            menu2.classList.add('hidden');
        }
    });

});


if (idusuario != 1 && idusuario != 2) {
    document.querySelector('.button-container').style.display = 'none';
    document.querySelector('#container-andamento').style.display = 'none';
    document.querySelector('#priority-container').style.display = 'none';
}

// Função para carregar as imagens com base no colaborador selecionado e no contêiner ativo
function carregarImagens(container, colaboradorId) {
    fetch('get_imagens.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            colaboradorId: colaboradorId,
            container: container // Passa o contêiner ativo
        }),
    })
        .then((response) => response.json())
        .then((data) => {
            if (container === 'andamento') {
                const imagensDivAndamento = document.getElementById('imagensColaboradorAndamento');
                imagensDivAndamento.innerHTML = '';
                if (data.imagens.length > 0) {
                    data.imagens.forEach((imagem) => {
                        const imgElement = document.createElement('div');
                        imgElement.classList.add('image-item');
                        imgElement.textContent = imagem.imagem_nome;
                        imagensDivAndamento.appendChild(imgElement);
                    });
                } else {
                    imagensDivAndamento.innerHTML = 'Nenhuma imagem encontrada.';
                }
            } else if (container === 'prioridade') {
                const dropZones = document.querySelectorAll('.drop-zone');
                dropZones.forEach((zone) => (zone.innerHTML = ''));
                if (data.imagens.length > 0) {
                    data.imagens.forEach((imagem) => {
                        const item = document.createElement('div');
                        item.classList.add('draggable-item');
                        item.setAttribute('draggable', 'true');
                        item.dataset.id = imagem.idfuncao_imagem;
                        item.dataset.funcaoId = imagem.funcao_id;
                        item.textContent = imagem.imagem_nome;

                        const priorityZone = document.querySelector(
                            `.drop-zone[data-priority="${imagem.prioridade}"]`
                        );
                        if (priorityZone) {
                            priorityZone.appendChild(item);
                            addDragAndDropEvents(item);
                        }
                    });
                }
            }
        })
        .catch((error) => console.error('Erro ao carregar as imagens:', error));
}


// Event listeners para os dois selects
document.getElementById('colaboradorSelectAndamento').addEventListener('change', function () {
    const colaboradorId = this.value;
    if (colaboradorId != '0') {
        carregarImagens('andamento', colaboradorId);
    }
});

document.getElementById('colaboradorSelectPrioridade').addEventListener('change', function () {
    const colaboradorId = this.value;
    if (colaboradorId != '0') {
        carregarImagens('prioridade', colaboradorId);
    }

});

const andamentoBtn = document.getElementById("show-andamento-btn");
const prioridadeBtn = document.getElementById("show-prioridade-btn");
const calendario = document.getElementById("calendario");
const andamentoContainer = document.getElementById("container-andamento");
const prioridadeContainer = document.getElementById("priority-container");
const calendarioContainer = document.getElementById("container-calendario");


const toggleButton = document.getElementById('toggleButton');
const iconContainer = document.getElementById('iconContainer');

toggleButton.addEventListener('click', () => {
    iconContainer.classList.toggle('active');
});


andamentoBtn.addEventListener("click", () => {
    andamentoContainer.classList.add("active");
    prioridadeContainer.classList.remove("active");
    calendarioContainer.style.display = 'none';
    iconContainer.classList.toggle('active');
});

prioridadeBtn.addEventListener("click", () => {
    prioridadeContainer.classList.add("active");
    andamentoContainer.classList.remove("active");
    calendarioContainer.style.display = 'none';
    iconContainer.classList.toggle('active');
});

calendario.addEventListener("click", () => {
    calendarioContainer.style.display = 'grid';
    andamentoContainer.classList.remove("active");
    prioridadeContainer.classList.remove("active");
    iconContainer.classList.toggle('active');
})




function addDragAndDropEvents(item) {
    item.addEventListener('dragstart', () => {
        item.classList.add('dragging');
    });

    item.addEventListener('dragend', () => {
        item.classList.remove('dragging');

        // Atualizar a prioridade no banco de dados
        const newPriority = item.parentElement.dataset.priority;
        const funcaoId = item.dataset.id;

        console.log('Atualizando prioridade:', {
            id: funcaoId,
            prioridade: newPriority
        });

        fetch('update_priority.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: funcaoId,
                prioridade: newPriority
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(`Prioridade atualizada para ${newPriority}`);
                } else {
                    console.error('Erro ao atualizar a prioridade:', data.message);
                }
            })
            .catch(error => console.error('Erro ao atualizar a prioridade:', error));
    });
}

// Eventos de drag and drop para as zonas
document.querySelectorAll('.drop-zone').forEach(zone => {
    zone.addEventListener('dragover', e => {
        e.preventDefault();
        zone.classList.add('over');

        const draggingItem = document.querySelector('.dragging');
        const afterElement = getDragAfterElement(zone, e.clientY);
        if (afterElement == null) {
            zone.appendChild(draggingItem);
        } else {
            zone.insertBefore(draggingItem, afterElement);
        }
    });

    zone.addEventListener('dragleave', () => {
        zone.classList.remove('over');
    });

    zone.addEventListener('drop', () => {
        zone.classList.remove('over');
    });
});

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.draggable-item:not(.dragging)')];

    return draggableElements.reduce(
        (closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return {
                    offset,
                    element: child
                };
            } else {
                return closest;
            }
        }, {
        offset: Number.NEGATIVE_INFINITY
    }
    ).element;
}