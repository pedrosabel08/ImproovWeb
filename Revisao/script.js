document.addEventListener("DOMContentLoaded", function () {

    fetchTarefas();

});

function revisarTarefa(idfuncao_imagem, nome_colaborador, imagem_nome, nome_funcao, isChecked) {
    const actionText = isChecked
        ? "marcar esta tarefa como revisada"
        : "indicar que esta tarefa precisa de alterações";
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
                isChecked: isChecked
            }),
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error("Erro ao atualizar a tarefa.");
                }
                return response.json();
            })
            .then(data => {
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
                    text: "Ocorreu um erro ao processar a solicitação.",
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
async function fetchTarefas(filtro = 'Todos') {
    try {
        const response = await fetch('atualizar.php'); // Altere para o caminho correto do seu script PHP
        if (!response.ok) {
            throw new Error("Erro ao buscar as tarefas.");
        }

        const data = await response.json();

        const tarefasFiltradas = data.filter(tarefa => {
            return filtro === 'Todos' || tarefa.nome_funcao === filtro;
        });
        exibirTarefas(tarefasFiltradas); // Passa os dados para a função que vai exibir as tarefas
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

// Função para exibir as tarefas
function exibirTarefas(tarefas) {
    const container = document.querySelector('.container');
    container.innerHTML = ''; // Limpa o conteúdo da página antes de exibir as novas tarefas

    if (tarefas.length > 0) {
        tarefas.forEach(tarefa => {
            const taskItem = document.createElement('div');
            taskItem.classList.add('task-item');
            taskItem.setAttribute('onclick', 'toggleTaskDetails(this)');

            taskItem.innerHTML = `
                <div class="task-info">
                    <h3>${tarefa.nome_funcao}</h3><span>${tarefa.nome_colaborador}</span>
                    <p>${tarefa.imagem_nome}</p>
                </div>
                <div class="task-actions">
                    <button class="action-btn" id="check" onclick="revisarTarefa(${tarefa.idfuncao_imagem}, '${tarefa.nome_colaborador}', '${tarefa.imagem_nome}', '${tarefa.nome_funcao}', true)">
                        <i class="fa-solid fa-check"></i>
                    </button>
                    <button class="action-btn" id="xmark" onclick="revisarTarefa(${tarefa.idfuncao_imagem}, '${tarefa.nome_colaborador}', '${tarefa.imagem_nome}', '${tarefa.nome_funcao}', false)">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <button class="whatsapp-btn" id="history" onclick="historyAJAX(${tarefa.idfuncao_imagem})">
                        <i class="fas fa-list"></i>
                    </button>
                    </a>
                </div>
                <div class="task-details">
                    <p><strong>Imagem:</strong> ${tarefa.imagem_nome}</p>
                    <p><strong>Colaborador:</strong> ${tarefa.nome_colaborador}</p>
                </div>
            `;
            container.appendChild(taskItem);
        });
    } else {
        container.innerHTML = '<p style="text-align: center; color: #888;">Não há tarefas de revisão no momento.</p>';
    }
}

document.getElementById('nome_funcao').addEventListener('change', filtrarTarefas);


function historyAJAX(idfuncao_imagem) {
    // Fazer requisição AJAX para `historico.php`
    fetch(`historico.php?ajid=${idfuncao_imagem}`)
        .then(response => response.json())
        .then(response => {
            // Exibir o modal
            const modal = document.getElementById('historico_modal');
            modal.style.display = 'grid';

            // Limpar o conteúdo atual do modal
            const historicoContainer = modal.querySelector('.historico-container');
            historicoContainer.innerHTML = '';


            // Verificar se há histórico retornado
            if (response.length > 0) {
                // Iterar sobre os itens do histórico
                response.forEach(item => {
                    // Criar o card para cada item
                    const card = document.createElement('div');
                    card.classList.add('historico-card'); // Classe para estilizar o card

                    // Adicionar conteúdo ao card
                    card.innerHTML = `
                        <div class="historico-item">
                            <strong>ID:</strong> ${item.id || 'N/A'}
                        </div>
                        <div class="historico-item">
                            <strong>Status Anterior:</strong> ${item.status_anterior || 'N/A'}
                        </div>
                        <div class="historico-item">
                            <strong>Status Novo:</strong> ${item.status_novo || 'N/A'}
                        </div>
                        <div class="historico-item">
                            <strong>Data Aprovação:</strong> ${item.data_aprovacao || 'N/A'}
                        </div>
                        <div class="historico-item">
                            <strong>Colaborador:</strong> ${item.colaborador_id || 'N/A'}
                        </div>
                        <div class="historico-item obs">
                            <strong>Observações:</strong>
                            <div class="observacoes"> ${item.observacoes || 'N/A'} </div>
                        </div>
                        <button id="add_obs" onclick="addObservacao(${item.id})"><i class="fa-solid fa-plus"></i></button>

                    `;


                    console.log(card.innerHTML);

                    // Adicionar o card ao container
                    historicoContainer.appendChild(card);
                });
            } else {
                // Exibir mensagem caso não haja histórico
                historicoContainer.innerHTML = '<p>Não há histórico disponível para esta função.</p>';
            }
        })
        .catch(error => console.error("Erro ao buscar dados da linha:", error));
}

const id_revisao = document.getElementById('id_revisao');

function addObservacao(id) {
    const modal = document.getElementById('historico_modal');
    const idRevisao = document.getElementById('id_revisao');
    const historicoAdd = modal.querySelector('.historico-add');

    // Mostrar ou esconder o formulário de observação
    historicoAdd.classList.toggle('hidden');

    // Verificar se o formulário está oculto
    if (historicoAdd.classList.contains('hidden')) {
        // Se estiver oculto, remover a classe 'complete' do modal
        modal.classList.remove('complete');
    } else {
        // Se não estiver oculto, adicionar a classe 'complete' no modal
        modal.classList.add('complete');
    }

    // Definir o ID de revisão
    idRevisao.innerText = `Revisão ID: ${id}`;
}



// Inicializa o editor Quill
var quill = new Quill('#text_obs', {
    theme: 'snow',  // Tema claro
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline'], // Negrito, itálico, sublinhado
            [{ 'header': 1 }, { 'header': 2 }], // Títulos
            [{ 'list': 'ordered' }, { 'list': 'bullet' }], // Listas
            [{ 'color': [] }, { 'background': [] }], // Cores
            ['clean'] // Limpar formatação
        ]
    }
});


const historico_modal = document.getElementById('historico_modal');

window.addEventListener('click', function (event) {
    if (event.target == historico_modal) {
        historico_modal.style.display = "none"
    }
});

window.addEventListener('touchstart', function (event) {
    if (event.target == historico_modal) {
        historico_modal.style.display = "none"
    }
});


// Captura o evento de envio do formulário
document.getElementById('adicionar_obs').addEventListener('submit', function (event) {
    event.preventDefault(); // Previne o comportamento padrão do envio do formulário

    // Exibe um prompt para o usuário digitar o número da revisão
    const numeroRevisao = prompt("Digite o número da revisão:");

    if (numeroRevisao) {
        // Captura o conteúdo do editor Quill
        const observacao = quill.root.innerHTML;

        // Exibe os valores no console (você pode remover esta parte depois)
        console.log("Número da Revisão: " + numeroRevisao);
        console.log("Observação: " + observacao);

        // Envia os dados para o servidor via fetch
        fetch('atualizar_historico.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                revisao: numeroRevisao,
                observacao: observacao
            })
        })
            .then(response => response.json())
            .then(data => {
                // Verifica se a atualização foi bem-sucedida
                if (data.success) {
                    alert('Observação adicionada com sucesso!');

                } else {
                    alert('Erro ao adicionar a observação.');
                }
            })
            .catch(error => {
                console.error("Erro ao enviar dados para o servidor:", error);
                alert("Ocorreu um erro ao tentar adicionar a observação.");
            });
    } else {
        alert("Número de revisão é obrigatório!");
    }
});

