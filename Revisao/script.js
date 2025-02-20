document.addEventListener("DOMContentLoaded", function () {

    fetchTarefas();

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
            taskItem.setAttribute('onclick', `historyAJAX(${tarefa.idfuncao_imagem}, '${tarefa.nome_funcao}', '${tarefa.imagem_nome}')`);

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


function historyAJAX(idfuncao_imagem, funcao_nome, imagem_nome) {
    fetch(`historico.php?ajid=${idfuncao_imagem}`)
        .then(response => response.json())
        .then(data => {

            document.getElementById("id_funcao").value = idfuncao_imagem;
            document.getElementById("imagem_nome").textContent = imagem_nome;
            document.getElementById("funcao_nome").textContent = funcao_nome;

            // Exibir o modal
            const modal = document.getElementById('historico_modal');
            modal.style.display = 'grid';

            // Verifique se os dados estão no formato correto (array de objetos)
            if (Array.isArray(data)) {
                // Inicialize a tabela DataTable
                let tabela = $('#tabelaHistorico').DataTable({
                    "destroy": true,  // Permite reinicializar a tabela sem erro
                    "data": data,  // Insere os dados diretos da resposta na tabela
                    "columns": [
                        { "data": "id" },
                        { "data": "status_anterior" },
                        { "data": "status_novo" },
                        { "data": "data_aprovacao" },
                        { "data": "colaborador_nome" },
                        { "data": "responsavel_nome" },
                        { "data": "observacoes" },
                        {
                            "data": null,
                            "render": function (data, type, row) {
                                return `
                                <div class="task-actions">
                                    <button class="action-btn tooltip" id="add_obs" onclick="addObservacao(${row.id})" data-tooltip="Adicionar Observação">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                    <button class="action-btn tooltip" id="check" data-tooltip="Aprovar" onclick="revisarTarefa(${row.funcao_imagem_id}, '${row.colaborador_nome}', '${row.imagem_nome}', '${row.nome_funcao}', '${row.colaborador_id}', true)">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                    <button class="action-btn tooltip" id="xmark" data-tooltip="Rejeitar" onclick="revisarTarefa(${row.funcao_imagem_id}, '${row.colaborador_nome}', '${row.imagem_nome}', '${row.nome_funcao}', '${row.colaborador_id}', false)">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                                `;
                            }
                        }
                    ],
                    // Usando createdRow para manipular cada linha após ser criada
                    "createdRow": function (row, data, dataIndex) {
                        // Verifica os valores de status_novo e status_anterior
                        if (data.status_novo === 'Em aprovação') {
                            // Alterando o background da coluna "status_novo"
                            $('td', row).eq(2).css('background-color', 'yellow');  // 2 é o índice da coluna "status_novo"
                        }

                        if (data.status_novo === 'Aprovado') {
                            // Alterando o background da coluna "status_anterior"
                            $('td', row).eq(2).css('background-color', 'green');  // 1 é o índice da coluna "status_anterior"
                            $('td', row).eq(2).css('color', 'white');  // 1 é o índice da coluna "status_anterior"
                        }
                        if (data.status_novo === 'Ajuste') {
                            // Alterando o background da coluna "status_anterior"
                            $('td', row).eq(2).css('background-color', 'red');  // 1 é o índice da coluna "status_anterior"
                        }
                    }

                });
            } else {
                console.error("Os dados recebidos não estão no formato esperado (array).");
            }
        })
        .catch(error => console.error("Erro ao buscar dados:", error));
}

const id_revisao = document.getElementById('id_revisao');

function addObservacao(id) {
    const modal = document.getElementById('historico_modal');
    const idRevisao = document.getElementById('id_revisao');
    const historicoAdd = modal.querySelector('.historico-add');

    historicoAdd.classList.toggle('hidden');

    if (historicoAdd.classList.contains('hidden')) {
        modal.classList.remove('complete');
    } else {
        modal.classList.add('complete');
    }

    idRevisao.innerText = `${id}`;
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
const historicoAdd = historico_modal.querySelector('.historico-add');

window.addEventListener('click', function (event) {
    if (event.target == historico_modal) {
        historico_modal.style.display = "none"
        historico_modal.classList.remove('complete');
        historicoAdd.classList.add('hidden');
    }
});

window.addEventListener('touchstart', function (event) {
    if (event.target == historico_modal) {
        historico_modal.style.display = "none"
        historico_modal.classList.remove('complete');
        historicoAdd.classList.add('hidden');

    }
});


// Captura o evento de envio do formulário
document.getElementById('adicionar_obs').addEventListener('submit', function (event) {
    event.preventDefault(); // Previne o comportamento padrão do envio do formulário

    // Exibe um prompt para o usuário digitar o número da revisão
    const numeroRevisao = document.getElementById('id_revisao').textContent;
    const idfuncao_imagem = document.getElementById("id_funcao").value;

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
                    Toastify({
                        text: 'Observação adicionada com sucesso!',
                        duration: 3000,
                        backgroundColor: 'green',
                        close: true,
                        gravity: "top",
                        position: "right"
                    }).showToast();

                    historico_modal.classList.remove('complete');
                    historicoAdd.classList.toggle('hidden');
                    historyAJAX(idfuncao_imagem)
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
                console.error("Erro ao enviar dados para o servidor:", error);
                alert("Ocorreu um erro ao tentar adicionar a observação.");
            });
    } else {
        alert("Número de revisão é obrigatório!");
    }
});

