var modal = document.getElementById("modal");
var modalRender = document.getElementById("renderModal");
var openModalBtn = document.getElementById("openModalBtn");
var openModalBtnRender = document.getElementById("openModalBtnRender");
var closeModal = document.getElementsByClassName("close")[0];
var closeModalRender = document.getElementsByClassName("closeModalRender")[0];
const formPosProducao = document.getElementById('formPosProducao');

function limparCampos() {
    document.getElementById('opcao_finalizador').selectedIndex = 0; // Resetar select
    document.getElementById('opcao_cliente').selectedIndex = 0; // Resetar select
    document.getElementById('opcao_obra').selectedIndex = 0; // Resetar select
    document.getElementById('imagem_id').value = ''; // Limpar campo de texto
    document.getElementById('id-pos').value = ''; // Limpar campo de texto
    document.getElementById('caminhoPasta').value = ''; // Limpar campo de texto
    document.getElementById('numeroBG').value = ''; // Limpar campo de texto
    document.getElementById('referenciasCaminho').value = ''; // Limpar campo de texto
    document.getElementById('observacao').value = ''; // Limpar campo de texto
}

openModalBtn.onclick = function () {
    modal.style.display = "flex";
    limparCampos();
};
openModalBtnRender.onclick = function () {
    modalRender.style.display = "flex";
    limparCampos();
};

closeModal.onclick = function () {
    modal.style.display = "none";
    limparCampos();
};
closeModalRender.onclick = function () {
    modalRender.style.display = "none";
    limparCampos();
};

window.onclick = function (event) {
    if (event.target == modalRender) {
        modalRender.style.display = "none";
    }
}

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

    document.getElementById('deleteButton').addEventListener('click', function () {
        const idPos = document.getElementById('id-pos').value;

        if (!idPos) {
            Toastify({
                text: "Nenhum item selecionado para deletar.",
                duration: 3000,
                gravity: "top",
                position: "left",
                backgroundColor: "#ff5f6d",
                close: true
            }).showToast();
            return;
        }

        if (confirm('Tem certeza que deseja deletar este item?')) {
            fetch('delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id_pos: idPos })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Toastify({
                            text: "Item deletado com sucesso.",
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
                            text: "Erro ao deletar item: " + data.message,
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
                        text: "Ocorreu um erro ao tentar deletar o item.",
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
                    tr.setAttribute('data-id', imagem.idpos_producao);
                    tr.setAttribute('data-obra-id', imagem.idobra);

                    let statusTexto = imagem.status_pos == 1 ? 'Não começou' : 'Finalizado';
                    let statusCor = imagem.status_pos == 1 ? 'red' : 'green';

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

                    tabela.appendChild(tr);
                });

                contarLinhasTabela();

                const linhasTabela = document.querySelectorAll('.linha-tabela');
                linhasTabela.forEach(linha => {
                    linha.addEventListener('click', function () {
                        modal.style.display = "flex";
                        limparCampos();
                        linhasTabela.forEach(outro => {
                            outro.classList.remove('selecionada');
                        });

                        this.classList.add('selecionada');

                        var idImagemSelecionada = this.getAttribute('data-id');

                        $.ajax({
                            type: "GET",
                            dataType: "json",
                            url: "https://www.improov.com.br/sistema/Pos-Producao/buscaAJAX.php",
                            data: { ajid: idImagemSelecionada },
                            success: function (response) {
                                if (response.length > 0) {
                                    setSelectValue('opcao_finalizador', response[0].nome_colaborador);
                                    setSelectValue('opcao_cliente', response[0].nome_cliente);
                                    setSelectValue('opcao_obra', response[0].nome_obra);
                                    setSelectValue('imagem_id', response[0].imagem_nome);
                                    document.getElementById('id-pos').value = response[0].idpos_producao;
                                    document.getElementById('caminhoPasta').value = response[0].caminho_pasta;
                                    document.getElementById('numeroBG').value = response[0].numero_bg;
                                    document.getElementById('referenciasCaminho').value = response[0].refs;
                                    document.getElementById('observacao').value = response[0].obs;
                                    setSelectValue('opcao_status', response[0].nome_status);

                                    const checkboxStatusPos = document.getElementById('status_pos');
                                    checkboxStatusPos.checked = response[0].status_pos == 0;
                                    checkboxStatusPos.disabled = false;

                                    document.getElementById('alterar_imagem').value = 'true';

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

function contarLinhasTabela() {
    const tabela = document.getElementById("tabela-imagens");
    const tbody = tabela.getElementsByTagName("tbody")[0];
    const linhas = tbody.getElementsByTagName("tr");
    let totalImagens = 0;

    for (let i = 0; i < linhas.length; i++) {
        if (linhas[i].style.display !== "none") {
            totalImagens++;
        }
    }

    document.getElementById("total-pos").innerText = totalImagens;
}

function aplicarFiltros() {
    const indiceColuna = document.getElementById("colunaFiltro").value;
    const filtro = document.getElementById("filtro-input").value.toLowerCase();
    const filtroMes = document.getElementById('filtro-mes').value;
    const anoAtual = new Date().getFullYear();
    const tabela = document.querySelector('#tabela-imagens tbody');
    const linhas = tabela.getElementsByTagName('tr');

    for (let i = 0; i < linhas.length; i++) {
        const linha = linhas[i];
        const cols = linha.getElementsByTagName('td');
        let mostraLinha = true;

        // Filtro por coluna
        if (cols[indiceColuna]) {
            const valorColuna = cols[indiceColuna].textContent || cols[indiceColuna].innerText;
            if (valorColuna.toLowerCase().indexOf(filtro) === -1) {
                mostraLinha = false;
            }
        }

        // Filtro por mês e ano atual
        const dataCell = linha.cells[3];
        if (dataCell) {
            const dataTexto = dataCell.textContent || dataCell.innerText;
            const [anoData, mesData] = dataTexto.split("-");
            if (filtroMes !== "" && (mesData !== filtroMes || anoData !== anoAtual.toString())) {
                mostraLinha = false;
            }
        }

        // Exibe ou oculta a linha com base nos filtros
        linha.style.display = mostraLinha ? "" : "none";
    }

    contarLinhasTabela(); // Atualiza o contador
}

// Atualiza os eventos para chamar a nova função de filtro combinado
document.getElementById("colunaFiltro").addEventListener("change", aplicarFiltros);
document.getElementById("filtro-input").addEventListener("input", aplicarFiltros);
document.getElementById("filtro-mes").addEventListener("change", aplicarFiltros);
