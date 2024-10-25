function formatarDataAtual() {
    const opcoes = { weekday: 'long', day: 'numeric', month: 'long' };
    const dataAtual = new Date();
    return dataAtual.toLocaleDateString('pt-BR', opcoes);
}

document.getElementById('data').textContent = formatarDataAtual();

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('colaborador').addEventListener('change', function () {
        const colaboradorId = this.value;
        console.log(colaboradorId);
        carregarDadosColab();
        carregarDadosGrafico(colaboradorId);
    });
    document.getElementById('mes').addEventListener('change', carregarDadosColab);

    var statusTarefasChart;

    function carregarDadosColab() {
        var colaboradorId = document.getElementById('colaborador').value;
        var mesId = document.getElementById('mes').value;

        if (colaboradorId) {
            var url = 'getColaborador.php?colaborador_id=' + encodeURIComponent(colaboradorId);

            if (mesId) {
                url += '&mes_id=' + encodeURIComponent(mesId);
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    // Atualiza a tabela
                    var tabela = document.querySelector('#tabela-faturamento tbody');
                    tabela.innerHTML = '';
                    let totalValor = 0;

                    data.forEach(function (item) {
                        var row = document.createElement('tr');
                        row.setAttribute('data-id', item.idfuncao_imagem);

                        var cellNomeImagem = document.createElement('td');
                        cellNomeImagem.textContent = item.imagem_nome;

                        var cellFuncao = document.createElement('td');
                        cellFuncao.textContent = item.nome_funcao;

                        var cellStatusFuncao = document.createElement('td');
                        cellStatusFuncao.textContent = item.status;

                        var cellValor = document.createElement('td');
                        cellValor.textContent = item.valor;

                        totalValor += parseFloat(item.valor) || 0;

                        var cellCheckbox = document.createElement('td');
                        var checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.classList.add('pagamento-checkbox');
                        checkbox.checked = item.pagamento === 1;
                        checkbox.setAttribute('data-id', item.idfuncao_imagem);

                        checkbox.addEventListener('change', function () {
                            if (checkbox.checked) {
                                row.classList.add('checked');
                            } else {
                                row.classList.remove('checked');
                            }
                        });
                        cellCheckbox.appendChild(checkbox);

                        row.appendChild(cellNomeImagem);
                        row.appendChild(cellStatusFuncao);
                        row.appendChild(cellFuncao);
                        row.appendChild(cellValor);
                        row.appendChild(cellCheckbox);

                        tabela.appendChild(row);

                        if (checkbox.checked) {
                            row.classList.add('checked');
                        }
                    });

                    var totalValorLabel = document.getElementById('totalValor');
                    totalValorLabel.textContent = 'Total: R$ ' + totalValor.toFixed(2).replace('.', ',');

                    var contagemLinhasLabel = document.getElementById('contagemLinhasLabel');
                    contagemLinhasLabel.textContent = 'Total de Linhas: ' + data.length;

                    // Atualiza o gráfico de status de tarefas quando o colaborador é alterado
                    atualizarGraficoStatusTarefas(data);

                })
                .catch(error => {
                    console.error('Erro ao carregar dados do colaborador:', error);
                });
        } else {
            document.querySelector('#tabela-faturamento tbody').innerHTML = '';
            var totalValorLabel = document.getElementById('totalValor');
            totalValorLabel.textContent = 'Total: R$ 0,00';
        }
    }

    function carregarDadosGrafico(colaboradorId) {
        // Crie uma URL que aponte para o seu script PHP que executa a consulta
        const url = `dados_grafico.php?colaborador_id=${colaboradorId}`;

        // Use Fetch API para obter os dados do servidor
        fetch(url)
            .then(response => {
                // Verifica se a resposta foi bem-sucedida
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Aqui você deve processar os dados retornados e atualizar o gráfico
                // Exemplo: supondo que você esteja usando Chart.js para o gráfico
                const meses = data.map(item => item.mes);
                const totais = data.map(item => item.total_funcoes);
                const totalValor = data.reduce((acc, item) => acc + parseFloat(item.total_valor), 0); // Calcula o total geral

                // Atualize seu gráfico com os dados recebidos
                atualizarGrafico(meses, totais);
                document.getElementById('valorTotal').innerText = `Valor Total: ${totalValor.toFixed(2)}`; // Formata para duas casas decimais

            })
            .catch(error => {
                console.error('Houve um problema com a requisição Fetch:', error);
            });
    }

    function atualizarGrafico(meses, totais) {
        const ctx = document.getElementById('tarefasPorMes').getContext('2d');
        if (window.meuGrafico) {
            window.meuGrafico.destroy(); // Destrói o gráfico existente antes de criar um novo
        }

        window.meuGrafico = new Chart(ctx, {
            type: 'bar', // ou 'line', 'pie', etc.
            data: {
                labels: meses,
                datasets: [{
                    label: 'Total de Tarefas',
                    data: totais,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
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

    function atualizarGraficoStatusTarefas(data) {
        // Se o gráfico já existe, destrua antes de recriar
        if (statusTarefasChart) {
            statusTarefasChart.destroy();
        }

        var ctx2 = document.getElementById('statusTarefas').getContext('2d');

        // Atualiza os dados do status de finalização
        var finalizadas = data.filter(item => item.status === 'Finalizado').length;
        var naoFinalizadas = data.length - finalizadas;

        statusTarefasChart = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Finalizadas', 'Não Finalizadas'],
                datasets: [{
                    data: [finalizadas, naoFinalizadas],
                    backgroundColor: ['#00FF66', '#FFC300'],
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Status das Tarefas'
                    }
                }
            }
        });
    }

    document.getElementById('marcar-todos').addEventListener('click', function () {
        var checkboxes = document.querySelectorAll('.pagamento-checkbox');
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = !checkbox.checked;
        });
    });

    document.getElementById('confirmar-pagamento').addEventListener('click', function () {
        var checkboxes = document.querySelectorAll('.pagamento-checkbox:checked');
        var ids = Array.from(checkboxes).map(cb => cb.getAttribute('data-id'));

        if (ids.length > 0) {
            fetch('updatePagamento.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ ids: ids })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Pagamentos atualizados com sucesso!');
                        carregarDadosColab();
                    } else {
                        alert('Erro ao atualizar pagamentos.');
                    }
                })
                .catch(error => {
                    console.error('Erro ao confirmar pagamentos:', error);
                });
        } else {
            alert('Selecione pelo menos uma imagem para confirmar o pagamento.');
        }
    });

    document.getElementById('adicionar-valor').addEventListener('click', function () {
        var checkboxes = document.querySelectorAll('.pagamento-checkbox:checked');
        var ids = Array.from(checkboxes).map(cb => cb.getAttribute('data-id'));

        var valor = document.getElementById('valor').value;

        if (ids.length > 0 && valor) {
            fetch('updateValor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ ids: ids, valor: valor })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Valores atualizados com sucesso!');
                        carregarDadosColab();
                    } else {
                        alert('Erro ao atualizar valores: ' + (data.error || 'Erro desconhecido.'));
                    }
                })
                .catch(error => {
                    console.error('Erro ao adicionar valores:', error);
                });
        } else {
            alert('Selecione pelo menos uma imagem e insira um valor.');
        }
    });
});

