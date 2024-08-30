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

            // Obtém o ID do produto da linha selecionada
            var idImagemSelecionada = linha.getAttribute("data-id");

            // Faz a requisição AJAX para buscar detalhes do produto
            $.ajax({
                type: "GET",
                dataType: "json",
                url: "http://localhost:8066/ImproovWeb/buscaLinhaAJAX.php",
                data: { ajid: idImagemSelecionada },
                success: function (response) {
                    if (response.length > 0) {
                        // Preenche o formulário com os dados recebidos
                        document.getElementById('nome_cliente').value = response[0].nome_cliente;
                        document.getElementById('nome_obra').value = response[0].nome_obra;
                        document.getElementById('nome_imagem').value = response[0].imagem_nome;
                        // document.getElementById('prazo_estimado').value = response[0].prazo;
                        // document.getElementById('caderno').value = response[0].validade;
                    } else {
                        console.log("Nenhum produto encontrado.");
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("Erro na requisição AJAX: " + textStatus, errorThrown);
                }
            });

            console.log("Linha selecionada: ID do produto = " + idImagemSelecionada);
        });
    });

    // // Evento para o botão de excluir
    // document.getElementById("botaoExcluir").addEventListener("click", function () {
    //     var linhaSelecionada = document.querySelector("#tabelaClientes tbody tr.selecionada");

    //     if (linhaSelecionada) {
    //         var idProdutoSelecionado = linhaSelecionada.getAttribute("data-id");
    //         document.getElementById("idProdutoExcluir").value = idProdutoSelecionado;
    //         document.getElementById("formExcluirProduto").submit();
    //     } else {
    //         console.log("Nenhuma linha selecionada para exclusão.");
    //     }
    // });

    // Evento para o botão de alterar
    // document.getElementById("botaoAlterar").addEventListener("click", function () {
    //     var linhaSelecionada = document.querySelector("#tabelaClientes tbody tr.selecionada");

    //     if (linhaSelecionada) {
    //         var idProdutoSelecionado = linhaSelecionada.getAttribute("data-id");
    //         document.getElementById("idProdutoAlterar").value = idProdutoSelecionado;
    //         document.getElementById('nomeProdutoAlterar').value = document.getElementById('nomeProduto').value;
    //         document.getElementById('qtdeProdutoAlterar').value = document.getElementById('quantidade').value;
    //         document.getElementById('umProdutoAlterar').value = document.getElementById('unidadeMedida').value;
    //         document.getElementById('validadeProdutoAlterar').value = document.getElementById('validade').value;
    //         document.getElementById("formAlterarProduto").submit();
    //     } else {
    //         console.log("Nenhuma linha selecionada para alterar.");
    //     }
    // });
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

