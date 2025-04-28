let graficoGeral;      // variáveis globais para os gráficos
let graficoDetalhado;

$('#selectImagem').on('change', function () {
    var obra_id = $(this).val();

    if (obra_id !== "") {
        $.ajax({
            url: 'busca_comercial.php',
            type: 'GET',
            dataType: 'json',
            data: { obra_id: obra_id },
            success: function (response) {
                if (response.length > 0) {
                    let linhas = '';
                    let totalComercial = 0;
                    let totalProducao = 0;

                    let nomesImagens = [];
                    let valoresComercial = [];
                    let valoresProducao = [];

                    response.forEach(function (item) {
                        linhas += `
                            <tr>
                                <td>${item.numero_contrato}</td>
                                <td>${item.imagem_nome}</td>
                                <td>R$ ${parseFloat(item.valor_comercial_bruto).toFixed(2)}</td>
                                <td>${item.imposto}%</td>
                                <td>R$ ${parseFloat(item.valor_imposto).toFixed(2)}</td>
                                <td>${item.comissao_comercial}%</td>
                                <td>R$ ${parseFloat(item.valor_comissao_comercial).toFixed(2)}</td>
                                <td>R$ ${parseFloat(item.valor_comercial_liquido).toFixed(2)}</td>
                                <td>R$ ${parseFloat(item.valor_producao_total).toFixed(2)}</td>
                            </tr>
                        `;

                        // Preparar os dados para os gráficos
                        totalComercial += parseFloat(item.valor_comercial_liquido);
                        totalProducao += parseFloat(item.valor_producao_total);

                        nomesImagens.push(item.imagem_nome);
                        valoresComercial.push(parseFloat(item.valor_comercial_liquido));
                        valoresProducao.push(parseFloat(item.valor_producao_total));
                    });

                    $('#tabelaComercial tbody').html(linhas);

                    atualizarGraficoGeral(totalComercial, totalProducao);
                    atualizarGraficoDetalhado(nomesImagens, valoresComercial, valoresProducao);

                } else {
                    $('#tabelaComercial tbody').html('<tr><td colspan="9" style="text-align:center;">Nenhum dado encontrado</td></tr>');
                    destruirGraficos(); // limpar gráficos se não tiver dados
                }
            },
            error: function () {
                alert('Erro ao buscar os dados comerciais.');
            }
        });
    } else {
        $('#tabelaComercial tbody').html('<tr><td colspan="9" style="text-align:center;">Selecione uma imagem para ver os dados</td></tr>');
        destruirGraficos();
    }
});

function atualizarGraficoGeral(totalComercial, totalProducao) {
    if (graficoGeral) {
        graficoGeral.destroy();
    }

    // Calcula o lucro
    var lucro = totalComercial - totalProducao;

    var ctx = document.getElementById('graficoGeral').getContext('2d');
    graficoGeral = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Comercial Líquido', 'Produção', 'Lucro'],
            datasets: [{
                label: 'Total em R$',
                data: [totalComercial, totalProducao, lucro],
                backgroundColor: ['#4e73df', '#1cc88a', '#f6c23e'], // Azul, Verde e Amarelo
                borderWidth: 1
            }]
        },
        options: {
            plugins: {
                title: {
                    display: true,
                    text: 'Comparativo Geral Comercial vs Produção'
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            let valor = context.parsed.y || 0;
                            return context.dataset.label + ': R$ ' + valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    }
                }
            }
        }
    });
}

function atualizarGraficoDetalhado(nomesImagens, valoresComercial, valoresProducao) {
    if (graficoDetalhado) {
        graficoDetalhado.destroy();
    }
    var ctx = document.getElementById('graficoDetalhado').getContext('2d');
    graficoDetalhado = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: nomesImagens,
            datasets: [
                {
                    label: 'Comercial Líquido',
                    data: valoresComercial,
                    backgroundColor: '#4e73df'
                },
                {
                    label: 'Produção',
                    data: valoresProducao,
                    backgroundColor: '#1cc88a'
                }
            ]
        },
        options: {
            plugins: {
                title: {
                    display: true,
                    text: 'Comparativo por Imagem'
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            let valor = context.parsed.y || 0;
                            return context.dataset.label + ': R$ ' + valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    }
                }
            },
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    }
                }
            }
        }
    });
}

function destruirGraficos() {
    if (graficoGeral) {
        graficoGeral.destroy();
    }
    if (graficoDetalhado) {
        graficoDetalhado.destroy();
    }
}
