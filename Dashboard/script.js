document.addEventListener('DOMContentLoaded', () => {
    const navLinks = document.querySelectorAll('.nav a'); // Seleciona todos os links dentro da classe .nav
    const currentPage = window.location.pathname.split('/').pop(); // Obtém o nome do arquivo atual da URL

    navLinks.forEach(link => {
        const linkHref = link.getAttribute('href'); // Obtém o valor do href de cada link
        if (linkHref === currentPage) {
            link.classList.add('active'); // Adiciona a classe active se o link corresponder à página atual
        }
    });
});



function formatarData(data) {
    const partes = data.split("-");
    const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
    return dataFormatada;
}

function mostrarImagens() {
    // Mostra as imagens restantes
    document.getElementById("imagens-restantes").style.display = "block";
    // Esconde o botão após clicar
    document.getElementById("mostrar-mais").style.display = "none";
}

const modalColab = document.getElementById('filtro-colab');

document.getElementById('ver_todas').addEventListener('click', function () {
    modalColab.style.display = 'flex';
    carregarDados();
})

var colaboradorId = localStorage.getItem('idcolaborador');
function carregarDados() {

    var dataInicio = document.getElementById('dataInicio').value;
    var dataFim = document.getElementById('dataFim').value;
    var obraId = document.getElementById('obraSelect').value;
    var funcaoId = document.getElementById('funcaoSelect').value;
    var status = document.getElementById('statusSelect').value;

    if (colaboradorId) {
        var url = '../getFuncoesPorColaborador.php?colaborador_id=' + colaboradorId;

        if (dataInicio) {
            url += '&data_inicio=' + encodeURIComponent(dataInicio);
        }
        if (dataFim) {
            url += '&data_fim=' + encodeURIComponent(dataFim);
        }
        if (obraId) {
            url += '&obra_id=' + encodeURIComponent(obraId);
        }
        if (funcaoId) {
            url += '&funcao_id=' + encodeURIComponent(funcaoId);
        }
        if (status) {
            url += '&status=' + encodeURIComponent(status);
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                var tabela = document.querySelector('#tabela-colab tbody');
                tabela.innerHTML = '';

                data.forEach(function (item) {
                    var row = document.createElement('tr');
                    row.classList.add('linha-tabela');
                    row.setAttribute('data-id', item.imagem_id);
                    var cellNomeImagem = document.createElement('td');
                    cellNomeImagem.textContent = item.imagem_nome;
                    var cellFuncao = document.createElement('td');
                    cellFuncao.textContent = item.nome_funcao;
                    var cellStatus = document.createElement('td');
                    cellStatus.textContent = item.status;
                    var cellPrazoImagem = document.createElement('td');
                    cellPrazoImagem.textContent = item.prazo;

                    row.appendChild(cellNomeImagem);
                    row.appendChild(cellFuncao);
                    row.appendChild(cellStatus);
                    row.appendChild(cellPrazoImagem);
                    tabela.appendChild(row);
                });

                document.getElementById('totalImagens').textContent = data.length;

            })
            .catch(error => console.error('Erro ao carregar funções:', error));
    } else {
        document.querySelector('#tabela-colab tbody').innerHTML = '';
        document.getElementById('totalImagens').textContent = '0';
    }
}


document.getElementById('dataInicio').addEventListener('change', carregarDados);
document.getElementById('dataFim').addEventListener('change', carregarDados);
document.getElementById('obraSelect').addEventListener('change', carregarDados);
document.getElementById('funcaoSelect').addEventListener('change', carregarDados);
document.getElementById('statusSelect').addEventListener('change', carregarDados);


const obraSelect = document.getElementById('obraSelect');
const mostrarLogsBtn = document.getElementById('mostrarLogsBtn');
const modalLogs = document.getElementById('modalLogs');

mostrarLogsBtn.addEventListener('click', function () {
    const obraId = obraSelect.value;
    modalLogs.style.display = 'flex';

    fetch(`../carregar_logs.php?colaboradorId=${colaboradorId}&obraId=${obraId}`)
        .then(response => response.json())
        .then(data => {
            const tabelaLogsBody = document.querySelector('#tabela-logs tbody');
            tabelaLogsBody.innerHTML = '';

            if (data && data.length > 0) {
                data.forEach(log => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${log.imagem_nome}</td>
                        <td>${log.nome_obra}</td>
                        <td>${log.status_anterior}</td>
                        <td>${log.status_novo}</td>
                        <td>${log.data}</td>
                    `;
                    tabelaLogsBody.appendChild(row);
                });
            } else {
                const row = document.createElement('tr');
                row.innerHTML = '<td colspan="5">Nenhum log encontrado.</td>';
                tabelaLogsBody.appendChild(row);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar os logs:', error);
        });
});


fetch('atualizarValores.php')
    .then(response => response.json())
    .then(data => {
        if (data && data.length > 0) {  // Verifica se há dados e se não está vazio
            const valores = data[0];  // Acessa o primeiro elemento do array

            // Converte valores para números (caso não estejam como número)
            const totalOrcamento = valores.total_orcamento;
            const totalProducao = valores.total_producao;
            const obrasAtivas = valores.obras_ativas;

            // Verifica se os valores são válidos números
            if (!isNaN(totalOrcamento)) {
                document.getElementById('total_orcamentos').textContent = `R$${totalOrcamento.toLocaleString('pt-BR')}`;
            } else {
                console.error('Valor de total_orcamento inválido');
            }

            if (!isNaN(totalProducao)) {
                document.getElementById('total_producao').textContent = `R$${totalProducao.toLocaleString('pt-BR')}`;
            }

            document.getElementById('obras_ativas').textContent = obrasAtivas;

            // Valor do orçamento do ano passado
            const orcamentoAnoPassado = 925000;

            // Calcula o lucro em porcentagem se o orçamento atual for válido
            if (!isNaN(totalOrcamento)) {
                const lucroPercentual = ((totalOrcamento - orcamentoAnoPassado) / orcamentoAnoPassado) * 100;
                document.getElementById('lucro_percentual').textContent = `${lucroPercentual.toFixed(2)}%`;
            } else {
                document.getElementById('lucro_percentual').textContent = 'N/A';
            }
        } else {
            console.error("Dados não encontrados");
        }
    })
    .catch(error => {
        console.error("Erro ao buscar dados:", error);
    });


let chartInstance = null;


fetch('obras.php')
    .then(response => response.json())
    .then(data => {
        const painel = document.getElementById('painel');

        function calcularDiferencaDias(prazo) {
            const dataAtual = new Date();
            const prazoDate = new Date(prazo);
            const diffTime = prazoDate - dataAtual;  // Diferença em milissegundos
            return Math.ceil(diffTime / (1000 * 3600 * 24));  // Converte para dias
        }

        function formatarData(data) {
            const partes = data.split('-');  // Divide a data em partes (ano, mês, dia)
            return `${partes[2]}/${partes[1]}/${partes[0]}`;  // Reorganiza para DD/MM/YYYY
        }

        // Iterar sobre os dados de obras e criar um card para cada obra

        function criarCards(obras, painel) {


            obras.forEach(item => {
                const card = document.createElement('div');
                card.classList.add('card'); // Adiciona a classe para estilo do card
                card.setAttribute('idobra', item.idobra);

                const nomeObra = document.createElement('h3');
                nomeObra.textContent = item.nomenclatura;


                // const prazo = document.createElement('h4');
                // prazo.textContent = formatarData(item.prazo);
                const porcentagem = document.createElement('h4');
                if (item.porcentagem_finalizada !== null) {
                    porcentagem.textContent = `${item.porcentagem_finalizada}%`;
                }


                // Calcular a diferença de dias
                const diasRestantes = calcularDiferencaDias(item.prazo);

                // Alterar a cor do card com base no prazo
                if (item.recebimento_arquivos === '0000-00-00' && item.porcentagem_finalizada <= 0) {
                    card.style.backgroundColor = '#1bd6f2'; // Azul
                    card.style.color = '#fff';
                } else {
                    if (diasRestantes < 0) {
                        // Prazo já passou
                        card.style.backgroundColor = '#ff6f61'; // Vermelho
                        card.style.color = '#fff';
                    } else if (diasRestantes <= 3) {
                        // Prazo próximo (3 dias ou menos)
                        card.style.backgroundColor = '#f7b731'; // Amarelo
                        card.style.color = '#333';
                    } else {
                        // Prazo distante
                        card.style.backgroundColor = '#28a745'; // Verde
                        card.style.color = '#fff';
                    }
                }



                card.addEventListener('click', function () {
                    const obraId = item.idobra;

                    localStorage.setItem("obraId", obraId);
                    document.getElementById('idObraOrcamento').value = obraId;


                    document.getElementById('modalInfos').style.display = 'flex';

                    fetch(`infosObra.php?obraId=${obraId}`)
                        .then(response => response.json())
                        .then(detalhes => {

                            const obra = detalhes.obra;
                            document.getElementById('nomenclatura').textContent = obra.nomenclatura || "Nome não disponível";
                            document.getElementById('data_inicio').textContent = `Data de Início: ${obra.data_inicio}`;
                            // document.getElementById('prazo').textContent = `Prazo: ${obra.prazo}`;
                            document.getElementById('dias_trabalhados').innerHTML = obra.dias_trabalhados ? `<strong>${obra.dias_trabalhados}</strong> dias` : '';
                            document.getElementById('total_imagens').textContent = `Total de Imagens: ${obra.total_imagens}`;
                            document.getElementById('total_imagens_antecipadas').textContent = `Imagens Antecipadas: ${obra.total_imagens_antecipadas}`;

                            const funcoes = detalhes.funcoes;
                            const nomesFuncoes = funcoes.map(funcao => funcao.nome_funcao);
                            const porcentagensFinalizadas = funcoes.map(funcao => parseFloat(funcao.porcentagem_finalizada));

                            const funcoesDiv = document.getElementById('funcoes');
                            funcoesDiv.innerHTML = "";
                            detalhes.funcoes.forEach(funcao => {
                                const funcaoDiv = document.createElement('div');
                                funcaoDiv.classList.add('funcao');
                                funcaoDiv.innerHTML = `
                                <strong>${funcao.nome_funcao}</strong><br>
                                Total de Imagens: ${funcao.total_imagens}<br>
                                Imagens Finalizadas: ${funcao.funcoes_finalizadas}<br>
                                Porcentagem Finalizada: ${funcao.porcentagem_finalizada}%<br><br>
                            `;
                                funcoesDiv.appendChild(funcaoDiv);
                            });

                            const prazosDiv = document.getElementById('prazos-list');

                            // Limpa o conteúdo da div
                            prazosDiv.innerHTML = "";

                            // Agrupa os prazos por status
                            const groupedPrazos = detalhes.prazos.reduce((acc, prazo) => {
                                if (!acc[prazo.nome_status]) {
                                    acc[prazo.nome_status] = [];
                                }
                                acc[prazo.nome_status].push({
                                    prazo: prazo.prazo,
                                    idsImagens: prazo.idImagens || [] // Use idImagens conforme o JSON retornado
                                });
                                return acc;
                            }, {});

                            // Renderiza os cards agrupados
                            Object.entries(groupedPrazos).forEach(([status, prazos]) => {
                                console.log('Status:', status, 'Prazos:', prazos);
                                const prazoList = document.createElement('div');
                                prazoList.classList.add('prazos');

                                prazoList.innerHTML = `
                                    <div class="prazo-card">
                                        <p class="nome_status">${status}</p>
                                        <ul>
                                        ${prazos.map(prazo => `
                                            <li 
                                                data-ids="${(prazo.idsImagens || []).join(',')}" 
                                                class="prazo-item">
                                                ${formatarData(prazo.prazo)} - <strong>${prazo.idsImagens.length} imagens</strong>
                                            </li>`).join("")}
                                        </ul>
                                    </div>
                                    `;

                                const prazoCard = prazoList.querySelector('.prazo-card');
                                applyStatusImagem(prazoCard, status);
                                prazosDiv.appendChild(prazoList);
                            });



                            const ctx = document.getElementById('graficoPorcentagem').getContext('2d');
                            if (chartInstance) {
                                chartInstance.destroy();
                            }
                            chartInstance = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: nomesFuncoes,
                                    datasets: [{
                                        label: 'Porcentagem de Conclusão (%)',
                                        data: porcentagensFinalizadas,
                                        backgroundColor: [
                                            'rgba(54, 162, 235, 0.2)',  // Cor para a 1ª barra
                                            'rgba(255, 99, 132, 0.2)',  // Cor para a 2ª barra
                                            'rgba(255, 159, 64, 0.2)',  // Cor para a 3ª barra
                                            'rgba(75, 192, 192, 0.2)',  // Cor para a 4ª barra
                                            'rgba(153, 102, 255, 0.2)', // Cor para a 5ª barra
                                            'rgba(255, 159, 64, 0.2)'   // Cor para a 6ª barra, e assim por diante
                                        ],
                                        borderColor: [
                                            'rgba(54, 162, 235, 1)',  // Cor para a borda da 1ª barra
                                            'rgba(255, 99, 132, 1)',  // Cor para a borda da 2ª barra
                                            'rgba(255, 159, 64, 1)',  // Cor para a borda da 3ª barra
                                            'rgba(75, 192, 192, 1)',  // Cor para a borda da 4ª barra
                                            'rgba(153, 102, 255, 1)', // Cor para a borda da 5ª barra
                                            'rgba(255, 159, 64, 1)'
                                        ],
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                stepSize: 10
                                            }
                                        }
                                    }
                                }
                            });
                        })
                        .catch(error => console.error('Erro ao carregar os detalhes da obra:', error));

                });

                card.appendChild(nomeObra);
                // card.appendChild(prazo);
                card.appendChild(porcentagem);
                painel.appendChild(card);
            });

        }

        criarCards(data.with_filter, painel);

    })
    .catch(error => console.error('Erro ao carregar os dados:', error));


function applyStatusImagem(cell, status) {
    switch (status) {
        case 'P00':
            cell.style.backgroundColor = '#ffc21c'
            break;
        case 'R00':
            cell.style.backgroundColor = '#1cf4ff'
            break;
        case 'R01':
            cell.style.backgroundColor = '#ff6200'
            break;
        case 'R02':
            cell.style.backgroundColor = '#ff3c00'
            break;
        case 'R03':
            cell.style.backgroundColor = '#ff0000'
            break;
        case 'EF':
            cell.style.backgroundColor = '#0dff00'
            break;
        case 'HOLD':
            cell.style.backgroundColor = '#ff0000';
            break;
        case 'TEA':
            cell.style.backgroundColor = '#f7eb07';
            break;
        case 'REN':
            cell.style.backgroundColor = '#0c9ef2';
            break;
        case 'APR':
            cell.style.backgroundColor = '#0c45f2';
            break;
        case 'APP':
            cell.style.backgroundColor = '#7d36f7';
    }
};


document.getElementById('orcamento').addEventListener('click', function () {
    document.getElementById('modalOrcamento').style.display = 'flex';
});


document.getElementById('formOrcamento').addEventListener('submit', function (e) {
    e.preventDefault();

    const idObra = document.getElementById('idObraOrcamento').value;
    const tipo = document.getElementById('tipo').value;
    const valor = document.getElementById('valor').value;
    const data = document.getElementById('data').value;

    // Enviar os dados para o backend
    fetch('salvarOrcamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ idObra, tipo, valor, data }),
    })
        .then(response => response.json())
        .then(data => {
            alert('Orçamento salvo com sucesso!');
            document.getElementById('modalOrcamento').style.display = 'none'; // Fecha o modal
        })
        .catch(error => {
            console.error('Erro ao salvar orçamento:', error);
        });
});

const modalInfos = document.getElementById('modalInfos')
const modalOrcamento = document.getElementById('modalOrcamento')
window.onclick = function (event) {
    if (event.target == modalInfos) {
        modalInfos.style.display = "none";
    }
    if (event.target == modalOrcamento) {
        modalOrcamento.style.display = "none";
    }
    if (event.target == modalColab) {
        modalColab.style.display = "none";
    }
    if (event.target == modalLogs) {
        modalLogs.style.display = "none";
    }
}

window.addEventListener('touchstart', function (event) {
    if (event.target == modalInfos) {
        modalInfos.style.display = "none";
    }
    if (event.target == modalOrcamento) {
        modalOrcamento.style.display = "none";
    }
});



document.addEventListener('DOMContentLoaded', function () {
    const cards = document.querySelectorAll('.stat-card');
    let currentIndex = 0;

    // Exibe o primeiro card
    cards[currentIndex].classList.add('active');

    function nextCard() {
        cards[currentIndex].classList.remove('active');

        currentIndex = (currentIndex + 1) % cards.length;

        cards[currentIndex].classList.add('active');
    }

    setInterval(nextCard, 3000); // 3000 ms = 3 segundos
});

// Obtém o 'obra_id' do localStorage
var obraId = localStorage.getItem('obraId');

if (obraId) {
    abrirModalAcompanhamento(obraId); // Carrega os acompanhamentos automaticamente
} else {
    console.warn('ID da obra não encontrado no localStorage.');
}


// Adiciona o botão de mostrar todos
const btnMostrarAcomps = document.getElementById('btnMostrarAcomps');
const acompanhamentoConteudo = document.getElementById('list_acomp');
// Ao clicar no botão "Mostrar Todos"
btnMostrarAcomps.addEventListener('click', () => {
    acompanhamentoConteudo.classList.toggle('expanded');
    const isExpanded = acompanhamentoConteudo.classList.contains('expanded');
    btnMostrarAcomps.innerHTML = isExpanded ?
        '<i class="fas fa-chevron-up"></i>' :
        '<i class="fas fa-chevron-down"></i>';
});


function abrirModalAcompanhamento(obraId) {
    fetch(`../Obras/getAcompanhamentoEmail.php?idobra=${obraId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erro ao carregar dados: ${response.status}`);
            }
            return response.json(); // Converte a resposta para JSON
        })
        .then(acompanhamentos => {
            // Limpa o conteúdo anterior
            acompanhamentoConteudo.innerHTML = '';

            if (acompanhamentos.length > 0) {
                acompanhamentos.forEach(acomp => {

                    const item = document.createElement('p');
                    item.innerHTML = `
                        <div class="acomp-conteudo">
                            <p class="acomp-assunto"><strong>Assunto:</strong> ${acomp.assunto}</p>
                            <p class="acomp-data"><strong>Data:</strong> ${formatarData(acomp.data)}</p>
                        </div>
                    `;
                    acompanhamentoConteudo.appendChild(item);
                });
            } else {
                acompanhamentoConteudo.innerHTML = '<p>Nenhum acompanhamento encontrado.</p>';
            }

        })
        .catch(error => {
            console.error('Erro:', error);
        });
}