var modal = document.getElementById("modal");
var modalRender = document.getElementById("renderModal");
var openModalBtn = document.getElementById("openModalBtn");
var openModalBtnRender = document.getElementById("openModalBtnRender");
var closeModal = document.getElementsByClassName("close")[0];
var closeModalRender = document.getElementsByClassName("closeModalRender")[0];
const formPosProducao = document.getElementById('formPosProducao');

function limparCampos() {
    document.getElementById('opcao_finalizador').selectedIndex = 0; // Resetar select
    document.getElementById('opcao_obra').selectedIndex = 0; // Resetar select
    document.getElementById('imagem_id_pos').value = ''; // Limpar campo de texto
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

    // function buscarImagens(obraId) {
    //     var imagemSelect = document.getElementById('imagem_id_pos');

    //     // Verifica se o valor selecionado é 0, então busca todas as imagens
    //     var url = 'buscar_imagens.php';
    //     if (obraId != "0") {
    //         url += '?obra_id=' + obraId;
    //     }

    //     var xhr = new XMLHttpRequest();
    //     xhr.open('GET', url, true);
    //     xhr.onreadystatechange = function () {
    //         if (xhr.readyState === 4 && xhr.status === 200) {
    //             var response = JSON.parse(xhr.responseText);

    //             // Limpa as opções atuais
    //             imagemSelect.innerHTML = '';

    //             // Adiciona as novas opções com base na resposta
    //             response.forEach(function (imagem) {
    //                 var option = document.createElement('option');
    //                 option.value = imagem.idimagens_cliente_obra;
    //                 option.text = imagem.imagem_nome;
    //                 imagemSelect.add(option);
    //             });
    //         }
    //     };
    //     xhr.send();
    // }


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
                // buscarImagens();
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

    let tabelaGlobal; // Declara a variável da tabela fora para reuso

    function atualizarTabela() {
        fetch('atualizar_tabela.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {

                if (!Array.isArray(data) || data.length === 0) {
                    console.warn("Dados vazios ou inválidos recebidos. A tabela pode não renderizar.");
                    document.getElementById('tabela-imagens').innerHTML = '<p>Nenhum dado encontrado para exibir.</p>';
                    document.getElementById('total-pos').innerText = 0;
                    return;
                }

                function listaValores(col) {
                    let valores = [];
                    data.forEach(item => {
                        if (item[col] && !valores.includes(item[col])) {
                            valores.push(item[col]);
                        }
                    });
                    return valores.sort();
                }

                // Se a tabela ainda não existe, cria ela
                if (!tabelaGlobal) {
                    tabelaGlobal = new Tabulator("#tabela-imagens", {
                        data: data,
                        layout: "fitColumns",
                        pagination: "local",
                        responsiveLayout: true,
                        index: 'idpos_producao',
                        maxHeight: "80vh",
                        height: "100%",
                        pagination: true,
                        paginationSize: 100,


                        rowFormatter: function (row) {
                            let rowData = row.getData();
                            let rowIdValue = rowData.idpos_producao;

                            row.getElement().setAttribute("data-tabulator-id", rowIdValue);
                        },

                        columns: [
                            { title: "Status Render", field: "status_render", headerFilter: "list", headerFilterParams: { values: listaValores("status_render") }, formatter: cell => { let val = cell.getValue(); let cor = val === "Finalizado" ? "green" : (val === "Em andamento" ? "orange" : "red"); return `<span style="background:${cor};color:white;padding:4px 6px;border-radius:4px;font-size:12px">${val || ''}</span>`; } },
                            { title: "Nome Finalizador", field: "nome_colaborador", headerFilter: "list", headerFilterParams: { values: listaValores("nome_colaborador") } },
                            { title: "Nome Obra", field: "nomenclatura", headerFilter: "list", headerFilterParams: { values: listaValores("nomenclatura") } }, // Alterei para input, list para nome_obra pode ser muito grande
                            {
                                title: "Data",
                                field: "data_pos",
                                headerFilter: "input",
                                // --- ADICIONE ESTA FUNÇÃO ---
                                headerFilterFunc: function (headerValue, rowValue, rowData, filterParams) {

                                    let formattedRowDate = formatarDataHora(rowValue);

                                    let lowerFormattedRowDate = String(formattedRowDate).toLowerCase();
                                    let lowerHeaderValue = String(headerValue).toLowerCase();

                                    return lowerFormattedRowDate.includes(lowerHeaderValue);
                                },
                                formatter: cell => formatarDataHora(cell.getValue())
                            }, { title: "Nome imagem", field: "imagem_nome", headerFilter: "input", widthGrow: 3 },
                            { title: "Status", field: "status_pos", headerFilter: "list", headerFilterParams: { values: { 1: "Não começou", 0: "Finalizado" } }, formatter: cell => { let val = cell.getValue(); let txt = val == 1 ? "Não começou" : "Finalizado"; let cor = val == 1 ? "red" : "green"; return `<span style="background:${cor};color:white;padding:4px 6px;border-radius:4px;font-size:12px">${txt}</span>`; } },
                            { title: "Revisão", field: "nome_status", headerFilter: "list", headerFilterParams: { values: listaValores("nome_status") } },
                            { title: "Responsável", field: "nome_responsavel", headerFilter: "list", headerFilterParams: { values: listaValores("nome_responsavel") } },
                        ],

                    });
                    // Atualiza total ao filtrar
                    tabelaGlobal.on("dataFiltered", function (filters, rows) {
                        document.getElementById('total-pos').innerText = rows.length;
                    });
                    // *** Adiciona o listener de clique no container da tabela APÓS a criação da tabela ***
                    document.getElementById('tabela-imagens').addEventListener('click', function (event) {
                        // Verifica se o clique foi em uma linha da tabela (ou em um filho de uma linha)
                        let target = event.target;
                        let rowElement = null;

                        rowElement = target.closest('.tabulator-row');

                        if (rowElement) {

                            let rowId = rowElement.getAttribute('data-tabulator-id');


                            if (rowId) { // Certifique-se de que o rowId não é nulo antes de tentar usá-lo
                                const clickedRow = tabelaGlobal.getRow(rowId); // Isso deve funcionar agora!
                                if (clickedRow) {
                                    let dados = clickedRow.getData();

                                    const statusRender = dados.status_render?.trim() || '';
                                    if (!modal) {
                                        console.error("Elemento modal não encontrado no DOM. Verifique o ID ou se o elemento existe.");
                                        return;
                                    }

                                    if (statusRender !== 'Finalizado') {
                                        Swal.fire({
                                            icon: 'warning',
                                            title: 'Atenção',
                                            text: 'O status deste item não é "Finalizado". Deseja continuar?',
                                            showCancelButton: true,
                                            confirmButtonText: 'OK',
                                            cancelButtonText: 'Sair'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                modal.style.display = "flex";
                                                limparCampos();
                                                buscarInfosImagem(dados.idpos_producao);
                                            }
                                        });
                                    } else {
                                        modal.style.display = "flex";
                                        limparCampos();
                                        buscarInfosImagem(dados.idpos_producao);
                                    }
                                } else {
                                    console.warn("Não foi possível encontrar os dados da linha para o elemento clicado.");
                                }
                            } else {
                                console.warn("WARN: O atributo 'data-tabulator-id' não foi encontrado no elemento da linha clicada.");
                            }
                        }
                    });

                } else { // Se a tabela já existe, apenas atualiza os dados
                    tabelaGlobal.setData(data); // ou .replaceData(data)
                }

                document.getElementById('total-pos').innerText = tabelaGlobal.getDataCount("active");
            })
            .catch(error => console.error('Erro ao atualizar a tabela ou buscar dados:', error));
    }

    atualizarTabela();

    document.getElementById('tabela-imagens').addEventListener('click', function (event) {
        // Verifica se o clique foi em uma linha da tabela (ou em um filho de uma linha)
        let target = event.target;
        let rowElement = null;

        // Percorre os pais até encontrar um elemento de linha do Tabulator
        while (target && target !== this) {
            if (target.classList.contains('tabulator-row')) {
                rowElement = target;
                break;
            }
            target = target.parentNode;
        }

        if (rowElement) {
            let rowId = rowElement.getAttribute('tabulator-row');

            if (clickedRow) {
                let dados = clickedRow.getData();
                console.log("Clicado via delegação de eventos. Dados da linha:", dados);

                const statusRender = dados.status_render?.trim() || '';
                if (!modal) {
                    console.error("Elemento modal não encontrado no DOM. Verifique o ID ou se o elemento existe.");
                    return;
                }

                if (statusRender !== 'Finalizado') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Atenção',
                        text: 'O status deste item não é "Finalizado". Deseja continuar?',
                        showCancelButton: true,
                        confirmButtonText: 'OK',
                        cancelButtonText: 'Sair'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            modal.style.display = "flex";
                            limparCampos();
                            buscarInfosImagem(dados.idpos_producao);
                        }
                    });
                } else {
                    modal.style.display = "flex";
                    limparCampos();
                    buscarInfosImagem(dados.idpos_producao);
                }
            } else {
                console.warn("Não foi possível encontrar os dados da linha para o elemento clicado.");
            }
        }
    });
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

function buscarInfosImagem(idImagemSelecionada) {
    $.ajax({
        type: "GET",
        dataType: "json",
        url: "https://www.improov.com.br/sistema/Pos-Producao/buscaAJAX.php",
        data: { ajid: idImagemSelecionada },
        success: function (response) {
            if (response.length > 0) {
                setSelectValue('opcao_finalizador', response[0].nome_colaborador);
                setSelectValue('opcao_obra', response[0].nome_obra);
                document.getElementById('imagem_id_pos').value = response[0].id_imagem;
                document.getElementById('id-pos').value = response[0].idpos_producao;
                document.getElementById('caminhoPasta').value = response[0].caminho_pasta;
                document.getElementById('numeroBG').value = response[0].numero_bg;
                document.getElementById('referenciasCaminho').value = response[0].refs;
                document.getElementById('observacao').value = response[0].obs;
                document.getElementById('render_id_pos').value = response[0].idrender;
                setSelectValue('opcao_status', response[0].nome_status);

                const checkboxStatusPos = document.getElementById('status_pos');
                checkboxStatusPos.checked = response[0].status_pos == 0;
                checkboxStatusPos.disabled = false;

                document.getElementById('alterar_imagem').value = 'true';
                setSelectValue('responsavel_id', response[0].nome_responsavel);

            } else {
                console.log("Nenhum produto encontrado.");
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error("Erro na requisição AJAX: " + textStatus, errorThrown);
        }
    });
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

// // Atualiza os eventos para chamar a nova função de filtro combinado
// document.getElementById("colunaFiltro").addEventListener("change", aplicarFiltros);
// document.getElementById("filtro-input").addEventListener("input", aplicarFiltros);
// document.getElementById("filtro-mes").addEventListener("change", aplicarFiltros);
