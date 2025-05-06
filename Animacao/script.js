var modal = document.getElementById("modal");
var modal_imagem = document.getElementById("modal_imagem");
var add_imagem = document.getElementById("add_imagem");
var closeModal = document.getElementsByClassName("close")[0];
var closeModalImagem = document.getElementsByClassName("close_imagem")[0];
const formAnimacao = document.getElementById('formAnimacao');
const formImagemAnimacao = document.getElementById('formImagemAnimacao');

function limparCampos() {
    document.getElementById('opcao_finalizador').selectedIndex = 0; // Resetar select
    document.getElementById('opcao_cliente').selectedIndex = 0; // Resetar select
    document.getElementById('opcao_obra').selectedIndex = 0; // Resetar select
    document.getElementById('imagem_id').value = ''; // Limpar campo de texto
    document.getElementById('idanimacao').value = ''; // Limpar campo de texto
    document.getElementById('status_cena').selectedIndex = 0; // Limpar campo de texto
    document.getElementById('prazo_cena').value = ''; // Limpar campo de texto
    document.getElementById('status_render').selectedIndex = 0; // Limpar campo de texto
    document.getElementById('prazo_render').value = ''; // Limpar campo de texto
    document.getElementById('status_pos').selectedIndex = 0; // Limpar campo de texto
    document.getElementById('prazo_pos').value = ''; // Limpar campo de texto
    document.getElementById('duracao').value = ''; // Limpar campo de texto
    document.getElementById('status_anima').value = ''; // Limpar campo de texto
}

function limparCamposImagem() {
    document.getElementById('opcao_obra2').selectedIndex = 0; // Resetar select
    document.getElementById('imagem_nome').value = '';
}


add_imagem.onclick = function () {
    modal_imagem.style.display = "flex";
    limparCamposImagem();
};

closeModal.onclick = function () {
    modal.style.display = "none";
    limparCampos();
};
closeModalImagem.onclick = function () {
    modal_imagem.style.display = "none";
    limparCamposImagem();
};

window.onclick = function (event) {
    // Verificar se o clique foi fora do modal de imagem
    if (event.target == modal_imagem) {
        modal_imagem.style.display = "none";
    }
    // Verificar se o clique foi fora do modal principal
    if (event.target == modal) {
        modal.style.display = "none";
    }
};

document.addEventListener("DOMContentLoaded", function () {

    document.getElementById('opcao_obra').addEventListener('change', function () {
        var obraId = this.value;
        buscarImagens(obraId);
    });

    function buscarImagens(obraId) {
        var imagemSelect = document.getElementById('imagem_id');

        // Verifica se o valor selecionado é 0, então busca todas as imagens
        var url = 'buscar_imagens.php';
        if (obraId != "0") {
            url += '?obra_id=' + obraId;
        }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);

                // Limpa as opções atuais
                imagemSelect.innerHTML = '';

                // Adiciona as novas opções com base na resposta
                response.forEach(function (imagem) {
                    var option = document.createElement('option');
                    option.value = imagem.idimagem_animacao;
                    option.text = imagem.imagem_nome;
                    imagemSelect.add(option);
                });
            }
        };
        xhr.send();
    }


    formAnimacao.addEventListener('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);

        fetch('inserir_animacao.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(data => {

                document.getElementById('modal').style.display = 'none';
                limparCampos();
                // atualizarTabela();
                buscarImagens();
                Toastify({
                    text: "Dados inseridos com sucesso!",
                    duration: 3000,
                    close: true,
                    gravity: "top",
                    position: "left",
                    backgroundColor: "green",
                    stopOnFocus: true,
                }).showToast();
            })
            .catch(error => console.error('Erro:', error));
    });

    document.getElementById('deleteButton').addEventListener('click', function () {
        const idAnima = document.getElementById('idanimacao').value;

        if (!idAnima) {
            Toastify({
                text: "Um ou mais IDs não foram selecionados.",
                duration: 3000,
                gravity: "top",
                position: "left",
                backgroundColor: "#ff5f6d",
                close: true
            }).showToast();
            return;
        }

        if (confirm('Tem certeza que deseja deletar os itens?')) {
            fetch('delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id_anima: idAnima
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Toastify({
                            text: "Itens deletados com sucesso.",
                            duration: 3000,
                            gravity: "top",
                            position: "left",
                            backgroundColor: "#ffa200",
                            close: true
                        }).showToast();
                        modal.style.display = "none";
                        atualizarTabela();
                    } else {
                        Toastify({
                            text: "Erro ao deletar itens: " + data.message,
                            duration: 3000,
                            gravity: "top",
                            position: "left",
                            backgroundColor: "red",
                            close: true
                        }).showToast();
                    }
                })
                .catch(error => {
                    console.error('Erro ao deletar:', error);
                    Toastify({
                        text: "Ocorreu um erro ao tentar deletar os itens.",
                        duration: 3000,
                        gravity: "top",
                        position: "left",
                        backgroundColor: "red",
                        close: true
                    }).showToast();
                });
        }
    });

    function atualizarTabela() {
        fetch('atualizar_tabela.php')
            .then(response => response.json())
            .then(data => {
                const tabela = document.getElementById('lista-imagens');
                tabela.innerHTML = '';

                data.forEach(imagem => {
                    const tr = document.createElement('tr');
                    tr.classList.add('linha-tabela');
                    tr.setAttribute('data-id', imagem.idanimacao);
                    tr.setAttribute('data-obra-id', imagem.idobra);

                    // Verifica os status e define as cores
                    const statusCenaClass = getStatusClass(imagem.status_cena);
                    const statusRenderClass = getStatusClass(imagem.status_render);
                    const statusPosClass = getStatusClass(imagem.status_pos);

                    // Define o status animação baseado nos outros status
                    const statusAnima = calcularStatusAnima(imagem.status_cena, imagem.status_render, imagem.status_pos);

                    tr.innerHTML = `
                        <td>${imagem.nome_cliente}</td>
                        <td>${imagem.nome_obra}</td>
                        <td>${imagem.imagem_nome}</td>
                        <td class="${getStatusClass(statusAnima)}">${statusAnima}</td>
                        <td class="${statusCenaClass}">${imagem.status_cena}</td>
                        <td>${imagem.prazo_cena}</td>
                        <td class="${statusRenderClass}">${imagem.status_render}</td>
                        <td>${imagem.prazo_render}</td>
                        <td class="${statusPosClass}">${imagem.status_pos}</td>
                        <td>${imagem.prazo_pos}</td>
                        <td>${imagem.duracao}</td>
                    `;

                    tabela.appendChild(tr);
                });

                const linhasTabela = document.querySelectorAll('.linha-tabela');
                linhasTabela.forEach(linha => {
                    linha.addEventListener('click', function () {
                        modal.style.display = "flex";
                        limparCampos();
                        linhasTabela.forEach(outro => {
                            outro.classList.remove('selecionada');
                        });

                        this.classList.add('selecionada');

                        var idAnimaSelecionada = this.getAttribute('data-id');

                        $.ajax({
                            type: "GET",
                            dataType: "json",
                            url: "https://www.improov.com.br/sistema/Animacao/buscaAJAX.php",
                            data: { ajid: idAnimaSelecionada },
                            success: function (response) {
                                if (response.length > 0) {
                                    setSelectValue('opcao_finalizador', response[0].nome_colaborador);
                                    setSelectValue('opcao_cliente', response[0].nome_cliente);
                                    setSelectValue('opcao_obra', response[0].nome_obra);
                                    setSelectValue('imagem_id', response[0].imagem_nome);
                                    document.getElementById('status_cena').value = response[0].status_cena;
                                    document.getElementById('prazo_cena').value = response[0].prazo_cena;
                                    document.getElementById('status_render').value = response[0].status_render;
                                    document.getElementById('prazo_render').value = response[0].prazo_render;
                                    document.getElementById('status_pos').value = response[0].status_pos;
                                    document.getElementById('prazo_pos').value = response[0].prazo_pos;
                                    document.getElementById('duracao').value = response[0].numero_bg;
                                    document.getElementById('idanimacao').value = response[0].idanimacao;
                                    document.getElementById('idrender').value = response[0].idrender;
                                    document.getElementById('idcena').value = response[0].idcena;
                                    document.getElementById('idpos').value = response[0].idpos;
                                    document.getElementById('duracao').value = response[0].duracao;
                                    setSelectValue('status_anima', response[0].status_anima);

                                } else {
                                    console.log("Nenhum produto encontrado.");
                                }
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                console.error("Erro na requisição AJAX: " + textStatus, errorThrown);
                            }
                        });
                    });
                });
            })
            .catch(error => console.error('Erro ao atualizar a tabela:', error));
    }

    atualizarTabela();

    function setSelectValue(selectId, valueToSelect) {
        var selectElement = document.getElementById(selectId);
        var options = selectElement.options;

        for (var i = 0; i < options.length; i++) {
            if (options[i].text === valueToSelect) {
                selectElement.selectedIndex = i;
                break;
            }
        }
    }

});

function filtrarTabela() {
    var indiceColuna = document.getElementById("colunaFiltro").value;
    var filtro = document.getElementById("filtro-input").value.toLowerCase();
    var tabela = document.querySelector('#lista-imagens');
    var linhas = tabela.getElementsByTagName('tr');

    for (var i = 0; i < linhas.length; i++) {
        var cols = linhas[i].getElementsByTagName('td');
        var mostraLinha = false;

        if (cols[indiceColuna]) {
            var valorColuna = cols[indiceColuna].textContent || cols[indiceColuna].innerText;
            if (valorColuna.toLowerCase().indexOf(filtro) > -1) {
                mostraLinha = true;
            }
        }

        if (mostraLinha) {
            linhas[i].style.display = '';
        } else {
            linhas[i].style.display = 'none';
        }
    }
}


document.getElementById('formImagemAnimacao').addEventListener('submit', function (event) {
    event.preventDefault(); // Impedir o envio padrão do formulário

    const obraId = document.getElementById('opcao_obra2').value;
    const imagemNome = document.getElementById('imagem_nome').value.trim();


    if (obraId === "0" || imagemNome === "") {
        alert("Selecione uma obra e insira um nome de imagem válido!");
        return;
    }

    const formData = new FormData(this);

    fetch('inserir_imagem.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.text())
        .then(result => {
            modal_imagem.style.display = "none";
            limparCampos();
            Toastify({
                text: "Imagem inserida com sucesso.",
                duration: 3000,
                gravity: "top",
                position: "left",
                backgroundColor: "#ffa200",
                close: true
            }).showToast();


        })
        .catch(error => {
            Toastify({
                text: "Erro ao inserir imagem: " + error,
                duration: 3000,
                gravity: "top",
                position: "left",
                backgroundColor: "red",
                close: true
            }).showToast();
        });
});


function getStatusClass(status) {
    switch (status) {
        case 'Finalizado':
            return 'status-finalizado';
        case 'Em andamento':
            return 'status-em-andamento';
        case 'Não iniciado':
            return 'status-nao-iniciado';
        default:
            return '';
    }
}

function calcularStatusAnima(statusCena, statusRender, statusPos) {
    if (statusCena === 'Finalizado' && statusRender === 'Finalizado' && statusPos === 'Finalizado') {
        return 'Finalizado';
    }
    if (statusCena === 'Em andamento' || statusRender === 'Em andamento' || statusPos === 'Em andamento') {
        return 'Em andamento';
    }
    if (statusCena === 'Não iniciado' && statusRender === 'Não iniciado' && statusPos === 'Não iniciado') {
        return 'Não iniciado';
    }
    return 'Em andamento';
}

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