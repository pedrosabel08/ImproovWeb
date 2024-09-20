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

                        // Nome da imagem
                        var cellNomeImagem = document.createElement('td');
                        cellNomeImagem.textContent = item.imagem_nome;

                        // Status do pagamento: 0 = "Não Pago", 1 = "Pago"
                        var cellStatus = document.createElement('td');
                        cellStatus.textContent = item.status_pagamento === 0 ? 'Não Pago' : 'Pago';
                        cellStatus.style.backgroundColor = item.status_pagamento === 0 ? 'red' : 'green'; // Define a cor com base no status

                        // Valor
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
