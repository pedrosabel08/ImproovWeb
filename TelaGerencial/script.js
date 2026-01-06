window.addEventListener('DOMContentLoaded', () => {
    const dataAtual = new Date();
    const mesAtual = dataAtual.getMonth() + 1; // Janeiro = 0, então soma 1
    const anoAtual = dataAtual.getFullYear();

    // Para o primeiro select (valores "01", "02", ...)
    const selectMes = document.getElementById('mes');
    if (selectMes) {
        const valorMes = mesAtual.toString().padStart(2, '0'); // Ex: 03
        selectMes.value = valorMes;
    }

    const selectAno = document.getElementById('ano');
    if (selectAno) {
        selectAno.value = anoAtual.toString();
    }

    // Para o segundo select (valores 1, 2, ...)
    const selectMesFuncao = document.getElementById('mesFuncao');
    if (selectMesFuncao) {
        selectMesFuncao.value = mesAtual.toString(); // Ex: 3
    }

    const selectAnoFuncao = document.getElementById('anoFuncao');
    if (selectAnoFuncao) {
        selectAnoFuncao.value = anoAtual.toString();
    }

    const selectMesAtual = document.getElementById('mes_atual');
    if (selectMesAtual) {
        selectMesAtual.value = mesAtual.toString(); // Ex: 3
    }
});

function formatarData(data) {
    const partes = data.split("-");
    const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
    return dataFormatada;
}


function buscarDados() {
    const mes = document.getElementById('mes').value;
    const ano = (document.getElementById('ano')?.value) || new Date().getFullYear();
    const nomeMeses = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];

    fetch('buscar_producao.php?mes=' + mes + '&ano=' + encodeURIComponent(ano))
        .then(res => res.json())
        .then(dados => {
            const tabela = document.querySelector('#tabelaProducao tbody');
            tabela.innerHTML = ''; // limpa

            dados.forEach(linha => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
            <td>${linha.nome_colaborador}</td>
            <td>${linha.nome_funcao}</td>
            <td>${linha.quantidade}</td>
            <td>${linha.mes_anterior}</td>
            <td>${linha.recorde_producao}</td>
          `;
                tabela.appendChild(tr);
            });
        })
        .catch(error => {
            console.error('Erro ao buscar dados:', error);
        });
}

// Tabela de valores por função
const valoresPorFuncao = {
    "caderno": 50,
    "filtro de assets": 20,
    "alteração": 0,
    "composição": 50,
    "modelagem": 50,
    "finalização": 350,
    "pré-finalização": 180,
    "pós-produção": 60
};


function filtrarPorTipo() {
    const tipo = document.getElementById("tipo").value;

    if (tipo === "mes_tipo") {
        buscarDadosFuncao(); // Já implementado para buscar por mês
    } else if (tipo === "dia_tipo") {
        buscarDadosPorDiaAnterior();
    } else if (tipo === "semana_tipo") {
        buscarDadosPorSemana();
    }
}

function buscarDadosPorDiaAnterior() {
    const dataAtual = new Date();
    const diaAnterior = new Date(dataAtual);
    diaAnterior.setDate(dataAtual.getDate() - 1);

    const dia = diaAnterior.getDate().toString().padStart(2, '0');
    const mes = (diaAnterior.getMonth() + 1).toString().padStart(2, '0');
    const ano = diaAnterior.getFullYear();

    document.getElementById("mesSelecionadoFuncao").innerText = `do dia ${dia}/${mes}/${ano}`; // Atualiza o mês selecionado
    document.getElementById("labelMesFuncao").style.display = "none";
    document.getElementById("mesFuncao").style.display = "none";
    const labelAnoFuncao = document.getElementById("labelAnoFuncao");
    const anoFuncao = document.getElementById("anoFuncao");
    if (labelAnoFuncao) labelAnoFuncao.style.display = "none";
    if (anoFuncao) anoFuncao.style.display = "none";

    fetch(`buscar_producao_funcao.php?data=${ano}-${mes}-${dia}`)
        .then(res => res.json())
        .then(data => {
            const tabela = document.querySelector("#tabelaFuncao tbody");
            tabela.innerHTML = ''; // Limpa a tabela

            let estimativaTotal = 0;

            data.forEach(linha => {
                const valorUnitario = valoresPorFuncao[linha.nome_funcao.toLowerCase()] || 0;
                const estimativa = linha.quantidade * valorUnitario;

                const tr = document.createElement("tr");
                tr.innerHTML = `
                    <td>${linha.nome_funcao}</td>
                    <td>${linha.quantidade}</td>
                    <td>R$ ${estimativa.toFixed(2).replace('.', ',')}</td>
                `;
                tabela.appendChild(tr);

                estimativaTotal += estimativa;
            });

            document.getElementById("valorTotal").innerHTML = `<strong>R$ ${estimativaTotal.toFixed(2).replace('.', ',')}</strong>`;
        })
        .catch(error => {
            console.error("Erro ao buscar dados do dia anterior:", error);
        });
}

function buscarDadosPorSemana() {
    const dataAtual = new Date();
    const diaSemana = dataAtual.getDay(); // 0 = Domingo, 1 = Segunda, ..., 6 = Sábado
    const inicioSemana = new Date(dataAtual);
    inicioSemana.setDate(dataAtual.getDate() - (diaSemana === 0 ? 6 : diaSemana - 1)); // Segunda-feira
    const fimSemana = new Date(inicioSemana);
    fimSemana.setDate(inicioSemana.getDate() + 6); // Domingo

    const inicio = `${inicioSemana.getFullYear()}-${(inicioSemana.getMonth() + 1).toString().padStart(2, '0')}-${inicioSemana.getDate().toString().padStart(2, '0')}`;
    const fim = `${fimSemana.getFullYear()}-${(fimSemana.getMonth() + 1).toString().padStart(2, '0')}-${fimSemana.getDate().toString().padStart(2, '0')}`;

    document.getElementById("mesSelecionadoFuncao").innerText = `de ${formatarData(inicio)} até ${formatarData(fim)}`; // Atualiza o mês selecionado
    document.getElementById("labelMesFuncao").style.display = "none";
    document.getElementById("mesFuncao").style.display = "none";
    const labelAnoFuncao = document.getElementById("labelAnoFuncao");
    const anoFuncao = document.getElementById("anoFuncao");
    if (labelAnoFuncao) labelAnoFuncao.style.display = "none";
    if (anoFuncao) anoFuncao.style.display = "none";


    fetch(`buscar_producao_funcao.php?inicio=${inicio}&fim=${fim}`)
        .then(res => res.json())
        .then(data => {
            const tabela = document.querySelector("#tabelaFuncao tbody");
            tabela.innerHTML = ''; // Limpa a tabela

            let estimativaTotal = 0;

            data.forEach(linha => {
                const valorUnitario = valoresPorFuncao[linha.nome_funcao.toLowerCase()] || 0;
                const estimativa = linha.quantidade * valorUnitario;

                const tr = document.createElement("tr");
                tr.innerHTML = `
                    <td>${linha.nome_funcao}</td>
                    <td>${linha.quantidade}</td>
                    <td>R$ ${estimativa.toFixed(2).replace('.', ',')}</td>
                `;
                tabela.appendChild(tr);

                estimativaTotal += estimativa;
            });

            document.getElementById("valorTotal").innerHTML = `<strong>R$ ${estimativaTotal.toFixed(2).replace('.', ',')}</strong>`;
        })
        .catch(error => {
            console.error("Erro ao buscar dados da semana:", error);
        });
}

function buscarDadosFuncao() {
    const mes = document.getElementById('mesFuncao').value;
    const ano = (document.getElementById('anoFuncao')?.value) || new Date().getFullYear();
    const nomeMeses = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
    document.getElementById("mesSelecionadoFuncao").innerText = `mês: ${nomeMeses[parseInt(mes) - 1]}`;

    document.getElementById("labelMesFuncao").style.display = "flex";
    document.getElementById("mesFuncao").style.display = "flex";
    const labelAnoFuncao = document.getElementById("labelAnoFuncao");
    const anoFuncao = document.getElementById("anoFuncao");
    if (labelAnoFuncao) labelAnoFuncao.style.display = "flex";
    if (anoFuncao) anoFuncao.style.display = "flex";

    fetch(`buscar_producao_funcao.php?mes=${mes}&ano=${encodeURIComponent(ano)}`)
        .then(res => res.json())
        .then(data => {
            const tabela = document.querySelector("#tabelaFuncao tbody");
            tabela.innerHTML = ''; // limpa

            let totalGeral = 0;
            let estimativaTotal = 0;

            data.forEach(linha => {
                const valorUnitario = valoresPorFuncao[linha.nome_funcao.toLowerCase()] || 0; // Valor por função
                const estimativa = linha.quantidade * valorUnitario; // Estimativa de valor

                const tr = document.createElement("tr");
                tr.innerHTML = `
            <td>${linha.nome_funcao}</td>
            <td>${linha.quantidade - linha.pagas}</td>
                        <td>${linha.mes_anterior ?? 0}</td>
                        <td>${linha.recorde_producao ?? 0}</td>
          `;
                tabela.appendChild(tr);

                estimativaTotal += estimativa;
            });

            // document.getElementById("valorTotal").innerHTML = `<strong>R$ ${estimativaTotal.toFixed(2).replace('.', ',')}</strong>`;
        })
        .catch(error => {
            console.error("Erro ao buscar dados:", error);
        });
}
window.onload = function () {
    buscarDados();
    buscarDadosFuncao();
    buscarEntregasMes();
};

// Gerar relatório: abre nova janela com cópia exata das tabelas atuais para visualização/impressão
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('gerar-relatorio');
    if (btn) btn.addEventListener('click', gerarRelatorio);
});

function coletarTabelaHtml(tableSelector) {
    const table = document.querySelector(tableSelector);
    if (!table) return '';
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
    const rows = Array.from(table.querySelectorAll('tbody tr')).map(tr =>
        Array.from(tr.querySelectorAll('td')).map(td => td.textContent.trim())
    );

    let html = '<table border="1" cellspacing="0" cellpadding="6" style="border-collapse:collapse;width:100%;">';
    html += '<thead><tr>' + headers.map(h => `<th style="background:#eee;text-align:left;">${h}</th>`).join('') + '</tr></thead>';
    html += '<tbody>' + rows.map(r => '<tr>' + r.map(c => `<td>${c}</td>`).join('') + '</tr>').join('') + '</tbody>';
    html += '</table>';
    return html;
}

function gerarRelatorio() {
    const mesSelect = document.getElementById('mes');
    const mes = mesSelect ? mesSelect.options[mesSelect.selectedIndex].text : '';
    const anoSelect = document.getElementById('ano');
    const ano = anoSelect ? anoSelect.value : '';
    const now = new Date();
    const header = `<h2>Relatório - Tela Gerencial</h2><p>Mês/ano selecionado: <strong>${mes}${ano ? '/' + ano : ''}</strong> — gerado em ${now.toLocaleString()}</p>`;

    const tabelaProducaoHtml = coletarTabelaHtml('#tabelaProducao');
    const tabelaFuncaoHtml = coletarTabelaHtml('#tabelaFuncao');
    const tabelaEntregasHtml = coletarTabelaHtml('#tabelaEntregas');

    const safeFileMonth = (mes || '').replace(/\s+/g, '_');
    const content = `
            <html>
            <head>
                <meta charset="utf-8">
                <title>Relatório - Tela Gerencial</title>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
                <style>
                    body{font-family:'Inter', Arial,Helvetica,sans-serif;margin:20px;color:#111}
                    h2{margin-bottom:6px}
                    table{margin-bottom:18px;border-collapse:collapse;width:100%}
                    th{background:#eee;text-align:left;padding:6px}
                    td{padding:6px}
                </style>
            </head>
            <body>
                <div id="report-root">
                    ${header}
                    <h3>Produção por Colaborador</h3>
                    ${tabelaProducaoHtml || '<p>Sem dados</p>'}
                    <br>
                    <h3>Produção por Função</h3>
                    ${tabelaFuncaoHtml || '<p>Sem dados</p>'}
                    <h3>Imagens entregues por mês</h3>
                    ${tabelaEntregasHtml || '<p>Sem dados</p>'}
                    <p style="margin-top:20px;font-size:12px;color:#666">Gerado pelo sistema</p>
                </div>

                <!-- libs via CDN -->
                <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
                <script>
                    // Aguarda o carregamento visual e das libs
                    (function waitAndExport() {
                        if (!(window.jspdf && window.html2canvas)) {
                            return setTimeout(waitAndExport, 200);
                        }
                        const { jsPDF } = window.jspdf;
                        const doc = new jsPDF({ unit: 'pt', format: 'a4' });
                        const element = document.getElementById('report-root');
                        // Ajustes de margem/escala podem ser alterados conforme necessário
                        doc.html(element, {
                            callback: function (doc) {
                                const fileName = 'Relatorio_Tela_Gerencial_${safeFileMonth}_' + new Date().getFullYear() + '.pdf';
                                doc.save(fileName);
                            },
                            x: 20,
                            y: 20,
                            html2canvas: { scale: 1.2 }
                        });
                    })();
                </script>
            </body>
            </html>
        `;

    const win = window.open('', '_blank');
    if (!win) {
        alert('Não foi possível abrir a janela do relatório (bloqueador de popups?).');
        return;
    }
    win.document.open();
    win.document.write(content);
    win.document.close();
}

/**
 * Busca entregas agrupadas por status para o mês selecionado.
 * Se nenhum mês for selecionado, usa o mês atual.
 */
function buscarEntregasMes() {
    const selectMes = document.getElementById('mes');
    const mes = selectMes ? parseInt(selectMes.value, 10) : (new Date().getMonth() + 1);
    const selectAno = document.getElementById('ano');
    const ano = selectAno ? parseInt(selectAno.value, 10) : new Date().getFullYear();

    fetch(`buscar_entregas_mes.php?mes=${mes}&ano=${ano}`)
        .then(res => res.json())
        .then(data => {
            const tabela = document.querySelector('#tabelaEntregas tbody');
            tabela.innerHTML = '';

            // Atualiza cabeçalho da tabela para refletir o breakdown por status
            const thead = document.querySelector('#tabelaEntregas thead tr');
            if (thead) {
                thead.innerHTML = `
                    <th>Status</th>
                    <th>Quantidade de imagens entregues</th>
                    <th>Quantidade de plantas entregues</th>
                `;
            }

            if (!Array.isArray(data)) return;

            data.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.nome_status}</td>
                    <td>${row.quantidade}</td>
                    <td>${row.quantidade_ph}</td>
                `;
                tabela.appendChild(tr);
            });
        })
        .catch(err => console.error('Erro ao buscar entregas por mês:', err));
}
