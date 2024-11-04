function formatarDataAtual() {
    const opcoes = { weekday: 'long', day: 'numeric', month: 'long' };
    const dataAtual = new Date();
    return dataAtual.toLocaleDateString('pt-BR', opcoes);
}

document.getElementById('data').textContent = formatarDataAtual();

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('colaborador').addEventListener('change', function () {
        carregarDadosColab();
    });
    document.getElementById('mes').addEventListener('change', carregarDadosColab);

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

                    contarLinhasTabela();
                    // Atualiza o gráfico de status de tarefas quando o colaborador é alterado

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
});


function contarLinhasTabela() {
    const tabela = document.getElementById("tabela-faturamento");
    const tbody = tabela.getElementsByTagName("tbody")[0];
    const linhas = tbody.getElementsByTagName("tr");
    let totalImagens = 0;
    let totalValor = 0;

    for (let i = 0; i < linhas.length; i++) {
        if (linhas[i].style.display !== "none") {
            totalImagens++;
            const valorCell = linhas[i].getElementsByTagName("td")[3]; // Supondo que o valor está na quarta coluna (índice 3)
            const valor = parseFloat(valorCell.textContent.replace('R$', '').replace(',', '.').trim());
            totalValor += !isNaN(valor) ? valor : 0; // Soma o valor se for um número
        }
    }

    document.getElementById("total-imagens").innerText = totalImagens;
    document.getElementById("totalValor").innerText = totalValor.toFixed(2).replace('.', ','); // Atualiza o total
    document.getElementById('valores').style.display = 'flex';
}


function filtrarTabela() {
    const tipoImagemFiltro = document.getElementById('tipoImagemFiltro').value;
    const tabela = document.querySelector('#tabela-faturamento tbody');
    const linhas = tabela.getElementsByTagName('tr');

    for (let i = 0; i < linhas.length; i++) {
        const linha = linhas[i];
        const funcaoCell = linha.cells[2];

        if (funcaoCell) {
            const funcaoText = funcaoCell.textContent || funcaoCell.innerText;
            if (tipoImagemFiltro === "" || funcaoText === tipoImagemFiltro) {
                linha.style.display = "";
            } else {
                linha.style.display = "none";
            }
        }
    }

    contarLinhasTabela();
}

document.getElementById('generate-pdf').addEventListener('click', function () {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({
        orientation: 'landscape',
    });

    const colaborador = document.getElementById('colaborador').options[document.getElementById('colaborador').selectedIndex].text;
    const mes = document.getElementById('mes').options[document.getElementById('mes').selectedIndex].text;
    const ano = new Date().getFullYear();
    let currentY = 20;

    const title = `Relatório mensal de ${colaborador}, ${mes} de ${ano}`;
    const valorTotal = "Valor total: ";
    const quantidadeTarefas = "Quantidade de tarefas: ";

    const totalValorElement = document.getElementById('totalValor');
    const totalValor = totalValorElement ? totalValorElement.innerText : "N/A";
    const quantidadeTarefasValue = document.querySelectorAll('#tabela-faturamento tbody tr').length;

    const imgPath = '../assets/logo.jpg';

    fetch(imgPath)
        .then(response => response.blob())
        .then(blob => {
            const reader = new FileReader();
            reader.onloadend = function () {
                const imgData = reader.result;
                doc.addImage(imgData, 'PNG', 14, currentY, 40, 40);
                currentY += 50;

                doc.setFontSize(16);
                doc.setTextColor(0, 0, 0);
                doc.text(title, 14, currentY);
                currentY += 10;

                doc.setFontSize(12);
                doc.text(`${valorTotal} ${totalValor}`, 14, currentY);
                currentY += 10;

                doc.text(`${quantidadeTarefas} ${quantidadeTarefasValue}`, 14, currentY);
                currentY += 20;

                const table = document.getElementById('tabela-faturamento');
                const selectedColumnIndexes = [0, 1, 2, 3]; // Colunas específicas que deseja incluir
                const headers = [];
                const rows = [];

                // Adiciona apenas os cabeçalhos das colunas selecionadas
                table.querySelectorAll('thead tr th').forEach((header, index) => {
                    if (selectedColumnIndexes.includes(index)) {
                        headers.push(header.innerText);
                    }
                });

                // Adiciona apenas os dados das colunas selecionadas
                table.querySelectorAll('tbody tr').forEach(row => {
                    const rowData = [];
                    row.querySelectorAll('td').forEach((cell, index) => {
                        if (selectedColumnIndexes.includes(index)) {
                            rowData.push(cell.innerText);
                        }
                    });
                    rows.push(rowData);
                });

                // Gera a tabela no PDF
                doc.autoTable({
                    head: [headers],
                    body: rows,
                    startY: currentY
                });

                doc.save(`Relatório_${colaborador}_${mes}_${ano}.pdf`);
            };
            reader.readAsDataURL(blob);
        })
        .catch(error => console.error('Erro ao carregar a imagem:', error));
});
