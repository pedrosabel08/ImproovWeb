function formatarDataAtual() {
    const opcoes = { weekday: 'long', day: 'numeric', month: 'long' };
    const dataAtual = new Date();
    return dataAtual.toLocaleDateString('pt-BR', opcoes);
}

document.getElementById('day').textContent = formatarDataAtual();

const ctx = document.getElementById('graph').getContext('2d');

// Dados do gráfico
const data = {
    labels: ['Funções Feitas', 'Funções Não Feitas'],
    datasets: [{
        label: 'Quantidade',
        data: [8, 5], // Exemplo: 8 funções feitas, 5 funções não feitas
        backgroundColor: [
            'rgba(75, 192, 192, 0.2)',
            'rgba(255, 99, 132, 0.2)'
        ],
        borderColor: [
            'rgba(75, 192, 192, 1)',
            'rgba(255, 99, 132, 1)'
        ],
        borderWidth: 1
    }]
};

// Configurações do gráfico
const config = {
    type: 'bar', // Tipo de gráfico: 'bar' ou 'line'
    data: data,
    options: {
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
};

// Renderiza o gráfico
const myChart = new Chart(ctx, config);