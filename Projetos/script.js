document.addEventListener('DOMContentLoaded', () => {
    // Cria a tabela com Tabulator
    const tabela = new Tabulator("#tabelaGestaoImagens", {
        ajaxURL: "getDados.php", // rota que retorna JSON
        layout: "fitColumns",
        responsiveLayout: "collapse",
        pagination: "local",
        paginationSize: 10,
        placeholder: "Nenhuma imagem encontrada",
        columns: [
            { title: "Prazo", field: "prazo", sorter: "date", hozAlign: "center" },
            { title: "Data Início", field: "data_inicio", sorter: "date", hozAlign: "center" },
            {
                title: "Descrição",
                field: "descricao",
                formatter: function (cell, formatterParams, onRendered) {
                    const data = cell.getData();
                    return `${data.nome_imagem} - ${data.status} - ${data.obra}`;
                }
            },
            {
                title: "Situação",
                field: "situacao",
                hozAlign: "center",
                formatter: function (cell) {
                    const value = cell.getValue();
                    let cor = "#ced4da"; // padrão cinza

                    if (value === "REN") cor = "#f8d7da";     // vermelho claro
                    if (value === "FIN") cor = "#d4edda";     // verde claro
                    if (value === "RVW") cor = "#fff3cd";     // amarelo claro

                    return `<span style="padding:4px 8px; border-radius:4px; background:${cor};">${value}</span>`;
                }
            }
        ],
    });
});
