document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('colaborador').addEventListener('change', carregarDadosColab);
    document.getElementById('obra').addEventListener('change', carregarDadosColab);

    function carregarDadosColab() {
        var colaboradorId = document.getElementById('colaborador').value;
        var obraId = document.getElementById('obra').value;

        if (colaboradorId) {
            var url = 'getColaborador.php?colaborador_id=' + encodeURIComponent(colaboradorId);

            if (obraId) {
                url += '&obra_id=' + encodeURIComponent(obraId);
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
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
