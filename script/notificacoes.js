var idusuario = localStorage.getItem('idusuario');
var colaborador_id = parseInt(localStorage.getItem('idcolaborador'));

function getUrlBuscarTarefas() {
    const idusuario = localStorage.getItem('idusuario');
    const colaborador_id = parseInt(localStorage.getItem('idcolaborador'));
    const query = `idusuario=${encodeURIComponent(idusuario)}&colaborador_id=${encodeURIComponent(colaborador_id)}`;
    return resolveImproovUrl(`buscar_tarefas.php?${query}`);
}


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
    fetch(getUrlBuscarTarefas(), {
        method: 'GET'
    })
        .then(response => response.json())
        .then(data => {
            const tarefas = data.tarefas || [];
            const notificacoesModulo = data.notificacoes_modulo || [];

            const contadorTarefas = document.getElementById('contador-tarefas');

            const qtdNotificacoes = (data.notificacoes || []).length + notificacoesModulo.length;

            if (tarefas.length > 0 || qtdNotificacoes > 0) {
                contadorTarefas.textContent = tarefas.length + qtdNotificacoes;
                document.title += ` (${tarefas.length + qtdNotificacoes})`;


            } else {
                contadorTarefas.textContent = '';
                contadorTarefas.style.display = 'none';

                const sino = document.getElementById('icone-sino');
                if (sino) sino.style.display = 'none';
            }
        })
        .catch(error => console.error('Erro ao buscar tarefas:', error));
}

let contagemTarefasGlobal = {};

let filaNotificacoesModulo = [];
let mostrandoNotificacaoModulo = false;
const notiModuloMostradas = new Set();

function configurarPdfJs() {
    if (window.pdfjsLib?.GlobalWorkerOptions) {
        // Prefer explicit PROJECT_ROOT when provided. Otherwise build an absolute
        // root using the current origin and prefer the "/flow/ImproovWeb" path
        // when the page is already under /flow/.
        let root = '';
        if (window.PROJECT_ROOT && String(window.PROJECT_ROOT).trim()) {
            root = String(window.PROJECT_ROOT).trim();
        } else {
            const origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
            const useFlow = String(window.location.pathname).includes('/flow/');
            root = origin + (useFlow ? '/flow/ImproovWeb' : '/ImproovWeb');
        }
        // Normalize (remove trailing slashes) and set worker src as absolute URL
        root = root.replace(/\/+$/, '');
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = root + '/assets/pdfjs/pdf.worker.min.js';
    }
}

function resolveImproovUrl(path) {
    let resolvedUrl = path;
    // If an absolute URL points to /ImproovWeb but is missing the /flow
    // prefix while the current page is under /flow/, insert /flow so
    // URLs like https://improov.com.br/ImproovWeb/... become
    // https://improov.com.br/flow/ImproovWeb/...
    try {
        const pageUseFlow = String(window.location.pathname).includes('/flow/');
        if (/^https?:\/\//i.test(resolvedUrl) && pageUseFlow && /\/ImproovWeb(\/|$)/i.test(resolvedUrl) && !/\/flow\/ImproovWeb/i.test(resolvedUrl)) {
            const m = resolvedUrl.match(/^(https?:\/\/[^\/]+)(\/.*)$/i);
            if (m) {
                resolvedUrl = m[1] + '/flow' + m[2];
            }
        }
    } catch (e) {
        console.debug('resolveImproovUrl: normalize flow prefix failed', e);
    }
    if (!/^https?:\/\//i.test(resolvedUrl)) {
        const origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
        const useFlow = String(window.location.pathname).includes('/flow/');
        const defaultRoot = origin + (useFlow ? '/flow/ImproovWeb' : '/ImproovWeb');

        if (resolvedUrl.startsWith('/')) {
            if (resolvedUrl.startsWith('/ImproovWeb') && useFlow && !resolvedUrl.startsWith('/flow/')) {
                resolvedUrl = origin + '/flow' + resolvedUrl;
            } else {
                resolvedUrl = origin + resolvedUrl;
            }
        } else {
            const base = (window.PROJECT_ROOT && String(window.PROJECT_ROOT).trim()) || defaultRoot;
            resolvedUrl = base.replace(/\/+$/, '') + '/' + resolvedUrl.replace(/^\/+/, '');
        }
    }
    return resolvedUrl;
}


function buscarTarefas(mostrarAlerta = true) {
    return fetch(getUrlBuscarTarefas(), {
        method: 'GET'
    })
        .then(response => response.json())
        .then(data => {
            console.debug('buscarTarefas: recebeu dados', data);
            const tarefas = data.tarefas || [];
            const notificacoes = data.notificacoes || [];
            const notificacoesModulo = data.notificacoes_modulo || [];
            const contadorTarefas = document.getElementById('contador-tarefas');

            if (tarefas.length > 0 || notificacoes.length > 0 || notificacoesModulo.length > 0) {
                contadorTarefas.textContent = tarefas.length + notificacoes.length + notificacoesModulo.length;
                ativarSino();

                // Contagem por função (considerando filtro por colaborador_id)
                const contagemPorFuncao = {};
                const funcoesPermitidas = filtrarFuncoesPorColaborador(colaborador_id);

                tarefas.forEach(tarefa => {
                    const funcao = tarefa.nome_funcao || 'Desconhecida';

                    if (funcoesPermitidas.includes(funcao)) {
                        contagemPorFuncao[funcao] = (contagemPorFuncao[funcao] || 0) + 1;
                    }
                });

                contagemTarefasGlobal = contagemPorFuncao; // Atualiza global

                const idsPermitidos = [1, 9, 19, 21];

                if (idsPermitidos.includes(colaborador_id) && mostrarAlerta) {
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


            if (mostrarAlerta && notificacoesModulo.length > 0) {
                console.debug('buscarTarefas: enfileirando notificacoesModulo', notificacoesModulo.length);
                enfileirarNotificacoesModulo(notificacoesModulo);
            }

            return { tarefas, notificacoes, notificacoesModulo };

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

async function agendarProximaExecucao() {
    const now = new Date();
    const minutos = now.getMinutes();
    const segundos = now.getSeconds();

    // Próximo intervalo 0 ou 30 minutos
    let proximosMinutos = minutos < 30 ? 30 : 60;
    const minutosRestantes = proximosMinutos - minutos;
    const milissegundosRestantes = (minutosRestantes * 60 - segundos) * 1000;

    setTimeout(async () => {
        buscarTarefas();
        try {
            await checkRenderItems(colaborador_id);
        } catch (e) {
            console.error('Erro ao verificar itens de render', e);
        }
        agendarProximaExecucao(); // Reagendar
    }, milissegundosRestantes);
}



document.addEventListener('DOMContentLoaded', () => {
    const isInicio = window.location.pathname.includes('inicio2.php');

    // Atualiza o contador ao carregar a página
    atualizarContadorTarefas();
    // Também buscar notificações do módulo ao carregar para enfileirar e mostrar modais imediatamente
    buscarTarefas(true).catch(() => { });
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
    const funcoes = filtrarFuncoesPorColaborador(colaborador_id);

    // 🔊 Som ao clicar
    const audio = new Audio('https://improov.com.br/flow/ImproovWeb/sons/not.mp3');
    audio.play();

    buscarTarefas(false).then(({ notificacoes, notificacoesModulo }) => {
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
                const safeMsg = (notificacao.mensagem || '').replace(/\r?\n/g, '<br>');
                htmlNotificacoes += `<p class="notificacao" data-not-id="${notificacao.id}">${safeMsg}</p><hr>`;
            });
        }

        if (notificacoesModulo.length > 0) {
            notificacoesModulo.forEach(notificacao => {
                const safeMsg = (notificacao.mensagem || '').replace(/\r?\n/g, '<br>');
                htmlNotificacoes += `
                    <div class="notificacao notificacao-modulo" data-not-mod-id="${notificacao.id}">
                        <strong>${notificacao.titulo || 'Notificação'}</strong><br>
                        <span>${safeMsg}</span>
                    </div>
                    <hr>
                `;
            });
        }

        if (!notificacoes.length && !notificacoesModulo.length) {
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
    window.open('https://improov.com.br/flow/ImproovWeb/Revisao', '_blank');
});


document.addEventListener('click', function (event) {
    if (event.target.classList.contains('notificacao')) {
        const notificacao = event.target;
        const idNotificacao = notificacao.getAttribute('data-not-id');

        if (idNotificacao) {
            fetch(`ler_notificacao.php?id=${idNotificacao}`, {
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
    const mesFormatado = String(mes).padStart(2, '0');

    fetch(`getFuncoesPorColaborador.php?colaborador_id=${colaborador_id}&ano=${ano}&mes=${mesFormatado}`)
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
    const mes = hoje.getMonth() + 1; // <- Certifique-se de definir o mês
    const mesFormatado = String(mes).padStart(2, '0');
    const ano = hoje.getFullYear();

    const modal = document.getElementById("modalPrimeiroDia");
    const radios = document.getElementsByName("situacao");
    const observacaoDiv = document.getElementById("observacaoDiv");
    const imagensList = document.getElementById("imagens_list_primeiro_dia");

    modal.style.display = "flex";

    fetch(`getFuncoesPorColaborador.php?colaborador_id=${colaborador_id}&ano=${ano}&mes=${mesFormatado}`)
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
    const situacao = document.querySelector('input[name="situacao"]:checked');
    const observacao = document.getElementById("observacaoTexto").value;
    const data = new Date().toISOString().slice(0, 10);

    if (!situacao) {
        alert("Selecione uma opção.");
        return;
    }

    const dados = {
        colaborador_id: colaborador_id,
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



// checkRenderItems também retorna uma Promise
function checkRenderItems(colaborador_id) {
    return new Promise((resolve, reject) => {
        fetch('https://improov.com.br/flow/ImproovWeb/verifica_render.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `colaborador_id=${colaborador_id}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.total > 0) {
                    Swal.fire({
                        title: `Você tem ${data.total} item(ns) na sua lista de render!`,
                        text: "Deseja ver agora ou depois?",
                        icon: "info",
                        showCancelButton: true,
                        confirmButtonText: "Ver agora",
                        cancelButtonText: "Ver depois",
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "./Render/";
                        } else {
                            resolve(); // segue o fluxo
                        }
                    });
                } else {
                    resolve(); // segue o fluxo mesmo sem render
                }
            })
            .catch(error => {
                console.error('Erro ao verificar itens de render:', error);
                reject();
            });
    });
}

function enfileirarNotificacoesModulo(lista) {
    lista.forEach(n => {
        if (!notiModuloMostradas.has(n.id)) {
            filaNotificacoesModulo.push(n);
            notiModuloMostradas.add(n.id);
        }
    });

    if (!mostrandoNotificacaoModulo) {
        mostrarProximaNotificacaoModulo();
    }
}

function mostrarProximaNotificacaoModulo() {
    if (filaNotificacoesModulo.length === 0) {
        mostrandoNotificacaoModulo = false;
        return;
    }

    mostrandoNotificacaoModulo = true;
    const notif = filaNotificacoesModulo.shift();
    abrirModalNotificacaoModulo(notif, () => {
        mostrarProximaNotificacaoModulo();
    });
}

function abrirModalNotificacaoModulo(notificacao, onClose) {
    const modalId = 'noti-modal-modulo';
    let modal = document.getElementById(modalId);

    if (!modal) {
        modal = document.createElement('div');
        modal.id = modalId;
        modal.className = 'noti-modal oculto';
        modal.innerHTML = `
            <div class="noti-modal__overlay"></div>
            <div class="noti-modal__panel">
                <button class="noti-modal__close" aria-label="Fechar">×</button>
                <div class="noti-modal__title"></div>
                <div class="noti-modal__message"></div>
                <div class="noti-modal__actions"></div>
                <div class="noti-modal__pdf" style="display:none;">
                    <canvas class="noti-modal__pdf-canvas"></canvas>
                </div>
                <div class="noti-modal__img" style="display:none;">
                    <img class="noti-modal__img-el" alt="Imagem da notificação" />
                </div>
                <div class="noti-modal__confirm" style="display:none;"></div>
            </div>
        `;
        document.body.appendChild(modal);

        modal.querySelector('.noti-modal__overlay')?.addEventListener('click', () => {
            const blocked = modal.getAttribute('data-block-close') === '1';
            if (!blocked) {
                fecharModalNotificacaoModulo(onClose);
            }
        });
    }

    const panel = modal.querySelector('.noti-modal__panel');
    const titleEl = modal.querySelector('.noti-modal__title');
    const msgEl = modal.querySelector('.noti-modal__message');
    const actionsEl = modal.querySelector('.noti-modal__actions');
    const pdfEl = modal.querySelector('.noti-modal__pdf');
    const imgEl = modal.querySelector('.noti-modal__img');
    const confirmEl = modal.querySelector('.noti-modal__confirm');

    if (panel) {
        panel.setAttribute('data-tipo', notificacao.tipo || 'info');
    }

    if (titleEl) titleEl.textContent = notificacao.titulo || 'Notificação';
    // Render message as HTML so formatting (bold, links, lists, etc.) appears as written
    // Convert plain-text newlines to <br> so stored plain text keeps visual breaks
    if (msgEl) msgEl.innerHTML = (notificacao.mensagem || '').replace(/\r?\n/g, '<br>');

    if (actionsEl) {
        actionsEl.innerHTML = '';
        const closeBtn = modal.querySelector('.noti-modal__close');
        if (closeBtn) {
            closeBtn.disabled = false;
            closeBtn.addEventListener('click', () => {
                const exige = notificacao.exige_confirmacao && String(notificacao.exige_confirmacao) !== '0';
                if (exige) {
                    confirmarNotificacaoModulo(notificacao.id, () => {
                        fecharModalNotificacaoModulo(onClose);
                    });
                } else {
                    fecharModalNotificacaoModulo(onClose);
                }
            });
        }

        if (notificacao.cta_label && notificacao.cta_url) {
            const btnCta = document.createElement('a');
            btnCta.textContent = notificacao.cta_label;
            btnCta.href = notificacao.cta_url;
            btnCta.target = '_blank';
            btnCta.rel = 'noopener noreferrer';
            btnCta.className = 'btn-noti primary';
            actionsEl.appendChild(btnCta);
        }

        if (notificacao.exige_confirmacao && String(notificacao.exige_confirmacao) !== '0') {
            modal.setAttribute('data-block-close', '1');
            if (closeBtn) {
                closeBtn.disabled = true;
                closeBtn.classList.add('disabled');
            }

            if (confirmEl) {
                confirmEl.style.display = '';
                confirmEl.innerHTML = `
                    <label class="noti-modal__checkbox">
                        <input type="checkbox" id="notiConfirmCheck">
                        <span>Li e entendi</span>
                    </label>
                `;

                const chk = confirmEl.querySelector('#notiConfirmCheck');
                if (chk) {
                    chk.addEventListener('change', (e) => {
                        const checked = e.target.checked;
                        if (checked) {
                            modal.setAttribute('data-block-close', '0');
                            if (closeBtn) {
                                closeBtn.disabled = false;
                                closeBtn.classList.remove('disabled');
                            }
                        } else {
                            modal.setAttribute('data-block-close', '1');
                            if (closeBtn) {
                                closeBtn.disabled = true;
                                closeBtn.classList.add('disabled');
                            }
                        }
                    });
                }
            }
        } else {
            modal.setAttribute('data-block-close', '0');
            if (confirmEl) {
                confirmEl.style.display = 'none';
                confirmEl.innerHTML = '';
            }
        }
    }

    if (pdfEl || imgEl) {
        const filePath = notificacao.arquivo_path || '';
        const fileName = notificacao.arquivo_nome || 'Arquivo';
        const fileRef = `${filePath} ${fileName}`.toLowerCase();
        const isPdf = /\.pdf(\s|$|\?)/i.test(fileRef);
        const isImage = /\.(png|jpe?g|gif|webp|bmp|svg)(\s|$|\?)/i.test(fileRef);

        if (filePath && isPdf && pdfEl) {
            pdfEl.style.display = '';
            if (imgEl) imgEl.style.display = 'none';

            const canvas = pdfEl.querySelector('.noti-modal__pdf-canvas');

            const resolvedUrl = resolveImproovUrl(filePath);
            if (canvas && window.pdfjsLib) {
                renderPdfInline(resolvedUrl, canvas);
                canvas.classList.add('clickable');
                canvas.title = 'Clique para ampliar';
                canvas.onclick = () => {
                    const rawUrl = resolvedUrl;
                    if (typeof openPdfViewerModal === 'function') {
                        openPdfViewerModal({
                            rawUrl,
                            downloadUrl: rawUrl,
                            titulo: fileName
                        });
                    } else {
                        window.open(rawUrl, '_blank');
                    }
                };
            }
        } else if (filePath && isImage && imgEl) {
            imgEl.style.display = '';
            if (pdfEl) pdfEl.style.display = 'none';

            const imgTag = imgEl.querySelector('.noti-modal__img-el');

            const resolvedUrl = resolveImproovUrl(filePath);
            if (imgTag) {
                imgTag.src = resolvedUrl;
                imgTag.classList.add('clickable');
                imgTag.title = 'Clique para ampliar';
                imgTag.onclick = () => {
                    const rawUrl = resolvedUrl;
                    if (typeof openImageViewerModal === 'function') {
                        openImageViewerModal({
                            rawUrl,
                            titulo: fileName
                        });
                    } else {
                        window.open(rawUrl, '_blank');
                    }
                };
            }
        } else {
            if (pdfEl) pdfEl.style.display = 'none';
            if (imgEl) imgEl.style.display = 'none';
        }
    }

    console.debug('abrirModalNotificacaoModulo: abrindo notificação', notificacao.id);
    modal.classList.remove('oculto');

    marcarNotificacaoModuloVisto(notificacao.id);
}

function fecharModalNotificacaoModulo(onClose) {
    const modal = document.getElementById('noti-modal-modulo');
    if (modal) {
        modal.classList.add('oculto');
    }

    if (typeof onClose === 'function') {
        onClose();
    }
}

function marcarNotificacaoModuloVisto(id) {
    const statusUrl = resolveImproovUrl('notificacao_modulo_status.php');
    fetch(statusUrl, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(id)}&action=visto`
    }).catch(() => { });
}

function confirmarNotificacaoModulo(id, onClose) {
    const statusUrl = resolveImproovUrl('notificacao_modulo_status.php');
    fetch(statusUrl, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(id)}&action=confirmado`
    })
        .then(response => {
            return response.json().catch(() => ({}));
        })
        .then(data => {
            const ok = data && (data.ok === true || data.success === true || data.result === true);
            if (ok) {
                if (typeof onClose === 'function') onClose();
            } else {
                // não fechar se servidor não retornar ok
                console.warn('confirmarNotificacaoModulo: servidor retornou não ok', data);
            }
        })
        .catch(err => {
            console.error('Erro ao confirmar notificação:', err);
        });
}

async function renderPdfInline(url, canvas) {
    try {
        if (!/\.pdf(\?|$)/i.test(String(url))) {
            return;
        }
        configurarPdfJs();
        const loadingTask = window.pdfjsLib.getDocument(url);
        const pdf = await loadingTask.promise;
        const page = await pdf.getPage(1);
        const viewport = page.getViewport({ scale: 1.1 });
        const ctx = canvas.getContext('2d');
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        await page.render({ canvasContext: ctx, viewport }).promise;
    } catch (e) {
        console.error('Erro ao renderizar PDF:', e);
    }
}