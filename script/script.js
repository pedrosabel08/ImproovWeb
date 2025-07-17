document.querySelectorAll('.titulo').forEach(titulo => {
    titulo.addEventListener('click', () => {
        const opcoes = titulo.nextElementSibling;
        if (opcoes.style.display === 'none') {
            opcoes.style.display = 'flex';
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

            atualizarModal(idImagemSelecionada);
        });
    });
}

// Seleciona todos os selects com id que começam com 'status_'
const statusSelects = document.querySelectorAll("select[id^='status_']");

statusSelects.forEach(select => {
    select.addEventListener("change", function () {
        // Pega o próximo elemento irmão que possui a classe 'revisao_imagem'
        const revisaoImagem = this.closest('.funcao').querySelector('.revisao_imagem');

        if (this.value === "Em aprovação") {
            revisaoImagem.style.display = "block";
        } else {
            revisaoImagem.style.display = "none";
        }

        // Pega o próximo elemento de prazo
        const prazoInput = this.closest('.funcao').querySelector('input[type="date"]');

        if (this.value === "Em andamento") {
            prazoInput.required = true;
        } else {
            prazoInput.required = false;
        }
    });
});

let idImagem = null;
let idObra = null;
function atualizarModal(idImagem) {
    // Limpar campos do formulário de edição
    limparCampos();

    // Fazer requisição AJAX para `buscaLinhaAJAX.php` usando Fetch
    fetch(`buscaLinhaAJAX.php?ajid=${idImagem}`)
        .then(response => response.json())
        .then(response => {
            document.getElementById('form-edicao').style.display = 'flex';
            if (response.funcoes && response.funcoes.length > 0) {
                document.getElementById("campoNomeImagem").textContent = response.funcoes[0].imagem_nome;
                document.getElementById("mood").textContent = `Mood da cena: ${response.funcoes[0].clima || ''}`;

                document.querySelectorAll('.revisao_imagem').forEach(element => {
                    element.style.display = 'none';
                });

                response.funcoes.forEach(function (funcao) {
                    let selectElement;
                    let checkboxElement;
                    let revisaoImagemElement;
                    switch (funcao.nome_funcao) {
                        case "Caderno":
                            selectElement = document.getElementById("opcao_caderno");
                            document.getElementById("status_caderno").value = funcao.status;
                            document.getElementById("prazo_caderno").value = funcao.prazo;
                            document.getElementById("obs_caderno").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_caderno");
                            break;
                        case "Modelagem":
                            selectElement = document.getElementById("opcao_model");
                            document.getElementById("status_modelagem").value = funcao.status;
                            document.getElementById("prazo_modelagem").value = funcao.prazo;
                            document.getElementById("obs_modelagem").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_model");
                            break;
                        case "Composição":
                            selectElement = document.getElementById("opcao_comp");
                            document.getElementById("status_comp").value = funcao.status;
                            document.getElementById("prazo_comp").value = funcao.prazo;
                            document.getElementById("obs_comp").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_comp");

                            break;
                        case "Finalização":
                            selectElement = document.getElementById("opcao_final");
                            document.getElementById("status_finalizacao").value = funcao.status;
                            document.getElementById("prazo_finalizacao").value = funcao.prazo;
                            document.getElementById("obs_finalizacao").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_final");
                            break;
                        case "Pós-produção":
                            selectElement = document.getElementById("opcao_pos");
                            document.getElementById("status_pos").value = funcao.status;
                            document.getElementById("prazo_pos").value = funcao.prazo;
                            document.getElementById("obs_pos").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_pos");
                            break;
                        case "Alteração":
                            selectElement = document.getElementById("opcao_alteracao");
                            document.getElementById("status_alteracao").value = funcao.status;
                            document.getElementById("prazo_alteracao").value = funcao.prazo;
                            document.getElementById("obs_alteracao").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_alt");
                            break;
                        case "Planta Humanizada":
                            selectElement = document.getElementById("opcao_planta");
                            document.getElementById("status_planta").value = funcao.status;
                            document.getElementById("prazo_planta").value = funcao.prazo;
                            document.getElementById("obs_planta").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_ph");
                            break;
                        case "Filtro de assets":
                            selectElement = document.getElementById("opcao_filtro");
                            document.getElementById("status_filtro").value = funcao.status;
                            document.getElementById("prazo_filtro").value = funcao.prazo;
                            document.getElementById("obs_filtro").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_filtro");
                            break;
                        case "Pré-Finalização":
                            selectElement = document.getElementById("opcao_pre");
                            document.getElementById("status_pre").value = funcao.status;
                            document.getElementById("prazo_pre").value = funcao.prazo;
                            document.getElementById("obs_pre").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_pre");
                            break;
                    }
                    if (revisaoImagemElement) {
                        revisaoImagemElement.setAttribute('data-id-funcao', funcao.id);
                    }
                    if (selectElement) {
                        selectElement.value = funcao.colaborador_id;

                        // Verifica se o botão de limpar já existe
                        if (!selectElement.parentElement.querySelector('.clear-button')) {
                            // Adiciona o botão de limpar se o selectElement tiver um valor
                            if (selectElement.value) {
                                const clearButton = document.createElement('button');
                                clearButton.type = 'button'; // Define o tipo do botão como "button"
                                clearButton.innerHTML = 'x';
                                clearButton.classList.add('clear-button', 'tooltip');
                                clearButton.setAttribute('data-id', funcao.id); // Adiciona o ID da função ao botão
                                clearButton.setAttribute('data-tooltip', 'Excluir função'); // Adiciona o tooltip
                                clearButton.addEventListener('click', function (event) {
                                    event.preventDefault(); // Previne o comportamento padrão do botão
                                    const funcaoId = this.getAttribute('data-id');
                                    excluirFuncao(funcaoId, selectElement);
                                });
                                selectElement.parentElement.appendChild(clearButton);
                            }
                        }

                        // Adiciona o botão de log se o selectElement tiver um valor
                        if (!selectElement.parentElement.querySelector('.log-button')) {
                            if (selectElement.value) {
                                const logButton = document.createElement('button');
                                logButton.type = 'button'; // Define o tipo do botão como "button"
                                logButton.innerHTML = '<i class="fas fa-file-alt"></i>';
                                logButton.classList.add('log-button', 'tooltip');
                                logButton.setAttribute('data-id', funcao.id); // Adiciona o ID da função ao botão
                                logButton.setAttribute('data-tooltip', 'Exibir log'); // Adiciona o tooltip
                                logButton.addEventListener('click', function (event) {
                                    event.preventDefault(); // Previne o comportamento padrão do botão
                                    const funcaoId = this.getAttribute('data-id');
                                    exibirLog(funcaoId);
                                });
                                selectElement.parentElement.appendChild(logButton);
                            }
                        }
                    }
                    if (checkboxElement) {
                        checkboxElement.title = funcao.responsavel_aprovacao || '';
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
    document.getElementById("status_pre").value = "";
    document.getElementById("prazo_pre").value = "";
    document.getElementById("obs_pre").value = "";
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
    document.getElementById("opcao_pre").value = "";
    document.getElementById('imagem_id_pos').value = ''; // Limpar campo de texto
    document.getElementById('id-pos').value = ''; // Limpar campo de texto
    document.getElementById('caminhoPasta').value = ''; // Limpar campo de texto
    document.getElementById('numeroBG').value = ''; // Limpar campo de texto
    document.getElementById('referenciasCaminho').value = ''; // Limpar campo de texto
    document.getElementById('observacao').value = ''; // Limpar campo de texto

    // Limpa todos os campos cujo id começa com "revisao_imagem"
    document.querySelectorAll('[id^="revisao_imagem"]').forEach(element => {
        element.value = ""; // Define o valor como vazio
    });

}

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

    // Carregar a tabela ao carregar a página
    carregarTabela();




    document.getElementById("salvar_funcoes").addEventListener("click", function (event) {
        event.preventDefault();

        if (!idImagem) {
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

        // var idImagemSelecionada = linhaSelecionada.getAttribute("data-id");

        // Verifica todos os campos de prazo que devem ser obrigatórios
        var form = document.getElementById("form-add");
        var camposPrazo = form.querySelectorAll("input[type='date'][required]");
        var camposVazios = Array.from(camposPrazo).filter(input => !input.value);

        // var funcoesTEA = localStorage.getItem("funcoesTEA");
        // if (funcoesTEA >= 4) {
        //     Swal.fire({
        //         icon: 'warning', // Ícone de aviso
        //         title: 'Atenção!',
        //         text: 'Termine as tarefas que estão em andamento primeiro!',
        //         confirmButtonText: 'Ok',
        //         confirmButtonColor: '#f39c12', // Cor do botão
        //     });
        //     return;
        // }

        if (camposVazios.length > 0) {
            Swal.fire({
                icon: 'warning', // Ícone de aviso
                title: 'Atenção!',
                text: 'Coloque a data de quando irá terminar a tarefa!',
                confirmButtonText: 'Ok',
                confirmButtonColor: '#f39c12', // Cor do botão
            });
            return;
        }

        var textos = {};
        var pElements = document.querySelectorAll(".form-edicao p");
        pElements.forEach(function (p) {
            textos[p.id] = p.textContent.trim();
        });

        var dados = {
            imagem_id: idImagem,
            caderno_id: document.getElementById("opcao_caderno").value || "",
            status_caderno: document.getElementById("status_caderno").value || "",
            prazo_caderno: document.getElementById("prazo_caderno").value || "",
            obs_caderno: document.getElementById("obs_caderno").value || "",
            comp_id: document.getElementById("opcao_comp").value || "",
            status_comp: document.getElementById("status_comp").value || "",
            prazo_comp: document.getElementById("prazo_comp").value || "",
            obs_comp: document.getElementById("obs_comp").value || "",
            modelagem_id: document.getElementById("opcao_model").value || "",
            status_modelagem: document.getElementById("status_modelagem").value || "",
            prazo_modelagem: document.getElementById("prazo_modelagem").value || "",
            obs_modelagem: document.getElementById("obs_modelagem").value || "",
            finalizacao_id: document.getElementById("opcao_final").value || "",
            status_finalizacao: document.getElementById("status_finalizacao").value || "",
            prazo_finalizacao: document.getElementById("prazo_finalizacao").value || "",
            obs_finalizacao: document.getElementById("obs_finalizacao").value || "",
            pre_id: document.getElementById("opcao_pre").value || "",
            status_pre: document.getElementById("status_pre").value || "",
            prazo_pre: document.getElementById("prazo_pre").value || "",
            obs_pre: document.getElementById("obs_pre").value || "",
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
        // Segundo fetch - agora enviando como JSON
        var fileInputs = document.querySelectorAll("input[type='file']");
        var filesExistem = Array.from(fileInputs).some(input => input.files.length > 0);
        const dataIdFuncoes = [];

        const formData = new FormData();

        console.log("Arquivos existem?", filesExistem);

        fileInputs.forEach(input => {
            // Verifica se o input tem arquivos
            if (input.files.length > 0) {
                const dataIdFuncao = input.getAttribute('data-id-funcao');

                // Adiciona apenas se o data-id-funcao existir e o input tiver arquivos
                if (dataIdFuncao && dataIdFuncao.trim() !== '') {
                    dataIdFuncoes.push(dataIdFuncao);
                }

                // Adiciona os arquivos ao FormData
                for (let i = 0; i < input.files.length; i++) {
                    formData.append('imagens[]', input.files[i]);
                }
            }
        });

        if (filesExistem) {
            // Adicionando apenas os valores válidos de dataIdFuncoes
            formData.append('dataIdFuncoes', JSON.stringify(dataIdFuncoes));

            console.log("Funções válidas: ", dataIdFuncoes);  // Para ver o array filtrado

            fetch('uploadArquivos.php', {
                method: "POST",
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log(data);
                    // Aqui você pode adicionar lógica para lidar com a resposta do servidor
                })
                .catch(error => {
                    console.error('Erro:', error);
                });

        }
    });

    // Função para enviar notificações
    // function enviarNotificacao(titulo, mensagem, icone) {
    //     if ('Notification' in window) {
    //         Notification.requestPermission().then(permission => {
    //             if (permission === 'granted') {
    //                 const notificacao = new Notification(titulo, {
    //                     body: mensagem,
    //                     icon: icone,
    //                 });

    //                 notificacao.onclick = () => {
    //                     window.focus();
    //                 };

    //                 setTimeout(() => notificacao.close(), 3000);
    //             }
    //         });
    //     }
    // }

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
                        row.setAttribute('obra-id', item.obra_id);

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

                    addEventListenersToRows();

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

    function checkHash() {
        if (window.location.hash === '#add-cliente') {
            openModal('add-cliente');
        }

        if (window.location.hash === '#filtro-colab') {
            openModal('filtro-colab');
        }

        if (window.location.hash === '#follow-up') {
            openModal('follow-up');
        }

        if (window.location.hash === '#filtro-obra') {
            openModal('filtro-obra');
        }
    }

    window.addEventListener('load', function () {
        const obraId = localStorage.getItem('obraId');
        if (obraId) {
            const obraSelect = document.getElementById('obra-follow');
            const obraSelectObra = document.getElementById('obraFiltro');

            if (obraSelect) {
                obraSelect.value = obraId;
                fetchFollowUpData(); // Busca os dados ao carregar a página
            }

            if (obraSelectObra) {
                obraSelectObra.value = obraId;
                atualizarFuncoes(); // Atualiza funções relacionadas à obra
            }
        }

        checkHash(); // Verifica o hash ao carregar a página
    });

    // Adiciona um evento para abrir o modal quando o hash mudar
    window.addEventListener('hashchange', checkHash);



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

});
function formatarData(data) {
    const partes = data.split("-");
    const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
    return dataFormatada;
}

document.addEventListener('DOMContentLoaded', () => {
    const idusuario = localStorage.getItem('idusuario');
    const idcolaborador = localStorage.getItem('idcolaborador');
    const colaboradorDiv = document.getElementById('div-colab');
    const colaboradorSelect = document.getElementById('colaboradorSelect');

    function carregarDados(colaboradorId = null) {
        // Se não tiver colaboradorId, verifica se o usuário é admin ou não
        if (!colaboradorId) {
            if (idusuario != 1 && idusuario != 2 && idusuario != 9) {
                colaboradorId = idcolaborador;
            } else {
                colaboradorId = colaboradorSelect.value;
            }
        }

        let mes = '';
        let ano = '';
        const mesAno = document.getElementById('mes').value;
        if (mesAno) {
            const partes = mesAno.split('-');
            ano = partes[0];
            mes = partes[1];
        }
        var obraId = document.getElementById('obraSelect').value;
        // Para pegar todos os valores selecionados de um select múltiplo
        const funcoesSelecionadas = Array.from(document.getElementById('funcaoSelect').selectedOptions).map(opt => opt.value);
        const statusSelecionados = Array.from(document.getElementById('statusSelect').selectedOptions).map(opt => opt.value);
        // var prioridade = document.getElementById('prioridadeSelect').value;

        var url = `getFuncoesPorColaborador.php?colaborador_id=${colaboradorId}`;
        if (mes) url += `&mes=${encodeURIComponent(mes)}`;
        if (ano) url += `&ano=${encodeURIComponent(ano)}`;
        if (obraId) url += `&obra_id=${encodeURIComponent(obraId)}`;
        if (funcoesSelecionadas.length) url += `&funcao_id=${encodeURIComponent(funcoesSelecionadas.join(','))}`;
        if (statusSelecionados.length) url += `&status=${encodeURIComponent(statusSelecionados.join(','))}`;
        // if (prioridade) url += `&prioridade=${encodeURIComponent(prioridade)}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                document.getElementById('kanban-board').style.display = 'flex';
                document.getElementById('image-count').style.display = 'block';
                // Limpa o quadro Kanban antes de adicionar os cartões
                const statusMap = {
                    'Não iniciado': [],
                    'Em andamento': [],
                    'Em aprovação': [],
                    'Finalizado': []
                };

                // Agrupa os itens por status
                data.forEach(item => {
                    let status = item.status || 'Não iniciado';

                    // Mapeamento dos status para as colunas do Kanban
                    if (['Ajuste', 'Aprovado', 'Aprovado com ajustes', 'Em aprovação'].includes(status)) {
                        status = 'Em aprovação';
                    } else if (status === 'Em andamento') {
                        status = 'Em andamento';
                    } else if (status === 'Finalizado') {
                        status = 'Finalizado';
                    } else {
                        status = 'Não iniciado';
                    }

                    statusMap[status].push(item);
                });

                // Renderiza os cartões em cada coluna do Kanban
                Object.keys(statusMap).forEach(status => {
                    const coluna = document.getElementById(`kanban-${status.replace(/\s/g, '').toLowerCase()}`);
                    if (coluna) {
                        const titulo = coluna.querySelector('.kanban-title');
                        coluna.innerHTML = '';

                        // Mostra o título com contador
                        const totalCards = statusMap[status].length;
                        const tituloNovo = document.createElement('div');
                        tituloNovo.className = 'kanban-title';
                        tituloNovo.innerHTML = `${status} <span class="kanban-count">(${totalCards})</span>`;
                        coluna.appendChild(tituloNovo);

                        const cards = statusMap[status];
                        const mostrarLimite = 10;

                        const cardsContainer = document.createElement('div');
                        cardsContainer.className = 'kanban-cards-container';

                        cards.slice(0, mostrarLimite).forEach(item => {
                            const card = document.createElement('div');
                            card.className = 'kanban-card';
                            card.setAttribute('data-id', item.imagem_id);

                            let statusDot = '';
                            // Dot para "Não iniciado" indicando liberada ou não
                            if (status === 'Não iniciado') {
                                if (item.liberada === true || item.liberada === 'true' || item.liberada === 1 || item.liberada === '1') {
                                    statusDot = `<span class="status-dot-liberada tool" data-tooltip='Função liberada'></span>`;
                                } else {
                                    statusDot = `<span class="status-dot-naoliberada tool" data-tooltip='Esperando concluir função anterior'></span>`;
                                }
                            }
                            // Adiciona o ícone colorido apenas na coluna "Em aprovação"

                            if (status === 'Em aprovação') {
                                let dotClass = 'status-em-aprovacao';
                                if (item.status === 'Aprovado') dotClass = 'status-aprovado';
                                else if (item.status === 'Aprovado com ajustes') dotClass = 'status-aprovado-ajustes';
                                else if (item.status === 'Ajuste') dotClass = 'status-ajuste';

                                statusDot = `<span class="status-dot ${dotClass} tool" data-tooltip='${item.status}'></span>`;
                            }

                            card.innerHTML = `
                                ${statusDot}<b>${item.imagem_nome}</b><br>
                                Função: ${item.nome_funcao}<br>
                                Prazo: ${item.prazo ? formatarData(item.prazo) : '-'}
                            `;

                            card.addEventListener('click', function () {
                                const imagemIdSelecionada = this.getAttribute('data-id');
                                const obraIdSelecionada = this.getAttribute('data-obra-id');
                                if (imagemIdSelecionada) {
                                    atualizarModal(imagemIdSelecionada); // Passa o ID da imagem para adicionar eventos
                                    idImagem = imagemIdSelecionada; // Atualiza a variável global imagemId
                                    idObra = obraIdSelecionada; // Atualiza a variável global imagemId
                                    console.log("ID da imagem selecionada:", idImagem);
                                }
                            });
                            cardsContainer.appendChild(card);
                        });

                        coluna.appendChild(cardsContainer);

                        // Se houver mais de 10, adiciona o botão "Mostrar mais"
                        if (cards.length > mostrarLimite) {
                            const gaveta = document.createElement('div');
                            gaveta.className = 'kanban-gaveta';

                            const btn = document.createElement('button');
                            btn.className = 'kanban-show-more';
                            btn.innerHTML = '<i class="fa-solid fa-chevron-down"></i>';

                            btn.addEventListener('click', function () {
                                // Adiciona os demais cards
                                cards.slice(mostrarLimite).forEach(item => {
                                    const card = document.createElement('div');
                                    card.className = 'kanban-card';
                                    card.setAttribute('data-id', item.imagem_id);

                                    card.innerHTML = `
                        <b>${item.imagem_nome}</b><br>
                        Função: ${item.nome_funcao}<br>
                        Prazo: ${item.prazo || '-'}
                    `;
                                    card.addEventListener('click', function () {
                                        const imagemIdSelecionada = this.getAttribute('data-id');
                                        const obraIdSelecionada = this.getAttribute('data-obra-id');
                                        if (imagemIdSelecionada) {
                                            atualizarModal(imagemIdSelecionada); // Passa o ID da imagem para adicionar eventos
                                            idImagem = imagemIdSelecionada; // Atualiza a variável global imagemId
                                            idObra = obraIdSelecionada; // Atualiza a variável global imagemId
                                            console.log("ID da imagem selecionada:", idImagem);
                                        }
                                    });
                                    cardsContainer.appendChild(card);
                                });
                                gaveta.style.display = 'none';
                            });

                            gaveta.appendChild(btn);
                            coluna.appendChild(gaveta);
                        }

                    }



                });

                document.getElementById('totalImagens').textContent = data.length;
            })
            .catch(error => console.error('Erro ao carregar funções:', error));

    }

    // Se não for admin, esconde o select e já carrega os dados
    if (idusuario != 1 && idusuario != 2 && idusuario != 9) {
        colaboradorDiv.style.display = 'none';
        carregarDados(); // Carrega automaticamente para o usuário logado
    } else {
        // Se for admin, mostra o select e aguarda seleção
        colaboradorSelect.style.display = 'block';
        colaboradorSelect.addEventListener('change', () => carregarDados(colaboradorSelect.value));
    }

    // Adiciona eventos nos filtros
    document.getElementById('mes').addEventListener('change', () => carregarDados());
    document.getElementById('obraSelect').addEventListener('change', () => carregarDados());
    document.getElementById('funcaoSelect').addEventListener('change', () => carregarDados());
    document.getElementById('statusSelect').addEventListener('change', () => carregarDados());
    // document.getElementById('prioridadeSelect').addEventListener('change', () => carregarDados());
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
    const dataEmailDiv = document.getElementById('data-email');

    // Se o tipo for "Email" (2), mostramos o campo de assunto
    if (tipo === '2') {
        assuntoEmailDiv.style.display = 'flex';
        dataEmailDiv.style.display = 'flex';
    } else {
        // Caso contrário, ocultamos o campo de assunto
        assuntoEmailDiv.style.display = 'none';
        dataEmailDiv.style.display = 'none';
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
        const date = document.getElementById('data').value;

        const data = {
            obraAcomp: obraAcomp,
            colab_id: colab_id,
            assunto: assunto,
            date: date
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
        case 'Em aprovação':
            cell.style.backgroundColor = 'yellow';
            cell.style.color = 'black';
            break;
        case 'Aprovado':
            cell.style.backgroundColor = 'lightseagreen';
            cell.style.color = 'black';
            break;
        case 'Ajuste':
            cell.style.backgroundColor = 'orangered';
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
            break;
        case 'TEA':
            cell.style.backgroundColor = '#f7eb07';
    }
};


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
    // if (event.target == desc_modal) {
    //     desc_modal.style.display = "none"
    // }
}

window.ontouchstart = function (event) {
    if (event.target == modalLogs) {
        modalLogs.style.display = "none";
    }
    if (event.target == form_edicao) {
        form_edicao.style.display = "none"
    }
    // if (event.target == desc_modal) {
    //     desc_modal.style.display = "none"
    // }
}


// const mostrarDesc = document.getElementById('mostrar-desc');
// const desc_modal = document.getElementById('desc-modal');
// const closeDesc = document.querySelector('.closeDesc');


// mostrarDesc.addEventListener('click', function () {
//     desc_modal.style.display = 'flex';
// })

// closeDesc.addEventListener('click', function () {
//     desc_modal.style.display = 'none';
// })

document.getElementById("addRender").addEventListener("click", function (event) {
    event.preventDefault();

    // var linhaSelecionada = document.querySelector(".linha-tabela.selecionada");
    if (!idImagem) {
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

    // var idImagemSelecionada = linhaSelecionada.getAttribute("data-id");
    // var idObraSelecionada = linhaSelecionada.getAttribute("obra-id");

    const statusId = document.getElementById("opcao_status").value;

    // Lista de status permitidos
    const statusPermitidos = ["2", "3", "4", "5", "6", "14", "15"];

    if (!statusPermitidos.includes(statusId)) {
        Swal.fire({
            icon: 'error',
            title: 'Status inválido',
            text: 'Este status não é permitido. Selecione um status válido.'
        });
        return;
    }

    const xhr = new XMLHttpRequest();
    xhr.open("POST", "addRender.php", true);
    xhr.setRequestHeader("Content-Type", "application/json");

    xhr.onload = function () {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            const idRenderAdicionado = response.idrender;

            if (response.status === "erro") {
                // Aqui vamos tratar o erro mais específico
                Swal.fire({
                    icon: 'error',
                    title: 'Erro ao adicionar render',
                    text: response.message  // Exibe a mensagem de erro do PHP diretamente
                });
                return;
            }
            Swal.fire({
                icon: 'success',
                title: 'Render adicionado!',
                text: 'Agora você pode preencher os dados da pós-produção.',
                confirmButtonText: 'Continuar'
            }).then(() => {
                const modal = document.getElementById("modal_pos");
                modal.classList.remove("hidden");

                // Preenche os selects com os valores salvos/localizados
                const finalizador = localStorage.getItem("idcolaborador");
                if (finalizador) {
                    document.getElementById("opcao_finalizador").value = finalizador;
                }

                const obra = idObra;
                if (obra) {
                    document.getElementById("opcao_obra_pos").value = obra;
                }

                document.getElementById("imagem_id_pos").value = idImagem;
                const statusSelecionado = document.getElementById("opcao_status");
                if (statusSelecionado) {
                    const statusValue = statusSelecionado.value;
                    document.getElementById("opcao_status_pos").value = statusValue;
                }

                const pos = document.getElementById("opcao_pos").value;
                if (pos) {
                    document.getElementById("responsavel_id").value = pos;

                }

                document.getElementById("render_id_pos").value = idRenderAdicionado;

                const form_edicao = document.getElementById("form-edicao");
                form_edicao.style.display = "none";
            });

        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro ao enviar',
                text: 'Tente novamente ou avise a NASA.'
            });
        }
    };

    const data = {
        imagem_id: idImagem,
        status_id: statusId,
    };

    xhr.send(JSON.stringify(data));
});



formPosProducao.addEventListener('submit', function (e) {
    e.preventDefault();

    var formData = new FormData(this);

    fetch('./Pos-Producao/inserir_pos_producao.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.text())
        .then(data => {

            document.getElementById('form-edicao').style.display = 'none';
            limparCampos();
            Toastify({
                text: "Dados inseridos com sucesso!",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "left",
                backgroundColor: "green",
                stopOnFocus: true,
            }).showToast();

            const modal = document.getElementById("modal_pos");
            modal.classList.add("hidden");
        })
        .catch(error => console.error('Erro:', error));
});



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



document.getElementById("copyColumn").addEventListener("click", function () {
    const table = document.getElementById("tabela-obra");
    const rows = table.querySelectorAll("tbody tr");
    const columnData = [];

    rows.forEach(row => {
        columnData.push(row.cells[0].innerText);
    });

    // Formata como lista
    const listText = columnData.join("\n");

    navigator.clipboard.writeText(listText)
        .then(() => {
            alert("Coluna copiada como lista!");
        })
        .catch(err => {
            console.error("Erro ao copiar a coluna: ", err);
        });
});

document.getElementById("copyColumnColab").addEventListener("click", function () {
    const table = document.getElementById("tabela-colab");
    const rows = table.querySelectorAll("tbody tr");
    const columnData = [];

    rows.forEach(row => {
        columnData.push(row.cells[1].innerText);
    });

    // Formata como lista
    const listText = columnData.join("\n");

    navigator.clipboard.writeText(listText)
        .then(() => {
            alert("Coluna copiada como lista!");
        })
        .catch(err => {
            console.error("Erro ao copiar a coluna: ", err);
        });
});




const dropArea = document.getElementById('drop-area');
const fileInput = document.getElementById('fileElem');
const fileList = document.getElementById('fileList');
let imagensSelecionadas = [];
let arquivosFinais = [];
let dataIdFuncoes = [];

function abrirModal(botao) {
    imagensSelecionadas = [];
    arquivosFinais = [];

    const dataIdFuncao = botao.getAttribute('data-id-funcao');
    dataIdFuncoes = dataIdFuncao?.split(',').map(f => f.trim()) || [];

    let containerFuncao = botao.closest('.funcao') || botao.closest('.funcao_comp');
    let nomeFuncao = containerFuncao?.querySelector('.titulo p')?.textContent.trim() || '';

    document.getElementById('funcao_id_revisao').value = dataIdFuncoes.join(',');
    document.getElementById('nome_funcao_upload').value = nomeFuncao;

    // Exibir o modal
    document.getElementById('modalUpload').style.display = 'block';

    // Verificação do nome da função
    const nomeNormalizado = nomeFuncao.toLowerCase();
    if (nomeNormalizado === 'caderno' || nomeNormalizado === 'filtro de assets') {
        // Pula direto para a etapa final
        document.getElementById('etapaPrevia').style.display = 'none';
        document.getElementById('etapaFinal').style.display = 'block';
        document.getElementById('etapaTitulo').textContent = "1. Envio de arquivos";
    } else {
        // Etapa padrão
        document.getElementById('etapaPrevia').style.display = 'block';
        document.getElementById('etapaFinal').style.display = 'none';
        document.getElementById('etapaTitulo').textContent = "1. Envio de Prévia";
    }

    configurarDropzone("drop-area-previa", "fileElemPrevia", "fileListPrevia", imagensSelecionadas);
    configurarDropzone("drop-area-final", "fileElemFinal", "fileListFinal", arquivosFinais);

    document.getElementById('modalUpload').style.display = 'block';
    document.getElementById('form-edicao').style.display = 'none';
}

function fecharModal() {
    imagensSelecionadas = [];
    arquivosFinais = [];
    renderizarLista(imagensSelecionadas, 'fileListPrevia');
    renderizarLista(arquivosFinais, 'fileListFinal');
    document.getElementById('modalUpload').style.display = 'none';
    document.querySelectorAll('.revisao_imagem').forEach(el => el.style.display = 'none');
}

function configurarDropzone(areaId, inputId, listaId, arquivosArray) {
    const dropArea = document.getElementById(areaId);
    const fileInput = document.getElementById(inputId);

    // Funções nomeadas para poder remover depois
    function handleDrop(e) {
        e.preventDefault();
        dropArea.classList.remove('highlight');
        for (let file of e.dataTransfer.files) arquivosArray.push(file);
        renderizarLista(arquivosArray, listaId);
    }
    function handleChange() {
        for (let file of fileInput.files) arquivosArray.push(file);
        renderizarLista(arquivosArray, listaId);
    }
    function handleClick() {
        fileInput.click();
    }
    function handleDragOver(e) {
        e.preventDefault();
        dropArea.classList.add('highlight');
    }
    function handleDragLeave() {
        dropArea.classList.remove('highlight');
    }

    // Remove listeners antigos
    dropArea.removeEventListener('click', dropArea._handleClick);
    dropArea.removeEventListener('dragover', dropArea._handleDragOver);
    dropArea.removeEventListener('dragleave', dropArea._handleDragLeave);
    dropArea.removeEventListener('drop', dropArea._handleDrop);
    fileInput.removeEventListener('change', fileInput._handleChange);

    // Adiciona listeners e guarda referência para remover depois
    dropArea.addEventListener('click', handleClick);
    dropArea.addEventListener('dragover', handleDragOver);
    dropArea.addEventListener('dragleave', handleDragLeave);
    dropArea.addEventListener('drop', handleDrop);
    fileInput.addEventListener('change', handleChange);

    // Guarda referência
    dropArea._handleClick = handleClick;
    dropArea._handleDragOver = handleDragOver;
    dropArea._handleDragLeave = handleDragLeave;
    dropArea._handleDrop = handleDrop;
    fileInput._handleChange = handleChange;
}

function renderizarLista(array, listaId) {
    const lista = document.getElementById(listaId);
    lista.innerHTML = '';
    array.forEach((file, i) => {
        // Calcula o tamanho em B, KB, MB ou GB
        let tamanho = file.size;
        let tamanhoStr = '';
        if (tamanho < 1024) {
            tamanhoStr = `${tamanho} B`;
        } else if (tamanho < 1024 * 1024) {
            tamanhoStr = `${(tamanho / 1024).toFixed(1)} KB`;
        } else if (tamanho < 1024 * 1024 * 1024) {
            tamanhoStr = `${(tamanho / (1024 * 1024)).toFixed(2)} MB`;
        } else {
            tamanhoStr = `${(tamanho / (1024 * 1024 * 1024)).toFixed(2)} GB`;
        }

        const li = document.createElement('li');
        li.innerHTML = `<div class="file-info">
            <span>${file.name} <small style="color:#888;">(${tamanhoStr})</small></span>
            <span onclick="removerArquivo(${i}, '${listaId}')" style="cursor:pointer;color: #c00;font-weight: bold;font-size: 1.2em;">×</span>
        </div>`;
        lista.appendChild(li);
    });
}

function removerArquivo(index, listaId) {
    if (listaId === 'fileListPrevia') {
        imagensSelecionadas.splice(index, 1);
        renderizarLista(imagensSelecionadas, listaId);
    } else {
        arquivosFinais.splice(index, 1);
        renderizarLista(arquivosFinais, listaId);
    }
}

// ENVIO DA PRÉVIA
function enviarImagens() {
    if (imagensSelecionadas.length === 0) {
        Toastify({
            text: "Selecione pelo menos uma imagem para enviar a prévia.",
            duration: 3000,
            gravity: "top",
            backgroundColor: "#f44336"
        }).showToast();
        return;
    }
    const formData = new FormData();
    imagensSelecionadas.forEach(file => formData.append('imagens[]', file));
    formData.append('dataIdFuncoes', JSON.stringify(dataIdFuncoes));
    formData.append('nome_funcao', document.getElementById('nome_funcao_upload').value);
    const campoNomeImagem = document.getElementById('campoNomeImagem')?.textContent || '';
    formData.append('nome_imagem', campoNomeImagem);

    // Extrai o número inicial antes do ponto
    const numeroImagem = campoNomeImagem.match(/^\d+/)?.[0] || '';
    formData.append('numeroImagem', numeroImagem);

    // Extrai a nomenclatura (primeira palavra com "_", depois do número e ponto)
    const match = campoNomeImagem.match(/^\d+\.\s*([A-Z0-9]+_[A-Z0-9]+)/i);

    const nomenclatura = match ? match[1] : '';

    formData.append('nomenclatura', nomenclatura);

    // Extrai a primeira palavra da descrição (depois da nomenclatura)
    const descricaoMatch = campoNomeImagem.match(/^\d+\.\s*[A-Z_]+\s+([^\s]+)/);
    const primeiraPalavra = descricaoMatch ? descricaoMatch[1] : '';
    formData.append('primeiraPalavra', primeiraPalavra);

    const statusSelect = document.getElementById('opcao_status');
    const statusNome = statusSelect.options[statusSelect.selectedIndex].text.trim();

    formData.append('status_nome', statusNome);

    fetch('uploadArquivos.php', {
        method: 'POST',
        body: formData
    })
        .then(resp => resp.json())
        .then(res => {
            Toastify({
                text: "Prévia enviada com sucesso!",
                duration: 3000,
                gravity: "top",
                backgroundColor: "#4caf50"
            }).showToast();

            // Avança para próxima etapa
            document.getElementById('etapaPrevia').style.display = 'none';
            document.getElementById('etapaFinal').style.display = 'block';
            document.getElementById('etapaTitulo').textContent = "2. Envio do Arquivo Final";

            Swal.fire({
                position: "center",
                icon: "success",
                title: "Agora adicione o arquivo final",
                showConfirmButton: false,
                timer: 1500,
                didOpen: () => {
                    const title = Swal.getTitle();
                    if (title) title.style.fontSize = "18px";
                }
            });


        })
        .catch(err => {
            Toastify({
                text: "Erro ao enviar prévia",
                duration: 3000,
                gravity: "top",
                backgroundColor: "#f44336"
            }).showToast();
        });
}

// ENVIO DO ARQUIVO FINAL
function enviarArquivo() {
    if (arquivosFinais.length === 0) {
        Toastify({
            text: "Selecione pelo menos um arquivo para enviar a prévia.",
            duration: 3000,
            gravity: "top",
            backgroundColor: "#f44336"
        }).showToast();
        return;
    }

    const formData = new FormData();
    arquivosFinais.forEach(file => formData.append('arquivo_final[]', file));
    formData.append('dataIdFuncoes', JSON.stringify(dataIdFuncoes));
    formData.append('nome_funcao', document.getElementById('nome_funcao_upload').value);

    const campoNomeImagem = document.getElementById('campoNomeImagem')?.textContent || '';
    formData.append('nome_imagem', campoNomeImagem);

    const numeroImagem = campoNomeImagem.match(/^\d+/)?.[0] || '';
    formData.append('numeroImagem', numeroImagem);

    const match = campoNomeImagem.match(/^\d+\.\s*([A-Z0-9]+_[A-Z0-9]+)/i);

    const nomenclatura = match ? match[1] : '';

    formData.append('nomenclatura', nomenclatura);

    const descricaoMatch = campoNomeImagem.match(/^\d+\.\s*[A-Z_]+\s+([^\s]+)/);
    const primeiraPalavra = descricaoMatch ? descricaoMatch[1] : '';
    formData.append('primeiraPalavra', primeiraPalavra);

    const statusSelect = document.getElementById('opcao_status');
    const statusNome = statusSelect.options[statusSelect.selectedIndex].text.trim();

    formData.append('status_nome', statusNome);

    // Criar container de progresso
    const progressContainer = document.createElement('div');
    progressContainer.style.fontSize = '16px';
    progressContainer.innerHTML = `
        <progress id="uploadProgress" value="0" max="100" style="width: 100%; height: 20px;"></progress>
        <div id="uploadStatus">Enviando... 0%</div>
        <div id="uploadTempo">Tempo: 0s</div>
        <div id="uploadVelocidade">Velocidade: 0 MB/s</div>
        <div id="uploadEstimativa">Tempo restante: ...</div>
        <button id="cancelarUpload" style="margin-top:10px;padding:5px 10px;">Cancelar</button>
    `;

    Swal.fire({
        title: 'Enviando arquivo...',
        html: progressContainer,
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            const xhr = new XMLHttpRequest();
            const startTime = Date.now();
            let uploadCancelado = false;

            xhr.open('POST', 'https://improov/ImproovWeb/uploadFinal.php');

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const now = Date.now();
                    const elapsed = (now - startTime) / 1000; // em segundos
                    const uploadedMB = e.loaded / (1024 * 1024);
                    const totalMB = e.total / (1024 * 1024);
                    const percent = (e.loaded / e.total) * 100;
                    const speed = uploadedMB / elapsed; // MB/s
                    const remainingMB = totalMB - uploadedMB;
                    const estimatedTime = remainingMB / (speed || 1); // evita divisão por 0

                    document.getElementById('uploadProgress').value = percent;
                    document.getElementById('uploadStatus').innerText = `Enviando... ${percent.toFixed(2)}%`;
                    document.getElementById('uploadTempo').innerText = `Tempo: ${elapsed.toFixed(1)}s`;
                    document.getElementById('uploadVelocidade').innerText = `Velocidade: ${speed.toFixed(2)} MB/s`;
                    document.getElementById('uploadEstimativa').innerText = `Tempo restante: ${estimatedTime.toFixed(1)}s`;
                }
            });

            xhr.onreadystatechange = () => {
                if (xhr.readyState === 4 && xhr.status === 200 && !uploadCancelado) {
                    const res = JSON.parse(xhr.responseText);
                    const destino = res[0]?.destino || 'Caminho não encontrado';
                    Swal.fire({
                        position: "center",
                        icon: "success",
                        title: "Arquivo final enviado com sucesso!",
                        text: `Salvo em: ${destino}, como: ${res[0]?.nome_arquivo || 'Nome não encontrado'}`,
                        showConfirmButton: false,
                        timer: 2000
                    });
                    fecharModal();
                }
            };

            xhr.onerror = () => {
                if (!uploadCancelado) {
                    Swal.close();
                    Toastify({
                        text: "Erro ao enviar arquivo final",
                        duration: 3000,
                        gravity: "top",
                        backgroundColor: "#f44336"
                    }).showToast();
                }
            };

            // Cancelar envio
            document.getElementById('cancelarUpload').addEventListener('click', () => {
                uploadCancelado = true;
                xhr.abort();
                Swal.fire({
                    icon: 'warning',
                    title: 'Upload cancelado',
                    showConfirmButton: false,
                    timer: 1500
                });
            });

            xhr.send(formData);
        }
    });
}

