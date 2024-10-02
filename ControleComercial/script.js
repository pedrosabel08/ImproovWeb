var modal = document.getElementById("modal");
var openModalBtn = document.getElementById("openModalBtn");
var closeModal = document.getElementsByClassName("close")[0];
const form_comercial = document.getElementById('form_comercial');


openModalBtn.onclick = function () {
    modal.style.display = "flex";
};

closeModal.onclick = function () {
    modal.style.display = "none";
};

window.onclick = function (event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

form_comercial.addEventListener('submit', function (e) {
    e.preventDefault();

    var formData = new FormData(this);

    fetch('inserir_orcamento.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.text())
        .then(data => {
            document.getElementById('modal').style.display = 'none';
            atualizarTabela();
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
            const tabela = document.getElementById('lista-orcamentos');
            tabela.innerHTML = '';

            data.forEach(orcamento => {
                const tr = document.createElement('tr');
                tr.classList.add('linha-tabela');
                tr.setAttribute('data-id', orcamento.idcontrole);

                tr.innerHTML = `
                    <td>${orcamento.resp}</td>
                    <td>${orcamento.contato}</td>
                    <td>${orcamento.construtora}</td>
                    <td>${orcamento.obra}</td>
                    <td>${orcamento.valor}</td>
                    <td>${orcamento.status}</td>
                    <td>${orcamento.mes}</td>
                `;

                tabela.appendChild(tr);
            });

            // Adiciona eventos de clique nas linhas da tabela após a atualização
            const linhasTabela = document.querySelectorAll('.linha-tabela');
            linhasTabela.forEach(linha => {
                linha.addEventListener('click', function () {
                    modal.style.display = "flex";
                    linhasTabela.forEach(outro => {
                        outro.classList.remove('selecionada');
                    });

                    this.classList.add('selecionada');

                    var idSelecionado = this.getAttribute('data-id');

                    $.ajax({
                        type: "GET",
                        dataType: "json",
                        url: "http://www.improov.com.br/sistema/ControleComercial/buscaAJAX.php",
                        data: { ajid: idSelecionado },
                        success: function (response) {
                            if (response.length > 0) {
                                document.getElementById('idcontrole').value = response[0].idcontrole;
                                setSelectValue('resp', response[0].resp);
                                document.getElementById('contato').value = response[0].contato;
                                document.getElementById('construtora').value = response[0].construtora;
                                document.getElementById('obra').value = response[0].obra;
                                document.getElementById('valor').value = response[0].valor;
                                setSelectValue('status', response[0].status);
                                setSelectValue('mes', response[0].mes);

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
        });
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


function filtrarTabela() {
    var indiceColuna = document.getElementById("colunaFiltro").value;
    var filtro = document.getElementById("filtro-select").value.toLowerCase();
    var tabela = document.querySelector('#lista-orcamentos');
    var linhas = tabela.getElementsByTagName('tr');

    for (var i = 0; i < linhas.length; i++) {
        var cols = linhas[i].getElementsByTagName('td');
        var mostraLinha = false;

        // Se a coluna existir, aplique o filtro
        if (cols[indiceColuna]) {
            var valorColuna = cols[indiceColuna].textContent || cols[indiceColuna].innerText;
            // Checa se o valor na coluna corresponde ao filtro selecionado
            if (valorColuna.toLowerCase() === filtro || filtro === "") {
                mostraLinha = true;
            }
        }

        // Mostrar ou esconder a linha
        if (mostraLinha) {
            linhas[i].style.display = '';
        } else {
            linhas[i].style.display = 'none';
        }
    }
}
