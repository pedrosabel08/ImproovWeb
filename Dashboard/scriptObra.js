// Obtém o 'obra_id' do localStorage
var obraId = localStorage.getItem('obraId');

let chartInstance = null;

document.querySelectorAll('.titulo').forEach(titulo => {
    titulo.addEventListener('click', () => {
        const opcoes = titulo.nextElementSibling;
        if (opcoes.style.display === 'none') {
            opcoes.style.display = 'block';
            titulo.querySelector('i').classList.remove('fa-chevron-down');
            titulo.querySelector('i').classList.add('fa-chevron-up');
            opcoes.classList.add('show-in');
        } else {
            opcoes.style.display = 'none';
            titulo.querySelector('i').classList.remove('fa-chevron-up');
            titulo.querySelector('i').classList.add('fa-chevron-down');
        }
    });
});

function limparCampos() {
    document.getElementById("campoNomeImagem").textContent = "";

    document.getElementById("status_caderno").value = "";
    document.getElementById("prazo_caderno").value = "";
    document.getElementById("obs_caderno").value = "";
    document.getElementById("status_modelagem").value = "";
    document.getElementById("prazo_modelagem").value = "";
    document.getElementById("obs_modelagem").value = "";
    document.getElementById("status_comp").value = "";
    document.getElementById("prazo_comp").value = "";
    document.getElementById("obs_comp").value = "";
    document.getElementById("status_finalizacao").value = "";
    document.getElementById("prazo_finalizacao").value = "";
    document.getElementById("obs_finalizacao").value = "";
    document.getElementById("status_pos").value = "";
    document.getElementById("prazo_pos").value = "";
    document.getElementById("obs_pos").value = "";
    document.getElementById("status_alteracao").value = "";
    document.getElementById("prazo_alteracao").value = "";
    document.getElementById("obs_alteracao").value = "";
    document.getElementById("status_planta").value = "";
    document.getElementById("prazo_planta").value = "";
    document.getElementById("obs_planta").value = "";
    document.getElementById("status_filtro").value = "";
    document.getElementById("prazo_filtro").value = "";
    document.getElementById("obs_filtro").value = "";

    document.getElementById("opcao_caderno").value = "";
    document.getElementById("opcao_model").value = "";
    document.getElementById("opcao_comp").value = "";
    document.getElementById("opcao_final").value = "";
    document.getElementById("opcao_pos").value = "";
    document.getElementById("opcao_alteracao").value = "";
    document.getElementById("opcao_planta").value = "";
    document.getElementById("opcao_filtro").value = "";
    document.getElementById("opcao_status").value = "";
}

let idsImagensObra = []; // Array para armazenar os IDs das imagens da obra
let indiceImagemAtual = 0; // Índice da imagem atualmente exibida no modal
let linhasTabela = [];

function addEventListenersToRows() {

    linhasTabela = document.querySelectorAll(".linha-tabela");

    linhasTabela.forEach(function (linha) {
        linha.addEventListener("click", function () {
            linhasTabela.forEach(function (outraLinha) {
                outraLinha.classList.remove("selecionada");
            });

            linha.classList.add("selecionada");

            const idImagemSelecionada = linha.getAttribute("data-id");
            document.getElementById("imagem_id").value = idImagemSelecionada;

            // Encontrar o índice da imagem clicada no array de IDs
            indiceImagemAtual = idsImagensObra.indexOf(parseInt(idImagemSelecionada));

            console.log("Linha selecionada: ID da imagem = " + idImagemSelecionada);

            atualizarModal(idImagemSelecionada);
        });
    });
}

function atualizarModal(idImagem) {
    // Limpar campos do formulário de edição
    limparCampos();

    // Fazer requisição AJAX para `buscaLinhaAJAX.php` usando Fetch
    fetch(`../buscaLinhaAJAX.php?ajid=${idImagem}`)
        .then(response => response.json())
        .then(response => {
            document.getElementById('form-edicao').style.display = 'flex';

            if (response.funcoes && response.funcoes.length > 0) {
                document.getElementById("campoNomeImagem").textContent = response.funcoes[0].imagem_nome;

                response.funcoes.forEach(function (funcao) {
                    let selectElement;
                    switch (funcao.nome_funcao) {
                        case "Caderno":
                            selectElement = document.getElementById("opcao_caderno");
                            document.getElementById("status_caderno").value = funcao.status;
                            document.getElementById("prazo_caderno").value = funcao.prazo;
                            document.getElementById("obs_caderno").value = funcao.observacao;
                            break;
                        case "Modelagem":
                            selectElement = document.getElementById("opcao_model");
                            document.getElementById("status_modelagem").value = funcao.status;
                            document.getElementById("prazo_modelagem").value = funcao.prazo;
                            document.getElementById("obs_modelagem").value = funcao.observacao;
                            break;
                        case "Composição":
                            selectElement = document.getElementById("opcao_comp");
                            document.getElementById("status_comp").value = funcao.status;
                            document.getElementById("prazo_comp").value = funcao.prazo;
                            document.getElementById("obs_comp").value = funcao.observacao;
                            break;
                        case "Finalização":
                            selectElement = document.getElementById("opcao_final");
                            document.getElementById("status_finalizacao").value = funcao.status;
                            document.getElementById("prazo_finalizacao").value = funcao.prazo;
                            document.getElementById("obs_finalizacao").value = funcao.observacao;
                            break;
                        case "Pós-produção":
                            selectElement = document.getElementById("opcao_pos");
                            document.getElementById("status_pos").value = funcao.status;
                            document.getElementById("prazo_pos").value = funcao.prazo;
                            document.getElementById("obs_pos").value = funcao.observacao;
                            break;
                        case "Alteração":
                            selectElement = document.getElementById("opcao_alteracao");
                            document.getElementById("status_alteracao").value = funcao.status;
                            document.getElementById("prazo_alteracao").value = funcao.prazo;
                            document.getElementById("obs_alteracao").value = funcao.observacao;
                            break;
                        case "Planta Humanizada":
                            selectElement = document.getElementById("opcao_planta");
                            document.getElementById("status_planta").value = funcao.status;
                            document.getElementById("prazo_planta").value = funcao.prazo;
                            document.getElementById("obs_planta").value = funcao.observacao;
                            break;
                        case "Filtro de assets":
                            selectElement = document.getElementById("opcao_filtro");
                            document.getElementById("status_filtro").value = funcao.status;
                            document.getElementById("prazo_filtro").value = funcao.prazo;
                            document.getElementById("obs_filtro").value = funcao.observacao;
                            break;
                    }
                    if (selectElement) {
                        selectElement.value = funcao.colaborador_id;
                    }
                });
            }

            const statusSelect = document.getElementById("opcao_status");
            if (response.status_id !== null) {
                statusSelect.value = response.status_id;
            }
        })
        .catch(error => console.error("Erro ao buscar dados da linha:", error));


}

// Verifica se obraId está presente no localStorage
if (obraId) {
    fetch(`infosObra.php?obraId=${obraId}`)
        .then(response => response.json())
        .then(data => {
            // Verifica se os dados são válidos e não vazios
            if (!Array.isArray(data.imagens) || data.imagens.length === 0) {
                console.warn('Nenhuma função encontrada para esta obra.');
                data.imagens = [{ // Exemplo de dados padrão para evitar que a tabela fique vazia
                    imagem_nome: 'Sem imagem',
                    tipo_imagem: 'N/A',
                    caderno_colaborador: '-',
                    caderno_status: '-',
                    modelagem_colaborador: '-',
                    modelagem_status: '-',
                    composicao_colaborador: '-',
                    composicao_status: '-',
                    finalizacao_colaborador: '-',
                    finalizacao_status: '-',
                    pos_producao_colaborador: '-',
                    pos_producao_status: '-',
                    alteracao_colaborador: '-',
                    alteracao_status: '-',
                    planta_colaborador: '-',
                    planta_status: '-'
                }];
            }

            var tabela = document.querySelector('#tabela-obra tbody');
            tabela.innerHTML = ''; // Limpa a tabela antes de adicionar os novos dados

            data.imagens.forEach(function (item) {
                idsImagensObra.push(parseInt(item.imagem_id));
                var row = document.createElement('tr');
                row.classList.add('linha-tabela');
                row.setAttribute('data-id', item.imagem_id);

                var cellNomeImagem = document.createElement('td');
                cellNomeImagem.textContent = item.imagem_nome;
                cellNomeImagem.setAttribute('antecipada', item.antecipada);
                row.appendChild(cellNomeImagem);

                if (Boolean(parseInt(item.antecipada))) {
                    cellNomeImagem.style.backgroundColor = '#ff9d00';
                }

                var cellTipoImagem = document.createElement('td');
                cellTipoImagem.textContent = item.tipo_imagem;
                row.appendChild(cellTipoImagem);

                var colunas = [
                    { col: 'caderno', label: 'Caderno' },
                    { col: 'modelagem', label: 'Modelagem' },
                    { col: 'composicao', label: 'Composição' },
                    { col: 'finalizacao', label: 'Finalização' },
                    { col: 'pos_producao', label: 'Pós Produção' },
                    { col: 'alteracao', label: 'Alteração' },
                    { col: 'planta', label: 'Planta' }
                ];

                colunas.forEach(function (coluna) {
                    var cellColaborador = document.createElement('td');
                    var cellStatus = document.createElement('td');
                    cellColaborador.textContent = item[`${coluna.col}_colaborador`] || '-';
                    cellStatus.textContent = item[`${coluna.col}_status`] || '-';
                    row.appendChild(cellColaborador);
                    row.appendChild(cellStatus);

                    applyStyleNone(cellColaborador, cellStatus, item[`${coluna.col}_colaborador`]);
                    applyStatusStyle(cellStatus, item[`${coluna.col}_status`], item[`${coluna.col}_colaborador`]);
                });

                tabela.appendChild(row);
            });

            // Adicionar os event listeners aos botões "Anterior" e "Próximo" APÓS carregar os dados
            const btnAnterior = document.getElementById("btnAnterior");
            const btnProximo = document.getElementById("btnProximo");

            btnAnterior.addEventListener("click", () => {
                navegar(-1); // Usando uma função para simplificar a lógica
            });

            btnProximo.addEventListener("click", () => {
                navegar(1); // Usando uma função para simplificar a lógica
            });

            // Adicionando evento para as teclas de seta
            document.addEventListener("keydown", (event) => {
                if (event.key === "ArrowLeft") {
                    navegar(-1); // Seta para a esquerda
                } else if (event.key === "ArrowRight") {
                    navegar(1); // Seta para a direita
                }
            });


            function navegar(direcao) {
                // Atualiza o índice da imagem atual
                indiceImagemAtual += direcao;

                // Garante que o índice está dentro dos limites
                if (indiceImagemAtual < 0) {
                    indiceImagemAtual = idsImagensObra.length - 1;
                } else if (indiceImagemAtual >= idsImagensObra.length) {
                    indiceImagemAtual = 0;
                }

                // Obtém o ID da imagem atual
                const idImagem = idsImagensObra[indiceImagemAtual];
                atualizarModal(idImagem);
                document.getElementById("imagem_id").value = idImagem;

                // Atualiza a seleção na tabela
                linhasTabela.forEach(linha => linha.classList.remove("selecionada"));
                let linhaSelecionada = document.querySelector(`tr[data-id="${idImagem}"]`);
                if (linhaSelecionada) {
                    linhaSelecionada.classList.add("selecionada");
                }
            }
            addEventListenersToRows();


            const obra = data.obra;
            document.getElementById('nomenclatura').textContent = obra.nomenclatura || "Nome não disponível";
            document.getElementById('data_inicio_obra').textContent = `Data de Início: ${obra.data_inicio}`;
            document.getElementById('prazo_obra').textContent = `Prazo: ${obra.prazo}`;
            document.getElementById('dias_trabalhados').innerHTML = obra.dias_trabalhados ? `<strong>${obra.dias_trabalhados}</strong> dias` : '';
            document.getElementById('total_imagens').textContent = `Total de Imagens: ${obra.total_imagens}`;
            document.getElementById('total_imagens_antecipadas').textContent = `Imagens Antecipadas: ${obra.total_imagens_antecipadas}`;

            const funcoes = data.funcoes;
            const nomesFuncoes = funcoes.map(funcao => funcao.nome_funcao);
            const porcentagensFinalizadas = funcoes.map(funcao => parseFloat(funcao.porcentagem_finalizada));

            const funcoesDiv = document.getElementById('funcoes');
            funcoesDiv.innerHTML = "";
            data.funcoes.forEach(funcao => {
                const funcaoDiv = document.createElement('div');
                funcaoDiv.classList.add('funcao');
                funcaoDiv.innerHTML = `
                <strong>${funcao.nome_funcao}</strong><br>
                Total de Imagens: ${funcao.total_imagens}<br>
                Imagens Finalizadas: ${funcao.funcoes_finalizadas}<br>
                Porcentagem Finalizada: ${funcao.porcentagem_finalizada}%<br><br>
            `;
                funcoesDiv.appendChild(funcaoDiv);
            });

            const infosDiv = document.getElementById('infos');

            // Limpa o conteúdo da div
            infosDiv.innerHTML = "";

            // Verifica se há dados no array
            if (data.infos.length === 0) {
                // Se não houver dados, oculta o botão
            } else {
                // Preenche a div com as informações
                data.infos.forEach(info => {
                    const infoDiv = document.createElement('div');
                    infoDiv.classList.add('info');
                    infoDiv.innerHTML = `
                        <p class="info-descricao">${info.descricao}</p>
                        <p class="info-data">${info.data}</p>
                    `;
                    infosDiv.appendChild(infoDiv);
                });

            }



            // const valores = data.valores;
            // document.getElementById('valor_orcamento').textContent = `R$ ${parseFloat(valores.valor_orcamento).toFixed(2)}`;
            // document.getElementById('valor_producao').textContent = `R$ ${parseFloat(valores.custo_producao).toFixed(2)}`;
            // document.getElementById('valor_fixo').textContent = `R$ ${parseFloat(valores.custo_fixo).toFixed(2)}`;
            // document.getElementById('lucro').textContent = `R$ ${parseFloat(valores.lucro).toFixed(2)}`;

            const ctx = document.getElementById('graficoPorcentagem').getContext('2d');
            if (chartInstance) {
                chartInstance.destroy();
            }
            chartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: nomesFuncoes,
                    datasets: [{
                        label: 'Porcentagem de Conclusão (%)',
                        data: porcentagensFinalizadas,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.2)',  // Cor para a 1ª barra
                            'rgba(255, 99, 132, 0.2)',  // Cor para a 2ª barra
                            'rgba(255, 159, 64, 0.2)',  // Cor para a 3ª barra
                            'rgba(75, 192, 192, 0.2)',  // Cor para a 4ª barra
                            'rgba(153, 102, 255, 0.2)', // Cor para a 5ª barra
                            'rgba(255, 159, 64, 0.2)'   // Cor para a 6ª barra, e assim por diante
                        ],
                        borderColor: [
                            'rgba(54, 162, 235, 1)',  // Cor para a borda da 1ª barra
                            'rgba(255, 99, 132, 1)',  // Cor para a borda da 2ª barra
                            'rgba(255, 159, 64, 1)',  // Cor para a borda da 3ª barra
                            'rgba(75, 192, 192, 1)',  // Cor para a borda da 4ª barra
                            'rgba(153, 102, 255, 1)', // Cor para a borda da 5ª barra
                            'rgba(255, 159, 64, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 10
                            }
                        }
                    }
                }
            });

        })
        .catch(error => console.error('Erro ao carregar funções:', error));
}


function filtrarTabela() {
    var tipoImagemFiltro = document.getElementById("tipo_imagem").value.toLowerCase(); // Captura o filtro de tipo de imagem
    var antecipadaFiltro = document.getElementById("antecipada_obra").value; // Captura o filtro de antecipada
    var tabela = document.getElementById("tabela-obra"); // Tabela de imagens
    var tbody = tabela.getElementsByTagName("tbody")[0]; // Obtém o corpo da tabela
    var linhas = tbody.getElementsByTagName("tr"); // Obtém todas as linhas da tabela

    for (var i = 0; i < linhas.length; i++) {
        var tipoImagemColuna = linhas[i].getElementsByTagName("td")[1].textContent || linhas[i].getElementsByTagName("td")[1].innerText; // Obtém o tipo de imagem da 2ª coluna (ajustado para corresponder à estrutura da sua tabela)

        // Verifica o valor do atributo antecipada da linha (onde o atributo é armazenado no tr)
        var isAntecipada = linhas[i].getAttribute("antecipada") === '1';

        var mostrarLinha = true;

        // Filtro para tipo de imagem
        if (tipoImagemFiltro && tipoImagemFiltro !== "0" && tipoImagemColuna.toLowerCase() !== tipoImagemFiltro.toLowerCase()) {
            mostrarLinha = false;
        }

        // Filtro para imagens antecipadas
        if (antecipadaFiltro === "Antecipada" && !isAntecipada) {
            mostrarLinha = false;
        }

        // Exibe ou esconde a linha dependendo do filtro
        linhas[i].style.display = mostrarLinha ? "" : "none";
    }
}

// Adiciona evento para filtrar sempre que o filtro mudar
document.getElementById("tipo_imagem").addEventListener("change", filtrarTabela);
document.getElementById("antecipada_obra").addEventListener("change", filtrarTabela);

function applyStatusStyle(cell, status, colaborador) {
    if (colaborador === 'Não se aplica') {
        return;
    }

    switch (status) {
        case 'Finalizado':
            cell.style.backgroundColor = 'green';
            cell.style.color = 'white';
            break;
        case 'Em andamento':
            cell.style.backgroundColor = 'orange';
            cell.style.color = 'black';
            break;
        case 'Em aprovação':
            cell.style.backgroundColor = 'yellow';
            cell.style.color = 'black';
            break;
        default:
            cell.style.backgroundColor = '';
            cell.style.color = '';
    }
}

function applyStyleNone(cell, cell2, nome) {
    if (nome === 'Não se aplica') {
        cell.style.backgroundColor = '#fff8ab';
        cell.style.color = 'black';
        cell2.style.backgroundColor = '#fff8ab';
        cell2.style.color = 'black';
    } else {
        cell.style.backgroundColor = '';
        cell.style.color = '';
        cell2.style.backgroundColor = '';
        cell2.style.color = '';
    }
}

// var sidebar = document.getElementById("sidebar");
// var toggleButton = document.getElementById("toggleSidebar");

// // Adiciona o evento de clique no botão (que contém o ícone)
// toggleButton.addEventListener("click", function () {
//     // Verifica se a sidebar está oculta (display: none)
//     if (sidebar.style.display === "none" || sidebar.style.display === "") {
//         // Torna a sidebar visível
//         sidebar.style.display = "flex";
//         toggleButton.style.display = "none";

//     } else {
//         // Oculta a sidebar
//         sidebar.style.display = "none";
//         toggleButton.style.display = "flex";

//     }
// });

// // Fecha a sidebar se o usuário clicar fora dela
// window.onclick = function (event) {
//     if (event.target !== sidebar && event.target !== toggleButton && !toggleButton.contains(event.target)) {
//         sidebar.style.display = "none"; // Fecha a sidebar se clicado fora
//         toggleButton.style.display = "flex";

//     }
// };
// window.ontouchstart = function (event) {
//     if (event.target !== sidebar && event.target !== toggleButton && !toggleButton.contains(event.target)) {
//         sidebar.style.display = "none"; // Fecha a sidebar se clicado fora
//         toggleButton.style.display = "flex";

//     }
// };



document.getElementById("salvar_funcoes").addEventListener("click", function (event) {
    event.preventDefault();

    var linhaSelecionada = document.querySelector(".linha-tabela.selecionada");
    if (!linhaSelecionada) {
        Toastify({
            text: "Nenhuma imagem selecionada",
            duration: 3000,
            close: true,
            gravity: "top",
            position: "left",
            backgroundColor: "red",
            stopOnFocus: true,
        }).showToast();
        return;
    }

    var idImagemSelecionada = linhaSelecionada.getAttribute("data-id");

    var textos = {};
    var pElements = document.querySelectorAll(".form-edicao p");
    pElements.forEach(function (p) {
        textos[p.id] = p.textContent.trim();
    });

    var dados = {
        imagem_id: idImagemSelecionada,
        caderno_id: document.getElementById("opcao_caderno").value || "",
        status_caderno: document.getElementById("status_caderno").value || "",
        prazo_caderno: document.getElementById("prazo_caderno").value || "",
        obs_caderno: document.getElementById("obs_caderno").value || "",
        comp_id: document.getElementById("opcao_comp").value || "",
        status_comp: document.getElementById("status_comp").value || "",
        prazo_comp: document.getElementById("prazo_comp").value || "",
        obs_comp: document.getElementById("obs_comp").value || "",
        model_id: document.getElementById("opcao_model").value || "",
        status_modelagem: document.getElementById("status_modelagem").value || "",
        prazo_modelagem: document.getElementById("prazo_modelagem").value || "",
        obs_modelagem: document.getElementById("obs_modelagem").value || "",
        final_id: document.getElementById("opcao_final").value || "",
        status_finalizacao: document.getElementById("status_finalizacao").value || "",
        prazo_finalizacao: document.getElementById("prazo_finalizacao").value || "",
        obs_finalizacao: document.getElementById("obs_finalizacao").value || "",
        pos_id: document.getElementById("opcao_pos").value || "",
        status_pos: document.getElementById("status_pos").value || "",
        prazo_pos: document.getElementById("prazo_pos").value || "",
        obs_pos: document.getElementById("obs_pos").value || "",
        alteracao_id: document.getElementById("opcao_alteracao").value || "",
        status_alteracao: document.getElementById("status_alteracao").value || "",
        prazo_alteracao: document.getElementById("prazo_alteracao").value || "",
        obs_alteracao: document.getElementById("obs_alteracao").value || "",
        planta_id: document.getElementById("opcao_planta").value || "",
        status_planta: document.getElementById("status_planta").value || "",
        prazo_planta: document.getElementById("prazo_planta").value || "",
        obs_planta: document.getElementById("obs_planta").value || "",
        filtro_id: document.getElementById("opcao_filtro").value || "",
        status_filtro: document.getElementById("status_filtro").value || "",
        prazo_filtro: document.getElementById("prazo_filtro").value || "",
        obs_filtro: document.getElementById("obs_filtro").value || "",
        textos: textos,
        status_id: document.getElementById("opcao_status").value || ""
    };

    $.ajax({
        type: "POST",
        url: "https://www.improov.com.br/sistema/insereFuncao.php",
        data: dados,
        success: function (response) {
            console.log(response);
            Toastify({
                text: "Dados salvos com sucesso!",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "left",
                backgroundColor: "green",
                stopOnFocus: true,
            }).showToast();


            // form_edicao.style.display = "none"
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
        }
    });
});

const modalInfos = document.getElementById('modalInfos')
const modalOrcamento = document.getElementById('modalOrcamento')
const modal = document.getElementById('modalAcompanhamento');
const modalObs = document.getElementById('modalObservacao');
const modalImages = document.getElementById('editImagesModal');
const infosModal = document.getElementById('infosModal');
const form_edicao = document.getElementById('form-edicao');


document.getElementById('orcamento').addEventListener('click', function () {
    document.getElementById('modalOrcamento').style.display = 'flex';
});


document.addEventListener('DOMContentLoaded', function () {

    const idObra = localStorage.getItem('obraId'); // Obtém o ID da obra armazenado no localStorage
    const acompanhamentoConteudo = document.getElementById('list_acomp');

    if (idObra) {
        abrirModalAcompanhamento(idObra); // Carrega os acompanhamentos automaticamente
    } else {
        console.warn('ID da obra não encontrado no localStorage.');
    }

    function abrirModalAcompanhamento(obraId) {
        fetch(`../Obras/getAcompanhamentoEmail.php?idobra=${obraId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erro ao carregar dados: ${response.status}`);
                }
                return response.json(); // Converte a resposta para JSON
            })
            .then(acompanhamentos => {
                // Limpa o conteúdo anterior
                acompanhamentoConteudo.innerHTML = '';

                if (acompanhamentos.length > 0) {
                    acompanhamentos.forEach(acomp => {
                        const item = document.createElement('p');
                        item.innerHTML = `
                        <p class="acomp-assunto"><strong>Assunto:</strong> ${acomp.assunto}</p>
                        <p class="acomp-data"><strong>Data:</strong> ${acomp.data}</p>
                    `;
                        acompanhamentoConteudo.appendChild(item);
                    });
                } else {
                    acompanhamentoConteudo.innerHTML = '<p>Nenhum acompanhamento encontrado.</p>';
                }

            })
            .catch(error => {
                console.error('Erro:', error);
            });
    }
});



document.getElementById('acomp').addEventListener('click', function () {
    modal.style.display = 'block';
});

document.getElementById('obsAdd').addEventListener('click', function () {
    modalObs.style.display = 'block';
});

const closeModal = document.querySelector('.close-modal');
closeModal.addEventListener('click', function () {
    modal.style.display = 'none';
});

closeModal.addEventListener('touchstart', function () {
    modal.style.display = 'none';
});

const closeModalImages = document.querySelector('.close-modal-images');
closeModalImages.addEventListener('click', function () {
    editImagesModal.style.display = 'none';
});

closeModalImages.addEventListener('touchstart', function () {
    editImagesModal.style.display = 'none';
});



document.getElementById("adicionar_acomp").addEventListener("submit", function (e) {
    e.preventDefault(); // Previne o envio padrão do formulário

    // Obtendo os dados do formulário
    const assunto = document.getElementById("assunto").value.trim(); // Valor do textarea assunto
    const data = document.getElementById("data_acomp").value; // Data selecionada

    console.log(assunto, data, obraId)

    // Validações simples
    if (!obraId || !assunto || !data) {
        Toastify({
            text: "Preencha todos os campos corretamente.",
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: "#f44336", // Cor de erro
        }).showToast();
        return;
    }

    // Enviando os dados via AJAX
    fetch("addAcompanhamento.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
            idobra: obraId,
            assunto: assunto,
            data: data,
        }),
    })
        .then(response => response.json()) // Converte a resposta para JSON
        .then(data => {
            // Exibe o Toastify com base na resposta
            if (data.success) {
                Toastify({
                    text: data.message,
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#4caf50", // Cor de sucesso
                }).showToast();
                document.getElementById("adicionar_acomp").reset(); // Reseta o formulário
                modal.style.display = 'none';
            } else {
                Toastify({
                    text: data.message,
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#f44336", // Cor de erro
                }).showToast();
            }
        })
        .catch(error => {
            console.error("Erro ao enviar acompanhamento:", error);
            Toastify({
                text: "Erro ao adicionar acompanhamento.",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "#f44336", // Cor de erro
            }).showToast();
        });
});

document.getElementById("adicionar_observacao").addEventListener("submit", function (e) {
    e.preventDefault(); // Previne o envio padrão do formulário

    // Obtendo os dados do formulário
    const desc = document.getElementById("desc").value.trim();

    // Validações simples
    if (!desc) {
        Toastify({
            text: "Preencha todos os campos corretamente.",
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: "#f44336", // Cor de erro
        }).showToast();
        return;
    }

    // Enviando os dados via AJAX
    fetch("addAcompanhamento.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
            idobra: obraId,
            desc: desc,
        }),
    })
        .then(response => response.json()) // Converte a resposta para JSON
        .then(data => {
            // Exibe o Toastify com base na resposta
            if (data.success) {
                Toastify({
                    text: data.message,
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#4caf50", // Cor de sucesso
                }).showToast();
                document.getElementById("adicionar_observacao").reset(); // Reseta o formulário
                modalObs.style.display = 'none';
            } else {
                Toastify({
                    text: data.message,
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#f44336", // Cor de erro
                }).showToast();
            }
        })
        .catch(error => {
            console.error("Erro ao enviar acompanhamento:", error);
            Toastify({
                text: "Erro ao adicionar acompanhamento.",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "#f44336", // Cor de erro
            }).showToast();
        });
});


window.addEventListener('click', function (event) {
    if (event.target == form_edicao) {
        form_edicao.style.display = "none"
    }
    if (event.target == modal) {
        modal.style.display = "none"
    }
    if (event.target == modalInfos) {
        modalInfos.style.display = "none";
    }
    if (event.target == modalOrcamento) {
        modalOrcamento.style.display = "none";
    }
    if (event.target == editImagesModal) {
        editImagesModal.style.display = "none";
    }
    if (event.target == addImagemModal) {
        addImagemModal.style.display = "none";
    }
    if (event.target == infosModal) {
        infosModal.style.display = "none";
    }
});

window.addEventListener('touchstart', function (event) {
    if (event.target == form_edicao) {
        form_edicao.style.display = "none"
    }
    if (event.target == modal) {
        modal.style.display = "none"
    }
    if (event.target == modalInfos) {
        modalInfos.style.display = "none";
    }
    if (event.target == modalOrcamento) {
        modalOrcamento.style.display = "none";
    }
    if (event.target == editImagesModal) {
        editImagesModal.style.display = "none";
    }
    if (event.target == addImagemModal) {
        addImagemModal.style.display = "none";
    }
    if (event.target == infosModal) {
        infosModal.style.display = "none";
    }
});



document.getElementById('formOrcamento').addEventListener('submit', function (e) {
    e.preventDefault();

    const idObra = document.getElementById('idObraOrcamento').value;
    const tipo = document.getElementById('tipo').value;
    const valor = document.getElementById('valor').value;
    const data = document.getElementById('data').value;

    // Enviar os dados para o backend
    fetch('salvarOrcamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ idObra, tipo, valor, data }),
    })
        .then(response => response.json())
        .then(data => {
            alert('Orçamento salvo com sucesso!');
            document.getElementById('modalOrcamento').style.display = 'none'; // Fecha o modal
        })
        .catch(error => {
            console.error('Erro ao salvar orçamento:', error);
        });
});



window.addEventListener('touchstart', function (event) {
    if (event.target == modalInfos) {
        modalInfos.style.display = "none";
    }
    if (event.target == modalOrcamento) {
        modalOrcamento.style.display = "none";
    }
    if (event.target == form_edicao) {
        form_edicao.style.display = "none"
    }
    if (event.target == modal) {
        modal.style.display = "none"
    }
});

document.querySelectorAll('.titulo_imagem').forEach(titulo_imagem => {
    titulo_imagem.addEventListener('click', () => {
        const conteudo_imagens = titulo_imagem.nextElementSibling;
        if (conteudo_imagens.style.display === 'none') {
            conteudo_imagens.style.display = 'block';
            titulo_imagem.querySelector('i').classList.remove('fa-chevron-down');
            titulo_imagem.querySelector('i').classList.add('fa-chevron-up');
            conteudo_imagens.classList.add('show-in');
        } else {
            conteudo_imagens.style.display = 'none';
            titulo_imagem.querySelector('i').classList.remove('fa-chevron-up');
            titulo_imagem.querySelector('i').classList.add('fa-chevron-down');
        }
    });
});



let modifiedImages = new Set(); // Armazena IDs das imagens alteradas

document.getElementById("editImagesBtn").addEventListener("click", () => {
    // Obtém o 'obraId' do localStorage
    const obraId = localStorage.getItem("obraId");

    if (!obraId) {
        alert("ID da obra não encontrado!");
        return;
    }

    // Faz a requisição para buscar imagens relacionadas à obra
    fetch("infosImagens.php", {
        method: "POST", // Usa POST para enviar dados ao servidor
        headers: {
            "Content-Type": "application/json", // Especifica que o corpo da requisição será JSON
        },
        body: JSON.stringify({ obraId }), // Envia o 'obraId' como JSON
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error("Erro ao buscar imagens");
            }
            return response.json();
        })
        .then((images) => {
            const imageList = document.getElementById("imageList");
            imageList.innerHTML = ""; // Limpa o conteúdo existente

            images.forEach((image) => {
                const imageContainer = document.createElement("div");
                imageContainer.innerHTML = `
                    <div class="image-item">
                        <div class="titulo_imagem">
                            <h4>${image.imagem_nome}</h4>
                            <i class="fas fa-chevron-down toggle-options"></i>
                        </div>

                        <div class="conteudo_imagens" id="conteudo_imagens">
                            <label>Imagem: <input type="text" data-id="${image.idimagem}" name="imagem_nome" value="${image.imagem_nome}"></label><br>
                            <label>Recebimento Arquivos: <input type="date" data-id="${image.idimagem}" name="recebimento_arquivos" value="${image.recebimento_arquivos}"></label><br>
                            <label>Data de Início: <input type="date" data-id="${image.idimagem}" name="data_inicio" value="${image.data_inicio}"></label><br>
                            <label>Prazo: <input type="date" data-id="${image.idimagem}" name="prazo" value="${image.prazo}"></label><br>
                            <label>Tipo de Imagem: <input type="text" data-id="${image.idimagem}" name="tipo_imagem" value="${image.tipo_imagem}"></label>
                        </div>
                    </div>
                `;
                imageList.appendChild(imageContainer);


                // Adiciona o evento de clique para mostrar/esconder o conteúdo e trocar o ícone
                const tituloImagem = imageContainer.querySelector(".titulo_imagem");
                const conteudoImagens = imageContainer.querySelector(".conteudo_imagens");
                const toggleIcon = tituloImagem.querySelector(".toggle-options");

                tituloImagem.addEventListener("click", () => {
                    if (conteudoImagens.style.display === "none") {
                        conteudoImagens.classList.add('show-in')
                        conteudoImagens.style.display = "block";
                        toggleIcon.classList.remove("fa-chevron-down");
                        toggleIcon.classList.add("fa-chevron-up");
                    } else {
                        conteudoImagens.style.display = "none";
                        toggleIcon.classList.remove("fa-chevron-up");
                        toggleIcon.classList.add("fa-chevron-down");
                    }
                });
            });

            // Exibe o modal
            document.getElementById("editImagesModal").style.display = "block";
        })
        .catch((error) => {
            console.error("Erro:", error);
            alert("Não foi possível carregar as imagens.");
        });
});


// Detecta alterações nos campos
document.getElementById("imageList").addEventListener("input", event => {
    const imageId = event.target.getAttribute("data-id");
    modifiedImages.add(imageId); // Marca a imagem como alterada
    document.getElementById("unsavedChanges").style.display = "flex"; // Mostra a mensagem de aviso
});

// Salva as alterações
document.getElementById("saveChangesBtn").addEventListener("click", () => {
    const updates = Array.from(modifiedImages).map(id => {
        return {
            idimagem: id,
            imagem_nome: document.querySelector(`input[name="imagem_nome"][data-id="${id}"]`).value,
            recebimento_arquivos: document.querySelector(`input[name="recebimento_arquivos"][data-id="${id}"]`).value,
            data_inicio: document.querySelector(`input[name="data_inicio"][data-id="${id}"]`).value,
            prazo: document.querySelector(`input[name="prazo"][data-id="${id}"]`).value,
            tipo_imagem: document.querySelector(`input[name="tipo_imagem"][data-id="${id}"]`).value
        };
    });

    fetch("saveImages.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(updates)
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert("Alterações salvas com sucesso!");
                modifiedImages.clear();
                document.getElementById("unsavedChanges").style.display = "none"; // Esconde a mensagem
            } else {
                alert("Erro ao salvar alterações.");
            }
        })
        .catch(error => {
            console.error("Erro ao salvar alterações:", error);
            alert("Erro ao salvar alterações. Por favor, tente novamente.");
        });
});

const addImagemModal = document.getElementById('add-imagem');
const addImagem = document.getElementById('addImagem');
addImagem.addEventListener('click', function () {
    addImagemModal.style.display = 'flex';
})

function submitFormImagem(event) {
    event.preventDefault();

    const opcaoCliente = document.getElementById('opcao_cliente').value;
    const opcaoObra = document.getElementById('opcao_obra').value;
    const arquivo = document.getElementById('arquivos').value;
    const data_inicio = document.getElementById('data_inicio').value;
    const prazo = document.getElementById('prazo').value;
    const imagem = document.getElementById('nome-imagem').value;
    const tipo = document.getElementById('tipo-imagem').value;

    const data = {
        opcaoCliente: opcaoCliente,
        opcaoObra: opcaoObra,
        arquivo: arquivo,
        data_inicio: data_inicio,
        prazo: prazo,
        imagem: imagem,
        tipo: tipo
    };

    console.log(data);

    fetch('inserir_imagem.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                Toastify({
                    text: result.message,
                    duration: 3000,
                    close: true,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "green",
                    stopOnFocus: true,
                }).showToast();

                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                Toastify({
                    text: result.message,
                    duration: 3000,
                    close: true,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "red",
                    stopOnFocus: true,
                }).showToast();
            }

        })
        .catch(error => {
            console.error('Erro:', error);
            Toastify({
                text: "Erro ao tentar salvar. Tente novamente.",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: "red",
                stopOnFocus: true,
            }).showToast();
        });
}


document.addEventListener("DOMContentLoaded", () => {
    const resizeHandles = document.querySelectorAll(".resize-handle");

    resizeHandles.forEach(handle => {
        let startX;
        let startWidth;

        handle.addEventListener("mousedown", (e) => {
            const th = handle.parentElement;
            startX = e.pageX;
            startWidth = th.offsetWidth;

            const onMouseMove = (e) => {
                const newWidth = startWidth + (e.pageX - startX);
                th.style.width = `${newWidth}px`;
            };

            const onMouseUp = () => {
                document.removeEventListener("mousemove", onMouseMove);
                document.removeEventListener("mouseup", onMouseUp);
            };

            document.addEventListener("mousemove", onMouseMove);
            document.addEventListener("mouseup", onMouseUp);
        });
    });
});



// Adiciona o botão de mostrar todos
const btnMostrarAcomps = document.getElementById('btnMostrarAcomps');
const acompanhamentoConteudo = document.getElementById('list_acomp');
// Ao clicar no botão "Mostrar Todos"
btnMostrarAcomps.addEventListener('click', () => {
    acompanhamentoConteudo.classList.toggle('expanded');
    const isExpanded = acompanhamentoConteudo.classList.contains('expanded');
    btnMostrarAcomps.innerHTML = isExpanded ?
        '<i class="fas fa-chevron-up"></i>' :
        '<i class="fas fa-chevron-down"></i>';
});