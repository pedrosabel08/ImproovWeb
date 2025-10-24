window.addEventListener('DOMContentLoaded', () => {
    const dataAtual = new Date();
    const mesAtual = dataAtual.getMonth() + 1; // Janeiro = 0, então soma 1

    // Para o primeiro select (valores "01", "02", ...)
    const selectMes = document.getElementById('mes');
    if (selectMes) {
        const valorMes = mesAtual.toString().padStart(2, '0'); // Ex: 03
        selectMes.value = valorMes;
    }

    // Para o segundo select (valores 1, 2, ...)
    const selectMesFuncao = document.getElementById('mesFuncao');
    if (selectMesFuncao) {
        selectMesFuncao.value = mesAtual.toString(); // Ex: 3
    }
});

function formatarData(data) {
    const partes = data.split("-");
    const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
    return dataFormatada;
}


function buscarDados() {
    const mes = document.getElementById('mes').value;
    const nomeMeses = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];

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
            <td>${linha.quantidade} (Pagas: ${linha.pagas})</td>
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
    const nomeMeses = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
    document.getElementById("mesSelecionadoFuncao").innerText = `mês: ${nomeMeses[parseInt(mes) - 1]}`;

    document.getElementById("labelMesFuncao").style.display = "flex";
    document.getElementById("mesFuncao").style.display = "flex";

    fetch(`buscar_producao_funcao.php?mes=${mes}`)
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
            <td>${linha.quantidade} (Pagas: ${linha.pagas})</td>
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

/**
 * Busca entregas agrupadas por status para o mês selecionado.
 * Se nenhum mês for selecionado, usa o mês atual.
 */
function buscarEntregasMes(ano) {
    const selectMes = document.getElementById('mes');
    const mes = selectMes ? selectMes.value : (new Date().getMonth() + 1).toString().padStart(2, '0');
    ano = ano || new Date().getFullYear();

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
                `;
            }

            if (!Array.isArray(data)) return;

            data.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.nome_status}</td>
                    <td>${row.quantidade}</td>
                `;
                tabela.appendChild(tr);
            });
        })
        .catch(err => console.error('Erro ao buscar entregas por mês:', err));
}
