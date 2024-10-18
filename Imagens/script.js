const modal = document.getElementById('modal');
const form = document.getElementById('formularioModal');

function abrirModal() {
    modal.style.display = "block";
}

// Função para fechar o modal
function fecharModal() {
    modal.style.display = "none";
    limparCampos();
}

// Para fechar o modal quando clicar fora dele
window.onclick = function (event) {
    var modal = document.getElementById("modal");
    if (event.target == modal) {
        fecharModal();
    }
};

function limparCampos() {
    form.reset();
}

function filtrarTabela() {
    var indiceColuna = document.getElementById("colunaFiltro").value;
    var filtro = document.getElementById("pesquisa").value.toLowerCase();
    var tipoImagemFiltro = document.getElementById("tipoImagemFiltro").value;
    var tabela = document.getElementById("tabelaClientes");
    var tbody = tabela.getElementsByTagName("tbody")[0];
    var linhas = tbody.getElementsByTagName("tr");

    for (var i = 0; i < linhas.length; i++) {
        var coluna = linhas[i].getElementsByTagName("td")[indiceColuna];
        var valorColuna = coluna.textContent || coluna.innerText;
        var tipoImagemColuna = linhas[i].getElementsByTagName("td")[7].textContent || linhas[i].getElementsByTagName("td")[7].innerText;

        var mostrarLinha = true;

        // Aplicar o filtro de texto
        if (filtro && valorColuna.toLowerCase().indexOf(filtro) === -1) {
            mostrarLinha = false;
        }

        // Aplicar o filtro do tipo de imagem
        if (tipoImagemFiltro && tipoImagemColuna.toLowerCase() !== tipoImagemFiltro.toLowerCase()) {
            mostrarLinha = false;
        }

        linhas[i].style.display = mostrarLinha ? "" : "none";
    }
}

function atualizarTabela() {
    fetch('atualizar_tabela.php')
        .then(response => response.json())
        .then(data => {
            const tabelaBody = document.querySelector('#tabelaClientes tbody');
            tabelaBody.innerHTML = ''; // Limpa o tbody

            data.forEach(imagem => {
                const tr = document.createElement('tr');
                tr.classList.add('linha-tabela');
                tr.setAttribute('data-id', imagem.idimagens_cliente_obra);

                tr.innerHTML = `
                    <td>${imagem.nome_cliente}</td>
                    <td>${imagem.nome_obra}</td>
                    <td>${imagem.imagem_nome}</td>
                    <td>${imagem.recebimento_arquivos}</td>
                    <td>${imagem.data_inicio}</td>
                    <td>${imagem.prazo}</td>
                    <td>${imagem.nome_status}</td>
                    <td>${imagem.tipo_imagem}</td>
                `;

                tabelaBody.appendChild(tr);
            });

            // Aqui chamamos a função filtrarTabela após a tabela ser atualizada
            filtrarTabela();

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
                        url: "https://improov.com.br/sistema/Imagens/buscaAJAX.php",
                        data: { ajid: idImagemSelecionada },
                        success: function (response) {
                            if (response.length > 0) {
                                document.getElementById('nome_cliente').value = response[0].nome_cliente;
                                document.getElementById('nome_obra').value = response[0].nome_obra;
                                document.getElementById('imagem_nome').value = response[0].imagem_nome;
                                document.getElementById('recebimento_arquivos').value = response[0].recebimento_arquivos;
                                document.getElementById('data_inicio').value = response[0].data_inicio;
                                document.getElementById('prazo').value = response[0].prazo;
                                document.getElementById('nome_status').value = response[0].nome_status;
                                document.getElementById('tipo_imagem').value = response[0].tipo_imagem;
                                document.getElementById('idimagens_cliente_obra').value = response[0].idimagens_cliente_obra;
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



document.getElementById("formularioModal").addEventListener("submit", function (event) {
    event.preventDefault();

    var formData = new FormData();
    formData.append("imagem_nome", document.getElementById("imagem_nome").value);
    formData.append("recebimento_arquivos", document.getElementById("recebimento_arquivos").value);
    formData.append("data_inicio", document.getElementById("data_inicio").value);
    formData.append("prazo", document.getElementById("prazo").value);
    formData.append("tipo_imagem", document.getElementById("tipo_imagem").value);
    formData.append("idimagens_cliente_obra", document.getElementById("idimagens_cliente_obra").value);

    fetch('atualizar_imagens.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Toastify({
                    text: "Atualização realizada com sucesso!",
                    backgroundColor: "green",
                    duration: 3000
                }).showToast();
                modal.style.display = "none";

                const idImagemAlterada = document.getElementById("idimagens_cliente_obra").value;
                destacarLinhaAtualizada(idImagemAlterada);

                // Aguarde 3 segundos para o destaque antes de atualizar a tabela
                setTimeout(() => {
                    atualizarTabela();
                }, 1500);

            } else {
                Toastify({
                    text: "Erro ao atualizar: " + data.message,
                    backgroundColor: "red",
                    duration: 3000
                }).showToast();
            }
        })
        .catch(error => {
            Toastify({
                text: "Erro na comunicação com o servidor.",
                backgroundColor: "red",
                duration: 3000
            }).showToast();
            console.error('Erro na requisição:', error);
        });
});

function destacarLinhaAtualizada(idImagemAlterada) {
    const linha = document.querySelector(`tr[data-id='${idImagemAlterada}']`);
    if (linha) {
        linha.classList.add('linha-atualizada');

        setTimeout(() => {
            linha.classList.remove('linha-atualizada');
        }, 3000);
    }
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