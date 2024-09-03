document.addEventListener("DOMContentLoaded", function () {
    // Seleciona todas as linhas da tabela
    var linhasTabela = document.querySelectorAll(".linha-tabela");

    // Adiciona um evento de clique em cada linha da tabela
    linhasTabela.forEach(function (linha) {
        linha.addEventListener("click", function () {
            // Remove a classe 'selecionada' de todas as linhas
            linhasTabela.forEach(function (outraLinha) {
                outraLinha.classList.remove("selecionada");
            });

            // Adiciona a classe 'selecionada' à linha clicada
            linha.classList.add("selecionada");

            // Obtém o ID da imagem da linha selecionada
            var idImagemSelecionada = linha.getAttribute("data-id");

            // Atualiza o campo de ID da imagem no formulário
            document.getElementById("imagem_id").value = idImagemSelecionada;

            // Faz a requisição AJAX para buscar detalhes das funções
            $.ajax({
                type: "GET",
                dataType: "json",
                url: "http://localhost:8066/ImproovWeb/buscaLinhaAJAX.php",
                data: { ajid: idImagemSelecionada },
                success: function (response) {
                    if (response.length > 0) {
                        // Loop through the response and populate the form fields
                        response.forEach(function (funcao) {
                            // Check which function is being processed and update the respective fields
                            switch (funcao.nome_funcao) {
                                case "Caderno":
                                    document.getElementById("status_caderno").value = funcao.status;
                                    document.getElementById("prazo_caderno").value = funcao.prazo;
                                    document.getElementById("obs_caderno").value = funcao.observacao;
                                    break;
                                case "Composição":
                                    document.getElementById("status_comp").value = funcao.status;
                                    document.getElementById("prazo_comp").value = funcao.prazo;
                                    document.getElementById("obs_comp").value = funcao.observacao;
                                    break;
                                case "Modelagem":
                                    document.getElementById("status_modelagem").value = funcao.status;
                                    document.getElementById("prazo_modelagem").value = funcao.prazo;
                                    document.getElementById("obs_modelagem").value = funcao.observacao;
                                    break;
                                case "Finalização":
                                    document.getElementById("status_finalizacao").value = funcao.status;
                                    document.getElementById("prazo_finalizacao").value = funcao.prazo;
                                    document.getElementById("obs_finalizacao").value = funcao.observacao;
                                    break;
                                case "Pós-produção":
                                    document.getElementById("status_pos").value = funcao.status;
                                    document.getElementById("prazo_pos").value = funcao.prazo;
                                    document.getElementById("obs_pos").value = funcao.observacao;
                                    break;
                                case "Planta Humanizada":
                                    document.getElementById("status_planta_humanizada").value = funcao.status;
                                    document.getElementById("prazo_planta_humanizada").value = funcao.prazo;
                                    document.getElementById("obs_planta_humanizada").value = funcao.observacao;
                                    break;
                            }
                        });
                    } else {
                        Toastify({
                            text: "Nenhuma função encontrada para essa imagem",
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
                    console.error("Erro na requisição AJAX: " + textStatus, errorThrown);
                }
            });

            console.log("Linha selecionada: ID da imagem = " + idImagemSelecionada);
        });
    });

    document.getElementById("salvar_funcoes").addEventListener("click", function (event) {
        event.preventDefault(); // Impede o envio padrão do formulário

        // Obtém o ID da imagem selecionada
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

        // Obtém os textos das tags <p>
        var textos = {};
        var pElements = document.querySelectorAll(".form-edicao p");
        pElements.forEach(function (p) {
            textos[p.id] = p.textContent.trim();
        });

        // Coleta os dados do formulário
        var dados = {
            imagem_id: document.getElementById("imagem_id").value,
            caderno_id: document.getElementById("opcao_caderno").value,
            status_caderno: document.getElementById("status_caderno").value,
            prazo_caderno: document.getElementById("prazo_caderno").value,
            obs_caderno: document.getElementById("obs_caderno").value,
            comp_id: document.getElementById("opcao_comp").value,
            status_comp: document.getElementById("status_comp").value,
            prazo_comp: document.getElementById("prazo_comp").value,
            obs_comp: document.getElementById("obs_comp").value,
            model_id: document.getElementById("opcao_model").value,
            status_modelagem: document.getElementById("status_modelagem").value,
            prazo_modelagem: document.getElementById("prazo_modelagem").value,
            obs_modelagem: document.getElementById("obs_modelagem").value,
            final_id: document.getElementById("opcao_final").value,
            status_finalizacao: document.getElementById("status_finalizacao").value,
            prazo_finalizacao: document.getElementById("prazo_finalizacao").value,
            obs_finalizacao: document.getElementById("obs_finalizacao").value,
            pos_id: document.getElementById("opcao_pos").value,
            status_pos: document.getElementById("status_pos").value,
            prazo_pos: document.getElementById("prazo_pos").value,
            obs_pos: document.getElementById("obs_pos").value,
            planta_id: document.getElementById("opcao_planta").value,
            status_planta_humanizada: document.getElementById("status_planta_humanizada").value,
            prazo_planta_humanizada: document.getElementById("prazo_planta_humanizada").value,
            obs_planta_humanizada: document.getElementById("obs_planta_humanizada").value,
            textos: textos // Inclui os textos das tags <p>
        };

        // Envia os dados para o servidor via AJAX
        $.ajax({
            type: "POST",
            url: "http://localhost:8066/ImproovWeb/insereFuncao.php",
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
    });
});

function filtrarTabela() {
    // Pega os valores do select e do input de pesquisa
    var indiceColuna = document.getElementById("colunaFiltro").value;
    var filtro = document.getElementById("pesquisa").value.toLowerCase();

    // Pega a referência da tabela e do tbody
    var tabela = document.getElementById("tabelaClientes");
    var tbody = tabela.getElementsByTagName("tbody")[0];
    var linhas = tbody.getElementsByTagName("tr");

    // Loop através de todas as linhas da tabela e oculta aquelas que não correspondem ao filtro
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

// Adiciona um event listener para o campo de pesquisa para filtrar ao pressionar Enter
document.getElementById("pesquisa").addEventListener("keyup", function (event) {
    if (event.key === "Enter") {
        filtrarTabela();
    }
});

function openModal(modalId, element) {
    // Fecha qualquer modal que esteja aberto
    closeModal('add-cliente');
    closeModal('add-imagem');

    // Mostra o modal correspondente, se houver
    if (modalId) {
        document.getElementById(modalId).style.display = 'flex';
    }

    // Remove a classe 'active' de todos os links de navegação
    var navLinks = document.querySelectorAll('nav a');
    navLinks.forEach(function (link) {
        link.classList.remove('active');
    });

    // Adiciona a classe 'active' ao link clicado
    if (element) {
        element.classList.add('active');
    }
}

function closeModal(modalId) {
    if (modalId) {
        // Esconde o modal correspondente
        document.getElementById(modalId).style.display = 'none';
    }

    // Remove a classe 'active' de todos os links de navegação
    var navLinks = document.querySelectorAll('nav a');
    navLinks.forEach(function (link) {
        link.classList.remove('active');
    });

    // Configura 'Ver imagens' como o link ativo
    var verImagensLink = document.querySelector('nav a[href="#filtro"]');
    verImagensLink.classList.add('active');
}


function submitForm(event) {
    event.preventDefault(); // Evita o envio tradicional do formulário

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
                    duration: 3000, // 3 segundos
                    close: true,
                    gravity: "top", // Toast aparecerá na parte superior
                    position: "right", // Toast será posicionado à direita
                    backgroundColor: "green",
                    stopOnFocus: true, // Parar se o usuário passar o mouse por cima
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

            closeModal('add-cliente'); // Fecha o modal após a inserção
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
                    duration: 3000, // 3 segundos
                    close: true,
                    gravity: "top", // Toast aparecerá na parte superior
                    position: "right", // Toast será posicionado à direita
                    backgroundColor: "green",
                    stopOnFocus: true, // Parar se o usuário passar o mouse por cima
                }).showToast();

                // Recarregar a página após 3 segundos (tempo para a Toastify desaparecer)
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

            closeModal('add-cliente'); // Fecha o modal após a inserção
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