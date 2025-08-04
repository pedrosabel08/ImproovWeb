let tabela;

document.addEventListener('DOMContentLoaded', () => {

    tabela = new Tabulator("#tabelaGestaoImagens", {
        ajaxURL: "getDados.php",
        ajaxResponse: function (url, params, response) {
            atualizarIndicadores(response.indicadores);

            const dados = response.dados;

            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);

            dados.forEach(item => {
                if (!item.nome_status_imagem || item.nome_status_imagem.trim() === "") {
                    item.nome_status_imagem = "Sem etapa";
                }

                if (item.prazo && item.prazo !== '0000-00-00') {
                    const partes = item.prazo.split('/');
                    if (partes.length === 3) {
                        const [dia, mes, ano] = partes;
                        item.prazo = `${ano}/${mes.padStart(2, '0')}/${dia.padStart(2, '0')}`;
                    }
                    const prazo = new Date(item.prazo);
                    item.situacao_prazo = (prazo < hoje) ? "Atrasada" : "OK";
                } else {
                    item.situacao_prazo = "N/A";
                }
                if (item.situacao === "DRV" || item.situacao === "RVW") {
                    item.situacao_prazo = "entregue";
                }
            });

            const etapasUnicas = [...new Set(dados.map(item => item.nome_status_imagem || "Sem etapa"))].sort();
            const etapasUnicasObj = Object.fromEntries(etapasUnicas.map(v => [v, v]));
            const statusUnicos = [...new Set(dados.map(item => item.situacao))].sort();
            const statusUnicosObj = Object.fromEntries(statusUnicos.map(v => [v, v]));

            tabela.getColumn("nome_status_imagem").updateDefinition({
                headerFilterParams: { values: etapasUnicasObj }
            });
            tabela.getColumn("situacao").updateDefinition({
                headerFilterParams: { values: statusUnicosObj }
            });

            return dados;
        },

        layout: "fitColumns",
        responsiveLayout: "collapse",
        pagination: "local",
        paginationSize: 100,
        placeholder: "Nenhuma imagem encontrada",
        pagination: false,

        groupBy: ["obra", "nome_status", "prazo"],
        groupToggleElement: "header",
        groupHeader: function (value, count, data, group) {
            const field = group.getField() || "custom";
            let headerText = `<strong>${value}</strong>`;
            let bgColor = "#f1f3f5";

            if (field === "obra") {
                headerText = `<strong>${value}</strong>`;
                bgColor = "#acacacff";
            }
            else if (field === "nome_status") {
                headerText = `<strong>${value}</strong>`;
                bgColor = "#cececeff";
            }
            else if (field === "prazo") {
                let dia, mes, ano;
                if (value && value !== '0000-00-00') {

                    const todasEntregues = data.every(item =>
                        item.situacao === "RVW" || item.situacao === "DRV"
                    );

                    if (value.includes('/')) {
                        [ano, mes, dia] = value.split('/');
                    } else if (value.includes('-')) {
                        [ano, mes, dia] = value.split('-');
                    }

                    const prazo = new Date(`${ano}-${mes}-${dia}`);
                    const hoje = new Date();
                    hoje.setHours(0, 0, 0, 0);

                    let textoPrazo = "";

                    if (todasEntregues) {
                        textoPrazo = "Entregue";
                    } else {
                        if (prazo >= hoje) {
                            let dias = 0;
                            let dt = new Date(hoje);
                            while (dt < prazo) {
                                const diaSemana = dt.getDay();
                                if (diaSemana !== 0 && diaSemana !== 6) dias++;
                                dt.setDate(dt.getDate() + 1);
                            }
                            textoPrazo = `${dias} dia${dias !== 1 ? 's' : ''} para entrega`;
                        } else {
                            let diasUteis = 0;
                            let dt = new Date(prazo);
                            dt.setDate(dt.getDate() + 1);
                            while (dt <= hoje) {
                                const diaSemana = dt.getDay();
                                if (diaSemana !== 0 && diaSemana !== 6) diasUteis++;
                                dt.setDate(dt.getDate() + 1);
                            }
                            textoPrazo = `${diasUteis} dia${diasUteis !== 1 ? 's' : ''} de atraso`;
                        }
                    }

                    headerText = `<strong>${dia.padStart(2, '0')}/${mes.padStart(2, '0')}/${ano}</strong> - ${textoPrazo}`;
                    bgColor = "#ddddddff";
                } else {
                    headerText = `<strong>N/A</strong>`;
                }
            }

            setTimeout(() => {
                const el = group.getElement();
                if (el) el.style.backgroundColor = bgColor;
            }, 0);

            return `${headerText} <span style="color: #000000ff;">(${count} ${count > 1 ? 'imagens' : 'imagem'})</span>`;
        },

        groupStartOpen: [true, true, false],
        columns: [
            {
                title: "Prazo Contratado",
                field: "prazo",
                sorter: "date",
                hozAlign: "center",
                headerFilter: true,
                formatter: function (cell) {
                    const valor = cell.getValue();
                    if (!valor || valor === '0000-00-00') return "N/A";
                    let data;
                    if (valor.includes('/')) {
                        const [ano, mes, dia] = valor.split('/');
                        data = new Date(Number(ano), Number(mes) - 1, Number(dia));
                    } else if (valor.includes('-')) {
                        const [ano, mes, dia] = valor.split('-');
                        data = new Date(Number(ano), Number(mes) - 1, Number(dia));
                    } else {
                        data = new Date(valor);
                    }
                    const dia = String(data.getDate()).padStart(2, '0');
                    const mes = String(data.getMonth() + 1).padStart(2, '0');
                    const ano = data.getFullYear();
                    return `${dia}/${mes}/${ano}`;
                }
            },
            {
                title: "Dias Úteis em Atraso",
                field: "dias_uteis_atraso",
                hozAlign: "center",
                headerSort: false,
                formatter: function (cell) {
                    const data = cell.getData();
                    if (data.situacao === "RVW" || data.situacao === "DRV" || !data.prazo || data.prazo === '0000-00-00') {
                        return "-";
                    }
                    let prazo;
                    if (data.prazo.includes('/')) {
                        const [dia, mes, ano] = data.prazo.split('/');
                        prazo = new Date(`${ano}-${mes}-${dia}`);
                    } else {
                        prazo = new Date(data.prazo);
                    }
                    const hoje = new Date();
                    hoje.setHours(0, 0, 0, 0);

                    if (prazo >= hoje) return "0 dias";

                    let diasUteis = 0;
                    let dt = new Date(prazo);
                    dt.setHours(0, 0, 0, 0);
                    dt.setDate(dt.getDate() + 1);

                    while (dt < hoje) {
                        const diaSemana = dt.getDay();
                        if (diaSemana !== 0 && diaSemana !== 6) diasUteis++;
                        dt.setDate(dt.getDate() + 1);
                    }
                    return diasUteis > 0 ? `${diasUteis} dias` : "0 dias";
                }
            },
            {
                title: "Data Recebimento",
                field: "recebimento_arquivos",
                sorter: "date",
                hozAlign: "center",
                headerFilter: true,
                formatter: function (cell) {
                    const valor = cell.getValue();
                    if (!valor || valor === '0000-00-00') return "N/A";
                    const data = new Date(valor);
                    const dia = String(data.getDate()).padStart(2, '0');
                    const mes = String(data.getMonth() + 1).padStart(2, '0');
                    const ano = data.getFullYear();
                    return `${dia}/${mes}/${ano}`;
                }
            },
            {
                title: "Nome da Imagem",
                field: "nome_imagem",
                headerFilter: true,
                widthGrow: 3,
                formatter: function (cell) {
                    const data = cell.getData();
                    return `<strong>${data.nome_imagem}</strong>`;
                }
            },
            {
                title: "Etapa",
                field: "nome_status_imagem",
                hozAlign: "center",
                headerFilter: "list",
                headerFilterParams: { values: {} },
                formatter: function (cell) {
                    const valor = cell.getValue();
                    return `<span class="tag ${valor}">${valor}</span>`;
                }
            },
            {
                title: "Status",
                field: "situacao",
                hozAlign: "center",
                headerFilter: "list",
                headerFilterParams: { values: {} },
                formatter: function (cell) {
                    const valor = cell.getValue();
                    return `<span class="tag ${valor}">${valor}</span>`;
                }
            },
            {
                title: "Situação",
                field: "situacao_prazo",
                hozAlign: "center",
                headerSort: false,
                headerFilter: 'list',
                headerFilterParams: {
                    values: {
                        "OK": "OK",
                        "Atrasada": "Atrasada",
                        "Entregue": "Entregue",
                    }
                },
                formatter: function (cell) {
                    const data = cell.getData();
                    let texto = cell.getValue();
                    let cor = "#d1e7dd";

                    if (texto === "entregue" || texto === "Entregue") {
                        texto = "Entregue";
                        cor = "#b9ffad";
                    } else if (!data.prazo) {
                        return `<span class="tag" style="background:#e9ecef; font-weight:600;">N/A</span>`;
                    } else {
                        const prazoStr = data.prazo;
                        const [dia, mes, ano] = prazoStr.split('/');
                        const prazo = new Date(`${ano}-${mes}-${dia}`);
                        const hoje = new Date();
                        hoje.setHours(0, 0, 0, 0);

                        texto = "OK";
                        cor = "#d1e7dd";

                        if (prazo < hoje) {
                            texto = "Atrasada";
                            cor = "#f8d7da";
                        }
                    }
                    return `<span class="tag" style="background:${cor}; font-weight:600;">${texto}</span>`;
                }
            }
        ]
    });

    // Funções auxiliares para evitar duplicação de código
    function applyFilterAndPreventScroll(filterField, filterValue, callback = null) {
        // Salva a posição de rolagem e desabilita a rolagem do body
        const scrollPos = window.scrollY;
        document.body.style.overflow = 'hidden';

        tabela.clearFilter();
        tabela.setFilter(filterField, "=", filterValue);

        // Aguarda a renderização e restaura a rolagem
        setTimeout(() => {
            if (callback) {
                callback();
            }
            window.scrollTo(0, scrollPos);
            document.body.style.overflow = '';
        }, 100);
    }

    document.querySelectorAll('[data-status]').forEach(el => {
        el.addEventListener('click', () => {
            const valor = el.getAttribute('data-status');
            applyFilterAndPreventScroll("situacao", valor);
        });
    });

    document.querySelectorAll('[data-situacao_prazo]').forEach(el => {
        el.addEventListener('click', () => {
            const valor = el.getAttribute('data-situacao_prazo');
            applyFilterAndPreventScroll("situacao_prazo", valor);
        });
    });

    document.querySelectorAll('[data-prazo]').forEach(el => {
        el.addEventListener('click', () => {
            const hoje = new Date();
            const dia = String(hoje.getDate()).padStart(2, '0');
            const mes = String(hoje.getMonth() + 1).padStart(2, '0');
            const ano = hoje.getFullYear();
            const hojeFormatado = `${ano}-${mes}-${dia}`;

            applyFilterAndPreventScroll("prazo", hojeFormatado, () => {
                tabela.getGroups().forEach(group => group.show());
            });
        });
    });

    document.querySelectorAll('[data-total]').forEach(el => {
        el.addEventListener('click', () => {
            const scrollPos = window.scrollY;
            document.body.style.overflow = 'hidden';

            tabela.clearFilter();
            tabela.groupStartOpen = true;

            setTimeout(() => {
                window.scrollTo(0, scrollPos);
                document.body.style.overflow = '';
            }, 100);
        });
    });
});

document.getElementById('btnRelatorio').addEventListener('click', function () {
    document.getElementById('modalRelatorio').style.display = 'block';
});

document.getElementById('gerarRelatorio').addEventListener('click', function () {
    const dataInicial = document.getElementById('dataInicial').value;
    const dataFinal = document.getElementById('dataFinal').value;

    // Filtra os dados da tabela pelo período
    const todosDados = tabela.getData();
    const filtrados = todosDados.filter(item => {
        if (!item.prazo || item.prazo === '0000-00-00') return false;
        const partes = item.prazo.split('-');
        if (partes.length === 3) {
            const prazo = new Date(item.prazo);
            const ini = new Date(dataInicial);
            const fim = new Date(dataFinal);
            return prazo >= ini && prazo <= fim;
        }
        return false;
    });

    tabela.groupStartOpen = true; // Abre todos os grupos

    // Gera o PDF
    gerarPDFRelatorio(filtrados);

    document.getElementById('modalRelatorio').style.display = 'none';
});

function gerarPDFRelatorio(dados) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    doc.setFontSize(14);
    doc.text("Relatório de Imagens", 10, 10);

    let y = 20;
    dados.forEach(item => {
        doc.setFontSize(10);
        doc.text(`Imagem: ${item.nome_imagem}`, 10, y);
        doc.text(`Prazo: ${item.prazo}`, 80, y);
        doc.text(`Etapa: ${item.nome_status_imagem}`, 120, y);
        doc.text(`Status: ${item.situacao}`, 160, y);
        y += 7;
        if (y > 280) {
            doc.addPage();
            y = 20;
        }
    });

    doc.save("relatorio_imagens.pdf");
}

function atualizarIndicadores(ind) {
    document.getElementById("totalREN").textContent = `Render: ${ind.REN}`;
    document.getElementById("totalFIN").textContent = `Finalizadas: ${ind.FIN}`;
    document.getElementById("totalRVW").textContent = `Em Review: ${ind.RVW}`;
    document.getElementById("totalDRV").textContent = `No Drive: ${ind.DRV}`;
    document.getElementById("totalAtrasadas").textContent = `Atrasadas: ${ind.atrasadas}`;
    document.getElementById("totalPrazoHoje").textContent = `Prazo Hoje: ${ind.prazo_hoje}`;
    document.getElementById("totalImagens").textContent = `Total imagens: ${ind.total}`;
}

