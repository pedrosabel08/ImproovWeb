function formatarData(data) {
    const opcoes = { year: 'numeric', month: 'long', day: 'numeric' };
    return data.toLocaleDateString('pt-BR', opcoes);
}

const dataAtual = new Date();
document.getElementById('dataAtual').textContent = formatarData(dataAtual);

const imagensPorAno = {
    2024: [10, 9, 15, 14, 20, 18, 22, 30, 25, 20, 23, 19], 
    2023: [8, 12, 15, 10, 14, 13, 9, 7, 8, 11, 10, 14],
    2022: [5, 10, 7, 4, 6, 8, 9, 11, 12, 3, 5, 6] 
};

const ctx = document.getElementById('imagensChart').getContext('2d');
let imagensChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
        datasets: [{
            label: 'Número de Imagens',
            data: imagensPorAno[2024], 
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Número de Imagens'
                }
            }
        }
    }
});

document.getElementById('anoSelect').addEventListener('change', function () {
    const anoSelecionado = this.value;
    imagensChart.data.datasets[0].data = imagensPorAno[anoSelecionado];
    imagensChart.update(); 
});



document.addEventListener("DOMContentLoaded", function () {

    function animateValue(id, start, end, duration, isPercentage = false) {
        const element = document.getElementById(id);
        let current = start;
        const range = Math.abs(end - start);
        const increment = end > start ? 1 : -1;
        const stepTime = Math.abs(Math.floor(duration / range));

        if (range === 0) {
            element.textContent = end + (isPercentage ? '%' : '');
            return;
        }

        const timer = setInterval(function () {
            current += increment;
            if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                current = end; 
                clearInterval(timer);
            }
            element.textContent = current + (isPercentage ? '%' : '');
        }, stepTime);
    }

    function loadImageCounts() {
        fetch('count_images.php')
            .then(response => response.json())
            .then(result => {
                if (result.status === 'success') {

                    animateValue("caderno", 0, result.data.caderno.total, 1500);
                    animateValue("meta-caderno", 0, result.data.caderno.meta, 1500);
                    animateValue("porcentagem-caderno", 0, result.data.caderno.porcentagem, 1500, true);

                    animateValue("model", 0, result.data.modelagem.total, 1500);
                    animateValue("meta-model", 0, result.data.modelagem.meta, 1500);
                    animateValue("porcentagem-model", 0, result.data.modelagem.porcentagem, 1500, true);

                    animateValue("comp", 0, result.data.composicao.total, 1500);
                    animateValue("meta-comp", 0, result.data.composicao.meta, 1500);
                    animateValue("porcentagem-comp", 0, result.data.composicao.porcentagem, 1500, true);

                    animateValue("final", 0, result.data.finalizacao.total, 1500);
                    animateValue("meta-final", 0, result.data.finalizacao.meta, 1500);
                    animateValue("porcentagem-final", 0, result.data.finalizacao.porcentagem, 1500, true);

                    animateValue("pos", 0, result.data.pos_producao.total, 1500);
                    animateValue("meta-pos", 0, result.data.pos_producao.meta, 1500);
                    animateValue("porcentagem-pos", 0, result.data.pos_producao.porcentagem, 1500, true);

                    animateValue("planta", 0, result.data.planta_humanizada.total, 1500);
                    animateValue("meta-planta", 0, result.data.planta_humanizada.meta, 1500);
                    animateValue("porcentagem-planta", 0, result.data.planta_humanizada.porcentagem, 1500, true);
                } else {
                    console.error('Erro ao carregar dados: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
            });
    }

    loadImageCounts();

    const totalImagens = parseInt(document.getElementById('totalImagens').getAttribute('data-value'));
    const metaImagens = parseInt(document.getElementById('metaImagens').getAttribute('data-value'));
    const porcentagem = parseInt(document.getElementById('porcentagem').getAttribute('data-value'));

    animateValue("totalImagens", 0, totalImagens, 1500);
    animateValue("metaImagens", 0, metaImagens, 1500);
    animateValue("porcentagem", 0, porcentagem, 1500, true);


});


document.addEventListener("DOMContentLoaded", function () {

    document.getElementById('menuButton').addEventListener('click', function () {
        const menu = document.getElementById('menu');
        menu.classList.toggle('hidden');
    });

    window.addEventListener('click', function (event) {
        const menu = document.getElementById('menu');
        const button = document.getElementById('menuButton');

        if (!button.contains(event.target) && !menu.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });

});