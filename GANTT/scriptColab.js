function gerarDatasComBaseNasEtapas(tarefas) {
    let minData = null;
    let maxData = null;

    tarefas.forEach(tarefa => {
        tarefa.etapas.forEach(etapa => {
            const ini = parseDataBR(etapa.data_inicio);
            const fim = parseDataBR(etapa.data_fim);
            if (!minData || ini < minData) minData = ini;
            if (!maxData || fim > maxData) maxData = fim;
        });
    });

    // Adiciona uma folga de alguns dias antes e depois
    minData.setDate(minData.getDate() - 5);
    maxData.setDate(maxData.getDate() + 5);

    const datas = [];
    const atual = new Date(minData);
    while (atual <= maxData) {
        datas.push(new Date(atual));
        atual.setDate(atual.getDate() + 1);
    }
    return datas;
}

function criarCabecalho(datas, feriados) {
    const headerMeses = document.querySelector('#gantt thead');
    const headerDias = document.createElement('tr');
    const headerMesesRow = document.createElement('tr');
    headerMeses.innerHTML = '';

    // Célula em branco para alinhar com a coluna de nomes (se houver)
    const cellBrancoMeses = document.createElement('th');
    headerMesesRow.appendChild(cellBrancoMeses);

    const cellBrancoDias = document.createElement('th');
    const cellBrancoDias2 = document.createElement('th');
    headerDias.appendChild(cellBrancoDias);
    headerDias.appendChild(cellBrancoDias2);

    let mesAtual = '';
    let mesContador = 0;

    datas.forEach((data, i) => {
        const mes = data.toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' });
        if (mes !== mesAtual) {
            if (mesAtual !== '') {
                const th = document.createElement('th');
                th.className = 'month';
                th.colSpan = mesContador;
                th.innerText = mesAtual;
                headerMesesRow.appendChild(th);
            }
            mesAtual = mes;
            mesContador = 1;
        } else {
            mesContador++;
        }

        if (i === datas.length - 1) {
            const th = document.createElement('th');
            th.className = 'month';
            th.colSpan = mesContador;
            th.innerText = mesAtual;
            headerMesesRow.appendChild(th);
        }
    });

    // Linha de dias
    datas.forEach(data => {
        const th = document.createElement('th');
        th.className = 'day';
        const diaSemana = data.getDay();
        if (diaSemana === 0 || diaSemana === 6) {
            th.style.backgroundColor = '#ffe0e0';
            th.style.fontWeight = 'bold';
        }
        th.innerText = data.getDate();
        headerDias.appendChild(th);
    });

    headerMeses.appendChild(headerMesesRow);
    headerMeses.appendChild(headerDias);
}

function dividirPorDiasUteis(datas, dataInicio, dataFim) {
    const blocos = [];
    let blocoInicio = null;
    let blocoFim = null;

    for (let i = 0; i < datas.length; i++) {
        const data = datas[i];
        if (data >= dataInicio && data <= dataFim) {
            const diaSemana = data.getDay();
            if (diaSemana !== 0 && diaSemana !== 6) { // 0 = domingo, 6 = sábado
                if (blocoInicio === null) blocoInicio = i;
                blocoFim = i;
            } else {
                if (blocoInicio !== null) {
                    blocos.push([blocoInicio, blocoFim]);
                    blocoInicio = null;
                    blocoFim = null;
                }
            }
        }
    }
    if (blocoInicio !== null) {
        blocos.push([blocoInicio, blocoFim]);
    }
    return blocos;
}

function parseDataBR(dataStr) {
    // dataStr: "2025-06-09"
    const [ano, mes, dia] = dataStr.split('-');
    return new Date(ano, mes - 1, dia);
}

function montarCorpo(datas, tarefasObj) {
    const tbody = document.querySelector("#gantt tbody");
    tbody.innerHTML = '';

    // Converta o objeto em array
    const tarefas = Object.values(tarefasObj);

    tarefas.forEach(tarefa => {
        const tr = document.createElement("tr");

        document.getElementById('colabNome').textContent = tarefa.nome_colaborador

        const tdObra = document.createElement("td");
        tdObra.textContent = tarefa.nomenclatura;
        tr.appendChild(tdObra);

        const tdImagem = document.createElement("td");
        tdImagem.textContent = tarefa.imagem_nome;
        tr.appendChild(tdImagem);

        let pos = 0;

        tarefa.etapas.forEach(etapa => {
            const dataInicio = parseDataBR(etapa.data_inicio);
            const dataFim = parseDataBR(etapa.data_fim);

            console.log(`Etapa: ${etapa.etapa}, Início: ${dataInicio}, Fim: ${dataFim}`);

            // Divide em blocos de dias úteis
            const blocos = dividirPorDiasUteis(datas, dataInicio, dataFim);

            blocos.forEach(bloco => {
                const [inicio, fim] = bloco;

                // Preenche espaço vazio até o início da etapa
                if (inicio > pos) {
                    const tdVazio = document.createElement("td");
                    tdVazio.colSpan = inicio - pos;
                    tr.appendChild(tdVazio);
                }

                const tdEtapa = document.createElement("td");
                tdEtapa.colSpan = fim - inicio + 1;
                tdEtapa.className = etapa.etapa
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/\s/g, '')
                    .replace(/[^a-z0-9]/g, '');
                tdEtapa.title = etapa.etapa;
                tdEtapa.textContent = etapa.etapa;
                tr.appendChild(tdEtapa);

                pos = fim + 1;
            });
        });

        // Preenche espaço vazio ao final
        if (pos < datas.length) {
            const tdVazio = document.createElement("td");
            tdVazio.colSpan = datas.length - pos;
            tr.appendChild(tdVazio);
        }

        tbody.appendChild(tr);
    });
}


// Mock de feriados fixos
const feriadosFixos = ['01/01', '21/04', '01/05', '07/09', '12/10', '15/11', '25/12'];

// Use assim após carregar os dados:
fetch('dados_colaborador.php')
    .then(response => response.json())
    .then(tarefas => {
        const datas = gerarDatasComBaseNasEtapas(Object.values(tarefas));
        criarCabecalho(datas, feriadosFixos);
        montarCorpo(datas, tarefas);
    })
    .catch(error => console.error('Erro ao carregar dados:', error));