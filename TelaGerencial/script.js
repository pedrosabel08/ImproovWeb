function formatarData(data) {
    const partes = data.split("-");
    const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
    return dataFormatada;
}


function buscarDados() {
    const mes = document.getElementById('mes').value;
    const nomeMeses = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
    document.getElementById("mesSelecionado").innerText = nomeMeses[parseInt(mes) - 1];

    fetch('buscar_producao.php?mes=' + mes)
        .then(res => res.json())
        .then(dados => {
            const tabela = document.querySelector('#tabelaProducao tbody');
            tabela.innerHTML = ''; // limpa

            dados.forEach(linha => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
            <td>${linha.nome_colaborador}</td>
            <td>${linha.nome_funcao}</td>
            <td>R$ ${parseFloat(linha.total_valor).toFixed(2)}</td>
            <td>${formatarData(linha.data_pagamento)}</td>
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

function buscarDadosFuncao() {
    const mes = document.getElementById('mesFuncao').value;
    const nomeMeses = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
    document.getElementById("mesSelecionadoFuncao").innerText = nomeMeses[parseInt(mes) - 1];

    fetch(`buscar_producao_funcao.php?mes=${mes}`)
        .then(res => res.json())
        .then(data => {
            const tabela = document.querySelector("#tabelaFuncao tbody");
            tabela.innerHTML = ''; // limpa

            let totalGeral = 0;

            data.forEach(linha => {
                const tr = document.createElement("tr");
                tr.innerHTML = `
            <td>${linha.nome_funcao}</td>
            <td>${linha.quantidade}</td>
            <td>R$ ${parseFloat(linha.valor_total).toFixed(2).replace('.', ',')}</td>
          `;
                tabela.appendChild(tr);
                totalGeral += parseFloat(linha.valor_total);
            });

            document.getElementById("valorTotal").innerHTML = `<strong>R$ ${totalGeral.toFixed(2).replace('.', ',')}</strong>`;
        })
        .catch(error => {
            console.error("Erro ao buscar dados:", error);
        });
}

window.onload = function () {
    buscarDados();
    buscarDadosFuncao();
};
