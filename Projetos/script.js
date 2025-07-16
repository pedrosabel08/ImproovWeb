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
                if (!item.nome_status_imagem || item.nome_status_imagem.trim() === "") {
                    item.nome_status_imagem = "Sem etapa";
                }

                if (item.prazo && item.prazo !== '0000-00-00') {
                    const partes = item.prazo.split('/');
                    if (partes.length === 3) {
                        const [dia, mes, ano] = partes;
                        item.prazo = `${ano}-${mes}-${dia}`;  // <-- ESSENCIAL
                    }
                    const prazo = new Date(item.prazo);
                    item.situacao_prazo = (prazo < hoje) ? "Atrasada" : "OK";
                } else {
                    item.situacao_prazo = "N/A";
                }
            });

            // Update header filters after processing data
            const etapasUnicas = [...new Set(dados.map(item => item.nome_status_imagem || "Sem etapa"))].sort();
            const etapasUnicasObj = Object.fromEntries(etapasUnicas.map(v => [v, v]));
            const statusUnicos = [...new Set(dados.map(item => item.situacao))].sort();

            tabela.getColumn("nome_status_imagem").updateDefinition({
                headerFilterParams: { values: etapasUnicasObj }
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
        groupBy: ["obra", "nome_status", "prazo"],
        groupToggleElement: "header",
        groupHeader: function (value, count, data, group) {
            const field = group.getField() || "custom";
            let headerText = `<strong>${value}</strong>`;
            let bgColor = "#f1f3f5"; // Cor padrão

            if (field === "obra") {
                headerText = `<strong>${value}</strong>`;
                bgColor = "#acacacff"; // azul claro
            } else if (field === "nome_status") {
                headerText = `<strong>${value}</strong>`;
                bgColor = "#cececeff"; // amarelo claro
            } else if (field === "prazo") {
                if (value && value !== '0000-00-00') {
                    const dateObj = new Date(value);
                    const dia = String(dateObj.getDate()).padStart(2, '0');
                    const mes = String(dateObj.getMonth() + 1).padStart(2, '0');
                    const ano = dateObj.getFullYear();
                    headerText = `<strong>${dia}/${mes}/${ano}</strong>`;
                    bgColor = "#ddddddff"; // cinza claro
                } else {
                    headerText = `<strong>N/A</strong>`;
                }
            }


            // Aplica o fundo no elemento do grupo
            setTimeout(() => {
                const el = group.getElement();
                if (el) {
                    el.style.backgroundColor = bgColor;
                }
            }, 0);

            return `${headerText} <span style="color: #000000ff;">(${count} ${count > 1 ? 'imagens' : 'imagem'})</span>`;
        },

        groupStartOpen: false, // Groups will start closed

        columns: [
            {
                title: "Prazo",
                field: "prazo",
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
                title: "Data Recebimento",
                field: "recebimento_arquivos",
                sorter: "date",
                hozAlign: "center",
                headerFilter: true,
                formatter: function (cell) {
                    const valor = cell.getValue();
                    if (!valor) return "N/A";
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
                formatter: function (cell) {
                    const data = cell.getData();
                    return `<strong>${data.nome_imagem}</strong>`;
                }
            },
            {
                title: "Etapa",
                field: "nome_status_imagem",
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

