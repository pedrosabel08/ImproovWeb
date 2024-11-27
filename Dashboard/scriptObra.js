// Obtém o 'obra_id' do localStorage
var obraId = localStorage.getItem('obraId');

let chartInstance = null;


// Verifica se obraId está presente no localStorage
if (obraId) {
    fetch(`infosObra.php?obraId=${obraId}`)
        .then(response => response.json())
        .then(data => {
            // Verifica se os dados são válidos e não vazios
            if (!Array.isArray(data.imagens) || data.imagens.length === 0) {
                console.warn('Nenhuma função encontrada para esta obra.');
                data.imagens = [{ // Exemplo de dados padrão para evitar que a tabela fique vazia
                    imagem_nome: 'Sem imagem',
                    tipo_imagem: 'N/A',
                    caderno_colaborador: '-',
                    caderno_status: '-',
                    modelagem_colaborador: '-',
                    modelagem_status: '-',
                    composicao_colaborador: '-',
                    composicao_status: '-',
                    finalizacao_colaborador: '-',
                    finalizacao_status: '-',
                    pos_producao_colaborador: '-',
                    pos_producao_status: '-',
                    alteracao_colaborador: '-',
                    alteracao_status: '-',
                    planta_colaborador: '-',
                    planta_status: '-'
                }];
            }

            var tabela = document.querySelector('#tabela-obra tbody');
            tabela.innerHTML = ''; // Limpa a tabela antes de adicionar os novos dados

            data.imagens.forEach(function (item) {
                var row = document.createElement('tr');
                row.classList.add('linha-tabela');
                row.setAttribute('data-id', item.imagem_id);

                var cellNomeImagem = document.createElement('td');
                cellNomeImagem.textContent = item.imagem_nome;
                cellNomeImagem.setAttribute('antecipada', item.antecipada);
                row.appendChild(cellNomeImagem);

                if (Boolean(parseInt(item.antecipada))) {
                    cellNomeImagem.style.backgroundColor = '#ff9d00';
                }

                var cellTipoImagem = document.createElement('td');
                cellTipoImagem.textContent = item.tipo_imagem;
                row.appendChild(cellTipoImagem);

                var colunas = [
                    { col: 'caderno', label: 'Caderno' },
                    { col: 'modelagem', label: 'Modelagem' },
                    { col: 'composicao', label: 'Composição' },
                    { col: 'finalizacao', label: 'Finalização' },
                    { col: 'pos_producao', label: 'Pós Produção' },
                    { col: 'alteracao', label: 'Alteração' },
                    { col: 'planta', label: 'Planta' }
                ];

                colunas.forEach(function (coluna) {
                    var cellColaborador = document.createElement('td');
                    var cellStatus = document.createElement('td');
                    cellColaborador.textContent = item[`${coluna.col}_colaborador`] || '-';
                    cellStatus.textContent = item[`${coluna.col}_status`] || '-';
                    row.appendChild(cellColaborador);
                    row.appendChild(cellStatus);

                    applyStyleNone(cellColaborador, cellStatus, item[`${coluna.col}_colaborador`]);
                    applyStatusStyle(cellStatus, item[`${coluna.col}_status`], item[`${coluna.col}_colaborador`]);
                });

                tabela.appendChild(row);
            });

            const obra = data.obra;
            document.getElementById('nomenclatura').textContent = obra.nomenclatura || "Nome não disponível";
            document.getElementById('data_inicio').textContent = `Data de Início: ${obra.data_inicio}`;
            document.getElementById('prazo').textContent = `Prazo: ${obra.prazo}`;
            document.getElementById('dias_trabalhados').innerHTML = obra.dias_trabalhados ? `<strong>${obra.dias_trabalhados}</strong> dias` : '';
            document.getElementById('total_imagens').textContent = `Total de Imagens: ${obra.total_imagens}`;
            document.getElementById('total_imagens_antecipadas').textContent = `Imagens Antecipadas: ${obra.total_imagens_antecipadas}`;

            const funcoes = data.funcoes;
            const nomesFuncoes = funcoes.map(funcao => funcao.nome_funcao);
            const porcentagensFinalizadas = funcoes.map(funcao => parseFloat(funcao.porcentagem_finalizada));

            const funcoesDiv = document.getElementById('funcoes');
            funcoesDiv.innerHTML = "";
            data.funcoes.forEach(funcao => {
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

            const valores = data.valores;
            document.getElementById('valor_orcamento').textContent = `R$ ${parseFloat(valores.valor_orcamento).toFixed(2)}`;
            document.getElementById('valor_producao').textContent = `R$ ${parseFloat(valores.custo_producao).toFixed(2)}`;
            document.getElementById('valor_fixo').textContent = `R$ ${parseFloat(valores.custo_fixo).toFixed(2)}`;
            document.getElementById('lucro').textContent = `R$ ${parseFloat(valores.lucro).toFixed(2)}`;

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
        .catch(error => console.error('Erro ao carregar funções:', error));
}


function filtrarTabela() {
    var tipoImagemFiltro = document.getElementById("tipo_imagem").value.toLowerCase(); // Captura o filtro de tipo de imagem
    var antecipadaFiltro = document.getElementById("antecipada_obra").value; // Captura o filtro de antecipada
    var tabela = document.getElementById("tabela-obra"); // Tabela de imagens
    var tbody = tabela.getElementsByTagName("tbody")[0]; // Obtém o corpo da tabela
    var linhas = tbody.getElementsByTagName("tr"); // Obtém todas as linhas da tabela

    for (var i = 0; i < linhas.length; i++) {
        var tipoImagemColuna = linhas[i].getElementsByTagName("td")[1].textContent || linhas[i].getElementsByTagName("td")[1].innerText; // Obtém o tipo de imagem da 2ª coluna (ajustado para corresponder à estrutura da sua tabela)

        // Verifica o valor do atributo antecipada da linha (onde o atributo é armazenado no tr)
        var isAntecipada = linhas[i].getAttribute("antecipada") === '1';

        var mostrarLinha = true;

        // Filtro para tipo de imagem
        if (tipoImagemFiltro && tipoImagemFiltro !== "0" && tipoImagemColuna.toLowerCase() !== tipoImagemFiltro.toLowerCase()) {
            mostrarLinha = false;
        }

        // Filtro para imagens antecipadas
        if (antecipadaFiltro === "Antecipada" && !isAntecipada) {
            mostrarLinha = false;
        }

        // Exibe ou esconde a linha dependendo do filtro
        linhas[i].style.display = mostrarLinha ? "" : "none";
    }
}

// Adiciona evento para filtrar sempre que o filtro mudar
document.getElementById("tipo_imagem").addEventListener("change", filtrarTabela);
document.getElementById("antecipada_obra").addEventListener("change", filtrarTabela);

function applyStatusStyle(cell, status, colaborador) {
    if (colaborador === 'Não se aplica') {
        return;
    }

    switch (status) {
        case 'Finalizado':
            cell.style.backgroundColor = 'green';
            cell.style.color = 'white';
            break;
        case 'Em andamento':
            cell.style.backgroundColor = 'orange';
            cell.style.color = 'black';
            break;
        default:
            cell.style.backgroundColor = '';
            cell.style.color = '';
    }
}

function applyStyleNone(cell, cell2, nome) {
    if (nome === 'Não se aplica') {
        cell.style.backgroundColor = '#fff8ab';
        cell.style.color = 'black';
        cell2.style.backgroundColor = '#fff8ab';
        cell2.style.color = 'black';
    } else {
        cell.style.backgroundColor = '';
        cell.style.color = '';
        cell2.style.backgroundColor = '';
        cell2.style.color = '';
    }
}

var sidebar = document.getElementById("sidebar");
var toggleButton = document.getElementById("toggleSidebar");

// Adiciona o evento de clique no botão (que contém o ícone)
toggleButton.addEventListener("click", function () {
    // Verifica se a sidebar está oculta (display: none)
    if (sidebar.style.display === "none" || sidebar.style.display === "") {
        // Torna a sidebar visível
        sidebar.style.display = "flex";
        toggleButton.style.display = "none";

    } else {
        // Oculta a sidebar
        sidebar.style.display = "none";
        toggleButton.style.display = "flex";

    }
});

// Fecha a sidebar se o usuário clicar fora dela
window.onclick = function (event) {
    if (event.target !== sidebar && event.target !== toggleButton && !toggleButton.contains(event.target)) {
        sidebar.style.display = "none"; // Fecha a sidebar se clicado fora
        toggleButton.style.display = "flex";

    }
};
