fetch('atualizarValores.php')
    .then(response => response.json())
    .then(data => {
        if (data && data.length > 0) {  // Verifica se há dados e se não está vazio
            const valores = data[0];  // Acessa o primeiro elemento do array

            // Converte valores para números (caso não estejam como número)
            const totalOrcamento = valores.total_orcamento;
            const totalProducao = valores.total_producao;
            const obrasAtivas = valores.obras_ativas;

            // Verifica se os valores são válidos números
            if (!isNaN(totalOrcamento)) {
                document.getElementById('total_orcamentos').textContent = `R$${totalOrcamento.toLocaleString('pt-BR')}`;
            } else {
                console.error('Valor de total_orcamento inválido');
            }

            if (!isNaN(totalProducao)) {
                document.getElementById('total_producao').textContent = `R$${totalProducao.toLocaleString('pt-BR')}`;
            }

            document.getElementById('obras_ativas').textContent = obrasAtivas;

            // Valor do orçamento do ano passado
            const orcamentoAnoPassado = 925000;

            // Calcula o lucro em porcentagem se o orçamento atual for válido
            if (!isNaN(totalOrcamento)) {
                const lucroPercentual = ((totalOrcamento - orcamentoAnoPassado) / orcamentoAnoPassado) * 100;
                document.getElementById('lucro_percentual').textContent = `${lucroPercentual.toFixed(2)}%`;
            } else {
                document.getElementById('lucro_percentual').textContent = 'N/A';
            }
        } else {
            console.error("Dados não encontrados");
        }
    })
    .catch(error => {
        console.error("Erro ao buscar dados:", error);
    });


// fetch('producao_orcamento.php') // Substitua pela URL correta
//     .then(response => response.json())
//     .then(data => {
//         // Separar dados para os dois gráficos
//         const funcaoImagem = data.funcao_imagem.map(item => ({
//             mes: item.mes,
//             total: item.total_funcao_imagem
//         }));

//         const controleComercial = data.controle_comercial.map(item => ({
//             mes: item.mes,
//             total: item.total_controle_comercial
//         }));

//         // Formatar os meses (para ambos os gráficos)
//         const labels = funcaoImagem.map(item => `Mês ${item.mes}`); // Exemplo: Mês 1, Mês 2...

//         // Dados para o gráfico de Produção
//         const dadosProducao = funcaoImagem.map(item => item.total);

//         // Dados para o gráfico de Orçamento
//         const dadosOrcamento = controleComercial.map(item => item.total);

//         // Criar o gráfico de Produção
//         const ctxProducao = document.getElementById('graficoProducao').getContext('2d');
//         new Chart(ctxProducao, {
//             type: 'bar',
//             data: {
//                 labels: labels,
//                 datasets: [{
//                     label: 'Produção',
//                     data: dadosProducao,
//                     backgroundColor: 'rgba(75, 192, 192, 0.2)',
//                     borderColor: 'rgba(75, 192, 192, 1)',
//                     borderWidth: 1
//                 }]
//             },
//             options: {
//                 responsive: true,
//                 scales: {
//                     y: {
//                         beginAtZero: true
//                     }
//                 }
//             }
//         });

//         // Criar o gráfico de Orçamento
//         const ctxOrcamento = document.getElementById('graficoOrcamento').getContext('2d');
//         new Chart(ctxOrcamento, {
//             type: 'line',
//             data: {
//                 labels: labels,
//                 datasets: [{
//                     label: 'Orçamento',
//                     data: dadosOrcamento,
//                     backgroundColor: 'rgba(255, 159, 64, 0.2)',
//                     borderColor: 'rgba(255, 159, 64, 1)',
//                     borderWidth: 1
//                 }]
//             },
//             options: {
//                 responsive: true,
//                 scales: {
//                     y: {
//                         beginAtZero: true
//                     }
//                 }
//             }
//         });
//     })
//     .catch(error => console.error('Erro ao carregar os dados:', error));


// fetch('tarefas.php')
//     .then(response => response.json())
//     .then(data => {
//         // Preparar os dados para o gráfico
//         const labels = data.map(item => `Função ${item.nome_funcao}`);
//         const percentuais = data.map(item => item.percentual_finalizado);

//         // Criar uma string combinando percentual e total de tarefas
//         const tooltips = data.map(item =>
//             `${item.percentual_finalizado}% - Total: ${item.total_finalizado} Tarefas Finalizadas - Total: ${item.total_tarefas} Tarefas`
//         );

//         // Média de tarefas por mês (pode ser um valor estático ou calculado dinamicamente)
//         const medias = [
//             15, 35, 30, 40, 55, 23, 9, 17,  // exemplo de médias para cada mês
//         ];

//         // Criar o gráfico de barras
//         const ctx = document.getElementById('graficoPercentual').getContext('2d');
//         new Chart(ctx, {
//             type: 'bar',
//             data: {
//                 labels: labels,
//                 datasets: [{
//                     label: '% de Tarefas Finalizadas (Mês Atual)',
//                     data: percentuais,  // Apenas o percentual vai no gráfico
//                     backgroundColor: 'rgba(54, 162, 235, 0.2)',
//                     borderColor: 'rgba(54, 162, 235, 1)',
//                     borderWidth: 1
//                 }, {
//                     label: 'Média de Tarefas por Mês',
//                     data: medias,  // Adicionando a média de tarefas
//                     type: 'line',  // Tipo de gráfico linha para a média
//                     borderColor: 'rgba(255, 99, 132, 1)',  // Cor da linha da média
//                     borderWidth: 2,
//                     fill: false,  // Não preencher a área abaixo da linha
//                     tension: 0.1  // Suavizar a linha da média
//                 }]
//             },
//             options: {
//                 responsive: true,
//                 plugins: {
//                     tooltip: {
//                         callbacks: {
//                             // Customizando o tooltip para mostrar a string formatada
//                             label: function (context) {
//                                 const index = context.dataIndex;
//                                 return tooltips[index];  // Exibe o tooltip customizado com percentual e total
//                             }
//                         }
//                     }
//                 },
//                 scales: {
//                     y: {
//                         beginAtZero: true
//                     }
//                 }
//             }
//         });
//     })
//     .catch(error => console.error('Erro ao carregar os dados:', error));

let chartInstance = null;


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
                const obraId = item.idobra;

                document.getElementById('idObraOrcamento').value = obraId;
                document.getElementById('modalInfos').style.display = 'flex';

                fetch(`detalhesObra.php?id=${obraId}`)
                    .then(response => response.json())
                    .then(detalhes => {

                        const obra = detalhes.obra;
                        document.getElementById('nomenclatura').textContent = obra.nomenclatura || "Nome não disponível";
                        document.getElementById('data_inicio').textContent = `Data de Início: ${obra.data_inicio}`;
                        document.getElementById('prazo').textContent = `Prazo: ${obra.prazo}`;
                        document.getElementById('dias_trabalhados').innerHTML = obra.dias_trabalhados ? `<strong>${obra.dias_trabalhados}</strong> dias` : '';
                        document.getElementById('total_imagens').textContent = `Total de Imagens: ${obra.total_imagens}`;
                        document.getElementById('total_imagens_antecipadas').textContent = `Imagens Antecipadas: ${obra.total_imagens_antecipadas}`;

                        const funcoes = detalhes.funcoes;
                        const nomesFuncoes = funcoes.map(funcao => funcao.nome_funcao);
                        const porcentagensFinalizadas = funcoes.map(funcao => parseFloat(funcao.porcentagem_finalizada));

                        const funcoesDiv = document.getElementById('funcoes');
                        funcoesDiv.innerHTML = "";
                        detalhes.funcoes.forEach(funcao => {
                            const funcaoDiv = document.createElement('div');
                            funcaoDiv.classList.add('funcao');
                            funcaoDiv.innerHTML = `
                                <strong>${funcao.nome_funcao}</strong><br>
                                Total de Imagens: ${funcao.total_imagens}<br>
                                Imagens Finalizadas: ${funcao.funcoes_finalizadas}<br>
                                Porcentagem Finalizada: ${funcao.porcentagem_finalizada}%<br><br>
                            `;
                            funcoesDiv.appendChild(funcaoDiv);
                        });

                        const valores = detalhes.valores;
                        document.getElementById('valor_orcamento').textContent = `R$ ${parseFloat(valores.valor_orcamento).toFixed(2)}`;
                        document.getElementById('valor_producao').textContent = `R$ ${parseFloat(valores.custo_producao).toFixed(2)}`;
                        document.getElementById('valor_fixo').textContent = `R$ ${parseFloat(valores.custo_fixo).toFixed(2)}`;
                        document.getElementById('lucro').textContent = `R$ ${parseFloat(valores.lucro).toFixed(2)}`;

                        const ctx = document.getElementById('graficoPorcentagem').getContext('2d');
                        if (chartInstance) {
                            chartInstance.destroy();
                        }
                        chartInstance = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: nomesFuncoes,
                                datasets: [{
                                    label: 'Porcentagem de Conclusão (%)',
                                    data: porcentagensFinalizadas,
                                    backgroundColor: [
                                        'rgba(54, 162, 235, 0.2)',  // Cor para a 1ª barra
                                        'rgba(255, 99, 132, 0.2)',  // Cor para a 2ª barra
                                        'rgba(255, 159, 64, 0.2)',  // Cor para a 3ª barra
                                        'rgba(75, 192, 192, 0.2)',  // Cor para a 4ª barra
                                        'rgba(153, 102, 255, 0.2)', // Cor para a 5ª barra
                                        'rgba(255, 159, 64, 0.2)'   // Cor para a 6ª barra, e assim por diante
                                    ],
                                    borderColor: [
                                        'rgba(54, 162, 235, 1)',  // Cor para a borda da 1ª barra
                                        'rgba(255, 99, 132, 1)',  // Cor para a borda da 2ª barra
                                        'rgba(255, 159, 64, 1)',  // Cor para a borda da 3ª barra
                                        'rgba(75, 192, 192, 1)',  // Cor para a borda da 4ª barra
                                        'rgba(153, 102, 255, 1)', // Cor para a borda da 5ª barra
                                        'rgba(255, 159, 64, 1)'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            stepSize: 10
                                        }
                                    }
                                }
                            }
                        });
                    })
                    .catch(error => console.error('Erro ao carregar os detalhes da obra:', error));

            });

            card.appendChild(nomeObra);
            card.appendChild(prazo);
            painel.appendChild(card);
        });

    })
    .catch(error => console.error('Erro ao carregar os dados:', error));

document.getElementById('orcamento').addEventListener('click', function () {
    document.getElementById('modalOrcamento').style.display = 'flex';
});


document.getElementById('formOrcamento').addEventListener('submit', function (e) {
    e.preventDefault();

    const idObra = document.getElementById('idObraOrcamento').value;
    const tipo = document.getElementById('tipo').value;
    const valor = document.getElementById('valor').value;
    const data = document.getElementById('data').value;

    // Enviar os dados para o backend
    fetch('salvarOrcamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ idObra, tipo, valor, data }),
    })
        .then(response => response.json())
        .then(data => {
            alert('Orçamento salvo com sucesso!');
            document.getElementById('modalOrcamento').style.display = 'none'; // Fecha o modal
        })
        .catch(error => {
            console.error('Erro ao salvar orçamento:', error);
        });
});

const modalInfos = document.getElementById('modalInfos')
const modalOrcamento = document.getElementById('modalOrcamento')
window.onclick = function (event) {
    if (event.target == modalInfos) {
        modalInfos.style.display = "none";
    }
    if (event.target == modalOrcamento) {
        modalOrcamento.style.display = "none";
    }
}

window.addEventListener('touchstart', function (event) {
    if (event.target == modalInfos) {
        modalInfos.style.display = "none";
    }
    if (event.target == modalOrcamento) {
        modalOrcamento.style.display = "none";
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const cards = document.querySelectorAll('.stat-card');
    let currentIndex = 0;

    // Exibe o primeiro card
    cards[currentIndex].classList.add('active');

    // Função para mostrar o próximo card
    function nextCard() {
        // Remove a classe 'active' do card atual
        cards[currentIndex].classList.remove('active');

        // Avança para o próximo card, ou volta ao primeiro card se chegar no final
        currentIndex = (currentIndex + 1) % cards.length;

        // Adiciona a classe 'active' ao próximo card
        cards[currentIndex].classList.add('active');
    }

    // Altere o card a cada 3 segundos (3000 ms)
    setInterval(nextCard, 3000); // 3000 ms = 3 segundos
});
