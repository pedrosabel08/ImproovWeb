var modalFiltro = document.getElementById("modalFiltro");
var modalCaderno = document.getElementById("modalCaderno");
var openFiltro = document.getElementById("openFiltro");
var closeModal = document.getElementsByClassName("close")[0];
var closeFiltro = document.getElementsByClassName("close-filtro")[0];
const formCaderno = document.getElementById('formCaderno');
const formFiltro = document.getElementById('formFiltro');

function limparCampos() {
    document.getElementById('opcao_finalizador').selectedIndex = 0; // Resetar select
    document.getElementById('opcao_cliente').selectedIndex = 0; // Resetar select
    document.getElementById('opcao_obra').selectedIndex = 0; // Resetar select
    document.getElementById('imagem_id').value = ''; // Limpar campo de texto
    document.getElementById('idfuncao_imagem').value = ''; // Limpar campo de texto
    document.getElementById('status').selectedIndex = 0; // Limpar campo de texto
    document.getElementById('prazo').value = ''; // Limpar campo de texto
}

openFiltro.onclick = function () {
    modalFiltro.style.display = "flex";
    limparCampos();
};

closeFiltro.onclick = function () {
    modalFiltro.style.display = "none";
    limparCampos();
};

closeModal.onclick = function () {
    modalCaderno.style.display = "none";
    limparCampos();
};

window.onclick = function (event) {
    // Verificar se o clique foi fora do modal principal
    if (event.target == modalFiltro) {
        modalFiltro.style.display = "none";
    }
    if (event.target == modalCaderno) {
        modalCaderno.style.display = "none";
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
                    option.value = imagem.idimagens_cliente_obra;
                    option.text = imagem.imagem_nome;
                    imagemSelect.add(option);
                });
            }
        };
        xhr.send();
    }


    formCaderno.addEventListener('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);

        fetch('update_funcao_caderno.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(data => {

                document.getElementById('modalFiltro').style.display = 'none';
                limparCampos();
                atualizarTabela();
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

    formFiltro.addEventListener('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);

        fetch('update_funcao_caderno.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(data => {

                document.getElementById('modalFiltro').style.display = 'none';
                limparCampos();
                atualizarTabela();
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

    function atualizarTabela() {
        fetch('atualizar_tabela.php')
            .then(response => response.json())
            .then(data => {
                const tabela = document.getElementById('lista-imagens');
                tabela.innerHTML = '';

                data.forEach(imagem => {
                    const tr = document.createElement('tr');
                    tr.classList.add('linha-tabela');
                    tr.setAttribute('data-id', imagem.idfuncao_imagem);
                    tr.setAttribute('data-obra-id', imagem.idobra);

                    // Define o status animação baseado nos outros status

                    tr.innerHTML = `
                        <td>${imagem.nome_colaborador}</td>
                        <td>${imagem.nome_cliente}</td>
                        <td>${imagem.nome_obra}</td>
                        <td>${imagem.imagem_nome}</td>
                        <td>${imagem.status}</td>
                        <td>${imagem.prazo}</td>
                    `;

                    tabela.appendChild(tr);
                });

                const linhasTabela = document.querySelectorAll('.linha-tabela');
                linhasTabela.forEach(linha => {
                    linha.addEventListener('click', function () {
                        modalCaderno.style.display = "flex";
                        limparCampos();
                        linhasTabela.forEach(outro => {
                            outro.classList.remove('selecionada');
                        });

                        this.classList.add('selecionada');

                        var idLinhaSelecionada = this.getAttribute('data-id');

                        $.ajax({
                            type: "GET",
                            dataType: "json",
                            url: "http://192.168.0.202:8066/ImproovWeb/Arquitetura/buscaAJAX.php",
                            data: { ajid: idLinhaSelecionada },
                            success: function (response) {
                                if (response.length > 0) {
                                    setSelectValue('opcao_finalizador', response[0].nome_colaborador);
                                    setSelectValue('opcao_cliente', response[0].nome_cliente);
                                    setSelectValue('opcao_obra', response[0].nome_obra);
                                    setSelectValue('imagem_id', response[0].imagem_nome);
                                    setSelectValue('status', response[0].status);
                                    document.getElementById('prazo').value = response[0].prazo;
                                    document.getElementById('idfuncao_imagem').value = response[0].idfuncao_imagem;


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