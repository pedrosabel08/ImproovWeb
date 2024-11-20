document.addEventListener("DOMContentLoaded", function () {
    atualizarValores();

});

function atualizarValores() {
    fetch('atualizarValores.php')
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {  // Verifica se há dados e se não está vazio
                const valores = data[0];  // Acessa o primeiro elemento do array

                // Define os valores nas tags HTML correspondentes
                document.getElementById('total_orcamentos').textContent = `R$${valores.total_orcamento}`;
                document.getElementById('total_producao').textContent = `R$${valores.total_producao}`;
                document.getElementById('obras_ativas').textContent = valores.obras_ativas;
            } else {
                console.error("Dados não encontrados");
            }
        })
        .catch(error => {
            console.error("Erro ao buscar dados:", error);
        });
}

fetch('producao_orcamento.php') // Substitua pela URL correta
    .then(response => response.json())
    .then(data => {
        // Separar dados para os dois gráficos
        const funcaoImagem = data.funcao_imagem.map(item => ({
            mes: item.mes,
            total: item.total_funcao_imagem
        }));

        const controleComercial = data.controle_comercial.map(item => ({
            mes: item.mes,
            total: item.total_controle_comercial
        }));

        // Formatar os meses (para ambos os gráficos)
        const labels = funcaoImagem.map(item => `Mês ${item.mes}`); // Exemplo: Mês 1, Mês 2...

        // Dados para o gráfico de Produção
        const dadosProducao = funcaoImagem.map(item => item.total);

        // Dados para o gráfico de Orçamento
        const dadosOrcamento = controleComercial.map(item => item.total);

        // Criar o gráfico de Produção
        const ctxProducao = document.getElementById('graficoProducao').getContext('2d');
        new Chart(ctxProducao, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Produção',
                    data: dadosProducao,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Criar o gráfico de Orçamento
        const ctxOrcamento = document.getElementById('graficoOrcamento').getContext('2d');
        new Chart(ctxOrcamento, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Orçamento',
                    data: dadosOrcamento,
                    backgroundColor: 'rgba(255, 159, 64, 0.2)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    })
    .catch(error => console.error('Erro ao carregar os dados:', error));


fetch('tarefas.php')
    .then(response => response.json())
    .then(data => {
        // Preparar os dados para o gráfico
        const labels = data.map(item => `Função ${item.nome_funcao}`);
        const percentuais = data.map(item => item.percentual_finalizado);

        // Criar uma string combinando percentual e total de tarefas
        const tooltips = data.map(item =>
            `${item.percentual_finalizado}% - Total: ${item.total_finalizado} Tarefas Finalizadas - Total: ${item.total_tarefas} Tarefas`
        );

        // Média de tarefas por mês (pode ser um valor estático ou calculado dinamicamente)
        const medias = [
            15, 35, 30, 40, 55, 23, 9, 17,  // exemplo de médias para cada mês
        ];

        // Criar o gráfico de barras
        const ctx = document.getElementById('graficoPercentual').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '% de Tarefas Finalizadas (Mês Atual)',
                    data: percentuais,  // Apenas o percentual vai no gráfico
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }, {
                    label: 'Média de Tarefas por Mês',
                    data: medias,  // Adicionando a média de tarefas
                    type: 'line',  // Tipo de gráfico linha para a média
                    borderColor: 'rgba(255, 99, 132, 1)',  // Cor da linha da média
                    borderWidth: 2,
                    fill: false,  // Não preencher a área abaixo da linha
                    tension: 0.1  // Suavizar a linha da média
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    tooltip: {
                        callbacks: {
                            // Customizando o tooltip para mostrar a string formatada
                            label: function (context) {
                                const index = context.dataIndex;
                                return tooltips[index];  // Exibe o tooltip customizado com percentual e total
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    })
    .catch(error => console.error('Erro ao carregar os dados:', error));


fetch('obras.php')
    .then(response => response.json())
    .then(data => {
        const painel = document.getElementById('painel');

        function calcularDiferencaDias(prazo) {
            const dataAtual = new Date();
            const prazoDate = new Date(prazo);
            const diffTime = prazoDate - dataAtual;  // Diferença em milissegundos
            return Math.ceil(diffTime / (1000 * 3600 * 24));  // Converte para dias
        }

        function formatarData(data) {
            const partes = data.split('-');  // Divide a data em partes (ano, mês, dia)
            return `${partes[2]}/${partes[1]}/${partes[0]}`;  // Reorganiza para DD/MM/YYYY
        }

        // Iterar sobre os dados de obras e criar um card para cada obra
        data.forEach(item => {
            const card = document.createElement('div');
            card.classList.add('card'); // Adiciona a classe para estilo do card
            card.setAttribute('idobra', item.idobra);

            const nomeObra = document.createElement('h3');
            nomeObra.textContent = item.nome_obra;

            const prazo = document.createElement('h4');
            prazo.textContent = formatarData(item.prazo);

            // Calcular a diferença de dias
            const diasRestantes = calcularDiferencaDias(item.prazo);

            // Alterar a cor do card com base no prazo
            if (diasRestantes < 0) {
                // Prazo já passou
                card.style.backgroundColor = '#ff6f61'; // Vermelho
                card.style.color = '#fff';
            } else if (diasRestantes <= 3) {
                // Prazo próximo (3 dias ou menos)
                card.style.backgroundColor = '#f7b731'; // Amarelo
                card.style.color = '#333';
            } else {
                // Prazo distante
                card.style.backgroundColor = '#28a745'; // Verde
                card.style.color = '#fff';
            }

            card.addEventListener('click', function () {

                const confirmRedirect = confirm("Você tem certeza que deseja ir para o Follow Up?");
            
                if (confirmRedirect) {
                    localStorage.setItem('obraId', item.idobra);
            
                    window.open('https://improov.com.br/sistema/main.php#follow-up', '_blank');
                } else {

                }
            });

            card.appendChild(nomeObra);
            card.appendChild(prazo);
            painel.appendChild(card);
        });
    })
    .catch(error => console.error('Erro ao carregar os dados:', error));
