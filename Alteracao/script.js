// Obtém o 'obra_id' do localStorage
var obraId = localStorage.getItem('obraId');

if (obraId) {
    fetch(`getAlteracao.php?obraId=${obraId}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('imagens-container');
            container.innerHTML = '';

            // Percorre as imagens e suas revisões
            data.alt.forEach(function (imagem) {
                let card = document.createElement('div');
                card.classList.add('card'); // Classe para o card

                // Se a imagem tem mais de uma revisão, adiciona o ícone de toggle
                let toggleIcon = '';
                if (imagem.revisoes.length > 1) {
                    toggleIcon = `
                        <button class="toggle-btn" onclick="toggleRevisoes('${imagem.imagem_nome}')">
                            <i class="fas fa-chevron-down"></i> Ver Revisões
                        </button>
                    `;
                }

                // Adiciona as revisões dentro de um único card
                let revisoesHTML = imagem.revisoes.map(revisao => `
                    <div class="card-info">
                        <h4>Revisão ${revisao.numero_revisao}</h4>
                        <p><strong>Descrição:</strong> ${revisao.descricao}</p>
                        <p><strong>Data Envio:</strong> ${revisao.data_envio}</p>
                        <p><strong>Data Recebimento:</strong> ${revisao.data_recebimento}</p>
                        <p><strong>Status:</strong> ${revisao.status}</p>
                        <p><strong>Colaborador:</strong> ${revisao.nome_colaborador}</p>
                    </div>
                `).join('');

                // Adiciona o nome da imagem e as revisões ao card
                card.innerHTML = `
                    <div class="card-header">
                        <h3>${imagem.imagem_nome}</h3>
                        ${toggleIcon}
                    </div>
                    <div class="card-body" id="revisoes-${imagem.imagem_nome}" style="display: none;">
                        ${revisoesHTML}
                    </div>
                `;

                container.appendChild(card);
            });
        })
        .catch(error => console.error('Erro ao buscar alterações:', error));
}

// Função para alternar a exibição das revisões
function toggleRevisoes(imagemNome) {
    const revisoesContainer = document.getElementById(`revisoes-${imagemNome}`);
    const toggleButton = revisoesContainer.previousElementSibling.querySelector('.toggle-btn');
    const icon = toggleButton.querySelector('i');

    // Alterna a visibilidade
    if (revisoesContainer.style.display === 'none') {
        revisoesContainer.style.display = 'block';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
        toggleButton.innerHTML = '<i class="fas fa-chevron-up"></i> Esconder Revisões';
    } else {
        revisoesContainer.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
        toggleButton.innerHTML = '<i class="fas fa-chevron-down"></i> Ver Revisões';
    }
}
