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

                    document.querySelectorAll('.tipo-imagem input[type="checkbox"]').forEach(checkbox => {
                        checkbox.checked = false;
                    });

                    data.forEach(function (item) {
                        var row = document.createElement('tr');
                        row.setAttribute('data-id', item.identificador);

                        var cellNomeImagem = document.createElement('td');
                        var cellStatusFuncao = document.createElement('td');
                        var cellFuncao = document.createElement('td');
                        var cellValor = document.createElement('td');
                        var cellCheckbox = document.createElement('td');
                        var cellData = document.createElement('td');
                        var checkbox = document.createElement('input');

                        checkbox.type = 'checkbox';
                        checkbox.classList.add('pagamento-checkbox');
                        checkbox.checked = item.pagamento === 1;
                        checkbox.setAttribute('data-id', item.identificador);
                        checkbox.setAttribute('data-origem', item.origem);

                        checkbox.addEventListener('change', function () {
                            if (checkbox.checked) {
                                row.classList.add('checked');
                            } else {
                                row.classList.remove('checked');
                            }
                        });
                        cellCheckbox.appendChild(checkbox);

                        // Verificar a origem e preencher os dados de acordo
                        if (item.origem === 'funcao_imagem') {
                            cellNomeImagem.textContent = item.imagem_nome;
                            cellFuncao.textContent = item.nome_funcao;
                            cellStatusFuncao.textContent = item.status;
                            cellValor.textContent = item.valor;
                            cellData.textContent = item.data_pagamento;

                            totalValor += parseFloat(item.valor) || 0;
                        } else if (item.origem === 'acompanhamento') {
                            cellNomeImagem.textContent = item.imagem_nome;
                            cellFuncao.textContent = 'Acompanhamento';
                            cellStatusFuncao.textContent = 'Finalizado';
                            cellValor.textContent = item.valor;
                            cellData.textContent = item.data_pagamento;

                            totalValor += parseFloat(item.valor) || 0;
                        } else if (item.origem === 'animacao') {
                            cellNomeImagem.textContent = item.imagem_nome;
                            cellFuncao.textContent = 'Animação';
                            cellStatusFuncao.textContent = item.status;
                            cellValor.textContent = item.valor;
                            cellData.textContent = item.data_pagamento;
                        }

                        row.appendChild(cellNomeImagem);
                        row.appendChild(cellStatusFuncao);
                        row.appendChild(cellFuncao);
                        row.appendChild(cellValor);
                        row.appendChild(cellCheckbox);
                        row.appendChild(cellData);

                        tabela.appendChild(row);

                        if (checkbox.checked) {
                            row.classList.add('checked');
                        }

                        document.querySelectorAll('.tipo-imagem input[type="checkbox"]').forEach(funcaoCheckbox => {
                            if (funcaoCheckbox.name === item.nome_funcao) {
                                funcaoCheckbox.checked = true;
                            }
                        });
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
        var checkboxes = Array.from(document.querySelectorAll('.pagamento-checkbox')).filter(checkbox => {
            return checkbox.closest('tr').offsetParent !== null; // Checa se a linha está visível
        });

        var todosMarcados = checkboxes.every(checkbox => checkbox.checked);

        checkboxes.forEach(function (checkbox) {
            checkbox.checked = !todosMarcados; // Marca ou desmarca baseado no estado atual
            var row = checkbox.closest('tr');
            if (checkbox.checked) {
                row.classList.add('checked');
            } else {
                row.classList.remove('checked');
            }
        });
    });

    document.getElementById('confirmar-pagamento').addEventListener('click', function () {
        var checkboxes = document.querySelectorAll('.pagamento-checkbox:checked');
        var ids = Array.from(checkboxes).map(cb => ({
            id: cb.getAttribute('data-id'),
            origem: cb.getAttribute('data-origem') // Coletando o atributo origem
        }));

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
        var ids = Array.from(checkboxes).map(cb => ({
            id: cb.getAttribute('data-id'),
            origem: cb.getAttribute('data-origem') // Coletando o atributo origem
        }));

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
}


function filtrarTabela() {
    const tabela = document.querySelector('#tabela-faturamento tbody');
    const linhas = tabela.getElementsByTagName('tr');

    // Obter todas as checkboxes marcadas
    const checkboxes = document.querySelectorAll('.tipo-imagem input[type="checkbox"]:checked');
    const funcoesSelecionadas = Array.from(checkboxes).map(checkbox => checkbox.name);

    for (let i = 0; i < linhas.length; i++) {
        const linha = linhas[i];
        const funcaoCell = linha.cells[2];

        if (funcaoCell) {
            const funcaoText = funcaoCell.textContent || funcaoCell.innerText;
            if (funcoesSelecionadas.length === 0 || funcoesSelecionadas.includes(funcaoText)) {
                linha.style.display = "";
            } else {
                linha.style.display = "none";
            }
        }
    }

    contarLinhasTabela();
}

// Função para converter números para texto
document.getElementById('generate-adendo').addEventListener('click', function () {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({
        orientation: 'landscape',
    });

    const colaborador = document.getElementById('colaborador').options[document.getElementById('colaborador').selectedIndex].text;
    const mesNome = document.getElementById('mes').options[document.getElementById('mes').selectedIndex].text; // Nome do mês
    const ano = new Date().getFullYear(); // Ano atual
    let currentY = 20;

    const title = `Relatório mensal de ${colaborador}, ${mesNome} de ${ano}`;
    const valorTotal = "Valor total: ";
    const quantidadeTarefas = "Quantidade de tarefas: ";

    const totalValorElement = document.getElementById('totalValor');
    const totalValor = totalValorElement ? parseFloat(totalValorElement.innerText.replace('R$ ', '').replace('.', '').replace(',', '.')) : 0; // Converter para float
    const totalValorExtenso = `${numeroPorExtenso(totalValor)} reais`; // Adiciona "reais" ao final
    const quantidadeTarefasValue = document.querySelectorAll('#tabela-faturamento tbody tr').length;

    const imgPath = '../assets/logo.jpg';

    // Mapeamento dos meses para número (Janeiro = 1, etc.)
    const mesesMap = {
        'Janeiro': 1,
        'Fevereiro': 2,
        'Março': 3,
        'Abril': 4,
        'Maio': 5,
        'Junho': 6,
        'Julho': 7,
        'Agosto': 8,
        'Setembro': 9,
        'Outubro': 10,
        'Novembro': 11,
        'Dezembro': 12
    };

    const mes = mesesMap[mesNome]; // Converte o nome do mês para seu número correspondente

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
                doc.text(`${valorTotal} R$ ${totalValor.toFixed(2).replace('.', ',')} (${totalValorExtenso})`, 14, currentY);
                currentY += 10;

                doc.text(`${quantidadeTarefas} ${quantidadeTarefasValue}`, 14, currentY);
                currentY += 20;

                const table = document.getElementById('tabela-faturamento');
                const selectedColumnIndexes = [0, 1, 2, 3]; // Colunas específicas que deseja incluir, inclusive a 5 que é data_pagamento
                const headers = [];
                const rows = [];

                // Adiciona apenas os cabeçalhos das colunas selecionadas
                table.querySelectorAll('thead tr th').forEach((header, index) => {
                    if (selectedColumnIndexes.includes(index)) {
                        headers.push(header.innerText);
                    }
                });

                // Adiciona apenas os dados das colunas selecionadas, se a data_pagamento (coluna 5) for do mês e ano atuais
                table.querySelectorAll('tbody tr').forEach(row => {
                    const rowData = [];
                    const dataPagamento = row.querySelectorAll('td')[5].innerText;

                    // Converter a data do formato "2024-10-05" para um objeto Date
                    const [anoPagamento, mesPagamento, diaPagamento] = dataPagamento.split('-');
                    const dataPagamentoObj = new Date(anoPagamento, mesPagamento - 1, diaPagamento); // Meses no JS são indexados a partir de 0

                    // Verificar se o mês e o ano da data_pagamento correspondem ao mês e ano atuais
                    if (parseInt(mesPagamento) === mes && parseInt(anoPagamento) === ano) {
                        row.querySelectorAll('td').forEach((cell, index) => {
                            if (selectedColumnIndexes.includes(index)) {
                                rowData.push(cell.innerText);
                            }
                        });
                        rows.push(rowData); // Adiciona apenas linhas que correspondem ao mês e ano
                    }
                });

                if (rows.length > 0) {
                    // Gera a tabela no PDF
                    doc.autoTable({
                        head: [headers],
                        body: rows,
                        startY: currentY
                    });

                    doc.save(`Relatório_${colaborador}_${mesNome}_${ano}.pdf`);
                } else {
                    alert("Nenhum dado disponível para o mês selecionado.");
                }
            };
            reader.readAsDataURL(blob);
        })
        .catch(error => console.error('Erro ao carregar a imagem:', error));
});

document.getElementById('generate-lista').addEventListener('click', function () {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({
        orientation: 'landscape',
    });

    const colaborador = document.getElementById('colaborador').options[document.getElementById('colaborador').selectedIndex].text;
    const mesNome = document.getElementById('mes').options[document.getElementById('mes').selectedIndex].text; // Nome do mês
    const ano = new Date().getFullYear(); // Ano atual
    let currentY = 20;

    const title = `Relatório completo de ${colaborador}, ${mesNome} de ${ano}`;
    const valorTotal = "Valor total: ";
    const quantidadeTarefas = "Quantidade de tarefas: ";

    const totalValorElement = document.getElementById('totalValor');
    const totalValor = totalValorElement ? parseFloat(totalValorElement.innerText.replace('R$ ', '').replace('.', '').replace(',', '.')) : 0; // Converter para float
    const totalValorExtenso = `${numeroPorExtenso(totalValor)} reais`; // Adiciona "reais" ao final
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
                doc.text(`${valorTotal} R$ ${totalValor.toFixed(2).replace('.', ',')} (${totalValorExtenso})`, 14, currentY);
                currentY += 10;

                doc.text(`${quantidadeTarefas} ${quantidadeTarefasValue}`, 14, currentY);
                currentY += 20;

                const table = document.getElementById('tabela-faturamento');
                const selectedColumnIndexes = [0, 1, 2, 3]; // Colunas específicas que deseja incluir (incluindo a coluna data_pagamento)
                const headers = [];
                const rows = [];

                // Adiciona apenas os cabeçalhos das colunas selecionadas
                table.querySelectorAll('thead tr th').forEach((header, index) => {
                    if (selectedColumnIndexes.includes(index)) {
                        headers.push(header.innerText);
                    }
                });

                // Adiciona todos os dados das colunas selecionadas, sem a verificação de data_pagamento
                table.querySelectorAll('tbody tr').forEach(row => {
                    const rowData = [];
                    row.querySelectorAll('td').forEach((cell, index) => {
                        if (selectedColumnIndexes.includes(index)) {
                            rowData.push(cell.innerText);
                        }
                    });
                    rows.push(rowData); // Adiciona todos os dados, sem filtro de data_pagamento
                });

                if (rows.length > 0) {
                    // Gera a tabela no PDF
                    doc.autoTable({
                        head: [headers],
                        body: rows,
                        startY: currentY
                    });

                    doc.save(`Relatório_Completo_${colaborador}_${mesNome}_${ano}.pdf`);
                } else {
                    alert("Nenhum dado disponível para gerar a lista.");
                }
            };
            reader.readAsDataURL(blob);
        })
        .catch(error => console.error('Erro ao carregar a imagem:', error));
});


// Função para converter números para texto
function numeroPorExtenso(num) {
    const unidades = [
        '', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove',
        'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis',
        'dezessete', 'dezoito', 'dezenove'
    ];
    const dezenas = [
        '', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta',
        'setenta', 'oitenta', 'noventa'
    ];
    const centenas = [
        '', 'cem', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos',
        'seiscentos', 'setecentos', 'oitocentos', 'novecentos'
    ];

    if (num === 0) return 'zero';

    let resultado = '';

    // Tratando milhares
    if (num >= 1000) {
        let milhar = Math.floor(num / 1000);
        resultado += milhar === 1 ? 'mil ' : `${unidades[milhar]} mil `;
        num %= 1000;
    }

    // Tratando centenas
    if (num >= 100) {
        let centena = Math.floor(num / 100);
        resultado += `${centenas[centena]} `;
        num %= 100;
    }

    // Tratando dezenas
    if (num >= 20) {
        let dezena = Math.floor(num / 10);
        resultado += `${dezenas[dezena]} `;
        num %= 10;
    }

    // Tratando unidades
    if (num > 0) {
        if (resultado.trim() !== '') {
            resultado += 'e '; // Adiciona "e" se já houver dezenas ou centenas
        }
        resultado += `${unidades[num]} `;
    }

    return resultado.trim(); // Remove espaços em branco no início e no fim
}


function exportToExcel() {
    // Seleciona a tabela HTML
    var tabela = document.getElementById('tabela-faturamento');

    // Converte a tabela para uma planilha usando SheetJS
    var planilha = XLSX.utils.table_to_sheet(tabela);

    // Cria um novo workbook e adiciona a planilha
    var wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, planilha, "Dados");

    // Pega as informações do colaborador, mês e ano
    const colaborador = document.getElementById('colaborador').options[document.getElementById('colaborador').selectedIndex].text;
    const mes = document.getElementById('mes').options[document.getElementById('mes').selectedIndex].text;
    const ano = new Date().getFullYear();

    // Define o nome do arquivo
    const nomeArquivo = `Relatório_${colaborador}_${mes}_${ano}.xlsx`;

    // Gera o arquivo Excel e faz o download com o nome personalizado
    XLSX.writeFile(wb, nomeArquivo);
}




document.addEventListener("DOMContentLoaded", function () {

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