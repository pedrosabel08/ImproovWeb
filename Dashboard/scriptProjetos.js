fetch('obras.php')
    .then(response => response.json())
    .then(data => {
        const painel = document.getElementById('painel');

        let obrasAtivas = 0;
        let obrasFinalizadas = 0;

        function formatarData(data) {
            const partes = data.split('-');  // Divide a data em partes (ano, mês, dia)
            return `${partes[2]}/${partes[1]}/${partes[0]}`;  // Reorganiza para DD/MM/YYYY
        }

        // Iterar sobre os dados de obras e criar um card para cada obra
        function criarCards(obras, painel) {
            // Iterar sobre os dados de obras e criar um card para cada obra
            obras.forEach(item => {
                const card = document.createElement('div');
                card.classList.add('card'); // Adiciona a classe para estilo do card
                card.setAttribute('idobra', item.idobra);

                const nomeObra = document.createElement('h3');
                nomeObra.textContent = item.nome_obra;

                const prazo = document.createElement('h4');
                prazo.textContent = formatarData(item.prazo);

                // Alterar a cor do card com base no prazo
                if (item.status_obra == 0) {
                    // Obra ativa
                    card.style.backgroundColor = '#28a745'; // Verde
                    card.style.color = '#fff';
                    obrasAtivas++;
                } else {
                    // Obra não ativa
                    card.style.backgroundColor = '#ff6f61'; // Vermelho
                    card.style.color = '#fff';
                    obrasFinalizadas++;
                }

                document.getElementById('obras_ativas').innerText = obrasAtivas;
                document.getElementById('obras_finalizadas').innerText = obrasFinalizadas;

                card.addEventListener('click', function () {
                    localStorage.setItem('obraId', item.idobra);

                    // Redireciona para obra.html
                    window.location.href = 'obra.html';
                });


                card.appendChild(nomeObra);
                card.appendChild(prazo);
                painel.appendChild(card);
            });
        }


        criarCards(data.without_filter, painel);


    })
    .catch(error => console.error('Erro ao carregar os dados:', error));