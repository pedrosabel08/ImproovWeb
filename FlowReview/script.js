// ================= NOVO FLUXO INICIAL =================
// Ao carregar a página buscamos entregas da obra fixa (74) e agrupamos versões por imagem.
// Mostramos automaticamente a primeira imagem (última versão) e thumbnails das versões.
// On load: ensure authentication first, then initialize the app.
document.addEventListener('DOMContentLoaded', async function () {
    try {
        const auth = await checkAuth();
        if (!auth.authenticated) {
            showAuthOverlay();
            attachAuthHandlers();
        } else {
            try { localStorage.setItem('idusuario', String(auth.idusuario || '')); } catch (e) {}
            hideAuthOverlay();
            carregarImagensAgrupadas();
        }
    } catch (e) {
        console.error('Auth check failed:', e);
        // fallback: show auth UI so the user can login
        showAuthOverlay();
        attachAuthHandlers();
    }
});

async function checkAuth() {
    try {
        const r = await fetch('auth_check.php', { credentials: 'same-origin' });
        if (!r.ok) throw new Error('Auth check failed');
        return await r.json();
    } catch (e) {
        console.error('checkAuth error', e);
        return { authenticated: false };
    }
}

function showAuthOverlay() {
    const overlay = document.querySelector('.auth-page');
    if (overlay) overlay.style.display = 'flex';
}

function hideAuthOverlay() {
    const overlay = document.querySelector('.auth-page');
    if (overlay) overlay.style.display = 'none';
}

function attachAuthHandlers() {
    // tab buttons
    const showLogin = document.getElementById('showLogin');
    const showRegister = document.getElementById('showRegister');
    if (showLogin) showLogin.addEventListener('click', () => { document.getElementById('loginBox').style.display = ''; document.getElementById('registerBox').style.display = 'none'; });
    if (showRegister) showRegister.addEventListener('click', () => { document.getElementById('loginBox').style.display = 'none'; document.getElementById('registerBox').style.display = ''; });

    // login form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const fd = new FormData(loginForm);
            try {
                const resp = await fetch(loginForm.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' });
                const json = await resp.json();
                if (json.success) {
                    if (json.idusuario) localStorage.setItem('idusuario', String(json.idusuario));
                    Toastify({ text: 'Login realizado com sucesso', duration: 2500, backgroundColor: 'green', close: true, gravity: 'top', position: 'right' }).showToast();
                    hideAuthOverlay();
                    carregarImagensAgrupadas();
                } else {
                    Toastify({ text: json.message || 'Credenciais inválidas', duration: 4000, backgroundColor: 'red', close: true, gravity: 'top', position: 'right' }).showToast();
                }
            } catch (err) {
                console.error('login error', err);
                Toastify({ text: 'Erro ao contatar o servidor. Tente novamente.', duration: 4000, backgroundColor: 'red', close: true, gravity: 'top', position: 'right' }).showToast();
            }
        });
    }

    // register form
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const fd = new FormData(registerForm);
            try {
                const resp = await fetch(registerForm.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' });
                const json = await resp.json();
                if (json.success) {
                    if (json.idusuario) localStorage.setItem('idusuario', String(json.idusuario));
                    Toastify({ text: 'Conta criada e logado', duration: 2500, backgroundColor: 'green', close: true, gravity: 'top', position: 'right' }).showToast();
                    hideAuthOverlay();
                    carregarImagensAgrupadas();
                } else {
                    Toastify({ text: json.message || 'Falha ao registrar', duration: 4000, backgroundColor: 'red', close: true, gravity: 'top', position: 'right' }).showToast();
                }
            } catch (err) {
                console.error('register error', err);
                Toastify({ text: 'Erro ao contatar o servidor. Tente novamente.', duration: 4000, backgroundColor: 'red', close: true, gravity: 'top', position: 'right' }).showToast();
            }
        });
    }
}

// Lê token da query string (ex: ?token=...)
function getTokenFromUrl() {
    try {
        // 1) Querystring fallback: ?token=...
        const params = new URLSearchParams(window.location.search);
        const q = params.get('token');
        if (q && q !== '') return q;

        // 2) Path-based token support for URLs like:
        //    /sistema/FlowReview/<token>
        //    /sistema/FlowReview/token/<token>
        const path = window.location.pathname || '';
        const parts = path.split('/').filter(Boolean); // remove empty segments
        // find 'FlowReview' segment
        const idx = parts.indexOf('FlowReview');
        if (idx === -1) return null;
        const next = parts[idx + 1];
        if (!next) return null;
        if (next === 'token') return parts[idx + 2] || null;
        return next;
    } catch (e) {
        return null;
    }
}

let imagensAgrupadasGlobal = []; // [{ imagem_id, preview_url, versoes:[...], totalVersoes }]

async function carregarImagensAgrupadas() {
    try {
        const dados = await fetchEntregasObraFixa(); // { entregas: [...] }
        imagensAgrupadasGlobal = agruparPorImagem(dados.entregas || []);
        exibirGridImagens(imagensAgrupadasGlobal);
        if (imagensAgrupadasGlobal.length > 0) {
            historyAJAX(imagensAgrupadasGlobal[0].imagem_id);
        }
    } catch (e) {
        console.error('Erro ao carregar imagens agrupadas:', e);
    }
}

async function fetchEntregasObraFixa() {
    const token = getTokenFromUrl();
    const url = token ? `atualizar.php?token=${encodeURIComponent(token)}` : 'atualizar.php';
    const r = await fetch(url, { credentials: 'same-origin' });
    if (!r.ok) throw new Error('Falha ao buscar entregas');
    return await r.json();
}

function agruparPorImagem(entregas) {
    const mapa = new Map();
    entregas.forEach(ent => {
        const identrega = ent.identrega;
        const nomeEtapa = ent.nome_etapa;
        (ent.itens || []).forEach(item => {
            const imagemId = item.imagem_id;
            if (!imagemId) return;
            if (!mapa.has(imagemId)) {
                mapa.set(imagemId, {
                    imagem_id: imagemId,
                    versoes: [],
                    funcao_imagem_ids: new Set(),
                    nome_funcao: item.nome_funcao || '',
                    nome_colaborador: item.nome_colaborador || '',
                    nome_status_imagem: item.nome_status_imagem || ''
                });
            }
            const grupo = mapa.get(imagemId);
            grupo.versoes.push({
                entrega_id: identrega,
                entrega_item_id: item.id,
                nome_etapa: nomeEtapa,
                historico_imagem_id: item.historico_imagem_id,
                nome_arquivo: item.nome_arquivo,
                imagem_nome: item.imagem_nome,
                indice_envio: item.indice_envio || 0,
                data_envio: item.data_envio,
                imagem_url: item.imagem,
                idfuncao_imagem: item.idfuncao_imagem,
                colaborador_id: item.colaborador_id,
                nome_funcao: item.nome_funcao
            });
            if (item.idfuncao_imagem) grupo.funcao_imagem_ids.add(item.idfuncao_imagem);
        });
    });
    const resultado = [];
    mapa.forEach(grupo => {
        grupo.versoes.sort((a, b) => {
            const da = a.data_envio ? new Date(a.data_envio) : null;
            const db = b.data_envio ? new Date(b.data_envio) : null;
            if (da && db && da.getTime() !== db.getTime()) return db - da;
            return b.entrega_id - a.entrega_id;
        });
        grupo.preview_url = (grupo.versoes[0] && grupo.versoes[0].imagem_url) || null;
        grupo.totalVersoes = grupo.versoes.length;
        resultado.push(grupo);
    });
    return resultado;
}

function exibirGridImagens(imagens) {
    const container = document.querySelector('.containerObra');
    if (!container) return;
    container.innerHTML = '';
    container.style.display = 'grid';
    container.style.gridTemplateColumns = 'repeat(auto-fill, minmax(220px, 1fr))';
    container.style.gap = '16px';
    if (imagens.length === 0) {
        container.innerHTML = '<p style="text-align:center;color:#888;">Nenhuma imagem encontrada.</p>';
        return;
    }
    imagens.forEach(img => {
        const card = document.createElement('div');
        card.className = 'imagem-card';
        card.style.cursor = 'pointer';
        card.style.border = '1px solid #ddd';
        card.style.borderRadius = '8px';
        card.style.overflow = 'hidden';
        card.style.background = '#fff';
        card.style.boxShadow = '0 2px 4px rgba(0,0,0,0.08)';
        card.innerHTML = `
          <div class="imagem-thumb" style="height:150px;display:flex;align-items:center;justify-content:center;background:#f8f8f8;">
            ${img.preview_url ? `<img src="${img.preview_url}" alt="Imagem ${img.imagem_id}" style="max-width:100%;max-height:100%;object-fit:contain;">` : '<span style="color:#aaa;font-size:12px;">Sem preview</span>'}
          </div>
          <div style="padding:8px 10px;">
            <div style="font-size:13px;font-weight:600;">Imagem #${img.imagem_id}</div>
            <div style="font-size:12px;color:#555;">${img.nome_funcao || 'Função não definida'}</div>
            <div style="font-size:11px;color:#777;">${img.nome_colaborador || ''}</div>
            <div style="margin-top:6px;font-size:11px;color:#444;">Versões: ${img.totalVersoes}</div>
          </div>
        `;
        card.addEventListener('click', () => historyAJAX(img.imagem_id));
        container.appendChild(card);
    });
}

// Modal de versões removido (uso direto)
function abrirModalVersoes() { /* placeholder */ }
// ================= FIM NOVO FLUXO INICIAL =================

function revisarTarefa(idfuncao_imagem, nome_colaborador, imagem_nome, nome_funcao, colaborador_id, tipoRevisao) {
    const idcolaborador = localStorage.getItem('idcolaborador');

    let actionText = "";
    switch (tipoRevisao) {
        case "aprovado":
            actionText = "aprovar esta tarefa";
            break;
        case "ajuste":
            actionText = "marcar esta tarefa como necessitando de ajustes";
            break;
        case "aprovado_com_ajustes":
            actionText = "aprovar com ajustes";
            break;
    }

    if (confirm(`Você tem certeza de que deseja ${actionText}?`)) {
        fetch('revisarTarefa.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                idfuncao_imagem,
                nome_colaborador,
                imagem_nome,
                nome_funcao,
                colaborador_id,
                responsavel: idcolaborador,
                tipoRevisao
            }),
        })
            .then(response => {
                if (!response.ok) throw new Error("Erro ao atualizar a tarefa.");
                return response.json();
            })
            .then(data => {
                let message = "";
                let bgColor = "";
                switch (tipoRevisao) {
                    case "aprovado":
                        message = "Tarefa aprovada com sucesso!";
                        bgColor = "green";
                        break;
                    case "ajuste":
                        message = "Tarefa marcada como necessitando de ajustes!";
                        bgColor = "orange";
                        break;
                    case "aprovado_com_ajustes":
                        message = "Tarefa aprovada com ajustes!";
                        bgColor = "blue";
                        break;
                }
                Toastify({
                    text: data.success ? message : "Falha ao atualizar a tarefa: " + data.message,
                    duration: 3000,
                    backgroundColor: data.success ? bgColor : "red",
                    close: true,
                    gravity: "top",
                    position: "right"
                }).showToast();
            })
            .catch(error => {
                console.error("Erro:", error);
                Toastify({
                    text: "Ocorreu um erro ao processar a solicitação. " + error.message,
                    duration: 3000,
                    backgroundColor: "red",
                    close: true,
                    gravity: "top",
                    position: "right"
                }).showToast();
            });
    }
    event.stopPropagation();
}

function toggleTaskDetails(taskElement) { taskElement.classList.toggle('open'); }

let dadosTarefas = [];
let todasAsObras = new Set();
let todosOsColaboradores = new Set();
let todasAsFuncoes = new Set();
let funcaoGlobalSelecionada = null;

async function fetchObrasETarefas() {
    try {
        const token = getTokenFromUrl();
        const url = token ? `atualizar.php?token=${encodeURIComponent(token)}` : 'atualizar.php';
        const response = await fetch(url);
        if (!response.ok) throw new Error("Erro ao buscar tarefas");
        const json = await response.json();
        const plano = [];
        if (json && json.entregas) {
            json.entregas.forEach(ent => {
                (ent.itens || []).forEach(item => {
                    plano.push({
                        nome_obra: 'Obra 74',
                        imagem: item.imagem,
                        imagem_id: item.imagem_id,
                        idfuncao_imagem: item.idfuncao_imagem,
                        nome_colaborador: item.nome_colaborador,
                        nome_funcao: item.nome_funcao,
                        nome_status: item.nome_status_imagem,
                        data_aprovacao: item.data_envio,
                        nomenclatura: `Imagem ${item.imagem_id}`,
                        status_novo: item.nome_status_imagem
                    });
                });
            });
        }
        dadosTarefas = plano;
        todasAsObras = new Set(plano.map(t => t.nome_obra));
        todosOsColaboradores = new Set(plano.map(t => t.nome_colaborador));
        todasAsFuncoes = new Set(plano.map(t => t.nome_funcao));
        const filtroSelect = document.getElementById('filtroFuncao');
        if (filtroSelect) filtroSelect.style.display = 'none';
    } catch (error) {
        console.error(error);
    }
}

async function buscarMencoesDoUsuario() { const r = await fetch('buscar_mencoes.php'); return await r.json(); }

async function exibirCardsDeObra(tarefas) {
    const mencoes = await buscarMencoesDoUsuario();
    if (mencoes.total_mencoes > 0) {
        Swal.fire({
            title: '📣 Você foi mencionado!',
            text: `Há ${mencoes.total_mencoes} menção(ões) nas tarefas.`,
            icon: 'info', confirmButtonText: 'Ver cards'
        });
    }
    const container = document.querySelector('.containerObra');
    container.innerHTML = '';
    const obrasMap = new Map();
    tarefas.forEach(t => { if (!obrasMap.has(t.nome_obra)) obrasMap.set(t.nome_obra, []); obrasMap.get(t.nome_obra).push(t); });
    obrasMap.forEach((tarefasDaObra, nome_obra) => {
        tarefasDaObra.sort((a, b) => new Date(b.data_aprovacao) - new Date(a.data_aprovacao));
        const tarefaComImagem = tarefasDaObra.find(t => t.imagem);
        const imagemPreview = tarefaComImagem ? `https://improov.com.br/sistema/${tarefaComImagem.imagem}` : '../assets/logo.jpg';
        const mencoesNaObra = mencoes.mencoes_por_obra[nome_obra] || 0;
        const card = document.createElement('div'); card.classList.add('obra-card');
        card.innerHTML = `
          ${mencoesNaObra > 0 ? `<div class="mencao-badge">${mencoesNaObra}</div>` : ''}
          <div class="obra-img-preview"><img src="${imagemPreview}" alt="Imagem da obra ${nome_obra}"></div>
          <div class="obra-info"><h3>${tarefasDaObra[0].nomenclatura}</h3><p>${tarefasDaObra.length} aprovações</p></div>
        `;
        card.addEventListener('click', () => filtrarTarefasPorObra(nome_obra));
        container.appendChild(card);
    });
}

function filtrarTarefasPorObra(obraSelecionada) {
    document.getElementById('filtro_obra').value = obraSelecionada;
    const tarefasDaObra = dadosTarefas.filter(t => t.nome_obra === obraSelecionada);
    atualizarFiltrosDinamicos(tarefasDaObra);
    const colaboradorSelecionado = document.getElementById('filtro_colaborador').value;
    if (tarefasDaObra.length > 0) {
        const nomeObra = tarefasDaObra[0].nome_obra;
        const nomenclatura = tarefasDaObra[0].nomenclatura;
        document.querySelectorAll('.obra_nav').forEach(link => { link.href = `https://improov.com.br/sistema/Revisao/index.php?obra_nome=${nomeObra}`; link.textContent = nomenclatura; });
    }
    const tarefasFiltradas = tarefasDaObra.filter(t => !colaboradorSelecionado || t.nome_colaborador === colaboradorSelecionado);
    exibirTarefas(tarefasFiltradas, tarefasDaObra);
}

function atualizarSelectColaborador(tarefas) {
    const selectColaborador = document.getElementById('filtro_colaborador');
    const valorAnterior = selectColaborador.value;
    const colaboradores = [...new Set(tarefas.map(t => t.nome_colaborador))];
    selectColaborador.innerHTML = '<option value="">Todos</option>';
    colaboradores.forEach(colab => { const option = document.createElement('option'); option.value = colab; option.textContent = colab; selectColaborador.appendChild(option); });
    if ([...selectColaborador.options].some(o => o.value === valorAnterior)) selectColaborador.value = valorAnterior;
}

function atualizarSelectFuncao() { }

function atualizarFiltrosDinamicos(tarefas) {
    const selectColaborador = document.getElementById('filtro_colaborador');
    const valorAnteriorColaborador = selectColaborador.value;
    const colaboradores = [...new Set(tarefas.map(t => t.nome_colaborador))];
    selectColaborador.innerHTML = '<option value="">Todos</option>';
    colaboradores.forEach(col => { const o = document.createElement('option'); o.value = col; o.textContent = col; selectColaborador.appendChild(o); });
    if ([...selectColaborador.options].some(o => o.value === valorAnteriorColaborador)) selectColaborador.value = valorAnteriorColaborador;
}

document.getElementById('filtro_colaborador').addEventListener('change', () => {
    const obraSelecionada = document.getElementById('filtro_obra').value;
    const colaboradorSelecionado = document.getElementById('filtro_colaborador').value;
    const tarefasDaObra = dadosTarefas.filter(t => t.nome_obra === obraSelecionada);
    const tarefasFiltradas = tarefasDaObra.filter(t => !colaboradorSelecionado || t.nome_colaborador === colaboradorSelecionado);
    atualizarSelectColaborador(tarefasFiltradas);
    filtrarTarefasPorObra(obraSelecionada);
});

function exibirTarefas(tarefas, tarefasCompletas) {
    const container = document.querySelector('.containerObra');
    container.style.display = 'none';
    document.getElementById('filtroFuncao').style.display = 'none';
    const tarefasObra = document.querySelector('.tarefasObra');
    tarefasObra.classList.remove('hidden');
    const tarefasImagensObra = document.querySelector('.tarefasImagensObra');
    tarefasImagensObra.innerHTML = '';
    exibirSidebarTabulator(tarefasCompletas);
    if (tarefas.length > 0) {
        tarefas.forEach(tarefa => {
            const taskItem = document.createElement('div');
            taskItem.classList.add('task-item');
            taskItem.setAttribute('onclick', `historyAJAX(${tarefa.imagem_id})`);
            const imagemPreview = tarefa.imagem ? `https://improov.com.br/sistema/${tarefa.imagem}` : '../assets/logo.jpg';
            const color = tarefa.status_novo === 'Em aprovação' ? '#000a59' : tarefa.status_novo === 'Ajuste' ? '#590000' : tarefa.status_novo === 'Aprovado com ajustes' ? '#2e0059ff' : 'transparent';
            const bgColor = tarefa.status_novo === 'Em aprovação' ? '#90c2ff' : tarefa.status_novo === 'Ajuste' ? '#ff5050' : tarefa.status_novo === 'Aprovado com ajustes' ? '#ae90ffff' : 'transparent';
            taskItem.innerHTML = `
              <div class="task-info">
                <div class="image-wrapper"><img src="${imagemPreview}" alt="Imagem da obra ${tarefa.nome_obra}" class="task-image" onerror="this.onerror=null;this.src='../assets/logo.jpg';"></div>
                <h3 class="nome_funcao">${tarefa.nome_status || tarefa.status_novo}</h3><span class="colaborador">${tarefa.nome_colaborador}</span>
                <p class="imagem_nome" data-obra="${tarefa.nome_obra}">${tarefa.imagem_nome}</p>
                <p class="data_aprovacao">${formatarDataHora(tarefa.data_aprovacao)}</p>
                <p id="status_funcao" style="color:${color};background-color:${bgColor}">${tarefa.nome_status || tarefa.status_novo}</p>
              </div>`;
            tarefasImagensObra.appendChild(taskItem);
        });
    } else {
        container.innerHTML = '<p style="text-align:center;color:#888;">Não há tarefas de revisão no momento.</p>';
    }
}

function formatarData(data) { const [ano, mes, dia] = data.split('-'); return `${dia}/${mes}/${ano}`; }
function formatarDataHora(data) { const d = new Date(data); const dia = String(d.getDate()).padStart(2, '0'); const mes = String(d.getMonth() + 1).padStart(2, '0'); const ano = d.getFullYear(); const h = String(d.getHours()).padStart(2, '0'); const m = String(d.getMinutes()).padStart(2, '0'); return `${dia}/${mes}/${ano} ${h}:${m}`; }

const idusuario = parseInt(localStorage.getItem('idusuario'));
let funcaoImagemId = null;
let ap_imagem_id = null;
let versoesAtuaisDaImagem = []; // armazenar versões da imagem atualmente aberta
let entregaItemIdAtual = null; // entrega_itens.id da versão atualmente aberta
let imagemTemDecisao = false; // flag: existe decisão para a versão atualmente exibida

function construirLabelVersao(v) {
    console.log(v);
    const envio = (v.indice_envio !== undefined && v.indice_envio !== null) ? `${v.nome_etapa}` : 'Envio ?';
    const data = v.data_envio ? new Date(v.data_envio) : null;
    const dataFmt = data ? `${String(data.getDate()).padStart(2, '0')}/${String(data.getMonth() + 1).padStart(2, '0')}/${data.getFullYear()}` : '';
    return `${envio}`;
}

function historyAJAX(imagemId) {
    const grupo = imagensAgrupadasGlobal.find(g => g.imagem_id == imagemId);
    if (!grupo) return;

    const main = document.querySelector('.main');
    if (main) main.classList.add('hidden');
    const container_aprovacao = document.querySelector('.container-aprovacao');
    if (container_aprovacao) container_aprovacao.classList.remove('hidden');

    const imageContainer = document.getElementById('imagens');
    if (imageContainer) imageContainer.innerHTML = '';

    const indiceSelect = document.getElementById('indiceSelect');
    if (indiceSelect) indiceSelect.style.display = 'none';

    // 1) Carrega a imagem atual (última versão deste grupo)
    const versoesOrdenadas = (grupo.versoes || []).slice().sort((a, b) => {
        const da = a.data_envio ? new Date(a.data_envio) : 0;
        const db = b.data_envio ? new Date(b.data_envio) : 0;
        if (db - da !== 0) return db - da;
        return (b.entrega_id || 0) - (a.entrega_id || 0);
    });

    let entregaReferencia = null;
    if (versoesOrdenadas[0]) {
        const v0 = versoesOrdenadas[0];
        entregaReferencia = v0.entrega_id || null;
        mostrarImagemCompleta(
            v0.imagem_url,
            v0.historico_imagem_id,
            v0.imagem_nome || '',
            v0.entrega_item_id || null
        );
    }

    // Armazena versões correntes e constrói/select de versões
    versoesAtuaisDaImagem = versoesOrdenadas;
    const selectVersoes = document.getElementById('indiceSelect');
    if (selectVersoes) {
        selectVersoes.innerHTML = '';
        // Mostrar novamente o select
        selectVersoes.style.display = 'block';
        versoesOrdenadas.forEach((v, idx) => {
            const opt = document.createElement('option');
            opt.value = v.historico_imagem_id;
            opt.textContent = idx === 0 ? construirLabelVersao(v) : construirLabelVersao(v);
            selectVersoes.appendChild(opt);
        });
        // Evento de mudança: mostrar versão selecionada
        selectVersoes.onchange = function () {
            const idHist = this.value;
            const versao = versoesAtuaisDaImagem.find(v => String(v.historico_imagem_id) === String(idHist));
            if (versao) {
                mostrarImagemCompleta(
                    versao.imagem_url,
                    versao.historico_imagem_id,
                    versao.imagem_nome || '',
                    versao.entrega_item_id || null
                );
            }
        };
    }

    // 2) Preenche a sidebar ESQUERDA com TODAS as imagens da mesma entrega
    //    (ex.: outras imagens 1871, 1872, 1873 que compõem a entrega)
    if (imageContainer) {
        // Filtra grupos que possuam ao menos uma versão com o mesmo entrega_id
        let gruposDaEntrega = imagensAgrupadasGlobal;
        if (entregaReferencia !== null) {
            gruposDaEntrega = imagensAgrupadasGlobal.filter(gr =>
                (gr.versoes || []).some(v => (v.entrega_id || null) === entregaReferencia)
            );
        }

        // Ordena por imagem_id para uma navegação previsível
        gruposDaEntrega.sort((a, b) => Number(a.imagem_id) - Number(b.imagem_id));

        gruposDaEntrega.forEach(gr => {
            // Última versão geral do grupo (já estão ordenadas em agruparPorImagem)
            const ultimaVersao = (gr.versoes && gr.versoes[0]) ? gr.versoes[0] : null;
            if (!ultimaVersao) return;

            const wrapper = document.createElement('div');
            wrapper.className = 'imageWrapper';

            const imgThumb = document.createElement('img');
            imgThumb.src = ultimaVersao.imagem_url;
            imgThumb.alt = ultimaVersao.nome_arquivo || ultimaVersao.imagem_url;
            imgThumb.className = 'image';
            imgThumb.setAttribute('data-id', ultimaVersao.historico_imagem_id);
            imgThumb.addEventListener('click', () => {
                // Recarrega o painel principal com esta imagem (sempre última versão)
                historyAJAX(gr.imagem_id);
            });
            wrapper.appendChild(imgThumb);

            const caption = document.createElement('div');
            caption.className = 'thumb-caption';
            caption.textContent = (ultimaVersao.imagem_nome || `Imagem ${gr.imagem_id}`);
            wrapper.appendChild(caption);

            // Destaque visual do item atualmente aberto
            if (gr.imagem_id == imagemId) {
                wrapper.classList.add('selected');
            }

            imageContainer.appendChild(wrapper);
        });
    }
}

// Comentários / interação
let relativeX = 0, relativeY = 0;
function mostrarImagemCompleta(src, id, imagem_nome = '') {
    let entrega_item_id = null;
    if (arguments.length >= 4) {
        entrega_item_id = arguments[3];
    }
    ap_imagem_id = id;
    entregaItemIdAtual = entrega_item_id || entregaItemIdAtual;
    const imageWrapper = document.getElementById('image_wrapper');
    const sidebar = document.querySelector('.sidebar-direita');
    if (sidebar) sidebar.style.display = 'flex';
    while (imageWrapper.firstChild) imageWrapper.removeChild(imageWrapper.firstChild);
    const container = document.createElement('div');
    container.className = 'imagem-completa-container';
    const imgElement = document.createElement('img');
    imgElement.id = 'imagem_atual';
    imgElement.src = src;
    imgElement.style.width = '100%';
    container.appendChild(imgElement);
    imageWrapper.appendChild(container);
    document.querySelector('#imagem_atual').scrollIntoView({ behavior: 'smooth' });
    renderComments(id);
    renderDecisions(id, entregaItemIdAtual);
    ajustarNavSelectAoTamanhoDaImagem();
    imgElement.addEventListener('click', function (event) {
        if (dragMoved) return;
        // Se já existe decisão nesta versão, bloquear comentários e avisar
        if (imagemTemDecisao) {
            Toastify({
                text: 'Comentário bloqueado: já existe uma decisão para esta versão.',
                duration: 4000,
                backgroundColor: 'orange',
                close: true,
                gravity: 'top',
                position: 'right'
            }).showToast();
            return;
        }
        if (![1, 2, 9, 20, 3].includes(idusuario)) return;
        const rect = imgElement.getBoundingClientRect(); relativeX = ((event.clientX - rect.left) / rect.width) * 100; relativeY = ((event.clientY - rect.top) / rect.height) * 100;
        document.getElementById('comentarioTexto').value = '';
        document.getElementById('imagemComentario').value = '';
        document.getElementById('comentarioModal').style.display = 'flex';
        mencionadosIds = [];
    });
    const nomeSpan = document.getElementById('imagem_nome'); if (nomeSpan) nomeSpan.textContent = imagem_nome;
}
async function renderDecisions(historicoId, entregaItemId) {
    try {
        const cont = document.getElementById('decisoes');
        if (!cont) return;
        // começar oculto; só mostraremos se houver decisões
        cont.innerHTML = '';
        cont.style.display = 'none';
        const params = new URLSearchParams();
        if (historicoId) params.append('historico_imagem_id', historicoId);
        if (entregaItemId) params.append('entrega_item_id', entregaItemId);
        const r = await fetch(`buscar_decisoes.php?${params.toString()}`);
        const j = await r.json();
        if (!j.success) {
            cont.innerHTML = '<div class="decisoes-empty">Erro ao carregar decisões.</div>';
            return;
        }
        const list = j.decisoes || [];
        if (list.length === 0) {
            // sem decisões: nada a exibir (mantém o contêiner oculto)
            cont.innerHTML = '';
            cont.style.display = 'none';
            // Atualiza estado e reabilita botão/inputs de comentário
            imagemTemDecisao = false;
            const btnOpen = document.getElementById('submit_decision');
            if (btnOpen) {
                // Mostrar botão apenas para usuários permitidos (IDs padrão)
                const allowed = [1, 2, 3, 9, 20];
                btnOpen.style.display = allowed.includes(idusuario) ? '' : 'none';
            }
            const enviarBtn = document.getElementById('enviarComentario');
            if (enviarBtn) enviarBtn.disabled = false;
            return;
        }
        const wrap = document.createElement('div');
        wrap.className = 'decisoes-list';
        list.forEach(d => {
            const item = document.createElement('div');
            item.className = 'decisao-item';
            const date = new Date(d.created_at);
            const dateStr = `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
            const label = d.decisao === 'aprovado' ? 'Aprovado' : d.decisao === 'aprovado_com_ajustes' ? 'Aprovado com ajustes' : 'Ajuste';
            item.innerHTML = `
                <div class="decisao-header"><strong>${d.usuario_nome}</strong> • <span class="decisao-label decisao-${d.decisao}">${label}</span></div>
                <div class="decisao-date">${dateStr}</div>
            `;
            wrap.appendChild(item);
        });
        // Há decisões: mostra o contêiner e adiciona o conteúdo
        cont.style.display = 'block';
        cont.appendChild(wrap);
        // Atualiza estado e desabilita ações de comentário/decisão
        imagemTemDecisao = true;
        const btnOpen2 = document.getElementById('submit_decision');
        if (btnOpen2) btnOpen2.style.display = 'none';
        const enviarBtn2 = document.getElementById('enviarComentario');
        if (enviarBtn2) enviarBtn2.disabled = true;
    } catch (e) {
        console.error('Erro ao renderizar decisões:', e);
    }
}


function ajustarNavSelectAoTamanhoDaImagem() {
    const img = document.getElementById('imagem_atual'); const navSelect = document.querySelector('.nav-select'); if (img && navSelect) { const apply = () => { navSelect.style.width = img.width + 'px'; }; img.onload = apply; if (img.complete) apply(); }
}

// (Restante do arquivo original: envio de imagens, comentários, zoom, etc.)
// ================= NOVO FLUXO INICIAL =================
// Agora ao carregar a página listamos diretamente TODAS as imagens da obra fixa (74)
// exibindo sempre a última versão (última entrega) como preview. Versões podem ser
// (Antiga implementação de historyAJAX removida - agora usamos versão local acima)
// (nenhum bloco aqui)

// Função para alternar a visibilidade dos detalhes da tarefa
function toggleTaskDetails(taskElement) {
    taskElement.classList.toggle('open');
}

// (Removidos duplicados de variáveis globais já declaradas acima)

// Função antiga mantida para evitar que outras partes que ainda a chamem quebrem.
// Agora converte a estrutura {entregas:[...]} em uma lista plana compatível com o restante.
// (Removida duplicata de fetchObrasETarefas)

// (Removida duplicata de buscarMencoesDoUsuario)

// (Removida duplicata de exibirCardsDeObra)

// (Removida duplicata de filtrarTarefasPorObra)

// (Removida duplicata de atualizarSelectColaborador)

// (Removido: duplicata de historyAJAX)

// (Removidas duplicatas de formatarData/formatarDataHora)



// REMOVIDO: duplicada antiga de historyAJAX baseada em historico2.php
// const modalComment = document.getElementById('modalComment');
// const idusuario = parseInt(localStorage.getItem('idusuario')); // Obtém o idusuario do localStorage
// let funcaoImagemId = null; // armazenado globalmente

// Função utilitária para substituir elementos por ID
function replaceElementById(id) {
    const oldEl = document.getElementById(id);
    const newEl = oldEl.cloneNode(true);
    oldEl.replaceWith(newEl);
    return newEl;
}

// Função utilitária para substituir elementos por classe (única ocorrência)
function replaceElementByClass(className) {
    const oldEl = document.querySelector(`.${className}`);
    const newEl = oldEl.cloneNode(true);
    oldEl.replaceWith(newEl);
    return newEl;
}

function exibirSidebarTabulator(tarefas) {
    const sidebarDiv = document.getElementById('sidebarTabulator');
    sidebarDiv.innerHTML = '';

    const tarefasPorFuncao = {};

    tarefas.forEach(t => {
        if (!tarefasPorFuncao[t.nome_status]) {
            tarefasPorFuncao[t.nome_status] = [];
        }
        tarefasPorFuncao[t.nome_status].push(t);
    });

    Object.entries(tarefasPorFuncao).forEach(([funcao, tarefas]) => {
        const grupoDiv = document.createElement('div');
        grupoDiv.classList.add('grupo-funcao');

        const header = document.createElement('div');
        header.classList.add('group-header');
        header.dataset.grupo = funcao;
        header.innerHTML = `
      <span class="funcao-label">${funcao.slice(0, 3)}</span>
      <span class="funcao-completa"><b>${funcao}</b> (${tarefas.length} imagens)</span>
    `;

        const lista = document.createElement('div');
        lista.classList.add('tarefas-lista');
        lista.style.display = 'none';

        tarefas.forEach(t => {
            const tarefa = document.createElement('div');
            tarefa.classList.add('tarefa-item');
            const imgSrc = t.imagem ? `https://improov.com.br/sistema/${t.imagem}` : '../assets/logo.jpg';
            tarefa.innerHTML = `
        <img src="${imgSrc}" class="tab-img" data-id="${t.idfuncao_imagem}" alt="${t.imagem_nome}">
        <span>${t.nome_colaborador} - ${t.imagem_nome}</span>
      `;
            tarefa.addEventListener('click', () => historyAJAX(t.imagem_id));
            lista.appendChild(tarefa);
        });

        // Comportamento inteligente ao clicar no header
        header.addEventListener('click', () => {
            const todasAsListas = sidebarDiv.querySelectorAll('.tarefas-lista');
            const todasAsHeaders = sidebarDiv.querySelectorAll('.group-header');

            const jaAberto = lista.style.display === 'block';

            // Fecha todos os grupos
            todasAsListas.forEach(l => {
                l.style.display = 'none';
            });
            if (jaAberto) {
                // Nenhum aberto: minimizar a sidebar
                sidebarDiv.classList.add('sidebar-min');
                sidebarDiv.classList.remove('sidebar-expanded');
            } else {
                // Abre o novo grupo e expande sidebar
                lista.style.display = 'block';
                sidebarDiv.classList.add('sidebar-expanded');
                sidebarDiv.classList.remove('sidebar-min');
            }
        });

        grupoDiv.appendChild(header);
        grupoDiv.appendChild(lista);
        sidebarDiv.appendChild(grupoDiv);
    });
}

document.querySelector('.close').addEventListener('click', () => {
    document.getElementById('imagem-modal').style.display = 'none';
    document.getElementById('input-imagens').value = '';
    document.getElementById('preview').innerHTML = '';
});

document.getElementById('input-imagens').addEventListener('change', function () {
    const preview = document.getElementById('preview');
    preview.innerHTML = '';

    const arquivos = this.files;

    for (let i = 0; i < arquivos.length; i++) {
        const reader = new FileReader();
        reader.onload = function (e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            preview.appendChild(img);
        };
        reader.readAsDataURL(arquivos[i]);
    }
});

document.getElementById('btn-enviar-imagens').addEventListener('click', () => {
    const input = document.getElementById('input-imagens');
    const arquivos = input.files;
    if (arquivos.length === 0 || !funcaoImagemId) return;

    const formData = new FormData();
    for (let i = 0; i < arquivos.length; i++) {
        formData.append('imagens[]', arquivos[i]);
    }

    formData.append('dataIdFuncoes', JSON.stringify([funcaoImagemId]));

    fetch('../uploadArquivos.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert(res.success);
                document.getElementById('imagem-modal').style.display = 'none';
                document.getElementById('input-imagens').value = '';
                document.getElementById('preview').innerHTML = '';
            } else {
                alert(res.error || 'Erro ao enviar imagens.');
            }
        })
        .catch(e => {
            console.error(e);
            alert('Erro na comunicação com o servidor.');
        });
});

function abrirMenuContexto(x, y, id, src) {
    const menu = document.getElementById('menuContexto');

    // Coloca info da imagem (caso precise usar depois)
    menu.setAttribute('data-id', id);
    menu.setAttribute('data-src', src);

    menu.style.top = `${y}px`;
    menu.style.left = `${x}px`;
    menu.style.display = 'block';
}

function excluirImagem() {
    const menu = document.getElementById('menuContexto');
    const idImagem = menu.getAttribute('data-id');

    if (!idImagem) {
        alert("ID da imagem não encontrado!");
        return;
    }

    if (confirm("Tem certeza que deseja excluir esta imagem?")) {
        fetch('excluir_imagem.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${idImagem}`
        })
            .then(response => response.text())
            .then(data => {
                console.log(data);
                // Remove a imagem da tela também, se quiser
                const imgElement = document.querySelector(`img[data-id='${idImagem}']`);
                if (imgElement) {
                    imgElement.parentElement.remove(); // Remove o wrapper da imagem
                }
                // Esconde o menu
                menu.style.display = 'none';
            })
            .catch(error => {
                console.error('Erro ao excluir imagem:', error);
                alert("Erro ao excluir imagem.");
            });
    } else {
        // Fecha o menu caso cancele
        menu.style.display = 'none';
    }
}

document.addEventListener('click', (e) => {
    const menu = document.getElementById('menuContexto');
    if (!menu.contains(e.target)) {
        menu.style.display = 'none';
    }
});

let mencionadosIds = []; // armazenar os IDs dos mencionados

document.addEventListener('DOMContentLoaded', async () => {
    // Removed user-lookup and Tribute initialization (buscar_usuarios.php)

    // Modal: fechar
    document.getElementById('fecharComentarioModal').onclick = () => {
        document.getElementById('comentarioModal').style.display = 'none';
    };

    // Decisão: wiring do modal de aprovação
    try {
        const btnOpen = document.getElementById('submit_decision');
        const modal = document.getElementById('decisionModal');
        const btnClose = modal.querySelector('.close');
        const cancelBtn = document.getElementById('cancelBtn');
        const btnConfirm = document.getElementById('confirmBtn');
        const radios = modal.querySelectorAll('input[name="decision"]');

        // Controle de permissão: apenas alguns usuários
        const USERS_PERMITIDOS_DECISAO = [1, 2, 3, 9, 20];
        if (!USERS_PERMITIDOS_DECISAO.includes(idusuario)) {
            if (btnOpen) btnOpen.style.display = 'none';
        } else {
            if (btnOpen) {
                btnOpen.addEventListener('click', () => {
                    if (!ap_imagem_id || !entregaItemIdAtual) {
                        Toastify({ text: 'Selecione uma imagem/versão válida antes.', duration: 3000, backgroundColor: 'orange', close: true, gravity: 'top', position: 'right' }).showToast();
                        return;
                    }
                    modal.classList.remove('hidden');
                });
            }

            if (btnClose) btnClose.addEventListener('click', () => {
                modal.classList.add('hidden');
                btnConfirm.classList.add('hidden');
                radios.forEach(r => r.checked = false);
            });
            if (cancelBtn) cancelBtn.addEventListener('click', () => {
                modal.classList.add('hidden');
                btnConfirm.classList.add('hidden');
                radios.forEach(r => r.checked = false);
            });

            radios.forEach(radio => {
                radio.addEventListener('change', () => {
                    btnConfirm.classList.remove('hidden');
                });
            });

            if (btnConfirm) btnConfirm.addEventListener('click', async () => {
                const selected = Array.from(radios).find(r => r.checked)?.value;
                if (!selected) return;
                try {
                    const resp = await fetch('salvar_decisao.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            entrega_item_id: entregaItemIdAtual,
                            historico_imagem_id: ap_imagem_id,
                            decisao: selected
                        })
                    });
                    const json = await resp.json();
                    if (json.success) {
                        Toastify({ text: 'Decisão registrada com sucesso!', duration: 3000, backgroundColor: 'green', close: true, gravity: 'top', position: 'right' }).showToast();
                        renderDecisions(ap_imagem_id, entregaItemIdAtual);
                    } else {
                        Toastify({ text: json.message || 'Falha ao registrar decisão.', duration: 3000, backgroundColor: 'red', close: true, gravity: 'top', position: 'right' }).showToast();
                    }
                } catch (err) {
                    console.error('Erro ao salvar decisão:', err);
                    Toastify({ text: 'Erro ao salvar decisão.', duration: 3000, backgroundColor: 'red', close: true, gravity: 'top', position: 'right' }).showToast();
                } finally {
                    modal.classList.add('hidden');
                    btnConfirm.classList.add('hidden');
                    radios.forEach(r => r.checked = false);
                }
            });
        }
    } catch (err) {
        console.error('Erro ao inicializar modal de decisão:', err);
    }
});

// (Removidos duplicados de ap_imagem_id e mostrarImagemCompleta)


const btnDownload = document.getElementById('btn-download-imagem');
if (btnDownload) {
    btnDownload.addEventListener('click', function () {
        const img = document.getElementById('imagem_atual');
        if (img && img.src) {
            const link = document.createElement('a');
            link.href = img.src;
            link.download = img.src.split('/').pop(); // nome do arquivo
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    });
}

// Capturar colagem de imagem no campo de texto
document.getElementById('comentarioTexto').addEventListener('paste', function (event) {
    const items = (event.clipboardData || event.originalEvent.clipboardData).items;

    for (let index in items) {
        const item = items[index];
        if (item.kind === 'file') {
            const blob = item.getAsFile();
            if (blob && blob.type.startsWith('image/')) {
                const fileInput = document.getElementById('imagemComentario');

                // Cria um objeto DataTransfer para injetar o arquivo no input
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(new File([blob], 'imagem_colada.png', { type: blob.type }));

                fileInput.files = dataTransfer.files;

                Toastify({
                    text: 'Imagem colada com sucesso!',
                    duration: 3000,
                    backgroundColor: 'linear-gradient(to right, #00b09b, #96c93d)',
                    close: true,
                    gravity: "top",
                    position: "right"
                }).showToast();
            }
        }
    }
});

// Função para enviar o comentário
document.getElementById('enviarComentario').onclick = async () => {
    const texto = document.getElementById('comentarioTexto').value.trim();
    const imagemFile = document.getElementById('imagemComentario').files[0];

    if (!texto && !imagemFile) {
        Toastify({
            text: 'Escreva um comentário ou anexe uma imagem!',
            duration: 3000,
            backgroundColor: 'orange',
            close: true,
            gravity: "top",
            position: "right"
        }).showToast();
        return;
    }

    const formData = new FormData();
    formData.append('ap_imagem_id', ap_imagem_id);
    formData.append('x', relativeX);
    formData.append('y', relativeY);
    formData.append('texto', texto);

    if (imagemFile) {
        formData.append('imagem', imagemFile);
    }

    try {
        const response = await fetch('salvar_comentario.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        document.getElementById('comentarioModal').style.display = 'none';

        if (result.sucesso) {
            Toastify({
                text: 'Comentário adicionado com sucesso!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();

            // Atualiza comentários
            renderComments(ap_imagem_id);
        } else {
            Toastify({
                text: result.mensagem || 'Erro ao salvar comentário!',
                duration: 3000,
                backgroundColor: 'red',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
        }

        // Limpa os mencionados depois do envio
        mencionadosIds = [];

    } catch (error) {
        console.error('Erro na requisição:', error);
        Toastify({
            text: 'Erro de conexão! Tente novamente.',
            duration: 3000,
            backgroundColor: 'red',
            close: true,
            gravity: "top",
            position: "left"
        }).showToast();
    }
};

function addComment(x, y) {
    const imagemCompletaDiv = document.getElementById("imagem_completa");

    // Cria o div do comentário
    const commentDiv = document.createElement('div');
    commentDiv.classList.add('comment');
    commentDiv.style.left = `${x}%`;
    commentDiv.style.top = `${y}%`;

    imagemCompletaDiv.appendChild(commentDiv);
}

const image = document.getElementById("imagem_atual");


// ---- CONFIGURAÇÃO ---------------------------------------------------------
const USERS_PERMITIDOS = [1, 2, 3, 9, 20];   // quem pode editar / excluir
// --------------------------------------------------------------------------

async function renderComments(id) {
    // console.log('renderComments', id); // debug
    const comentariosDiv = document.querySelector(".comentarios");
    comentariosDiv.innerHTML = '';
    const imagemCompletaDiv = document.getElementById("image_wrapper");
    const response = await fetch(`buscar_comentarios.php?id=${id}`);
    const comentarios = await response.json();

    imagemCompletaDiv.querySelectorAll('.comment').forEach(c => c.remove());

    // Oculta a sidebar-direita se não houver comentários
    if (comentarios.length === 0) {
        comentariosDiv.style.display = 'none';
    } else {
        comentariosDiv.style.display = 'flex';
    }

    // Mention support disabled: removed fetch to buscar_usuarios.php and Tribute setup

    comentarios.forEach(comentario => {
        const commentCard = document.createElement('div');
        commentCard.classList.add('comment-card');
        commentCard.setAttribute('data-id', comentario.id);

        const header = document.createElement('div');
        header.classList.add('comment-header');
        header.innerHTML = `
            <div class="comment-number">${comentario.numero_comentario}</div>
            <div class="comment-user">${comentario.nome_responsavel}</div>
        `;

        const commentBody = document.createElement('div');
        commentBody.classList.add('comment-body');

        const p = document.createElement('p');
        p.classList.add('comment-input');
        p.textContent = comentario.texto;

        commentBody.appendChild(p);

        const footer = document.createElement('div');
        footer.classList.add('comment-footer');
        footer.innerHTML = `
            <div class="comment-date">${comentario.data}</div>
            <div class="comment-actions">
                <button class="comment-resp">&#8617</button>
                <button class="comment-edit">✏️</button>
                <button class="comment-delete" onclick="deleteComment(${comentario.id})">🗑️</button>
            </div>
        `;

        const respostas = document.createElement('div');
        respostas.classList.add('respostas-container');
        respostas.id = `respostas-${comentario.id}`;

        commentCard.appendChild(header);
        if (comentario.imagem) {
            const imagemDiv = document.createElement('div');
            imagemDiv.classList.add('comment-image');
            imagemDiv.innerHTML = `
                <img src="${comentario.imagem}" class="comment-img-thumb" onclick="abrirImagemModal('${comentario.imagem}')">
            `;
            commentCard.appendChild(imagemDiv);
        }
        commentCard.appendChild(commentBody);
        commentCard.appendChild(footer);
        commentCard.appendChild(respostas);

        // Permissões
        if (!USERS_PERMITIDOS.includes(idusuario)) {
            footer.querySelector('.comment-delete').style.display = 'none';
            footer.querySelector('.comment-edit').style.display = 'none';
        }

        const editButton = footer.querySelector('.comment-edit');

        editButton.addEventListener('click', () => {
            p.contentEditable = true;
            p.focus();

            const handleKeyDown = async function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();

                    const novoTexto = p.textContent.trim();

                    p.contentEditable = false;

                    updateComment(comentario.id, novoTexto);

                    // Remove o listener pra não acumular
                    p.removeEventListener('keydown', handleKeyDown);
                }
            };

            p.addEventListener('keydown', handleKeyDown);
        });

        const commentDiv = document.createElement('div');
        commentDiv.classList.add('comment');
        commentDiv.setAttribute('data-id', comentario.id);
        commentDiv.innerText = comentario.numero_comentario;
        commentDiv.style.left = `${comentario.x}%`;
        commentDiv.style.top = `${comentario.y}%`;

        commentDiv.addEventListener('click', () => {
            document.querySelectorAll('.comment-number').forEach(n => n.classList.remove('highlight'));
            const number = document.querySelector(`.comment-card[data-id="${comentario.id}"] .comment-number`);
            if (number) {
                number.classList.add('highlight');
                number.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        commentCard.addEventListener('click', () => {
            // Remove o highlight de todas as bolinhas
            document.querySelectorAll('.comment.highlight').forEach(n => n.classList.remove('highlight'));

            // Pega a bolinha correspondente ao comentário
            const number = document.querySelector(`.comment[data-id="${comentario.id}"]`);

            if (number) {
                number.classList.add('highlight');
            }
        });


        const respButton = commentCard.querySelector('.comment-resp');

        respButton.addEventListener('click', async () => {
            const textoResposta = prompt("Digite sua resposta:");
            if (textoResposta && textoResposta.trim() !== '') {
                const respostaSalva = await salvarResposta(comentario.id, textoResposta);
                if (respostaSalva) {
                    adicionarRespostaDOM(comentario.id, respostaSalva);

                    const mencoes = textoResposta.match(/@(\w+)/g);
                    if (mencoes) {
                        for (const mencao of mencoes) {
                            const nome = mencao.replace('@', '');
                            const colaborador = users.find(u => u.nome_colaborador === nome);
                            if (colaborador) {
                                await fetch('registrar_mencao.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        comentario_id: comentario.id,
                                        mencionado_id: colaborador.idcolaborador
                                    })
                                });
                            }
                        }
                    }
                }
            }
        });

        imagemCompletaDiv.appendChild(commentDiv);
        comentariosDiv.appendChild(commentCard);

        if (comentario.respostas && comentario.respostas.length > 0) {
            comentario.respostas.forEach(resposta => {
                adicionarRespostaDOM(comentario.id, resposta);
            });
        }
    });
}

// Função para enviar resposta pro backend
async function salvarResposta(comentarioId, texto) {
    const response = await fetch('responder_comentario.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            comentario_id: comentarioId,
            texto: texto
        })
    });
    return await response.json();
}

// Função pra adicionar resposta no DOM
function adicionarRespostaDOM(comentarioId, resposta) {
    const container = document.getElementById(`respostas-${comentarioId}`);
    const respostaDiv = document.createElement('div');
    respostaDiv.classList.add('resposta');
    respostaDiv.innerHTML = `
        <div class="resposta-nome"><span class="reply-icon">&#8617;</span>  ${resposta.nome_responsavel}</div>
        <div class="corpo-resposta">
            <div class="resposta-texto">${resposta.texto}</div>
            <div class="resposta-data">${resposta.data}</div>
        </div>
    `;
    container.appendChild(respostaDiv);
}

// Função para atualizar o comentário no banco de dados
async function updateComment(commentId, novoTexto) {
    try {
        const response = await fetch('atualizar_comentario.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: commentId, texto: novoTexto })
        });

        const result = await response.json();
        if (result.sucesso) {
            Toastify({
                text: 'Comentário atualizado com sucesso!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
        } else {
            Toastify({
                text: 'Erro ao atualizar comentário!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
        }
    } catch (error) {
        console.error('Erro ao atualizar comentário:', error);
        alert('Ocorreu um erro ao tentar atualizar o comentário.');
    }
}

// Função para excluir o comentário do banco de dados
async function deleteComment(commentId) {
    try {
        const response = await fetch('excluir_comentario.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: commentId })
        });

        const result = await response.json();
        if (result.sucesso) {
            Toastify({
                text: 'Comentário excluído com sucesso!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
            renderComments(ap_imagem_id); // Atualiza a lista de comentários
        } else {
            Toastify({
                text: 'Erro ao excluir comentário!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
        }
    } catch (error) {
        console.error('Erro ao excluir comentário:', error);
        alert('Ocorreu um erro ao tentar excluir o comentário.');
    }
}

function abrirImagemModal(src) {
    const modal = document.getElementById('modal-imagem');
    const imagem = document.getElementById('imagem-ampliada');
    imagem.src = src;
    modal.style.display = 'flex';
}

function fecharImagemModal() {
    const modal = document.getElementById('modal-imagem');
    modal.style.display = 'none';
}


document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        const comentarioModal = document.getElementById("comentarioModal");

        if (comentarioModal.style.display === 'flex') {
            comentarioModal.style.display = 'none';
            return; // Interrompe aqui se o modal estava visível
        }

        const main = document.querySelector('.main');
        main.classList.remove('hidden');

        const container_aprovacao = document.querySelector('.container-aprovacao');
        container_aprovacao.classList.add('hidden');

        const imagemWrapperDiv = document.querySelector(".image_wrapper");
        imagemWrapperDiv.innerHTML = '';

        const comentariosDiv = document.querySelector(".comentarios");
        comentariosDiv.innerHTML = '';
    }
});

const imageWrapper = document.getElementById('image_wrapper');
const comments = document.querySelectorAll('.comment');
let currentZoom = 1;
const zoomStep = 0.1;
const maxZoom = 3;
const minZoom = 0.5;

// Pan variables
let isDragging = false;
let startX;
let startY;
let currentTranslateX = 0;
let currentTranslateY = 0;
let dragMoved = false;

// Function to apply transforms (zoom and pan)
function applyTransforms() {
    imageWrapper.style.transform = `scale(${currentZoom}) translate(${currentTranslateX}px, ${currentTranslateY}px)`;

    // Adjust comment scaling based on the new currentZoom
    comments.forEach(comment => {

        comment.style.transform = `scale(${1 / currentZoom})`;
    });
}

// --- Zoom functionality ---
document.addEventListener('wheel', function (event) {
    if (event.ctrlKey) {
        event.preventDefault(); // Prevent default browser zoom/scroll

        const oldZoom = currentZoom; // Store old zoom for potential pan adjustment (not used in your current code but good practice)

        if (event.deltaY < 0) {
            currentZoom += zoomStep;
        } else {
            currentZoom -= zoomStep;
        }

        currentZoom = Math.max(minZoom, Math.min(maxZoom, currentZoom));

        if (currentZoom === minZoom) {
            // When zoomed out completely, reset pan to origin
            currentTranslateX = 0;
            currentTranslateY = 0;
        }

        applyTransforms();
    }
}, { passive: false });

document.getElementById('btn-mais-zoom').addEventListener('click', function () {
    currentZoom = Math.min(currentZoom + zoomStep, maxZoom);
    applyTransforms();
});

document.getElementById('btn-menos-zoom').addEventListener('click', function () {
    currentZoom = Math.max(currentZoom - zoomStep, minZoom);
    applyTransforms();
});

document.getElementById('reset-zoom').addEventListener('click', function () {
    currentZoom = 1;
    currentTranslateX = 0; // reseta deslocamento horizontal
    currentTranslateY = 0; // reseta deslocamento vertical
    applyTransforms();
});

imageWrapper.addEventListener('mousedown', (e) => {
    if (e.button === 0 && !e.ctrlKey) {
        isDragging = true;
        dragMoved = false; // reset
        imageWrapper.style.cursor = 'grabbing'; // mão fechada

        imageWrapper.classList.add('grabbing');
        startX = e.clientX - currentTranslateX;
        startY = e.clientY - currentTranslateY;
        imageWrapper.style.transition = 'none';
    }
});

document.addEventListener('mousemove', (e) => {
    if (!isDragging) return;
    imageWrapper.style.cursor = 'grabbing'; // mão fechada

    e.preventDefault();

    const dx = e.clientX - startX;
    const dy = e.clientY - startY;

    // Marcar que houve movimento significativo
    if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
        dragMoved = true;
    }

    currentTranslateX = dx;
    currentTranslateY = dy;

    applyTransforms();
});

document.addEventListener('mouseup', (e) => {
    if (isDragging) {
        isDragging = false;
        imageWrapper.style.cursor = 'grab'; // mão aberta
        imageWrapper.classList.remove('grabbing');
        imageWrapper.style.transition = 'transform 0.1s ease-out';
    }
});

// Initialize transforms
applyTransforms();

const id_revisao = document.getElementById('id_revisao');

// function addObservacao(id) {
//     const modal = document.getElementById('historico_modal');
//     const idRevisao = document.getElementById('id_revisao');
//     const historicoAdd = modal.querySelector('.historico-add');

//     historicoAdd.classList.toggle('hidden');

//     if (historicoAdd.classList.contains('hidden')) {
//         modal.classList.remove('complete');
//     } else {
//         modal.classList.add('complete');
//     }

//     idRevisao.innerText = `${id}`;
// }

// Inicializa o editor Quill
// var quill = new Quill('#text_obs', {
//     theme: 'snow',  // Tema claro
//     modules: {
//         toolbar: [
//             ['bold', 'italic', 'underline'], // Negrito, itálico, sublinhado
//             [{ 'header': 1 }, { 'header': 2 }], // Títulos
//             [{ 'list': 'ordered' }, { 'list': 'bullet' }], // Listas
//             [{ 'color': [] }, { 'background': [] }], // Cores
//             ['clean'] // Limpar formatação
//         ]
//     }
// });


// const historico_modal = document.getElementById('historico_modal');
// const historicoAdd = historico_modal.querySelector('.historico-add');

// window.addEventListener('click', function (event) {
//     if (event.target == historico_modal) {
//         historico_modal.style.display = "none"
//         historico_modal.classList.remove('complete');
//         historicoAdd.classList.add('hidden');
//     }
// });

// window.addEventListener('touchstart', function (event) {
//     if (event.target == historico_modal) {
//         historico_modal.style.display = "none"
//         historico_modal.classList.remove('complete');
//         historicoAdd.classList.add('hidden');

//     }
// });


// Captura o evento de envio do formulário
// document.getElementById('adicionar_obs').addEventListener('submit', function (event) {
//     event.preventDefault(); // Previne o comportamento padrão do envio do formulário

//     // Exibe um prompt para o usuário digitar o número da revisão
//     const numeroRevisao = document.getElementById('id_revisao').textContent;
//     const idfuncao_imagem = document.getElementById("id_funcao").value;

//     if (numeroRevisao) {
//         // Captura o conteúdo do editor Quill
//         const observacao = quill.root.innerHTML;

//         // Exibe os valores no console (você pode remover esta parte depois)
//         console.log("Número da Revisão: " + numeroRevisao);
//         console.log("Observação: " + observacao);

//         // Envia os dados para o servidor via fetch
//         fetch('atualizar_historico.php', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json'
//             },
//             body: JSON.stringify({
//                 revisao: numeroRevisao,
//                 observacao: observacao
//             })
//         })
//             .then(response => response.json())
//             .then(data => {
//                 // Verifica se a atualização foi bem-sucedida
//                 if (data.success) {
//                     Toastify({
//                         text: 'Observação adicionada com sucesso!',
//                         duration: 3000,
//                         backgroundColor: 'green',
//                         close: true,
//                         gravity: "top",
//                         position: "right"
//                     }).showToast();

//                     historico_modal.classList.remove('complete');
//                     historicoAdd.classList.toggle('hidden');
//                     historyAJAX(idfuncao_imagem)
//                 } else {
//                     Toastify({
//                         text: "Falha ao atualizar a tarefa: " + data.message,
//                         duration: 3000,
//                         backgroundColor: "red",
//                         close: true,
//                         gravity: "top",
//                         position: "right"
//                     }).showToast();
//                 }
//             })
//             .catch(error => {
//                 console.error("Erro ao enviar dados para o servidor:", error);
//                 alert("Ocorreu um erro ao tentar adicionar a observação.");
//             });
//     } else {
//         alert("Número de revisão é obrigatório!");
//     }
// });

