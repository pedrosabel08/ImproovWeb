function gerarMetricas() {
    fetch('getMetrics.php')
        .then(response => response.json())
        .then(data => {
            const metricas = data.metricas;

            // Inicializa contadores
            let totalProjects = 0;
            let totalTasks = 0;
            let inProgress = 0;
            let completed = 0;

            // Percorre cada funcao
            Object.values(metricas).forEach(funcao => {
                totalProjects++; // considerando 1 projeto por função
                Object.entries(funcao).forEach(([status, qtd]) => {
                    totalTasks += qtd;
                    if (status === 'Em andamento') inProgress += qtd;
                    if (status === 'Finalizado') completed += qtd;
                });
            });

            // Atualiza os cards no HTML
            document.querySelector('.total-card:nth-child(1) .card-value').textContent = totalProjects;
            document.querySelector('.total-card:nth-child(2) .card-value').textContent = totalTasks;
            document.querySelector('.total-card:nth-child(3) .card-value').textContent = inProgress;
            document.querySelector('.total-card:nth-child(4) .card-value').textContent = completed;
        })
        .catch(error => console.error('Erro ao buscar métricas:', error));
}

// Chama a função ao carregar a página
gerarMetricas();

