function gerarDatas() {
    const datas = [];
    const hoje = new Date();
    const inicio = new Date();
    const fim = new Date();
    inicio.setDate(hoje.getDate() - 10);
    fim.setDate(hoje.getDate() + 20);

    while (inicio <= fim) {
        datas.push(new Date(inicio));
        inicio.setDate(inicio.getDate() + 1);
    }
    return datas;
}

function criarCabecalho(datas, feriados) {
    const thead = document.querySelector("#gantt thead");
    thead.innerHTML = '';

    const mesRow = document.createElement("tr");
    const diaRow = document.createElement("tr");

    const thObra = document.createElement("th");
    thObra.textContent = "Obra";
    thObra.classList.add('nomenclatura_header')
    thObra.rowSpan = 2;
    mesRow.appendChild(thObra);

    const thImagem = document.createElement("th");
    thImagem.textContent = "Imagem";
    thImagem.classList.add('nome_imagem_header')
    thImagem.rowSpan = 2;
    mesRow.appendChild(thImagem);

    let mesAtual = '';
    let mesInicio = 0;

    datas.forEach((data, i) => {
        const mes = data.toLocaleDateString('pt-BR', { month: 'long' });

        if (i === 0 || mes !== mesAtual) {
            if (i !== 0) {
                const thMes = document.createElement("th");
                thMes.textContent = mesAtual.charAt(0).toUpperCase() + mesAtual.slice(1);
                thMes.colSpan = i - mesInicio;
                mesRow.appendChild(thMes);
                mesInicio = i;
            }
            mesAtual = mes;
        }

        const thDia = document.createElement("th");
        thDia.textContent = data.getDate();
        const dataStr = data.toLocaleDateString('pt-BR');
        const diaSemana = data.getDay();
        const dataMMDD = dataStr.slice(0, 5);

        if (diaSemana === 0 || diaSemana === 6) thDia.classList.add('fim-semana');
        if (feriados.includes(dataMMDD)) thDia.classList.add('feriado');

        const hoje = new Date();
        if (data.getDate() === hoje.getDate() && data.getMonth() === hoje.getMonth() && data.getFullYear() === hoje.getFullYear()) {
            thDia.classList.add('hoje');
        }

        diaRow.appendChild(thDia);
    });

    const thMesFinal = document.createElement("th");
    thMesFinal.textContent = mesAtual.charAt(0).toUpperCase() + mesAtual.slice(1);
    thMesFinal.colSpan = datas.length - mesInicio;
    mesRow.appendChild(thMesFinal);

    thead.appendChild(mesRow);
    thead.appendChild(diaRow);
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
            const dataInicio = new Date(etapa.data_inicio);
            const dataFim = new Date(etapa.data_fim);

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

const datas = gerarDatas();
criarCabecalho(datas, feriadosFixos);

// Carrega JSON do PHP
fetch('dados_colaborador.php')
    .then(response => response.json())
    .then(tarefas => {
        montarCorpo(datas, tarefas);
    })
    .catch(error => console.error('Erro ao carregar dados:', error));

