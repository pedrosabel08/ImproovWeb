document.addEventListener("DOMContentLoaded", function () {

    fetchTarefas();

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

});

// Função para revisar uma tarefa com confirmação e solicitação ao PHP
function revisarTarefa(idfuncao_imagem, nome_colaborador, imagem_nome, nome_funcao) {
    if (confirm("Você tem certeza de que deseja marcar esta tarefa como revisada?")) {
        fetch('revisarTarefa.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                idfuncao_imagem: idfuncao_imagem,
                nome_colaborador: nome_colaborador,
                imagem_nome: imagem_nome,
                nome_funcao: nome_funcao
            }),
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error("Erro ao atualizar a tarefa.");
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Exibe o sucesso usando Toastify
                    Toastify({
                        text: "Tarefa marcada como revisada com sucesso!",
                        duration: 3000, // duração do toast (3 segundos)
                        backgroundColor: "green", // cor de fundo
                        close: true, // botao de fechar
                        gravity: "top", // aparece no topo
                        position: "right" // posição do lado direito
                    }).showToast();

                    fetchTarefas();
                    // location.reload(); // Atualiza a página para refletir a mudança
                } else {
                    // Exibe a falha usando Toastify
                    Toastify({
                        text: "Falha ao marcar a tarefa como revisada: " + data.message,
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
                // Exibe o erro usando Toastify
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
    event.stopPropagation(); // Impede o clique na tarefa de abrir os detalhes
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
                    <button class="action-btn" onclick="revisarTarefa(${tarefa.idfuncao_imagem}, '${tarefa.nome_colaborador}', '${tarefa.imagem_nome}', '${tarefa.nome_funcao}')">
                        <i class="fa-solid fa-check"></i>
                    </button>
                    <a href="https://wa.me/55${tarefa.telefone.replace(/\D/g, '')}?text=Olá, tenho uma dúvida sobre a tarefa. Poderia me ajudar?" target="_blank">
                        <button class="whatsapp-btn">
                            <i class="fa-brands fa-whatsapp"></i>
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
