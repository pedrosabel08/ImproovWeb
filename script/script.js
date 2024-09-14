document.addEventListener("DOMContentLoaded", function () {
    var linhasTabela = document.querySelectorAll(".linha-tabela");

    linhasTabela.forEach(function (linha) {
        linha.addEventListener("click", function () {
            linhasTabela.forEach(function (outraLinha) {
                outraLinha.classList.remove("selecionada");
            });

            linha.classList.add("selecionada");

            var idImagemSelecionada = linha.getAttribute("data-id");
            document.getElementById("imagem_id").value = idImagemSelecionada;

            limparCampos();

            $.ajax({
                type: "GET",
                dataType: "json",
                url: "http://www.improov.com.br/sistema/buscaLinhaAJAX.php",
                data: { ajid: idImagemSelecionada },
                success: function (response) {
                    if (response.nome_imagem) {
                        document.getElementById("campoNomeImagem").textContent = response.nome_imagem;
                    }

                    if (response.funcoes && response.funcoes.length > 0) {
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
                            }
                            if (selectElement) {
                                selectElement.value = funcao.colaborador_id;
                            }
                        });
                    }

                    var statusSelect = document.getElementById("opcao_status");
                    if (response.status_id !== null) {
                        statusSelect.value = response.status_id;
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("Erro na requisição AJAX: " + textStatus, errorThrown);
                }
            });

            console.log("Linha selecionada: ID da imagem = " + idImagemSelecionada);
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

        document.getElementById("opcao_caderno").value = "";
        document.getElementById("opcao_model").value = "";
        document.getElementById("opcao_comp").value = "";
        document.getElementById("opcao_final").value = "";
        document.getElementById("opcao_pos").value = "";
        document.getElementById("opcao_alteracao").value = "";
        document.getElementById("opcao_planta").value = "";
        document.getElementById("opcao_status").value = "";
    }

    function buscarNomeImagem(idImagem) {
        $.ajax({
            type: "GET",
            dataType: "json",
            url: "http://www.improov.com.br/sistema/buscaNomeImagem.php",
            data: { ajid: idImagem },
            success: function (response) {
                if (response.imagem_nome !== undefined && response.status_id !== undefined) {
                    if (response.imagem_nome && response.status_id) {
                        document.getElementById("campoNomeImagem").textContent = response.imagem_nome;

                        var opcaoStatus = document.getElementById("opcao_status");
                        if (opcaoStatus) {
                            var statusId = response.status_id.toString();

                            opcaoStatus.value = statusId;

                            var found = Array.from(opcaoStatus.options).some(option => option.value === statusId);
                            if (!found) {
                                console.warn("Status ID não encontrado nas opções do select:", statusId);
                            }
                        } else {
                            console.warn("Elemento do select não encontrado.");
                        }
                    } else {
                        Toastify({
                            text: "Nome da imagem ou status não encontrado.",
                            duration: 3000,
                            close: true,
                            gravity: "top",
                            position: "left",
                            backgroundColor: "red",
                            stopOnFocus: true,
                        }).showToast();
                    }
                } else {
                    Toastify({
                        text: "Resposta incompleta do servidor.",
                        duration: 3000,
                        close: true,
                        gravity: "top",
                        position: "left",
                        backgroundColor: "red",
                        stopOnFocus: true,
                    }).showToast();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Erro na requisição AJAX para o nome da imagem: " + textStatus, errorThrown);
            }
        });
    }

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
            textos: textos,
            status_id: document.getElementById("opcao_status").value || ""
        };

        $.ajax({
            type: "POST",
            url: "http://www.improov.com.br/sistema/insereFuncao.php",
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
});

function filtrarTabela() {
    var indiceColuna = document.getElementById("colunaFiltro").value;
    var filtro = document.getElementById("pesquisa").value.toLowerCase();
    var tabela = document.getElementById("tabelaClientes");
    var tbody = tabela.getElementsByTagName("tbody")[0];
    var linhas = tbody.getElementsByTagName("tr");

    for (var i = 0; i < linhas.length; i++) {
        var coluna = linhas[i].getElementsByTagName("td")[indiceColuna];
        if (coluna) {
            var valorColuna = coluna.textContent || coluna.innerText;
            if (valorColuna.toLowerCase().indexOf(filtro) > -1) {
                linhas[i].style.display = "";
            } else {
                linhas[i].style.display = "none";
            }
        }
    }
}

document.getElementById("pesquisa").addEventListener("keyup", function (event) {
    if (event.key === "Enter") {
        filtrarTabela();
    }
});

function openModal(modalId, element) {

    closeModal('add-cliente');
    closeModal('add-imagem');
    closeModal('tabela-form');
    closeModal('filtro-colab');
    closeModal('filtro-obra');
    closeModal('follow-up');

    if (modalId) {
        document.getElementById(modalId).style.display = 'flex';
    }

    var navLinks = document.querySelectorAll('nav a');
    navLinks.forEach(function (link) {
        link.classList.remove('active');
    });

    if (element) {
        element.classList.add('active');
    }
}

function openModalClass(modalClass, element) {

    closeModal('add-cliente');
    closeModal('add-imagem');
    closeModal('tabela-form');
    closeModal('filtro-colab');
    closeModal('filtro-obra');
    closeModal('follow-up');

    if (modalClass) {
        var modal = document.querySelector('.' + modalClass);
        if (modal) {
            modal.style.display = 'grid';
        }
    }

    var navLinks = document.querySelectorAll('nav a');
    navLinks.forEach(function (link) {
        link.classList.remove('active');
    });

    if (element) {
        element.classList.add('active');
    }
}

function closeModal(modalId) {
    if (modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    var navLinks = document.querySelectorAll('nav a');
    navLinks.forEach(function (link) {
        link.classList.remove('active');
    });

    var verImagensLink = document.querySelector('nav a[href="#filtro"]');
    verImagensLink.classList.add('active');
}


function submitForm(event) {
    event.preventDefault();

    const opcao = document.getElementById('opcao-cliente').value;
    const nome = document.getElementById('nome').value;

    const data = {
        opcao: opcao,
        nome: nome
    };

    fetch('inserircliente_obra.php', {
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

            closeModal('add-cliente');
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

function submitFormImagem(event) {
    event.preventDefault();

    const opcaoCliente = document.getElementById('opcao_cliente').value;
    const opcaoObra = document.getElementById('opcao_obra').value;
    const arquivo = document.getElementById('arquivos').value;
    const data_inicio = document.getElementById('data_inicio').value;
    const prazo = document.getElementById('prazo').value;
    const imagem = document.getElementById('nome-imagem').value;

    console.log(arquivo, data_inicio, prazo)

    const data = {
        opcaoCliente: opcaoCliente,
        opcaoObra: opcaoObra,
        arquivo: arquivo,
        data_inicio: data_inicio,
        prazo: prazo,
        imagem: imagem
    };

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

            closeModal('add-cliente');
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


document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('colaboradorSelect').addEventListener('change', carregarDados);
    document.getElementById('dataInicio').addEventListener('change', carregarDados);
    document.getElementById('dataFim').addEventListener('change', carregarDados);

    function carregarDados() {
        var colaboradorId = document.getElementById('colaboradorSelect').value;
        var dataInicio = document.getElementById('dataInicio').value;
        var dataFim = document.getElementById('dataFim').value;

        if (colaboradorId) {
            var url = 'getFuncoesPorColaborador.php?colaborador_id=' + colaboradorId;

            if (dataInicio) {
                url += '&data_inicio=' + encodeURIComponent(dataInicio);
            }
            if (dataFim) {
                url += '&data_fim=' + encodeURIComponent(dataFim);
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    var tabela = document.querySelector('#tabela-colab tbody');
                    tabela.innerHTML = '';

                    data.forEach(function (item) {
                        var row = document.createElement('tr');
                        var cellNomeImagem = document.createElement('td');
                        cellNomeImagem.textContent = item.imagem_nome;
                        var cellFuncao = document.createElement('td');
                        cellFuncao.textContent = item.funcao;
                        var cellStatus = document.createElement('td');
                        cellStatus.textContent = item.status;
                        var cellPrazoImagem = document.createElement('td');
                        cellPrazoImagem.textContent = item.prazo;


                        row.appendChild(cellNomeImagem);
                        row.appendChild(cellStatus);
                        row.appendChild(cellPrazoImagem);
                        tabela.appendChild(row);
                    });

                    document.getElementById('totalImagens').textContent = data.length;
                })
                .catch(error => console.error('Erro ao carregar funções:', error));
        } else {
            document.querySelector('#tabela-colab tbody').innerHTML = '';
            document.getElementById('totalImagens').textContent = '0';
        }
    }

});

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('obra').addEventListener('change', function () {
        var obraId = this.value;

        if (obraId) {
            fetch('getFuncoesPorObra.php?obra_id=' + obraId)
                .then(response => response.json())
                .then(data => {
                    var tabela = document.querySelector('#tabela-obra tbody');
                    tabela.innerHTML = '';

                    data.forEach(function (item) {
                        var row = document.createElement('tr');

                        var cellNomeImagem = document.createElement('td');
                        cellNomeImagem.textContent = item.imagem_nome;
                        row.appendChild(cellNomeImagem);

                        var cellCadernoColaborador = document.createElement('td');
                        cellCadernoColaborador.textContent = item.caderno_colaborador || '-';
                        var cellCadernoStatus = document.createElement('td');
                        cellCadernoStatus.textContent = item.caderno_status || '-';
                        row.appendChild(cellCadernoColaborador);
                        row.appendChild(cellCadernoStatus);
                        applyStatusStyle(cellCadernoStatus, item.caderno_status);

                        var cellModelagemColaborador = document.createElement('td');
                        cellModelagemColaborador.textContent = item.modelagem_colaborador || '-';
                        var cellModelagemStatus = document.createElement('td');
                        cellModelagemStatus.textContent = item.modelagem_status || '-';
                        row.appendChild(cellModelagemColaborador);
                        row.appendChild(cellModelagemStatus);
                        applyStatusStyle(cellModelagemStatus, item.modelagem_status);

                        var cellComposicaoColaborador = document.createElement('td');
                        cellComposicaoColaborador.textContent = item.composicao_colaborador || '-';
                        var cellComposicaoStatus = document.createElement('td');
                        cellComposicaoStatus.textContent = item.composicao_status || '-';
                        row.appendChild(cellComposicaoColaborador);
                        row.appendChild(cellComposicaoStatus);
                        applyStatusStyle(cellComposicaoStatus, item.composicao_status);

                        var cellFinalizacaoColaborador = document.createElement('td');
                        cellFinalizacaoColaborador.textContent = item.finalizacao_colaborador || '-';
                        var cellFinalizacaoStatus = document.createElement('td');
                        cellFinalizacaoStatus.textContent = item.finalizacao_status || '-';
                        row.appendChild(cellFinalizacaoColaborador);
                        row.appendChild(cellFinalizacaoStatus);
                        applyStatusStyle(cellFinalizacaoStatus, item.finalizacao_status);

                        var cellPosProducaoColaborador = document.createElement('td');
                        cellPosProducaoColaborador.textContent = item.pos_producao_colaborador || '-';
                        var cellPosProducaoStatus = document.createElement('td');
                        cellPosProducaoStatus.textContent = item.pos_producao_status || '-';
                        row.appendChild(cellPosProducaoColaborador);
                        row.appendChild(cellPosProducaoStatus);
                        applyStatusStyle(cellPosProducaoStatus, item.pos_producao_status);

                        var cellAlteracaoColaborador = document.createElement('td');
                        cellAlteracaoColaborador.textContent = item.alteracao_colaborador || '-';
                        var cellAlteracaoStatus = document.createElement('td');
                        cellAlteracaoStatus.textContent = item.alteracao_status || '-';
                        row.appendChild(cellAlteracaoColaborador);
                        row.appendChild(cellAlteracaoStatus);
                        applyStatusStyle(cellAlteracaoStatus, item.alteracao_status);

                        tabela.appendChild(row);
                    });
                })
                .catch(error => console.error('Erro ao carregar funções:', error));
        } else {
            document.querySelector('#tabela-obra tbody').innerHTML = '';
        }
    });


    document.getElementById('obra-follow').addEventListener('change', function () {
        var obraId = this.value;

        if (obraId) {
            fetch('followup.php?obra_id=' + obraId)
                .then(response => response.json())
                .then(data => {
                    var tabela = document.querySelector('#tabela-follow tbody');
                    tabela.innerHTML = '';

                    data.forEach(function (item) {
                        var row = document.createElement('tr');

                        var cellNomeImagem = document.createElement('td');
                        cellNomeImagem.textContent = item.imagem_nome;
                        row.appendChild(cellNomeImagem);

                        var cellStatusImagem = document.createElement('td');
                        cellStatusImagem.textContent = item.imagem_status;
                        row.appendChild(cellStatusImagem)
                        applyStatusImagem(cellStatusImagem, item.imagem_status)

                        var cellCadernoStatus = document.createElement('td');
                        cellCadernoStatus.textContent = item.caderno_status || '-';
                        row.appendChild(cellCadernoStatus);

                        var cellModelagemStatus = document.createElement('td');
                        cellModelagemStatus.textContent = item.modelagem_status || '-';
                        row.appendChild(cellModelagemStatus);

                        var cellComposicaoStatus = document.createElement('td');
                        cellComposicaoStatus.textContent = item.composicao_status || '-';
                        row.appendChild(cellComposicaoStatus);

                        var cellFinalizacaoStatus = document.createElement('td');
                        cellFinalizacaoStatus.textContent = item.finalizacao_status || '-';
                        row.appendChild(cellFinalizacaoStatus);

                        var cellPosProducaoStatus = document.createElement('td');
                        cellPosProducaoStatus.textContent = item.pos_producao_status || '-';
                        row.appendChild(cellPosProducaoStatus);

                        var cellAlteracaoStatus = document.createElement('td');
                        cellAlteracaoStatus.textContent = item.alteracao_status || '-';
                        row.appendChild(cellAlteracaoStatus);

                        tabela.appendChild(row);
                    });
                })
                .catch(error => console.error('Erro ao carregar funções:', error));
        } else {
            document.querySelector('#tabela-obra tbody').innerHTML = '';
        }
    });


});

function applyStatusStyle(cell, status) {
    switch (status) {
        case 'Finalizado':
            cell.style.backgroundColor = 'green';
            cell.style.color = 'white';
            break;
        case 'Em andamento':
            cell.style.backgroundColor = 'orange';
            cell.style.color = 'black';
            break;
        default:
            cell.style.backgroundColor = '';
            cell.style.color = '';
    }
}

function applyStatusImagem(cell, status) {
    switch (status) {
        case 'P00':
            cell.style.backgroundColor = '#ffc21c'
            break;
        case 'R00':
            cell.style.backgroundColor = '#1cf4ff'
            break;
        case 'R01':
            cell.style.backgroundColor = '#ff6200'
            break;
        case 'R02':
            cell.style.backgroundColor = '#ff3c00'
            break;
        case 'R03':
            cell.style.backgroundColor = '#ff0000'
            break;
        case 'EF':
            cell.style.backgroundColor = '#0dff00'
            break;
    }
}

// document.addEventListener('DOMContentLoaded', function () {
//     // Função para calcular a diferença em dias entre duas datas
//     function daysBetween(date1, date2) {
//         const oneDay = 24 * 60 * 60 * 1000; // Número de milissegundos em um dia
//         return Math.round((date2 - date1) / oneDay);
//     }

//     // Seleciona todas as linhas da tabela
//     const rows = document.querySelectorAll('#tabelaClientes tbody tr');

//     rows.forEach(row => {
//         // Obtém a célula que contém a data do prazo estimado
//         const prazoCell = row.cells[5]; // A célula do prazo estimado está na 6ª coluna (índice 5)
//         const prazoEstimado = prazoCell.textContent;

//         if (prazoEstimado) {
//             // Converte o valor da célula para uma data
//             const prazoDate = new Date(prazoEstimado);
//             const currentDate = new Date();

//             // Calcula a diferença em dias
//             const daysRemaining = daysBetween(currentDate, prazoDate);

//             // Aplica o estilo com base na diferença de dias
//             if (daysRemaining <= 5) {
//                 row.style.backgroundColor = 'red'; // Muda a cor do fundo para vermelho
//                 row.style.color = 'white'; // Muda a cor do texto para branco
//                 row.style.fontWeight = 'bold'; // Muda o peso da fonte para negrito
//             } else if (daysRemaining <= 10) {
//                 row.style.backgroundColor = 'orange'; // Muda a cor do fundo para laranja
//                 row.style.color = 'black'; // Muda a cor do texto para preto
//                 row.style.fontWeight = 'bold'; // Muda o peso da fonte para negrito
//             } else if (daysRemaining <= 30) {
//                 row.style.backgroundColor = 'yellow'; // Muda a cor do fundo para amarelo
//                 row.style.color = 'black'; // Muda a cor do texto para preto
//                 row.style.fontWeight = 'normal'; // Peso da fonte normal
//             } else {
//                 // Se não se encaixa em nenhum dos critérios acima, usa o estilo padrão
//                 row.style.backgroundColor = ''; // Cor padrão do fundo
//                 row.style.color = ''; // Cor padrão do texto
//                 row.style.fontWeight = ''; // Peso da fonte padrão
//             }
//         }
//     });
// });
