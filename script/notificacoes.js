const idusuario = localStorage.getItem('idusuario');


function ativarSino() {
    // Ativar sino com animação
    const sino = document.getElementById('icone-sino');
    sino.classList.add('ativado');

    // Remover a classe 'ativado' após 2 segundos
    setTimeout(() => {
        sino.classList.remove('ativado');
    }, 2000); // 2 segundos (2000 milissegundos)
}

function atualizarContadorTarefas() {
    fetch('https://improov.com.br/sistema/buscar_tarefas.php', {
        method: 'GET'
    })
        .then(response => response.json())
        .then(tarefas => {
            const contadorTarefas = document.getElementById('contador-tarefas');

            if (tarefas.length > 0) {
                contadorTarefas.textContent = tarefas.length;
            } else {
                contadorTarefas.textContent = '';
                contadorTarefas.style.display = 'none';
                sino.style.display = 'none';
            }
        })
        .catch(error => console.error('Erro ao buscar tarefas:', error));
}

function buscarTarefas() {
    fetch('https://improov.com.br/sistema/buscar_tarefas.php', {
        method: 'GET'
    })
        .then(response => response.json())
        .then(tarefas => {
            const contadorTarefas = document.getElementById('contador-tarefas');
            const idColaborador = parseInt(localStorage.getItem('idcolaborador'));

            if (tarefas.length > 0) {
                contadorTarefas.textContent = tarefas.length;
                ativarSino();

                // Contagem por função (considerando filtro por idcolaborador)
                const contagemPorFuncao = {};
                const funcoesPermitidas = filtrarFuncoesPorColaborador(idColaborador);

                tarefas.forEach(tarefa => {
                    const funcao = tarefa.nome_funcao || 'Desconhecida';

                    if (funcoesPermitidas.includes(funcao)) {
                        contagemPorFuncao[funcao] = (contagemPorFuncao[funcao] || 0) + 1;
                    }
                });

                // Verifica se o usuário está autorizado a ver o alerta
                const idsPermitidos = [1, 9, 19, 21]; // IDs com permissão

                if (idsPermitidos.includes(idColaborador)) {
                    // Exibir SweetAlert com resumo
                    let mensagem = '';
                    for (const funcao in contagemPorFuncao) {
                        mensagem += `<p><strong>${funcao}</strong>: ${contagemPorFuncao[funcao]} tarefas</p>`;
                    }

                    const htmlContent = `<div>${mensagem || '<p>Nenhuma tarefa para aprovação.</p>'}</div>`;

                    Swal.fire({
                        title: 'Tarefas em aprovação',
                        icon: 'info',
                        html: htmlContent,
                        confirmButtonText: 'OK'
                    });
                }

            } else {
                contadorTarefas.textContent = '';
                console.log("Nenhuma tarefa pendente.");
            }
        })
        .catch(error => console.error('Erro ao buscar tarefas:', error));
}

function filtrarFuncoesPorColaborador(id) {
    switch (id) {
        case 21:
            return ['Finalização', 'Pós-produção', 'Modelagem', 'Composição', 'Caderno', 'Filtro de assets']; // Todas
        case 9:
            return ['Finalização', 'Pós-produção'];
        case 1:
            return ['Modelagem', 'Composição', 'Caderno', 'Filtro de assets']; // Todas menos finalização e pós-produção
        case 19:
            return ['Modelagem', 'Composição'];
        default:
            return []; // Nenhuma função por padrão
    }
}

function agendarProximaExecucao() {
    const now = new Date();
    const minutos = now.getMinutes();
    const segundos = now.getSeconds();

    // Calcular próximo intervalo (00, 15, 30, 45)
    const proximosMinutos = [0, 30].find(min => min > minutos) || 60;
    const minutosRestantes = proximosMinutos === 60 ? 60 - minutos : proximosMinutos - minutos;
    const milissegundosRestantes = (minutosRestantes * 60 - segundos) * 1000;

    setTimeout(() => {
        buscarTarefas();
        agendarProximaExecucao(); // Reagendar para o próximo intervalo
    }, milissegundosRestantes);
}



document.addEventListener('DOMContentLoaded', () => {
    const isInicio = window.location.pathname.includes('inicio2.php');

    // Atualiza o contador ao carregar a página
    atualizarContadorTarefas();

    if (!isInicio) {
        agendarProximaExecucao();
    }
});

const sino = document.getElementById('icone-sino');

sino.addEventListener('click', function () {

    const result = confirm("Você quer ir para a página de revisão?");

    if (result) {
        window.open('https://improov.com.br/sistema/Revisao', '_blank');
    }
});