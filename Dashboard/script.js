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


var colaboradorId = localStorage.getItem('idcolaborador');


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
    .then(res => res.json())
    .then(data => {
        // Obras Paradas
        const holdCards = document.getElementById('hold-cards');
        holdCards.innerHTML = '';
        data.hold.forEach(obra => {
            holdCards.innerHTML += `
                <div class="kanban-card" id="${obra.idobra}">${obra.nomenclatura} - (${obra.idobra})</div>`;
        });
        document.getElementById('count-hold').textContent = data.hold.length;

        // Obras em Andamento
        const andamentoCards = document.getElementById('andamento-cards');
        andamentoCards.innerHTML = '';
        data.andamento.forEach(obra => {
            andamentoCards.innerHTML += `
                <div class="kanban-card" id="${obra.idobra}">${obra.nomenclatura} - (${obra.idobra})</div>`;
        });
        document.getElementById('count-andamento').textContent = data.andamento.length;

        // Obras Finalizadas
        const finalizadasCards = document.getElementById('finalizadas-cards');
        finalizadasCards.innerHTML = '';
        data.finalizadas.forEach(obra => {
            finalizadasCards.innerHTML += `
                <div class="kanban-card" id="${obra.idobra}">${obra.nomenclatura} - (${obra.idobra})</div>`;
        });
        document.getElementById('count-finalizadas').textContent = data.finalizadas.length;
    });


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