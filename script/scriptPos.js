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

function buscarImagens() {
    var obraId = document.getElementById('opcao_obra').value;

    if (obraId) {
        // Faz uma requisição AJAX para buscar as imagens da obra selecionada
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'buscar_imagens.php?obra_id=' + obraId, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4 && xhr.status == 200) {
                document.getElementById('nomeImagem').innerHTML = xhr.responseText;
            }
        };
        xhr.send();
    } else {
        document.getElementById('nomeImagem').innerHTML = '<option value="">Selecione uma obra primeiro</option>';
    }
}

document.getElementById('opcao_obra').addEventListener('change', buscarImagens);

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

document.addEventListener("DOMContentLoaded", function () {
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
            })
            .catch(error => console.error('Erro ao atualizar a tabela:', error));
    }

    // Atualiza a tabela a cada 1000 ms (1 segundo)
    setInterval(atualizarTabela, 1000);
});