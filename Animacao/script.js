var modal = document.getElementById("modal");
var modal_imagem = document.getElementById("modal_imagem");
var openModalBtn = document.getElementById("openModalBtn");
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

function limparCamposImagem(){
    document.getElementById('opcao_obra2').selectedIndex = 0; // Resetar select
    document.getElementById('imagem_nome').value = '';
}

openModalBtn.onclick = function () {
    modal.style.display = "flex";
    limparCampos();
};

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
                    option.value = imagem.idimagens_cliente_obra;
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
                    tr.setAttribute('data-id', imagem.idanimacao);
                    tr.setAttribute('data-obra-id', imagem.idobra);

                    let statusTexto = imagem.status_anima == 1 ? 'Não começou' : 'Finalizado';
                    let statusCor = imagem.status_anima == 1 ? 'red' : 'green';

                    tr.innerHTML = `
                        <td>${imagem.nome_colaborador}</td>
                        <td>${imagem.nome_cliente}</td>
                        <td>${imagem.nome_obra}</td>
                        <td>${imagem.imagem_nome}</td>
                        <td>${imagem.status_cena}</td>
                        <td>${imagem.status_render}</td>
                        <td>${imagem.status_pos}</td>
                        <td>${imagem.duracao}</td>
                        <td>${imagem.status_anima}</td>
                        <td style="background-color: ${statusCor}; color: white;">${statusTexto}</td>
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
                            url: "http://192.168.0.202:8066/Animacao/buscaAJAX.php",
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
                                    document.getElementById('duracao').value = response[0].duracao;

                                    const checkboxStatusPos = document.getElementById('status_anima');
                                    checkboxStatusPos.checked = response[0].status_anima == 0;
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
    const imagemNome = document.getElementById('imagem_nome').value.trim(); // Usar trim() para remover espaços em branco

    console.log('Obra ID:', obraId); // Debug: mostrar ID da obra
    console.log('Nome da Imagem:', imagemNome); // Debug: mostrar nome da imagem

    // Verifica se o ID da obra é válido ou o nome da imagem não é vazio
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
            // Aqui você pode tratar a resposta do PHP, como exibir um alerta
            console.log(result); // Ver resposta do PHP
        })
        .catch(error => {
            console.error('Erro:', error);
        });
});
