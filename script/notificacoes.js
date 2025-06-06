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
        .then(data => {
            const tarefas = data.tarefas || [];

            const contadorTarefas = document.getElementById('contador-tarefas');

            if (tarefas.length > 0 || data.notificacoes.length > 0) {
                contadorTarefas.textContent = tarefas.length + data.notificacoes.length;

            } else {
                contadorTarefas.textContent = '';
                contadorTarefas.style.display = 'none';
                sino.style.display = 'none';
            }
        })
        .catch(error => console.error('Erro ao buscar tarefas:', error));
}

let contagemTarefasGlobal = {};


function buscarTarefas(mostrarAlerta = true) {
    return fetch('https://improov.com.br/sistema/buscar_tarefas.php', {
        method: 'GET'
    })
        .then(response => response.json())
        .then(data => {
            const tarefas = data.tarefas || [];
            const notificacoes = data.notificacoes || [];
            const contadorTarefas = document.getElementById('contador-tarefas');
            const idColaborador = parseInt(localStorage.getItem('idcolaborador'));

            if (tarefas.length > 0 || notificacoes.length > 0) {
                contadorTarefas.textContent = tarefas.length + notificacoes.length;
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

                contagemTarefasGlobal = contagemPorFuncao; // Atualiza global

                const idsPermitidos = [1, 9, 19, 21];

                if (idsPermitidos.includes(idColaborador) && mostrarAlerta) {
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
                contagemTarefasGlobal = {};
                console.log("Nenhuma tarefa pendente.");
            }


            return { tarefas, notificacoes };

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
            return ['Finalização', 'Pós-produção', 'Modelagem', 'Composição', 'Caderno', 'Filtro de assets']; // Todas
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
    // avisoUltimoDiaUtil()
    if (!isInicio) {
        agendarProximaExecucao();
    }
});

const sino = document.getElementById('icone-sino');
const popover = document.getElementById('popover-tarefas');
const conteudoTarefas = document.getElementById('conteudo-tarefas');
const conteudoNotificacoes = document.getElementById('conteudo-notificacoes');
const btnIr = document.getElementById('btn-ir-revisao');

const badgeTarefas = document.getElementById('badge-tarefas');
const badgeNotificacoes = document.getElementById('badge-notificacoes');

sino.addEventListener('click', function () {
    const idColaborador = parseInt(localStorage.getItem('idcolaborador'));
    const funcoes = filtrarFuncoesPorColaborador(idColaborador);

    // 🔊 Som ao clicar
    const audio = new Audio('https://improov.com.br/sistema/sons/not.mp3');
    audio.play();

    buscarTarefas(false).then(({ notificacoes }) => {
        // --- TAREFAS ---
        let htmlTarefas = '';
        let qtdTarefas = 0;

        for (const funcao of funcoes) {
            if (contagemTarefasGlobal[funcao]) {
                htmlTarefas += `<p><strong>${funcao}</strong>: ${contagemTarefasGlobal[funcao]} tarefas</p>`;
                qtdTarefas += contagemTarefasGlobal[funcao];
            }
        }

        conteudoTarefas.innerHTML = htmlTarefas || '<p>Sem tarefas para você.</p>';

        let htmlNotificacoes = '';

        if (notificacoes.length > 0) {
            notificacoes.forEach(notificacao => {
                htmlNotificacoes += `<p class="notificacao" data-not-id="${notificacao.id}">${notificacao.mensagem}</p><hr>`;
            });
        } else {
            htmlNotificacoes = '';
        }

        conteudoNotificacoes.innerHTML = htmlNotificacoes;

        // --- Atualiza badges ---
        badgeTarefas.textContent = qtdTarefas;
        const qtdNotificacoes = conteudoNotificacoes.querySelectorAll('p').length;
        if (qtdTarefas === 0) {
            document.querySelector('.secao-tarefas').classList.add('oculto');
            btnIr.classList.add('oculto');
        }
        if (qtdNotificacoes === 0) {
            document.querySelector('.secao-notificacoes').classList.add('oculto');
        }
        badgeNotificacoes.textContent = qtdNotificacoes;

        // --- Mostrar popover ---
        popover.classList.toggle('oculto');

        // --- Posicionamento do popover ---
        const sinoRect = sino.getBoundingClientRect();
        const popoverHeight = popover.offsetHeight;
        const popoverWidth = popover.offsetWidth;

        const top = sinoRect.top - popoverHeight - 10;
        const left = sinoRect.left - popoverWidth + 20;

        popover.style.top = `${top}px`;
        popover.style.left = `${left}px`;
    });
});


function toggleSecao(secao) {
    const conteudo = document.getElementById(`conteudo-${secao}`);
    const isHidden = conteudo.classList.contains('oculto');
    conteudo.classList.toggle('oculto', !isHidden);
}

// Oculta o popover se clicar fora
document.addEventListener('click', function (event) {
    if (!popover.contains(event.target) && event.target !== sino) {
        popover.classList.add('oculto');
    }
});

// Botão "Ir para Revisão"
btnIr.addEventListener('click', function () {
    window.open('https://improov.com.br/sistema/Revisao', '_blank');
});


document.addEventListener('click', function (event) {
    if (event.target.classList.contains('notificacao')) {
        const notificacao = event.target;
        const idNotificacao = notificacao.getAttribute('data-not-id');

        if (idNotificacao) {
            fetch(`https://improov.com.br/sistema/ler_notificacao.php?id=${idNotificacao}`, {
                method: 'POST'
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Notificação lida:', data);

                    // ✨ Efeito visual de sumir
                    notificacao.classList.add('fade-out');
                    setTimeout(() => {
                        notificacao.remove();
                    }, 200);
                    atualizarContadorTarefas(); // Atualiza as tarefas após marcar como lida
                    popover.classList.add('oculto');

                })
                .catch(error => console.error('Erro ao marcar notificação como lida:', error));
        }
    }
});



function exibirAvisoUltimoDiaUtil() {
    const hoje = new Date();
    const ano = hoje.getFullYear();
    const mes = hoje.getMonth() + 1;

    const ultimoDiaDoMes = new Date(ano, mes, 0);
    let ultimoDiaUtil = new Date(ultimoDiaDoMes);

    while (ultimoDiaUtil.getDay() === 0 || ultimoDiaUtil.getDay() === 6) {
        ultimoDiaUtil.setDate(ultimoDiaUtil.getDate() - 1);
    }

    const hojeStr = hoje.toISOString().slice(0, 10);
    const ultimoDiaStr = ultimoDiaUtil.toISOString().slice(0, 10);

    if (hojeStr !== ultimoDiaStr) {
        console.log("Hoje não é o último dia útil do mês.");
        return;
    }

    // Mostrar modal
    const modal = document.getElementById("modalLastDay");
    const textoAlerta = document.getElementById("textoAlerta");
    const imagensList = document.getElementById("imagens-list");

    textoAlerta.innerHTML = `
        <h2>Hoje é o último dia útil do mês.</h2>
        <p>Atualize todos os status e prazos das tarefas que você trabalhou nesse mês!</p>
    `;

    imagensList.innerHTML = `<p>Carregando produção do mês...</p>`;

    modal.style.display = "flex";

    // Buscar e exibir a lista de produção
    const idColaborador = parseInt(localStorage.getItem('idcolaborador'));
    const mesFormatado = String(mes).padStart(2, '0');

    fetch(`getFuncoesPorColaborador.php?colaborador_id=${idColaborador}&ano=${ano}&mes=${mesFormatado}`)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                imagensList.innerHTML = `<p style="color:red;">Nenhuma produção registrada neste mês.</p>`;
                return;
            }

            let html = '<ul style="text-align: left">';
            data.forEach((item, i) => {
                html += `<li><strong>${i + 1}.</strong> ${item.imagem_nome} — ${item.nome_funcao}</li>`;
            });
            html += '</ul>';

            imagensList.innerHTML = html;
        })
        .catch(err => {
            imagensList.innerHTML = `<p style="color:red;">Erro ao buscar dados de produção.</p>`;
            console.error(err);
        });
}


function exibirModalPrimeiroDiaUtil() {
    if (!isHojePrimeiroDiaUtil()) return;

    const modal = document.getElementById("modalPrimeiroDia");
    const radios = document.getElementsByName("situacao");
    const observacaoDiv = document.getElementById("observacaoDiv");

    modal.style.display = "block";

    // Mostrar campo de observação se "alteracao" for selecionado
    radios.forEach(radio => {
        radio.addEventListener('change', () => {
            observacaoDiv.style.display = (radio.value === "alteracao" && radio.checked) ? 'block' : 'none';
        });
    });
}

function isHojePrimeiroDiaUtil() {
    const hoje = new Date();
    const ano = hoje.getFullYear();
    const mes = hoje.getMonth(); // 0 a 11

    let primeiroDiaUtil = new Date(ano, mes, 1);

    while (primeiroDiaUtil.getDay() === 0 || primeiroDiaUtil.getDay() === 6) {
        primeiroDiaUtil.setDate(primeiroDiaUtil.getDate() + 1);
    }

    const hojeStr = hoje.toISOString().slice(0, 10);
    const primeiroStr = primeiroDiaUtil.toISOString().slice(0, 10);

    return hojeStr === primeiroStr;
}


function exibirModalPrimeiroDiaUtil() {
    // if (!isHojePrimeiroDiaUtil()) return;

    // Buscar e exibir a lista de produção
    const hoje = new Date();
    const idColaborador = parseInt(localStorage.getItem('idcolaborador'));
    const mes = hoje.getMonth() + 1; // <- Certifique-se de definir o mês
    const mesFormatado = String(mes).padStart(2, '0');
    const ano = hoje.getFullYear();

    const modal = document.getElementById("modalPrimeiroDia");
    const radios = document.getElementsByName("situacao");
    const observacaoDiv = document.getElementById("observacaoDiv");
    const imagensList = document.getElementById("imagens_list_primeiro_dia");

    modal.style.display = "flex";

    fetch(`getFuncoesPorColaborador.php?colaborador_id=${idColaborador}&ano=${ano}&mes=${mesFormatado}`)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                imagensList.innerHTML = `<p style="color:red;">Nenhuma produção registrada neste mês.</p>`;
                return;
            }

            let html = '<ul style="text-align: left">';
            data.forEach((item, i) => {
                html += `<li><strong>${i + 1}.</strong> ${item.imagem_nome} — ${item.nome_funcao}</li>`;
            });
            html += '</ul>';

            imagensList.innerHTML = html;
        })
        .catch(err => {
            imagensList.innerHTML = `<p style="color:red;">Erro ao buscar dados de produção.</p>`;
            console.error(err);
        });

    // Mostrar campo de observação se "alteracao" for selecionado
    radios.forEach(radio => {
        radio.addEventListener('change', () => {
            observacaoDiv.style.display = (radio.value === "alteracao" && radio.checked) ? 'block' : 'none';
        });
    });
}

function enviarRevisaoMes() {
    const idColaborador = parseInt(localStorage.getItem('idcolaborador'));
    const situacao = document.querySelector('input[name="situacao"]:checked');
    const observacao = document.getElementById("observacaoTexto").value;
    const data = new Date().toISOString().slice(0, 10);

    if (!situacao) {
        alert("Selecione uma opção.");
        return;
    }

    const dados = {
        idcolaborador: idColaborador,
        situacao: situacao.value,
        observacao: observacao,
        data: data
    };

    fetch("salvarRevisaoMes.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(dados)
    })
        .then(response => response.text())
        .then(res => {
            alert("Revisão enviada com sucesso.");
            document.getElementById("modalPrimeiroDia").style.display = "none";
        })
        .catch(err => {
            alert("Erro ao enviar revisão.");
            console.error(err);
        });
}
