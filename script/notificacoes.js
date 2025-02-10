const idusuario = localStorage.getItem('idusuario');

if (idusuario === '1' || idusuario === '2' || idusuario === '9') {

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
                if (tarefas.length > 0) {
                    // Notificação Web
                    const contadorTarefas = document.getElementById('contador-tarefas');
                    contadorTarefas.textContent = tarefas.length;

                    ativarSino();


                } else {

                    const contadorTarefas = document.getElementById('contador-tarefas');
                    contadorTarefas.textContent = '';
                }
            })
            .catch(error => console.error('Erro ao buscar tarefas:', error));
    }

    function agendarProximaExecucao() {
        const now = new Date();
        const minutos = now.getMinutes();
        const segundos = now.getSeconds();

        // Calcular próximo intervalo (00, 15, 30, 45)
        const proximosMinutos = [0, 15, 30, 45].find(min => min > minutos) || 60;
        const minutosRestantes = proximosMinutos === 60 ? 60 - minutos : proximosMinutos - minutos;
        const milissegundosRestantes = (minutosRestantes * 60 - segundos) * 1000;

        setTimeout(() => {
            buscarTarefas();
            agendarProximaExecucao(); // Reagendar para o próximo intervalo
        }, milissegundosRestantes);
    }


}

document.addEventListener('DOMContentLoaded', () => {
    const isInicio = window.location.pathname.includes('inicio.php');

    // Atualiza o contador ao carregar a página
    atualizarContadorTarefas();

    if (isInicio) {
        // Mostrar as tarefas imediatamente na página de início com notificação
        buscarTarefas();
    } else {
        // Mostrar notificações somente após 15 minutos nas outras páginas
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