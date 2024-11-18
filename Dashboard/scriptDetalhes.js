function toggleVisibility(sectionId) {
    const section = document.getElementById(sectionId);
    const button = section.previousElementSibling; // Botão associado à seção
    if (section.style.display === 'none') {
        section.style.display = 'block';
        button.textContent = 'Ocultar Detalhes';
    } else {
        section.style.display = 'none';
        button.textContent = 'Mostrar Detalhes';
    }
}


document.addEventListener('DOMContentLoaded', function () {

    const sections = document.querySelectorAll('.toggle-btn');
    sections.forEach(section => {
        const div = section.nextElementSibling;
        div.style.display = 'none'; // Esconde as seções inicialmente
    });

    // Função para obter o parâmetro 'id' da URL
    function getQueryParameter(name) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }

    const obraId = getQueryParameter('id');

    if (obraId) {
        fetch(`detalhesObra.php?id=${obraId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor');
                }
                return response.text();  // Obtemos a resposta como texto para depuração
            })
            .then(text => {
                console.log('Resposta bruta do servidor:', text); // Exibe a resposta para depuração
                try {
                    const data = JSON.parse(text);  // Tenta converter a resposta em JSON
                    if (data.error) {
                        console.error(data.error);
                    } else {
                        console.log('Detalhes da obra:', data);  // Exibe os detalhes no console

                        // Preencher os campos HTML com os dados recebidos
                        document.getElementById('nome_obra').textContent = data.nome_obra;
                        document.getElementById('cliente').textContent = data.cliente;
                        document.getElementById('loc').textContent = data.localizacao || 'Não disponível'; // Exemplo de localizacao, se necessário
                        document.getElementById('status_total').textContent = data.status_obra;
                        document.getElementById('data_inicio').textContent = data.data_inicio;
                        document.getElementById('prazo').textContent = data.prazo;
                        document.getElementById('data_final').textContent = data.data_final;

                        document.getElementById('total_colabs').textContent = data.total_colaboradores;

                        document.getElementById('colab_caderno').textContent = (data.colab_caderno || 'Não atribuído').replace(/,/g, ', ');
                        document.getElementById('colab_filtro').textContent = (data.colab_filtro || 'Não atribuído').replace(/,/g, ', ');
                        document.getElementById('colab_model').textContent = (data.colab_model || 'Não atribuído').replace(/,/g, ', ');
                        document.getElementById('colab_comp').textContent = (data.colab_comp || 'Não atribuído').replace(/,/g, ', ');
                        document.getElementById('colab_final').textContent = (data.colab_final || 'Não atribuído').replace(/,/g, ', ');
                        document.getElementById('colab_pos').textContent = (data.colab_pos || 'Não atribuído').replace(/,/g, ', ');
                        document.getElementById('colab_planta').textContent = (data.colab_planta || 'Não atribuído').replace(/,/g, ', ');
                        document.getElementById('colab_alt').textContent = (data.colab_alt || 'Não atribuído').replace(/,/g, ', ');

                        // Exibir os status das imagens
                        // document.getElementById('status_ef').textContent = data.percentual_status_6 + '%';
                        // document.getElementById('status_P00').textContent = data.percentual_status_2 + '%';
                        // document.getElementById('status_R00').textContent = data.percentual_status_1 + '%';
                        // document.getElementById('status_revisao').textContent = data.percentual_status_3_4_5 + '%';
                        // document.getElementById('status_hold').textContent = data.percentual_status_9 + '%';

                        // Animação
                        // document.getElementById('animacao').checked = data.animacao === 1;  // Marcar o checkbox se 'animacao' for 1

                        // document.getElementById('total_revisoes').textContent = data.total_revisoes;

                        // Custos
                        // document.getElementById('valor_contrato').textContent = data.total_contrato || 0;  // Supondo que seja o total de produção
                        // document.getElementById('valor_producao').textContent = data.total_gasto_producao;  // Pode ser outro valor se necessário

                        const ctxImagem = document.getElementById('imagemChart').getContext('2d');
                        const imagemChart = new Chart(ctxImagem, {
                            type: 'pie',
                            data: {
                                labels: ['EF', 'P00', 'R00', 'Revisão', 'HOLD'],
                                datasets: [{
                                    label: 'Status das Imagens (%)',
                                    data: [data.percentual_status_6, data.percentual_status_2, data.percentual_status_1, data.percentual_status_3_4_5, data.percentual_status_9],
                                    backgroundColor: ['#FF5733', '#33FF57', '#3357FF', '#FF33A6', '#FF8C33'],
                                    borderColor: '#000',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });

                        // Gráfico para Custos
                        const ctxCusto = document.getElementById('custoChart').getContext('2d');
                        const custoChart = new Chart(ctxCusto, {
                            type: 'bar',
                            data: {
                                labels: ['Valor de contrato', 'Custos de produção'],
                                datasets: [{
                                    label: 'Custos (%)',
                                    data: [data.total_contrato, data.total_gasto_producao],
                                    backgroundColor: ['#FFD700', '#4CAF50'],
                                    borderColor: '#000',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });


                    }
                } catch (e) {
                    console.error('Erro ao analisar a resposta JSON:', e);
                    console.error('Resposta não é um JSON válido');
                }
            })
            .catch(error => {
                console.error('Erro ao carregar detalhes da obra:', error);
            });
    } else {
        console.error('ID da obra não fornecido na URL');
    }

});

