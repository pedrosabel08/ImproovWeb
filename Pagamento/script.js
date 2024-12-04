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
    document.getElementById('ano').addEventListener('change', carregarDadosColab);

    function carregarDadosColab() {
        var colaboradorId = document.getElementById('colaborador').value;
        var mesId = document.getElementById('mes').value;
        var anoId = document.getElementById('ano').value;

        const confirmarPagamentoButton = document.getElementById('confirmar-pagamento');
        confirmarPagamentoButton.disabled = true;

        if (colaboradorId) {
            var url = 'getColaborador.php?colaborador_id=' + encodeURIComponent(colaboradorId);

            if (mesId) {
                url += '&mes_id=' + encodeURIComponent(mesId);
            }
            if (anoId) {
                url += '&ano=' + encodeURIComponent(anoId)
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {

                    var infoColaborador = document.getElementById('info-colaborador');
                    var colaborador = data.dadosColaborador;
                    if (colaborador) {
                        infoColaborador.innerHTML = `
                            <p id='nomeColaborador'>${colaborador.nome_usuario}</p>
                            <p id='nomeEmpresarial'>${colaborador.nome_empresarial}</p>
                            <p id='cnpjColaborador'>${colaborador.cnpj}</p>
                            <p id='enderecoColaborador'>${colaborador.rua}, ${colaborador.numero}, ${colaborador.bairro}</p>
                            <p id='estadoCivil'>${colaborador.estado_civil}</p>
                            <p id='cpfColaborador'>${colaborador.cpf}</p>
                            <p id='enderecoCNPJ'>${colaborador.rua_cnpj} , ${colaborador.numero_cnpj} , ${colaborador.bairro_cnpj}</p>
                            <p id='cep'>${colaborador.cep}</p>
                            <p id='cepCNPJ'>${colaborador.cep_cnpj}</p>
                        `;
                    }

                    // Atualiza a tabela
                    var tabela = document.querySelector('#tabela-faturamento tbody');
                    tabela.innerHTML = '';
                    let totalValor = 0;

                    document.querySelectorAll('.tipo-imagem input[type="checkbox"]').forEach(checkbox => {
                        checkbox.checked = false;
                    });

                    data.funcoes.forEach(function (item) {
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
                        checkbox.setAttribute('funcao', item.funcao_id)

                        checkbox.addEventListener('change', function () {
                            if (checkbox.checked) {
                                row.classList.add('checked');
                            } else {
                                row.classList.remove('checked');
                            }
                            verificarValoresMaiorQueZero();

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

                    verificarValoresMaiorQueZero();

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
            origem: cb.getAttribute('data-origem'),
            funcao_id: cb.getAttribute('funcao')
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

function verificarValoresMaiorQueZero() {
    let allGreaterThanZero = true;
    document.querySelectorAll('#tabela-faturamento tbody tr').forEach(row => {
        let valorCell = row.querySelector('td:nth-child(4)'); // Assume que o valor está na 4ª coluna
        let valor = parseFloat(valorCell.textContent.replace('R$', '').replace(',', '.'));

        if (isNaN(valor) || valor <= 0) {
            allGreaterThanZero = false;
        }
    });

    // Habilita ou desabilita o botão "Confirmar Pagamento"
    const confirmarPagamentoButton = document.getElementById('confirmar-pagamento');
    if (allGreaterThanZero) {
        confirmarPagamentoButton.disabled = false;
    } else {
        confirmarPagamentoButton.disabled = true;
    }
}

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
    const nomeColaborador = document.getElementById("nomeColaborador").textContent.trim();
    const cnpjColaborador = document.getElementById("cnpjColaborador").textContent.trim();
    const enderecoColaborador = document.getElementById("enderecoColaborador").textContent.trim();
    const cpfColaborador = document.getElementById("cpfColaborador").textContent.trim();
    const estadoCivil = document.getElementById("estadoCivil").textContent.trim();
    const enderecoCNPJ = document.getElementById("enderecoCNPJ").textContent.trim();
    const nomeEmpresarial = document.getElementById("nomeEmpresarial").textContent.trim();
    const cep = document.getElementById("cep").textContent.trim();
    const cepCNPJ = document.getElementById("cepCNPJ").textContent.trim();


    // const totalValorElement = document.getElementById('totalValor');
    // const totalValor = totalValorElement ? parseFloat(totalValorElement.innerText.replace('R$ ', '').replace('.', '').replace(',', '.')) : 0;
    // const totalValorExtenso = `${numeroPorExtenso(totalValor)} reais`;

    const today = new Date();
    const day = String(today.getDate()).padStart(2, '0');

    // Obtém o número do mês (0 = Janeiro, 11 = Dezembro)
    const currentMonthIndex = today.getMonth();

    // Calcula o índice do mês anterior
    const previousMonthIndex = currentMonthIndex === 0 ? 11 : currentMonthIndex - 1;

    // Lista dos nomes dos meses
    const monthNames = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];

    // Nome do mês atual e anterior
    const currentMonthName = monthNames[currentMonthIndex].toUpperCase();
    const previousMonthName = monthNames[previousMonthIndex].toUpperCase();

    const year = today.getFullYear();

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.setFont("helvetica");
    doc.setFontSize(12);

    let y = 30; // Posição inicial
    const maxHeight = 260; // Limite para a altura

    // Função para adicionar texto com verificação de página
    function addTextWithPageCheck(text, margin = 0, boldWords = []) {
        const lines = doc.splitTextToSize(text, 170); // Quebra o texto em várias linhas
        lines.forEach((line, lineIndex) => {
            if (y + 8 > maxHeight) {
                doc.addPage(); // Adiciona nova página se o texto exceder o limite
                y = 20;
            }

            let x = 20; // Definindo a margem inicial
            const words = line.split(/(\s+)/); // Divide as palavras, mantendo os espaços
            const lineWidth = doc.getTextWidth(line);

            // Calcula o espaço extra necessário para justificar, excluindo a última linha
            const justify = lineIndex < lines.length - 1 ? (170 - lineWidth) / (words.length - 1) : 0;

            words.forEach((word, wordIndex) => {
                const cleanWord = word.replace(/[.,/()-]/g, ""); // Remove pontuação para comparação

                // Verifica se a palavra está exatamente em `boldWords`
                if (
                    boldWords.some(
                        (boldWord) =>
                            boldWord.replace(/[.,/()-]/g, "").toLowerCase() === cleanWord.toLowerCase()
                    )
                ) {
                    doc.setFont(undefined, "bold"); // Define a fonte como negrito
                } else {
                    doc.setFont(undefined, "normal"); // Define a fonte como normal
                }

                doc.text(word.trim(), x, y); // Adiciona o texto na posição especificada
                x += doc.getTextWidth(word) + (wordIndex < words.length - 1 ? justify : 0); // Atualiza a posição horizontal com espaçamento justificado
            });

            y += 8; // Avança para a próxima linha
        });

        y += margin; // Ajusta o espaço após o texto
    }


    doc.setFont("helvetica", "bold"); // Define a fonte para negrito
    doc.setFontSize(16); // Aumenta o tamanho da fonte

    // Calcula a posição x para centralizar o texto
    const pageWidth = doc.internal.pageSize.getWidth();
    const text = `ADENDO CONTRATUAL - ${previousMonthName} ${year}`;
    const textWidth = doc.getTextWidth(text);
    const x = (pageWidth - textWidth) / 2; // Centraliza o texto

    doc.text(text, x, y); // Adiciona o texto na posição calculada
    y += 20; // Espaço após o título

    doc.setFont("helvetica", "normal");
    doc.setFontSize(12); // Tamanho padrão para o corpo do texto

    // Parte 1: Texto do contrato
    // Definição das variáveis de texto
    let text1 = "De um lado IMPROOV LTDA., CNPJ: 37.066.879/0001-84, com endereço/sede na RUA BAHIA, 988, SALA 304, BAIRRO DO SALTO, BLUMENAU, SC, CEP 89.031-001;Se seguir denominado simplesmente parte CONTRATANTE, neste ato representado por DIOGO JOSÉ POFFO, nacionalidade: brasileira, estado civil: divorciado, inscrito no CPF sob o nº. 036.698.519-17, residente e domiciliado na Avenida Senador Atílio Fontana, nº 2101 apt. 308 Edifício Caravelas, bairro Balneário Pereque – Porto Belo/SC – CEP 88210-000, doravante denominada parte CONTRATANTE.";

    let text2 = `De outro, ${nomeEmpresarial}, CNPJ: ${cnpjColaborador}, com endereço/sede na ${enderecoColaborador} , CEP: ${cep} ; se seguir denominado simplesmente parte CONTRATADA; neste ato representado por ${nomeEmpresarial}, brasileiro(a), ${estadoCivil}, inscrito(a) no CPF sob o nº. ${cpfColaborador} , residente e domiciliado na ${enderecoCNPJ} e CEP: ${cepCNPJ} doravante denominada parte CONTRATADA.`;

    let text3 = "Os denominados têm, entre si, justo e acertado, promover o TERMO ADITIVO, nos seguintes termos e condições.";

    let text4 = "DO OBJETO";
    let text5 = "Cláusula 1ª - O presente termo aditivo tem por escopo dar quitação aos valores devidos pelo CONTRATANTE  ao CONTRATADO  pela elaboração e desenvolvimento dos seguintes serviços que não eram parte inicial do contrato de prestação de serviços firmado em " + `${previousMonthName}:`;

    const nomeEmpresarialWords = nomeEmpresarial.split(" ");


    // Adicionando os textos ao PDF
    addTextWithPageCheck(text1, 10, ["IMPROOV", "LTDA", "DIOGO", "JOSÉ", "POFFO", "37.066.879/0001-84", "036.698.519-17", "CONTRATANTE", "CONTRATADO"]);
    addTextWithPageCheck(text2, 10, ["CONTRATADA", "CONTRATATO", ...nomeEmpresarialWords, cnpjColaborador, cpfColaborador, estadoCivil]);
    addTextWithPageCheck(text3, 10, ["TERMO", "ADITIVO"]);
    addTextWithPageCheck(text4, 0, ["DO OBJETO"]);
    addTextWithPageCheck(text5, 10, ["Cláusula", "1ª", "CONTRATANTE", "CONTRATADO", previousMonthName]);


    // Parte 2: Lista de tarefas/tabela
    const table = document.getElementById('tabela-faturamento');
    const selectedColumnIndexes = [0, 2, 3];
    const dataPagamentoColumnIndex = 5;
    const headers = [];
    const rows = [];
    let totalValor = 0; // Inicializa o total em 0
    let totalValorExtenso = "";

    table.querySelectorAll('thead tr th').forEach((header, index) => {
        if (selectedColumnIndexes.includes(index)) {
            headers.push(header.innerText);
        }
    });

    table.querySelectorAll('tbody tr').forEach(row => {
        const cells = row.querySelectorAll('td');
        const dataPagamento = cells[dataPagamentoColumnIndex]?.innerText.trim(); // Data de pagamento
        if (dataPagamento === '0000-00-00' && row.style.display !== 'none') {
            const rowData = [];
            cells.forEach((cell, index) => {
                if (selectedColumnIndexes.includes(index)) {
                    rowData.push(cell.innerText.trim());
                }
            });
            const valorColumnIndex = 3; // Substitua pelo índice da coluna com os valores
            const valor = parseFloat(cells[valorColumnIndex]?.innerText.trim().replace('R$ ', '').replace('.', '').replace(',', '.') || 0);
            totalValor += valor; // Soma o valor da linha ao total
            if (rowData.length) {
                rows.push(rowData);
            }
        }
    });

    totalValorExtenso = `${numeroPorExtenso(totalValor)} reais`;


    if (rows.length > 0) {
        doc.autoTable({
            head: [headers],
            body: rows,
            startY: y,
            theme: 'grid',
            headStyles: { fillColor: [0, 0, 0], textColor: [255, 255, 255] },
            bodyStyles: { fillColor: [255, 255, 255], textColor: [0, 0, 0] },
            margin: { top: 10, left: 20, right: 20 },
            styles: { fontSize: 10, cellPadding: 2 }
        });
        y = doc.lastAutoTable.finalY + 20; // Atualiza a posição Y após a tabela
    }

    // Parte 3: Segunda parte do contrato
    let text6 = `Cláusula 2ª - O CONTRATADO  declara que no dia ${day} de ${currentMonthName} de ${year}, recebeu do CONTRATANTE  o valor de R$ ${totalValor.toFixed(2)} (${totalValorExtenso}), pela entrega dos serviços acima referidos, e dá a mais ampla, geral e irrestrita quitação à dívida, renunciando seu direito de cobrança relativos a tais valores. `;

    let text7 = `E por estarem justas e perfeitamente acertadas, assinam o presente em 02 (duas) vias de igual teor e forma, vias na presença de 2 (duas) testemunhas.`;

    let text8 = `Porto Belo/SC, ${day} de ${currentMonthName} de ${year}.`;

    addTextWithPageCheck(text6, 10, ["Cláusula", "2ª", "CONTRATADO", "CONTRATANTE"]);
    addTextWithPageCheck(text7, 10);
    addTextWithPageCheck(text8, 10);

    function checkAndAddPageIfNeeded(doc, positionY) {
        if (positionY + 40 > maxHeight) { // Verifica se o espaço restante na página é suficiente
            doc.addPage(); // Se não for suficiente, adiciona uma nova página
            positionY = 20; // Reseta a posição Y para o topo da página
        }
        return positionY; // Retorna a posição Y ajustada
    }

    // Parte 4: Assinaturas
    y += 20; // Espaço antes das assinaturas

    // Assinatura da IMPROOV LTDA.
    y = checkAndAddPageIfNeeded(doc, y); // Verifica se há espaço para a assinatura
    const xEmpresa = 20; // Posição inicial para a IMPROOV LTDA.
    const xNovaColuna = 105; // Posição da nova coluna, à direita da IMPROOV LTDA.

    // Primeira assinatura: IMPROOV LTDA.
    doc.text("_________________________", xEmpresa, y);  // Linha para assinatura
    doc.text("IMPROOV LTDA.", xEmpresa, y + 8); // Nome da empresa
    doc.text("CNPJ: 37.066.879/0001-84", xEmpresa, y + 18); // CNPJ da empresa
    doc.text("DIOGO JOSÉ POFFO", xEmpresa, y + 28); // Nome do responsável
    doc.text("CPF: 036.698.519-17", xEmpresa, y + 38); // CPF do responsável

    // Nova coluna à direita da assinatura IMPROOV LTDA.
    y = checkAndAddPageIfNeeded(doc, y); // Verifica se há espaço para a assinatura
    doc.text("_________________________", xNovaColuna, y); // Linha de assinatura na nova coluna
    doc.text(`${nomeColaborador}`, xNovaColuna, y + 8); // Nome na nova coluna
    doc.text("CNPJ: " + cnpjColaborador, xNovaColuna, y + 18); // CNPJ do colaborador
    doc.text(nomeEmpresarial, xNovaColuna, y + 28); // Nome empresarial
    doc.text("CPF: " + cpfColaborador, xNovaColuna, y + 38); // CPF do colaborador

    y += 40; // Espaço após a assinatura de IMPROOV LTDA.

    y += 40;
    // Assinaturas das testemunhas (lado a lado)
    const xTestemunha1 = 20;    // Posição para a primeira testemunha
    const xTestemunha2 = 105;   // Posição para a segunda testemunha (ajustada para segunda coluna)

    // Testemunha 1
    y = checkAndAddPageIfNeeded(doc, y); // Verifica se há espaço para a assinatura
    doc.text("_________________________", xTestemunha1, y); // Linha de assinatura
    doc.text("Testemunha 1", xTestemunha1, y + 8); // Nome da testemunha 1
    doc.text("Nome completo:", xTestemunha1, y + 18); // Detalhes de testemunha 1
    doc.text("CPF:", xTestemunha1, y + 28); // CPF de testemunha 1

    // Testemunha 2 (na posição horizontal diferente, criando a coluna)
    y = checkAndAddPageIfNeeded(doc, y); // Verifica se há espaço para a assinatura
    doc.text("_________________________", xTestemunha2, y); // Linha de assinatura
    doc.text("Testemunha 2", xTestemunha2, y + 8); // Nome da testemunha 2
    doc.text("Nome completo:", xTestemunha2, y + 18); // Detalhes de testemunha 2
    doc.text("CPF:", xTestemunha2, y + 28); // CPF de testemunha 2

    // Gerar o PDF
    doc.save(`ADENDO_CONTRATUAL_${nomeColaborador}_${previousMonthName}_${year}.pdf`);
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
    // const valorTotal = "Valor total: ";
    const quantidadeTarefas = "Quantidade de tarefas: ";

    // const totalValorElement = document.getElementById('totalValor');
    // const totalValor = totalValorElement ? parseFloat(totalValorElement.innerText.replace('R$ ', '').replace('.', '').replace(',', '.')) : 0; // Converter para float
    // const totalValorExtenso = `${numeroPorExtenso(totalValor)} reais`; // Adiciona "reais" ao final

    const quantidadeTarefasValue = Array.from(document.querySelectorAll('#tabela-faturamento tbody tr'))
        .filter(row => row.style.display !== 'none').length;

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

                // doc.setFontSize(12);
                // doc.text(`${valorTotal} R$ ${totalValor.toFixed(2).replace('.', ',')} (${totalValorExtenso})`, 14, currentY);
                // currentY += 10;

                doc.text(`${quantidadeTarefas} ${quantidadeTarefasValue}`, 14, currentY);
                currentY += 20;

                const table = document.getElementById('tabela-faturamento');
                const selectedColumnIndexes = [0, 1, 2]; // Colunas específicas que deseja incluir (incluindo a coluna data_pagamento)
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
                    if (row.style.display !== 'none') { // Verifica se a linha está visível
                        const rowData = [];
                        row.querySelectorAll('td').forEach((cell, index) => {
                            if (selectedColumnIndexes.includes(index)) {
                                rowData.push(cell.innerText);
                            }
                        });
                        rows.push(rowData); // Adiciona apenas as linhas visíveis
                    }
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