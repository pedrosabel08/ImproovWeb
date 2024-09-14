// Seleciona o modal, botão e o botão de fechar
var modal = document.getElementById("modal");
var openModalBtn = document.getElementById("openModalBtn");
var closeModal = document.getElementsByClassName("close")[0];
const formPosProducao = document.getElementById('formPosProducao');

// Abre o modal ao clicar no botão
openModalBtn.onclick = function () {
    modal.style.display = "flex";
}

// Fecha o modal ao clicar no "X"
closeModal.onclick = function () {
    modal.style.display = "none";
}

// Fecha o modal ao clicar fora da área de conteúdo
window.onclick = function (event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

function buscarImagens(obraId = null, imagemSelecionada = null) {
    obraId = obraId || document.getElementById('opcao_obra').value;

    if (obraId) {
        // Faz uma requisição AJAX para buscar as imagens da obra selecionada
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'buscar_imagens.php?obra_id=' + obraId, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4 && xhr.status == 200) {
                document.getElementById('nomeImagem').innerHTML = xhr.responseText;

                // Se uma imagem já estiver selecionada, marcá-la como escolhida
                if (imagemSelecionada) {
                    var options = document.getElementById('nomeImagem').options;
                    for (var i = 0; i < options.length; i++) {
                        if (options[i].text === imagemSelecionada) {
                            options[i].selected = true;
                            break;
                        }
                    }
                }
            }
        };
        xhr.send();
    } else {
        document.getElementById('nomeImagem').innerHTML = '<option value="">Selecione uma obra primeiro</option>';
    }
}

document.getElementById('opcao_obra').addEventListener('change', buscarImagens);


document.addEventListener("DOMContentLoaded", function () {
    formPosProducao.addEventListener('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);

        fetch('inserir_pos_producao.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(data => {

                document.getElementById('modal').style.display = 'none';
                atualizarTabela();
                formPosProducao.reset();
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
        fetch('atualizar_tabela.php') // Caminho para o seu script PHP
            .then(response => response.json())
            .then(data => {
                const tabela = document.getElementById('lista-imagens');
                tabela.innerHTML = ''; // Limpa a tabela atual

                data.forEach(imagem => {
                    // Cria uma nova linha
                    const tr = document.createElement('tr');
                    tr.classList.add('linha-tabela');
                    tr.setAttribute('data-id', imagem.idpos_producao);
                    tr.setAttribute('data-obra-id', imagem.idobra); // Adiciona o data-obra-id para uso posterior

                    // Verifica o status_pos e define o texto e a cor de fundo apropriada
                    let statusTexto = imagem.status_pos == 1 ? 'Não começou' : 'Finalizado';
                    let statusCor = imagem.status_pos == 1 ? 'red' : 'green';

                    // Adiciona as células à linha
                    tr.innerHTML = `
                        <td>${imagem.nome_colaborador}</td>
                        <td>${imagem.nome_cliente}</td>
                        <td>${imagem.nome_obra}</td>
                        <td>${imagem.data_pos}</td>
                        <td>${imagem.imagem_nome}</td>
                        <td>${imagem.caminho_pasta}</td>
                        <td>${imagem.numero_bg}</td>
                        <td>${imagem.refs}</td>
                        <td>${imagem.obs}</td>
                        <td style="background-color: ${statusCor}; color: white;">${statusTexto}</td>
                        <td>${imagem.nome_status}</td>
                    `;

                    // Adiciona a linha à tabela
                    tabela.appendChild(tr);
                });

                // Adiciona o evento de clique às linhas
                const linhasTabela = document.querySelectorAll('.linha-tabela');
                linhasTabela.forEach(linha => {
                    linha.addEventListener('click', function () {
                        modal.style.display = "flex";
                        linhasTabela.forEach(outro => {
                            outro.classList.remove('selecionada');
                        });

                        this.classList.add('selecionada');

                        var idImagemSelecionada = this.getAttribute('data-id');

                        $.ajax({
                            type: "GET",
                            dataType: "json",
                            url: "www.improov.com.br/sistema/Pos-Producao/buscaAJAX.php",
                            data: { ajid: idImagemSelecionada },
                            success: function (response) {
                                if (response.length > 0) {
                                    setSelectValue('opcao_finalizador', response[0].nome_colaborador);
                                    setSelectValue('opcao_cliente', response[0].nome_cliente);
                                    setSelectValue('opcao_obra', response[0].nome_obra);
                                    setSelectValue('imagem_id', response[0].imagem_nome);
                                    document.getElementById('caminhoPasta').value = response[0].caminho_pasta;
                                    document.getElementById('numeroBG').value = response[0].numero_bg;
                                    document.getElementById('referenciasCaminho').value = response[0].refs;
                                    document.getElementById('observacao').value = response[0].obs;
                                    setSelectValue('opcao_status', response[0].status_pos);

                                    const checkboxStatusPos = document.getElementById('status_pos');
                                    checkboxStatusPos.checked = response[0].status_pos == 0;
                                    checkboxStatusPos.disabled = false;
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

    document.getElementById('formPosProducao').addEventListener('submit', function () {
        document.getElementById('status_pos').disabled = true;
    });


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
