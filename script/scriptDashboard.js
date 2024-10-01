document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('ano').addEventListener('change', carregarDados);
    document.getElementById('cliente').addEventListener('change', carregarDados);
    document.getElementById('obra').addEventListener('change', carregarDados);

    function carregarDados() {
        var anoId = document.getElementById('ano').value;
        var clienteId = document.getElementById('cliente').value;
        var obraId = document.getElementById('obra').value;

        if (anoId) {
            var url = 'getFaturamento.php?ano=' + encodeURIComponent(anoId);

            if (clienteId) {
                url += '&cliente_id=' + encodeURIComponent(clienteId);
            }
            if (obraId) {
                url += '&obra_id=' + encodeURIComponent(obraId);
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    var tabela = document.querySelector('#tabela-faturamento tbody');
                    tabela.innerHTML = '';

                    data.forEach(function (item) {
                        var row = document.createElement('tr');

                        var cellNomeImagem = document.createElement('td');
                        cellNomeImagem.textContent = item.imagem_nome;

                        var cellStatus = document.createElement('td');
                        cellStatus.textContent = item.status_pagamento === 0 ? 'Não Pago' : 'Pago';
                        cellStatus.style.backgroundColor = item.status_pagamento === 0 ? 'red' : 'green';

                        var cellValor = document.createElement('td');
                        cellValor.textContent = item.valor;

                        row.appendChild(cellNomeImagem);
                        row.appendChild(cellStatus);
                        row.appendChild(cellValor);
                        tabela.appendChild(row);
                    });
                })
                .catch(error => console.error('Erro ao carregar faturamento:', error));
        } else {
            document.querySelector('#tabela-faturamento tbody').innerHTML = '';
        }
    }

    document.getElementById('tabela-faturamento-colab').style.display = 'none';
    document.getElementById('buttons').style.display = 'none';

});

function toggleNav() {
    const menu = document.querySelector('.nav-menu');
    menu.classList.toggle('active');
}

function openModal(modalId) {
    if (modalId === 'tabela-faturamento-colab') {
        alterarTabela('tabela-faturamento-colab', 'tabela-faturamento', true);
    } else if (modalId === 'tabela-faturamento') {
        alterarTabela('tabela-faturamento', 'tabela-faturamento-colab', false);
    }
}

function alterarTabela(tabelaAtivaId, tabelaInativaId, mostrarBotoes) {
    toggleNav();

    const tabelaAtiva = document.getElementById(tabelaAtivaId);
    const tabelaInativa = document.getElementById(tabelaInativaId);

    tabelaAtiva.style.display = '';
    tabelaInativa.style.display = 'none';

    document.getElementById('buttons').style.display = mostrarBotoes ? 'grid' : 'none';

    const tbodyAtiva = tabelaAtiva.querySelector('tbody');
    tbodyAtiva.innerHTML = '';

}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('colaborador').addEventListener('change', carregarDadosColab);
    document.getElementById('cliente').addEventListener('change', carregarDadosColab);
    document.getElementById('obra').addEventListener('change', carregarDadosColab);

    function carregarDadosColab() {
        var colaboradorId = document.getElementById('colaborador').value;
        var clienteId = document.getElementById('cliente').value;
        var obraId = document.getElementById('obra').value;

        if (colaboradorId) {
            var url = 'getColaborador.php?colaborador_id=' + encodeURIComponent(colaboradorId);

            if (clienteId) {
                url += '&cliente_id=' + encodeURIComponent(clienteId);
            }
            if (obraId) {
                url += '&obra_id=' + encodeURIComponent(obraId);
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    var tabela = document.querySelector('#tabela-faturamento-colab tbody');
                    tabela.innerHTML = '';
                    let totalValor = 0;


                    data.forEach(function (item) {
                        var row = document.createElement('tr');
                        row.setAttribute('data-id', item.idfuncao_imagem);

                        var cellNomeImagem = document.createElement('td');
                        cellNomeImagem.textContent = item.imagem_nome;

                        var cellStatusPagamento = document.createElement('td');
                        cellStatusPagamento.textContent = item.pagamento === 0 ? 'Não Pago' : 'Pago';
                        cellStatusPagamento.style.backgroundColor = item.pagamento === 0 ? 'red' : 'green';

                        var cellValor = document.createElement('td');
                        cellValor.textContent = item.valor;

                        totalValor += parseFloat(item.valor) || 0;

                        var cellCheckbox = document.createElement('td');
                        var checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.classList.add('pagamento-checkbox');
                        checkbox.checked = item.pagamento === 1;
                        checkbox.setAttribute('data-id', item.idfuncao_imagem);
                        cellCheckbox.appendChild(checkbox);

                        row.appendChild(cellNomeImagem);
                        row.appendChild(cellStatusPagamento);
                        row.appendChild(cellValor);
                        row.appendChild(cellCheckbox);


                        tabela.appendChild(row);
                    });

                    var totalValorLabel = document.getElementById('totalValor');
                    totalValorLabel.textContent = 'Total: R$ ' + totalValor.toFixed(2).replace('.', ',');

                    var contagemLinhasLabel = document.getElementById('contagemLinhasLabel');
                    contagemLinhasLabel.textContent = 'Total de Linhas: ' + data.length;
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
                        carregarDadosColab(); // Recarrega a tabela
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

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('obra').addEventListener('change', carregarGrafico);

    function carregarGrafico() {
        var obraId = document.getElementById('obra').value;

        if (obraId) {
            var url = 'getGraficoStatus.php?obra_id=' + encodeURIComponent(obraId);

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    var labels = [];
                    var cadernoCount = [];
                    var modelagemCount = [];
                    var composicaoCount = [];
                    var finalizacaoCount = [];
                    var posProducaoCount = [];
                    var alteracaoCount = [];

                    data.forEach(function (item) {
                        labels.push(item.imagem_status);
                        cadernoCount.push(item.caderno_count);
                        modelagemCount.push(item.modelagem_count);
                        composicaoCount.push(item.composicao_count);
                        finalizacaoCount.push(item.finalizacao_count);
                        posProducaoCount.push(item.pos_producao_count);
                        alteracaoCount.push(item.alteracao_count);
                    });

                    atualizarGrafico(labels, cadernoCount, modelagemCount, composicaoCount, finalizacaoCount, posProducaoCount, alteracaoCount);
                })
                .catch(error => {
                    console.error('Erro ao carregar dados do gráfico:', error);
                });
        }
    }

    function atualizarGrafico(labels, caderno, modelagem, composicao, finalizacao, posProducao, alteracao) {
        var ctx = document.getElementById('meuGrafico').getContext('2d');

        // Destruir o gráfico anterior, se existir
        if (window.meuGrafico && typeof window.meuGrafico.destroy === 'function') {
            window.meuGrafico.destroy();
        }

        window.meuGrafico = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Caderno', data: caderno, backgroundColor: 'rgba(255, 99, 132, 0.2)', borderColor: 'rgba(255, 99, 132, 1)', borderWidth: 1 },
                    { label: 'Modelagem', data: modelagem, backgroundColor: 'rgba(54, 162, 235, 0.2)', borderColor: 'rgba(54, 162, 235, 1)', borderWidth: 1 },
                    { label: 'Composição', data: composicao, backgroundColor: 'rgba(75, 192, 192, 0.2)', borderColor: 'rgba(75, 192, 192, 1)', borderWidth: 1 },
                    { label: 'Finalização', data: finalizacao, backgroundColor: 'rgba(153, 102, 255, 0.2)', borderColor: 'rgba(153, 102, 255, 1)', borderWidth: 1 },
                    { label: 'Pós Produção', data: posProducao, backgroundColor: 'rgba(255, 159, 64, 0.2)', borderColor: 'rgba(255, 159, 64, 1)', borderWidth: 1 },
                    { label: 'Alteração', data: alteracao, backgroundColor: 'rgba(255, 206, 86, 0.2)', borderColor: 'rgba(255, 206, 86, 1)', borderWidth: 1 }
                ]
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
});


const filtroGrafico = document.getElementById('filtro-grafico');
const selectAno = document.getElementById('ano');
const selectMes = document.getElementById('mes');

// Adiciona o evento de mudança (change) no select filtro-grafico
filtroGrafico.addEventListener('change', function () {
    const selectedValue = this.value;

    // Oculta os selects "ano" e "mes" por padrão
    selectAno.classList.add('hidden');
    selectMes.classList.add('hidden');

    // Verifica o valor selecionado e exibe o select correspondente
    if (selectedValue === 'ano') {
        selectAno.classList.remove('hidden');
    } else if (selectedValue === 'mes') {
        selectMes.classList.remove('hidden');
    }
});
