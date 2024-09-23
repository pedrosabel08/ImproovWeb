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
});

function toggleNav() {
    const menu = document.querySelector('.nav-menu');
    menu.classList.toggle('active');
}

function openModal(modalId, button) {
    if (modalId === 'tabela-colab') {
        alterarTabelaParaColaboradores();
    }
    // Outras funções podem ser adicionadas para abrir modais
}

function alterarTabelaParaColaboradores() {
    const tabela = document.getElementById('tabela-faturamento');
    const thead = tabela.querySelector('thead');
    const tbody = tabela.querySelector('tbody');

    thead.innerHTML = `
        <tr>
            <th><input type="checkbox" id="select-all-checkbox"></th>
            <th>Nome imagem</th>
            <th>Status Pagamento</th>
            <th>Valor Imagem</th>
        </tr>
    `;

    tbody.innerHTML = ''; // Limpar a tabela antes de adicionar novos dados

    // Adicionar evento para o checkbox "Selecionar Todos"
    document.getElementById('select-all-checkbox').addEventListener('change', function () {
        const checkboxes = tbody.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked; // Marcar ou desmarcar todos os checkboxes
        });
    });

    // Adicionar botão para confirmar atualização de pagamento
    const confirmButton = document.createElement('button');
    confirmButton.textContent = 'Confirmar Pagamentos';
    confirmButton.addEventListener('click', updateAllPagamentos);
    tabela.parentElement.insertBefore(confirmButton, tabela); // Adiciona o botão acima da tabela
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
                    console.log(data); // Verifique a saída no console
                    var tabela = document.querySelector('#tabela-faturamento tbody');
                    tabela.innerHTML = ''; // Limpar a tabela antes de adicionar novos dados

                    data.forEach(function (item) {
                        var row = document.createElement('tr');
                        row.setAttribute('data-id', item.idfuncao_imagem); // Adicione esta linha

                        // Criar checkbox para cada linha
                        var cellSelect = document.createElement('td');
                        var checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.checked = item.pagamento === 1; // Marcar se o pagamento está pago
                        cellSelect.appendChild(checkbox);
                        row.appendChild(cellSelect);

                        var cellNomeImagem = document.createElement('td');
                        cellNomeImagem.textContent = item.imagem_nome;

                        var cellStatusPagamento = document.createElement('td');
                        cellStatusPagamento.textContent = item.pagamento === 0 ? 'Não Pago' : 'Pago';
                        cellStatusPagamento.style.backgroundColor = item.pagamento === 0 ? 'red' : 'green';

                        var cellValor = document.createElement('td');
                        cellValor.textContent = item.valor;

                        row.appendChild(cellNomeImagem);
                        row.appendChild(cellStatusPagamento);
                        row.appendChild(cellValor);

                        tabela.appendChild(row);
                    });
                })
                .catch(error => {
                    console.error('Erro ao carregar dados do colaborador:', error);
                });
        } else {
            document.querySelector('#tabela-faturamento tbody').innerHTML = ''; // Limpar a tabela caso não haja colaborador selecionado
        }
    }
});

function updateAllPagamentos() {
    const checkboxes = document.querySelectorAll('#tabela-faturamento tbody input[type="checkbox"]');
    const updates = [];

    checkboxes.forEach((checkbox, index) => {
        const row = checkbox.closest('tr');
        const pagamento = checkbox.checked ? 1 : 0; // 1 para pago, 0 para não pago

        // Obter o ID da linha correspondente (supondo que você tenha um atributo data-id ou similar)
        const id = row.getAttribute('data-id'); // Certifique-se de que as linhas têm esse atributo

        updates.push({ idfuncao_imagem: id, pagamento: pagamento });
    });

    // Enviar as atualizações em lote
    fetch('updatePagamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(updates)
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro ao atualizar pagamentos');
            }
            return response.json();
        })
        .then(data => {
            console.log('Pagamentos atualizados:', data);
        })
        .catch(error => {
            console.error('Erro ao atualizar pagamentos:', error);
        });
}
