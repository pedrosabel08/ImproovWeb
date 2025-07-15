document.addEventListener('DOMContentLoaded', () => {
    const tabela = new Tabulator("#tabelaGestaoImagens", {
        ajaxURL: "getDados.php",
        ajaxResponse: function (url, params, response) {
            // Your existing logic for updating indicators
            atualizarIndicadores(response.indicadores);

            const dados = response.dados;

            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);

            // This loop calculates 'situacao_prazo' before data is returned to Tabulator
            dados.forEach(item => {
                if (item.prazo && item.prazo !== '0000-00-00') {
                    const [dia, mes, ano] = item.prazo.split('/');
                    const prazo = new Date(`${ano}-${mes}-${dia}`);
                    item.situacao_prazo = (prazo < hoje) ? "Atrasada" : "OK";
                } else if (!item.prazo || item.prazo === '0000-00-00') {
                    item.situacao_prazo = "N/A"; // Data ausente ou inválida
                }
            });

            // Update header filters after processing data
            const etapasUnicas = [...new Set(dados.map(item => item.status))].sort();
            const statusUnicos = [...new Set(dados.map(item => item.situacao))].sort();

            tabela.getColumn("status").updateDefinition({
                headerFilterParams: { values: etapasUnicas }
            });
            tabela.getColumn("situacao").updateDefinition({
                headerFilterParams: { values: statusUnicos }
            });

            return dados; // Return the processed data
        },

        layout: "fitColumns",
        responsiveLayout: "collapse",
        pagination: "local",
        paginationSize: 100,
        placeholder: "Nenhuma imagem encontrada",

        // 👇 Here's the key change for multi-level grouping
        groupBy: ["obra", "nome_status_imagem", "prazo"], // Group by these three fields in order
        groupToggleElement: "header",
        groupHeader: function (value, count, data, group) {

            const field = group.getField();
            let headerText = `<strong>${value}</strong>`;

            if (field === "obra") {

            } else if (field === "status") {

            } else if (field === "prazo") {

            }

            return `${headerText} <span style="color: #6c757d;">(${count} ${count > 1 ? 'imagens' : 'imagem'})</span>`;
        },
        groupStartOpen: false, // Groups will start closed

        columns: [
            { title: "Prazo", field: "prazo", sorter: "date", hozAlign: "center", headerFilter: true },
            { title: "Data Recebimento", field: "data_inicio", sorter: "date", hozAlign: "center", headerFilter: true },
            {
                title: "Nome da Imagem",
                field: "nome_imagem",
                headerFilter: true,
                formatter: function (cell) {
                    const data = cell.getData();
                    return `<strong>${data.nome_imagem}</strong>`;
                }
            },
            {
                title: "Etapa",
                field: "status",
                hozAlign: "center",
                headerFilter: "list"
            },
            {
                title: "Status",
                field: "situacao",
                hozAlign: "center",
                headerFilter: "list",
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
                        "Atrasada": "Atrasada"
                    }
                },
                formatter: function (cell) {
                    const data = cell.getData();
                    // Ensure prazo exists before splitting
                    if (!data.prazo) {
                        return `<span class="tag" style="background:#e9ecef; font-weight:600;">N/A</span>`;
                    }
                    const prazoStr = data.prazo;
                    const [dia, mes, ano] = prazoStr.split('/');
                    const prazo = new Date(`${ano}-${mes}-${dia}`);
                    const hoje = new Date();
                    hoje.setHours(0, 0, 0, 0);

                    let texto = "OK";
                    let cor = "#d1e7dd";

                    if (prazo < hoje) {
                        texto = "Atrasada";
                        cor = "#f8d7da";
                    }

                    return `<span class="tag" style="background:${cor}; font-weight:600;">${texto}</span>`;
                }
            }
        ]
    });
    document.querySelectorAll('[data-status]').forEach(el => {
        el.addEventListener('click', () => {
            const valor = el.getAttribute('data-status');
            tabela.clearFilter();
            tabela.setFilter("situacao", "=", valor);
            tabela.groupStartOpen = true;
        });
    });

    document.querySelectorAll('[data-situacao_prazo]').forEach(el => {
        el.addEventListener('click', () => {
            const valor = el.getAttribute('data-situacao_prazo');
            tabela.clearFilter();
            tabela.setFilter("situacao_prazo", "=", valor);
            tabela.groupStartOpen = true;
        });
    });

    document.querySelectorAll('[data-prazo]').forEach(el => {
        el.addEventListener('click', () => {
            const hoje = new Date();
            const dia = String(hoje.getDate()).padStart(2, '0');
            const mes = String(hoje.getMonth() + 1).padStart(2, '0');
            const ano = hoje.getFullYear();
            const hojeFormatado = `${ano}-${mes}-${dia}`;

            tabela.clearFilter();
            tabela.setFilter("prazo", "=", hojeFormatado);
            setTimeout(() => {
                tabela.getGroups().forEach(group => group.show());
            }, 100);
        });
    });

});

function atualizarIndicadores(ind) {
    document.getElementById("totalREN").textContent = `Render: ${ind.REN}`;
    document.getElementById("totalFIN").textContent = `Finalizadas: ${ind.FIN}`;
    document.getElementById("totalRVW").textContent = `Em Review: ${ind.RVW}`;
    document.getElementById("totalDRV").textContent = `No Drive: ${ind.DRV}`;
    document.getElementById("totalAtrasadas").textContent = `Atrasadas: ${ind.atrasadas}`;
    document.getElementById("totalPrazoHoje").textContent = `Prazo Hoje: ${ind.prazo_hoje}`;
}

