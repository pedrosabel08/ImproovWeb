let graficoGeral;      // variáveis globais para os gráficos
let graficoDetalhado;
let dataTable = null;

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
                            <tr data-idimagem="${item.imagem_id}">   
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

                        totalComercial += parseFloat(item.valor_comercial_liquido);
                        totalProducao += parseFloat(item.valor_producao_total);

                        nomesImagens.push(item.imagem_nome);
                        valoresComercial.push(parseFloat(item.valor_comercial_liquido));
                        valoresProducao.push(parseFloat(item.valor_producao_total));
                    });

                    // Atualiza o corpo da tabela
                    $('#tabelaComercial tbody').html(linhas);

                    // Destroi o DataTable anterior, se existir
                    if (dataTable) {
                        dataTable.destroy();
                    }

                    // Recria o DataTable
                    dataTable = $('#tabelaComercial').DataTable({
                        language: {
                            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
                        },
                        "paging": false,
                        "lengthChange": false,
                        "info": false,
                        "ordering": true,
                        "searching": true,
                        order: [] // <-- impede ordenação inicial

                    });

                    atualizarGraficoGeral(totalComercial, totalProducao);
                    atualizarGraficoDetalhado(nomesImagens, valoresComercial, valoresProducao);

                } else {
                    if (dataTable) {
                        dataTable.clear().draw(); // limpa o datatable
                    } else {
                        $('#tabelaComercial tbody').html('<tr><td colspan="9" style="text-align:center;">Nenhum dado encontrado</td></tr>');
                    }
                    destruirGraficos();
                }
            },
            error: function () {
                alert('Erro ao buscar os dados comerciais.');
            }
        });
    } else {
        if (dataTable) {
            dataTable.clear().draw();
        } else {
            $('#tabelaComercial tbody').html('<tr><td colspan="9" style="text-align:center;">Selecione uma imagem para ver os dados</td></tr>');
        }
        destruirGraficos();
    }
});

$('#tabelaComercial tbody').on('click', 'tr', function () {
    const imagemId = $(this).data('idimagem');

    // Remove a classe 'selecionada' de todas as linhas
    $('#tabelaComercial tbody tr').removeClass('selecionada');

    // Adiciona a classe 'selecionada' apenas à linha clicada
    this.classList.add('selecionada');

    // POSICIONA O MODAL LOGO ABAIXO DA CÉLULA "Valor Produção Total"
    const cell = $(this).find('td').last(); // ou use .eq(N) se não for a última coluna
    const position = cell.offset();

    $('#modalDetalhes').css({
        top: position.top + cell.outerHeight(),
        left: position.left - 100,
        position: 'absolute',
        display: 'block',
        zIndex: 9999
    });

    const modal = $('#modalDetalhes');
    const modalHeight = modal.outerHeight();
    const windowHeight = $(window).height();
    const scrollTop = modal.offset().top - ((windowHeight - modalHeight) / 2);

    $('html, body').animate({
        scrollTop: scrollTop
    }, 300);

    // CHAMA A REQUISIÇÃO AJAX NORMALMENTE
    if (imagemId) {
        $.ajax({
            url: 'busca_detalhes.php',
            type: 'GET',
            dataType: 'json',
            data: { imagem_id: imagemId },
            success: function (response) {
                if (response.length > 0) {
                    let linhasDetalhes = '';
                    response.forEach(function (item) {
                        linhasDetalhes += `
                            <tr>
                                <td>${item.nome_funcao}</td>
                                <td>${item.nome_colaborador}</td>
                                <td>R$ ${parseFloat(item.valor).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                    $('#tabelaDetalhes tbody').html(linhasDetalhes);
                } else {
                    $('#tabelaDetalhes tbody').html('<tr><td colspan="3" style="text-align:center;">Sem dados</td></tr>');
                }
            },
            error: function () {
                alert('Erro ao buscar detalhes da função.');
            }
        });
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
