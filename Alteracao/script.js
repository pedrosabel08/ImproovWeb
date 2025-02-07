// Obtém o 'obra_id' do localStorage
var obraId = localStorage.getItem('obraId');

if (obraId) {
    fetch(`getAlteracao.php?obraId=${obraId}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('imagens-container');
            container.innerHTML = '';

            data.alt.forEach(function (imagem) {
                let card = document.createElement('div');
                card.classList.add('card');

                let ultimaRevisao = imagem.revisoes[0]; // Agora pega a última revisão (pois SQL retorna DESC)
                let revisoesAnteriores = imagem.revisoes.slice(1); // O restante das revisões

                let toggleIcon = '';
                if (revisoesAnteriores.length > 0) {
                    toggleIcon = `
                        <button class="toggle-btn" onclick="toggleRevisoes('${imagem.imagem_nome}')">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    `;
                }

                let ultimaRevisaoHTML = `
                    <div class="card-info">
                        <h4>Revisão ${ultimaRevisao.numero_revisao}</h4>
                        <p><strong>Descrição:</strong> 
                            <input id="autoInput" type="text" value="${ultimaRevisao.descricao}" 
                                onblur="atualizarRevisao(${ultimaRevisao.id_alteracao}, 'descricao', this.value)" oninput="adjustWidth(this)">
                        </p>
                        <p><strong>Data Envio:</strong> 
                            <input id="autoInput" type="date" value="${ultimaRevisao.data_envio}" 
                                onblur="atualizarRevisao(${ultimaRevisao.id_alteracao}, 'data_envio', this.value)" oninput="adjustWidth(this)">
                        </p>
                        <p><strong>Data Recebimento:</strong> 
                            <input id="autoInput" type="date" value="${ultimaRevisao.data_recebimento}" 
                                onblur="atualizarRevisao(${ultimaRevisao.id_alteracao}, 'data_recebimento', this.value)" oninput="adjustWidth(this)">
                        </p>
                        <p><strong>Status:</strong> 
                            <input id="autoInput" type="text" value="${ultimaRevisao.status}" 
                                onblur="atualizarRevisao(${ultimaRevisao.id_alteracao}, 'status', this.value)" oninput="adjustWidth(this)">
                        </p>
                        <p>Colaborador: <strong>${ultimaRevisao.nome_colaborador}</strong></p>
                    </div>

                `;

                let revisoesHTML = revisoesAnteriores.map(revisao => `
                    <div class="card-info">
                        <h4>Revisão ${revisao.numero_revisao}</h4>
                        <p><strong>Descrição:</strong> 
                            <input id="autoInput" type="text" value="${revisao.descricao}" 
                                onblur="atualizarRevisao(${revisao.id_alteracao}, 'descricao', this.value)" oninput="adjustWidth(this)">
                        </p>
                        <p><strong>Data Envio:</strong> 
                            <input id="autoInput" type="date" value="${revisao.data_envio}" 
                                onblur="atualizarRevisao(${revisao.id_alteracao}, 'data_envio', this.value)" oninput="adjustWidth(this)">
                        </p>
                        <p><strong>Data Recebimento:</strong> 
                            <input id="autoInput" type="date" value="${revisao.data_recebimento}" 
                                onblur="atualizarRevisao(${revisao.id_alteracao}, 'data_recebimento', this.value)" oninput="adjustWidth(this)">
                        </p>
                        <p><strong>Status:</strong> 
                            <input id="autoInput" type="text" value="${revisao.status}" 
                                onblur="atualizarRevisao(${revisao.id_alteracao}, 'status', this.value)" oninput="adjustWidth(this)">
                        </p>
                        <p>Colaborador: <strong>${revisao.nome_colaborador}</strong></p>
                    </div>
                `).join('');


                card.innerHTML = `
                    <div class="card-header">
                        <h3>${imagem.imagem_nome}</h3>
                        ${toggleIcon}
                    </div>
                    <div class="card-body">
                        <div class="ultima-revisao">
                            ${ultimaRevisaoHTML}
                        </div>
                        <div class="revisoes-anteriores" id="revisoes-${imagem.imagem_nome}" style="display: none;">
                            ${revisoesHTML}
                        </div>
                    </div>
                `;

                container.appendChild(card);
            });
        })
        .catch(error => console.error('Erro ao buscar alterações:', error));
}

// Função corrigida para alternar a exibição das revisões anteriores
function toggleRevisoes(imagemNome) {
    const revisoesContainer = document.getElementById(`revisoes-${imagemNome}`);

    if (!revisoesContainer) {
        console.error(`Elemento revisoes-${imagemNome} não encontrado.`);
        return;
    }

    // Acessa o botão de toggle corretamente
    const toggleButton = document.querySelector(`button[onclick="toggleRevisoes('${imagemNome}')"]`);
    const icon = toggleButton.querySelector('i');

    if (revisoesContainer.style.display === 'none') {
        revisoesContainer.style.display = 'block';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        revisoesContainer.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

function atualizarRevisao(id_alteracao, campo, valor) {
    fetch('atualizarRevisao.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_alteracao, campo, valor })
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === "sucesso") {
                Toastify({
                    text: 'Campo atualizado com sucesso',
                    duration: 3000,
                    backgroundColor: "green",
                    close: true,
                    gravity: "top",
                    position: "right"
                }).showToast();
            } else {
                console.error(`Erro ao atualizar ${campo}:`, data.mensagem);
                Toastify({
                    text: `Erro ao atualizar ${campo}:, ${data.mensagem}`,
                    duration: 3000,
                    backgroundColor: "red",
                    close: true,
                    gravity: "top",
                    position: "right"
                }).showToast();
            }
        })
        .catch(error => console.error('Erro na requisição:', error));
}


function ajustarTamanho() {
    var input = document.getElementById("autoInput");
    input.style.width = (input.value.length + 1) * 10 + "px"; // Ajusta a largura do input
}

// Ajustar o tamanho ao carregar a página
window.onload = ajustarTamanho;