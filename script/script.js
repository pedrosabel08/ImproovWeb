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

document.addEventListener("DOMContentLoaded", function () {
    // Função para carregar e atualizar a tabela com dados de `atualizarTabela.php`
    function carregarTabela() {
        fetch('atualizarTabela.php')
            .then(response => response.json())
            .then(data => {
                const tbody = document.querySelector("#tabelaClientes tbody");
                tbody.innerHTML = ""; // Limpa o conteúdo atual

                let contadorStatusZero = 0;
                let contadorAntecipada = 0;

                if (data.length > 0) {
                    data.forEach(row => {
                        const tr = document.createElement("tr");
                        tr.classList.add("linha-tabela");
                        tr.setAttribute("data-id", row.idimagens_cliente_obra);
                        tr.setAttribute("antecipada", row.antecipada);

                        if (row.antecipada === '1') {
                            tr.style.backgroundColor = ('#ff9d00')
                            contadorAntecipada++;
                        }

                        if (row.status_obra === '0') {
                            contadorStatusZero++;
                        }

                        tr.innerHTML = `
                            <td title="${row.nome_cliente}">${row.nome_cliente}</td>
                            <td title="${row.nome_obra} - Status: ${row.status_obra}" data-status-obra="${row.status_obra}">${row.nome_obra}</td>
                            <td title="${row.imagem_nome}">${row.imagem_nome}</td>
                            <td title="${row.nome_status}">${row.nome_status}</td>
                            <td title="${row.tipo_imagem}">${row.tipo_imagem}</td>
                        `;

                        tbody.appendChild(tr);
                    });

                    // Adicionar ouvintes de eventos para cada linha da tabela
                    addEventListenersToRows();
                } else {
                    tbody.innerHTML = "<tr><td colspan='5'>Nenhum dado encontrado</td></tr>";
                }

                document.getElementById("total-imagens").textContent = contadorStatusZero;
                document.getElementById("total-imagens-antecipada").textContent = contadorAntecipada;

            })
            .catch(error => console.error('Erro ao buscar dados:', error));
    }

    // Função para adicionar eventos de clique nas linhas da tabela
    function addEventListenersToRows() {
        const linhasTabela = document.querySelectorAll(".linha-tabela");

        linhasTabela.forEach(function (linha) {
            linha.addEventListener("click", function () {
                linhasTabela.forEach(function (outraLinha) {
                    outraLinha.classList.remove("selecionada");
                });

                linha.classList.add("selecionada");

                const idImagemSelecionada = linha.getAttribute("data-id");
                document.getElementById("imagem_id").value = idImagemSelecionada;

                // Limpar campos do formulário de edição
                limparCampos();

                // Fazer requisição AJAX para `buscaLinhaAJAX.php` usando Fetch
                fetch(`buscaLinhaAJAX.php?ajid=${idImagemSelecionada}`)
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

                console.log("Linha selecionada: ID da imagem = " + idImagemSelecionada);
            });
        });
    }

    // Carregar a tabela ao carregar a página
    carregarTabela();


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


                form_edicao.style.display = "none"
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

    function carregarDados() {
        var colaboradorId = document.getElementById('colaboradorSelect').value;
        var dataInicio = document.getElementById('dataInicio').value;
        var dataFim = document.getElementById('dataFim').value;
        var obraId = document.getElementById('obraSelect').value;
        var funcaoId = document.getElementById('funcaoSelect').value;
        var status = document.getElementById('statusSelect').value;

        if (colaboradorId) {
            var url = 'getFuncoesPorColaborador.php?colaborador_id=' + colaboradorId;

            if (dataInicio) {
                url += '&data_inicio=' + encodeURIComponent(dataInicio);
            }
            if (dataFim) {
                url += '&data_fim=' + encodeURIComponent(dataFim);
            }
            if (obraId) {
                url += '&obra_id=' + encodeURIComponent(obraId);
            }
            if (funcaoId) {
                url += '&funcao_id=' + encodeURIComponent(funcaoId);
            }
            if (status) {
                url += '&status=' + encodeURIComponent(status);
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    var tabela = document.querySelector('#tabela-colab tbody');
                    tabela.innerHTML = '';

                    data.forEach(function (item) {
                        var row = document.createElement('tr');
                        row.classList.add('linha-tabela');
                        row.setAttribute('data-id', item.imagem_id);
                        var cellNomeImagem = document.createElement('td');
                        cellNomeImagem.textContent = item.imagem_nome;
                        var cellFuncao = document.createElement('td');
                        cellFuncao.textContent = item.nome_funcao;
                        var cellStatus = document.createElement('td');
                        cellStatus.textContent = item.status;
                        var cellPrazoImagem = document.createElement('td');
                        cellPrazoImagem.textContent = item.prazo;

                        row.appendChild(cellNomeImagem);
                        row.appendChild(cellFuncao);
                        row.appendChild(cellStatus);
                        row.appendChild(cellPrazoImagem);
                        tabela.appendChild(row);
                    });

                    document.getElementById('totalImagens').textContent = data.length;

                    addEventListenersToRows();
                })
                .catch(error => console.error('Erro ao carregar funções:', error));
        } else {
            document.querySelector('#tabela-colab tbody').innerHTML = '';
            document.getElementById('totalImagens').textContent = '0';
        }
    }


    document.getElementById('colaboradorSelect').addEventListener('change', carregarDados);
    document.getElementById('dataInicio').addEventListener('change', carregarDados);
    document.getElementById('dataFim').addEventListener('change', carregarDados);
    document.getElementById('obraSelect').addEventListener('change', carregarDados);
    document.getElementById('funcaoSelect').addEventListener('change', carregarDados);
    document.getElementById('statusSelect').addEventListener('change', carregarDados);


    document.getElementById('obraFiltro').addEventListener('change', function () {
        atualizarFuncoes();
    });

    document.getElementById('tipo_imagem').addEventListener('change', function () {
        atualizarFuncoes();
    });

    document.getElementById('antecipada_obra').addEventListener('change', function () {
        atualizarFuncoes();
    });

    function atualizarFuncoes() {
        var obraId = document.getElementById('obraFiltro').value;
        var tipoImagem = document.getElementById('tipo_imagem').value;
        var antecipada_obra = document.getElementById('antecipada_obra').value;

        if (obraId) {
            fetch(`getFuncoesPorObra.php?obra_id=${obraId}&tipo_imagem=${tipoImagem}&antecipada=${antecipada_obra}`)
                .then(response => response.json())
                .then(data => {
                    // Verifica se os dados são válidos e não vazios
                    if (!Array.isArray(data.funcoes) || data.funcoes.length === 0) {
                        console.warn('Nenhuma função encontrada para esta obra e tipo de imagem.');
                        data.funcoes = [{ // Exemplo de dados padrão para evitar que a tabela fique vazia
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
                    tabela.innerHTML = '';

                    data.funcoes.forEach(function (item) {
                        var row = document.createElement('tr');
                        row.classList.add('linha-tabela');
                        row.setAttribute('data-id', item.imagem_id);

                        var cellNomeImagem = document.createElement('td');
                        cellNomeImagem.textContent = item.imagem_nome;
                        cellNomeImagem.setAttribute('antecipada', item.antecipada)
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

                    data.totais.forEach(function (item) {
                        // Exibe outros totais
                        document.getElementById('data-inicio').textContent = item.data_inicio || 'Não disponível';
                        document.getElementById('receb-arq').textContent = item.recebimento_arquivos || 'Não disponível';
                        document.getElementById('prazo-previsto').textContent = item.prazo || 'Não disponível';

                        var ctx = document.getElementById('graficoTotais').getContext('2d');

                        var graficoTotais = new Chart(ctx, {
                            type: 'bar', // ou 'pie', 'line', etc. dependendo do seu gráfico
                            data: {
                                labels: [
                                    'Caderno', 'Modelagem', 'Composição', 'Finalização',
                                    'Pós Produção', 'Alteração', 'Planta'
                                ],
                                datasets: [{
                                    label: 'Porcentagem de Finalizados',
                                    data: [
                                        item.caderno_porcentagem,
                                        item.modelagem_porcentagem,
                                        item.composicao_porcentagem,
                                        item.finalizacao_porcentagem,
                                        item.pos_producao_porcentagem,
                                        item.alteracao_porcentagem,
                                        item.planta_porcentagem
                                    ],
                                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                    borderColor: 'rgba(75, 192, 192, 1)',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    });

                })
                .catch(error => console.error('Erro ao carregar funções:', error));
        } else {
            document.querySelector('#tabela-obra tbody').innerHTML = '';
        }
    }


    document.getElementById('obra-follow').addEventListener('change', fetchFollowUpData);
    document.getElementById('status_imagem').addEventListener('change', fetchFollowUpData);
    document.getElementById('tipo_imagem_follow').addEventListener('change', fetchFollowUpData);
    document.getElementById('antecipada_follow').addEventListener('change', fetchFollowUpData); // Adiciona evento para "Antecipada"

    window.addEventListener('load', function () {

        const obraId = localStorage.getItem('obraId');

        if (obraId) {
            const obraSelect = document.getElementById('obra-follow');

            if (obraSelect) {
                obraSelect.value = obraId;
            }

            fetchFollowUpData();
        }
    });

    function fetchFollowUpData() {
        var obraId = document.getElementById('obra-follow').value;
        var statusImagem = document.getElementById('status_imagem').value;
        var tipoImagem = document.getElementById('tipo_imagem_follow').value;
        var antecipada = document.getElementById('antecipada_follow').value;

        if (obraId) {
            fetch(`followup.php?obra_id=${obraId}&status_imagem=${statusImagem}&tipo_imagem=${tipoImagem}&antecipada=${antecipada}`)
                .then(response => response.json())
                .then(data => {
                    var tabela = document.querySelector('#tabela-follow tbody');
                    tabela.innerHTML = '';

                    data.forEach(function (item) {
                        var row = document.createElement('tr');

                        var cellNomeImagem = document.createElement('td');
                        cellNomeImagem.textContent = item.imagem_nome;
                        cellNomeImagem.setAttribute('antecipada', item.antecipada)
                        row.appendChild(cellNomeImagem);

                        if (Boolean(parseInt(item.antecipada))) {
                            cellNomeImagem.style.backgroundColor = '#ff9d00';
                        }

                        var cellStatusImagem = document.createElement('td');
                        cellStatusImagem.textContent = item.imagem_status;
                        row.appendChild(cellStatusImagem);
                        applyStatusImagem(cellStatusImagem, item.imagem_status);

                        var cellPrazoImagem = document.createElement('td');
                        cellPrazoImagem.textContent = item.prazo;
                        row.appendChild(cellPrazoImagem);

                        var cellCadernoStatus = document.createElement('td');
                        cellCadernoStatus.textContent = item.caderno_status || '-';
                        row.appendChild(cellCadernoStatus);

                        var cellFiltroStatus = document.createElement('td');
                        cellFiltroStatus.textContent = item.filtro_status || '-';
                        row.appendChild(cellFiltroStatus);

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

                        var cellPlantaStatus = document.createElement('td');
                        cellPlantaStatus.textContent = item.planta_status || '-';
                        row.appendChild(cellPlantaStatus);

                        var cellQntRevisoes = document.createElement('td');
                        cellQntRevisoes.textContent = item.total_revisoes || '-';
                        row.appendChild(cellQntRevisoes);

                        tabela.appendChild(row);
                    });
                })
                .catch(error => console.error('Erro ao carregar funções:', error));
        } else {
            document.querySelector('#tabela-follow tbody').innerHTML = '';
        }
    }

    if (window.location.hash === '#follow-up') {
        // Se sim, chama a função para abrir o modal de follow-up
        openModal('follow-up');
    }


});

function toggleNav() {
    const navMenu = document.querySelector('.nav-menu');
    navMenu.classList.toggle('active');
}

function filtrarTabela() {
    var indiceColuna = document.getElementById("colunaFiltro").value;
    var filtro = document.getElementById("pesquisa").value.toLowerCase();
    var tipoImagemFiltro = document.getElementById("tipoImagemFiltro").value;
    var antecipadaFiltro = document.getElementById("imagem").value;
    var tabela = document.getElementById("tabelaClientes");
    var tbody = tabela.getElementsByTagName("tbody")[0];
    var linhas = tbody.getElementsByTagName("tr");

    // Contadores para os itens filtrados
    let contadorStatusZero = 0;
    let contadorAntecipada = 0;

    for (var i = 0; i < linhas.length; i++) {
        var coluna = linhas[i].getElementsByTagName("td")[indiceColuna];
        var valorColuna = coluna.textContent || coluna.innerText;
        var tipoImagemColuna = linhas[i].getElementsByTagName("td")[4].textContent || linhas[i].getElementsByTagName("td")[4].innerText;

        // Verifica se a linha é antecipada e o status_obra
        var isAntecipada = linhas[i].getAttribute("antecipada") === '1';
        var statusObra = linhas[i].getElementsByTagName("td")[1].getAttribute("data-status-obra") === '0'; // Acesse o status_obra pela célula

        var mostrarLinha = true;

        // Filtra por texto digitado
        if (filtro && valorColuna.toLowerCase().indexOf(filtro) === -1) {
            mostrarLinha = false;
        }

        // Filtra pelo tipo de imagem selecionado
        if (tipoImagemFiltro && tipoImagemColuna.toLowerCase() !== tipoImagemFiltro.toLowerCase()) {
            mostrarLinha = false;
        }

        // Filtra pela seleção de antecipada
        if (antecipadaFiltro === "Antecipada" && !isAntecipada) {
            mostrarLinha = false;
        }

        // Mostrar ou ocultar a linha com base nos filtros
        linhas[i].style.display = mostrarLinha ? "" : "none";

        // Atualiza os contadores apenas para as linhas visíveis
        if (mostrarLinha) {
            if (isAntecipada) contadorAntecipada++;
            if (statusObra) contadorStatusZero++;
        }
    }

    // Atualizar os contadores no HTML
    document.getElementById("total-imagens").textContent = contadorStatusZero;
    document.getElementById("total-imagens-antecipada").textContent = contadorAntecipada;
}


document.getElementById("pesquisa").addEventListener("keyup", function (event) {
    if (event.key === "Enter") {
        filtrarTabela();
    }
});

function openModal(modalId, element) {

    closeModal('add-cliente');
    closeModal('add-imagem');
    closeModal('filtro-tabela');
    closeModal('filtro-colab');
    closeModal('filtro-obra');
    closeModal('follow-up');
    closeModal('add-acomp');

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
    closeModal('filtro-tabela');
    closeModal('filtro-colab');
    closeModal('filtro-obra');
    closeModal('follow-up');
    closeModal('add-acomp');


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
document.getElementById('tipo').addEventListener('change', function () {
    const tipo = this.value;
    const assuntoEmailDiv = document.getElementById('assunto-email');

    // Se o tipo for "Email" (2), mostramos o campo de assunto
    if (tipo === '2') {
        assuntoEmailDiv.style.display = 'flex';
    } else {
        // Caso contrário, ocultamos o campo de assunto
        assuntoEmailDiv.style.display = 'none';
    }
});

function submitFormAcomp(event) {
    event.preventDefault();

    const tipo = document.getElementById('tipo').value;
    const obraAcomp = document.getElementById('obraAcomp').value;
    const colab_id = document.getElementById('colab_id').value;

    if (tipo === '1') {
        // Lógica de inserção para tipo "Obra"
        const data = {
            obraAcomp: obraAcomp,
            colab_id: colab_id
        };

        fetch('inserir_acomp.php', {
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

                closeModal('add-acomp');
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

    } else if (tipo === '2') {
        // Lógica de inserção para tipo "Email"
        const assunto = document.getElementById('assunto').value;

        const data = {
            obraAcomp: obraAcomp,
            colab_id: colab_id,
            assunto: assunto
        };

        fetch('inserir_acomp_email.php', {
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

                closeModal('add-acomp');
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
}

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

});

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
        case 'HOLD':
            cell.style.backgroundColor = '#ff0000';
    }
};

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

});

var modalLogs = document.getElementById("modalLogs");
var closeBtn = document.getElementsByClassName("close")[0];
const formPosProducao = document.getElementById('formPosProducao');


const colaboradorSelect = document.getElementById('colaboradorSelect');
const mostrarLogsBtn = document.getElementById('mostrarLogsBtn');

colaboradorSelect.addEventListener('change', function () {
    mostrarLogsBtn.disabled = this.value === "0";
});

const obraSelect = document.getElementById('obraSelect');

mostrarLogsBtn.addEventListener('click', function () {
    const colaboradorId = colaboradorSelect.value;
    const obraId = obraSelect.value;
    modalLogs.style.display = 'flex';

    fetch(`carregar_logs.php?colaboradorId=${colaboradorId}&obraId=${obraId}`)
        .then(response => response.json())
        .then(data => {
            const tabelaLogsBody = document.querySelector('#tabela-logs tbody');
            tabelaLogsBody.innerHTML = '';

            if (data && data.length > 0) {
                data.forEach(log => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${log.imagem_nome}</td>
                        <td>${log.nome_obra}</td>
                        <td>${log.status_anterior}</td>
                        <td>${log.status_novo}</td>
                        <td>${log.data}</td>
                    `;
                    tabelaLogsBody.appendChild(row);
                });
            } else {
                const row = document.createElement('tr');
                row.innerHTML = '<td colspan="5">Nenhum log encontrado.</td>';
                tabelaLogsBody.appendChild(row);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar os logs:', error);
        });
});

closeBtn.onclick = function () {
    modalLogs.style.display = "none";
    limparCampos();
};

const form_edicao = document.getElementById('form-edicao');

window.onclick = function (event) {
    if (event.target == modalLogs) {
        modalLogs.style.display = "none";
    }
    if (event.target == form_edicao) {
        form_edicao.style.display = "none"
    }
    if (event.target == desc_modal) {
        desc_modal.style.display = "none"
    }
}


const mostrarDesc = document.getElementById('mostrar-desc');
const desc_modal = document.getElementById('desc-modal');
const closeDesc = document.querySelector('.closeDesc');


mostrarDesc.addEventListener('click', function () {
    desc_modal.style.display = 'flex';
})

closeDesc.addEventListener('click', function () {
    desc_modal.style.display = 'none';
})

document.getElementById('generate-pdf').addEventListener('click', function () {
    const { jsPDF } = window.jspdf;

    const doc = new jsPDF({
        orientation: 'landscape',
    });

    let currentY = 20;

    const title = `Olá,\nSeguem as informações atualizadas sobre o status do seu projeto. Qualquer dúvida ou necessidade de ajuste, estamos à disposição.\n\n`;
    const importante = `IMPORTANTE: `;
    const importantMessage = `Todos os prazos serão pausados entre os dias 20/12/2024 a 06/01/2025.`;
    const assinatura = `\n\nAtenciosamente,\nEquipe IMPROOV`;
    const legenda = `\n\nP00 - Envio em Toon: Primeira versão conceitual do projeto, enviada com estilo gráfico simplificado para avaliação inicial.
    \nR00 - Primeiro Envio: Primeira entrega completa, após ajustes da versão inicial.
    \nR01, R02, etc. - Revisão Enviada: Número de revisões enviadas, indicando cada versão revisada do projeto.
    \nEF - Entrega Final: Projeto concluído e aprovado em sua versão final.
    \nHOLD - Falta de Arquivos: O projeto está temporariamente parado devido à ausência de arquivos ou informações necessárias. O prazo de entrega também ficará pausado até o recebimento dos arquivos para darmos continuidade ao trabalho.`;

    const imgPath = 'assets/logo.jpg';

    fetch(imgPath)
        .then(response => response.blob())
        .then(blob => {
            const reader = new FileReader();
            reader.onloadend = function () {
                const imgData = reader.result;
                doc.addImage(imgData, 'PNG', 14, currentY, 40, 40);
                currentY += 50;

                doc.setFontSize(14);
                doc.setTextColor(0, 0, 0);
                doc.text(doc.splitTextToSize(title, 180), 14, currentY);
                currentY += 20;

                doc.setFontSize(14);
                doc.setTextColor(255, 0, 0);
                doc.text(importante, 14, currentY);

                doc.setTextColor(0, 0, 0);
                doc.text(doc.splitTextToSize(importantMessage, 180), 14 + doc.getTextWidth(importante), currentY);
                currentY += 10;

                doc.setFontSize(12);
                doc.text(assinatura, 14, currentY);
                currentY += 20;

                doc.setFontSize(10);
                const legendaLines = doc.splitTextToSize(legenda, 180);
                doc.text(legendaLines, 14, currentY);
                currentY += (legendaLines.length * 10) + 10;

                const table = document.getElementById('tabela-follow');
                const rows = [];
                const headers = [];

                table.querySelectorAll('thead tr th').forEach(header => {
                    headers.push(header.innerText);
                });

                table.querySelectorAll('tbody tr').forEach(row => {
                    const rowData = [];
                    row.querySelectorAll('td').forEach(cell => {
                        rowData.push(cell.innerText);
                    });
                    rows.push(rowData);
                });

                doc.autoTable({
                    head: [headers],
                    body: rows,
                    startY: currentY
                });

                doc.save('follow-up.pdf');
            }
            reader.readAsDataURL(blob);
        })
        .catch(error => console.error('Erro ao carregar a imagem:', error));
});
