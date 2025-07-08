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

                obraCard.innerHTML = `<strong>${obra}</strong><br>Prazo: ${prazo}<br>Total de imagens: ${total}`;

                const detalhes = document.createElement('div');
                detalhes.className = 'obra-detalhes';

                dados.imagens.forEach(img => {
                    const item = document.createElement('div');
                    item.className = 'imagem-detalhe';
                    item.textContent = `${img.imagem} - ${img.colaborador} - Prazo: ${img.prazo} - Alteração: ${img.status_alteracao}`;
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