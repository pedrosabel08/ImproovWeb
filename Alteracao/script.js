let idImagemSelecionada = null;

fetch('getAlteracao.php')
    .then(response => response.json())
    .then(data => {
        for (const [status, obras] of Object.entries(data)) {
            const column = document.getElementById(`kanban-${status}`);

            if (!column) continue;

            for (const [obra, dados] of Object.entries(obras)) {
                const obraCard = document.createElement('div');
                obraCard.className = 'obra-card';
                const total = dados.imagens.length;
                const prazo = dados.imagens[0]?.prazo || '';
                const status_nome = dados.status_nome || 'Indefinido';

                obraCard.innerHTML = `<strong>${obra} - ${status_nome}</strong><br>Prazo: ${prazo}<br>Total de imagens: ${total}`;

                const detalhes = document.createElement('div');
                detalhes.className = 'obra-detalhes';

                dados.imagens.forEach(img => {
                    const item = document.createElement('div');
                    item.className = 'imagem-detalhe';
                    item.setAttribute('data-imagem-id', img.imagem_id);

                    item.innerHTML = `
                            <div class="imagem-nome"><strong>${img.imagem}</strong></div>
                            <div class="imagem-colaborador">Colaborador: ${img.colaborador ? img.colaborador : '-'}</div>
                        `;

                    if (!img.colaborador) {
                        item.style.backgroundColor = '#f95757'; // cor de fundo para imagens sem colaborador
                    }

                    item.addEventListener('click', (e) => {
                        e.stopPropagation(); // impede que o clique também dispare o toggle da obra
                        abrirModal(img.imagem_id);
                    });
                    detalhes.appendChild(item);
                });
                obraCard.appendChild(detalhes);

                obraCard.addEventListener('click', () => {
                    const isHidden = window.getComputedStyle(detalhes).display === 'none';
                    detalhes.style.display = isHidden ? 'block' : 'none';
                });

                column.appendChild(obraCard);
            }
        }
    })
    .catch(error => console.error('Erro ao carregar Kanban:', error));



function abrirModal(idimagem) {
    document.getElementById('form-edicao').style.display = 'flex';

    atualizarModal(idimagem);
    idImagemSelecionada = idimagem; // Armazena o ID da imagem selecionada
}

function limparCampos() {
    document.getElementById("campoNomeImagem").textContent = "";
    document.getElementById("status_alteracao").value = "";
    document.getElementById("prazo_alteracao").value = "";
    document.getElementById("obs_alteracao").value = "";
    document.getElementById("opcao_alteracao").value = "";
}


function atualizarModal(idImagem) {
    // Limpar campos do formulário de edição
    limparCampos();

    // Fazer requisição AJAX para `buscaLinhaAJAX.php` usando Fetch
    fetch(`../buscaLinhaAJAX.php?ajid=${idImagem}`)
        .then(response => response.json())
        .then(response => {
            document.getElementById('form-edicao').style.display = 'flex';

            if (response.funcoes && response.funcoes.length > 0) {
                document.getElementById("campoNomeImagem").textContent = response.funcoes[0].imagem_nome;

                response.funcoes.forEach(function (funcao) {

                    let selectElement;
                    switch (funcao.nome_funcao) {
                        case "Alteração":
                            document.getElementById("opcao_alteracao").value = funcao.colaborador_id;
                            document.getElementById("status_alteracao").value = funcao.status;
                            document.getElementById("prazo_alteracao").value = funcao.prazo;
                            document.getElementById("obs_alteracao").value = funcao.observacao;
                            break;
                    }
                });
            }
        })
        .catch(error => console.error("Erro ao buscar dados da linha:", error));
}


document.getElementById("salvar_funcoes").addEventListener("click", function (event) {
    event.preventDefault();


    if (!idImagemSelecionada) {
        Toastify({
            text: "Nenhuma imagem selecionada",
            duration: 3000,
            close: true,
            gravity: "top",
            position: "left",
            backgroundColor: "red",
            stopOnFocus: true,
        }).showToast();
        return;
    }

    var textos = {};
    document.querySelectorAll(".form-edicao p").forEach(function (p) {
        textos[p.id] = p.textContent.trim();
    });

    const dados = {
        imagem_id: idImagemSelecionada,
        alteracao_id: document.getElementById("opcao_alteracao").value || "",
        status_alteracao: document.getElementById("status_alteracao").value || "",
        prazo_alteracao: document.getElementById("prazo_alteracao").value || "",
        obs_alteracao: document.getElementById("obs_alteracao").value || "",
        textos: textos,
    };

    $.ajax({
        type: "POST",
        url: "https://www.improov.com.br/sistema/insereFuncao.php",
        data: dados,
        success: function (response) {
            Toastify({
                text: "Dados salvos com sucesso!",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "left",
                backgroundColor: "green",
                stopOnFocus: true,
            }).showToast();
            document.getElementById('form-edicao').style.display = 'none';
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error("Erro ao salvar dados: " + textStatus, errorThrown);
            Toastify({
                text: "Erro ao salvar dados.",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "left",
                backgroundColor: "red",
                stopOnFocus: true,
            }).showToast();
        },
    });


});

const form_edicao = document.getElementById('form-edicao');

window.addEventListener('touchstart', function (event) {
    if (event.target == form_edicao) {
        form_edicao.style.display = "none";
    }
});

['click', 'touchstart', 'keydown'].forEach(eventType => {
    window.addEventListener(eventType, function (event) {
        // Fecha os modais ao clicar fora ou pressionar Esc
        if (eventType === 'keydown' && event.key !== 'Escape') return;

        if (event.target == form_edicao || (eventType === 'keydown' && event.key === 'Escape')) {
            form_edicao.style.display = "none";
        }
    });
});