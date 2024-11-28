document.addEventListener('DOMContentLoaded', () => {
    const navLinks = document.querySelectorAll('.nav a'); // Seleciona todos os links dentro da classe .nav
    const currentPage = window.location.pathname.split('/').pop(); // Obtém o nome do arquivo atual da URL

    navLinks.forEach(link => {
        const linkHref = link.getAttribute('href'); // Obtém o valor do href de cada link
        if (linkHref === currentPage) {
            link.classList.add('active'); // Adiciona a classe active se o link corresponder à página atual
        }
    });
});


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
        function criarCards(obras) {
            const painelAtivas = document.getElementById('painel'); // Painel para obras ativas
            const obrasInativasContainer = document.getElementById('obrasInativas'); // Container para obras inativas

            obras.forEach(item => {
                const card = document.createElement('div');
                card.classList.add('card');
                card.setAttribute('idobra', item.idobra);

                const nomeObra = document.createElement('h3');
                nomeObra.textContent = item.nome_obra;

                const prazo = document.createElement('h4');
                prazo.textContent = formatarData(item.prazo);

                // Definir a aparência e localização do card com base no status
                if (item.status_obra == 0) {
                    // Obra ativa
                    card.style.backgroundColor = '#28a745'; // Verde
                    card.style.color = '#fff';
                    painelAtivas.appendChild(card); // Adicionar no painel principal
                    obrasAtivas++;
                } else {
                    // Obra inativa
                    card.style.backgroundColor = '#ff6f61'; // Vermelho
                    card.style.color = '#fff';
                    obrasInativasContainer.appendChild(card); // Adicionar na gaveta
                    obrasFinalizadas++;
                }

                // Atualizar contadores
                document.getElementById('obras_ativas').innerText = obrasAtivas;
                document.getElementById('obras_finalizadas').innerText = obrasFinalizadas;

                // Adicionar evento de clique ao card
                card.addEventListener('click', function () {
                    localStorage.setItem('obraId', item.idobra);
                    window.location.href = 'obra.php';
                });

                // Adicionar informações ao card
                card.appendChild(nomeObra);
                card.appendChild(prazo);
            });
        }


        criarCards(data.without_filter);


    })
    .catch(error => console.error('Erro ao carregar os dados:', error));



document.getElementById('toggleGaveta').addEventListener('click', function () {
    const gaveta = document.getElementById('gaveta');
    const icone = this.querySelector('i'); // Seleciona o ícone dentro do botão

    // Alterna a classe "open" na gaveta
    gaveta.classList.toggle('open');

    // Alterna o ícone entre "fa-chevron-down" e "fa-chevron-up"
    if (gaveta.classList.contains('open')) {
        icone.classList.remove('fa-chevron-down');
        icone.classList.add('fa-chevron-up');
    } else {
        icone.classList.remove('fa-chevron-up');
        icone.classList.add('fa-chevron-down');
    }
});
