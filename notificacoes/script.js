async function confirmDelete(e) {
    e.preventDefault();
    e.stopPropagation();
    const form = e.target;
    const { isConfirmed } = await Swal.fire({
        title: 'Excluir notificação?',
        text: 'Esta ação não pode ser desfeita.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Excluir',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
    });
    if (isConfirmed) enviarFormularioNotificacao(form);
}

function toastNotificacao(message, type = 'success') {
    if (!message || !window.Toastify) return;
    const colors = {
        success: 'linear-gradient(135deg, #16a34a, #15803d)',
        error: 'linear-gradient(135deg, #dc2626, #b91c1c)',
        warning: 'linear-gradient(135deg, #d97706, #b45309)',
        info: 'linear-gradient(135deg, #2563eb, #1d4ed8)',
    };
    Toastify({ text: String(message), duration: 4200, gravity: 'top', position: 'right', close: true, stopOnFocus: true, style: { background: colors[type] || colors.info } }).showToast();
}

function salvarToastParaReload(message, type) {
    sessionStorage.setItem('notificacoesToast', JSON.stringify({ message, type }));
}

async function enviarFormularioNotificacao(form) {
    if (!form || form.dataset.sending === '1') return;
    form.dataset.sending = '1';
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton ? submitButton.innerHTML : '';
    if (submitButton) { submitButton.disabled = true; submitButton.textContent = 'Salvando...'; }
    try {
        const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || !json.ok) throw new Error(json.message || 'Não foi possível concluir a operação.');
        salvarToastParaReload(json.message || 'Operação concluída.', json.warning ? 'warning' : 'success');
        window.location.href = json.redirect || 'index.php';
    } catch (error) {
        toastNotificacao(error.message || 'Erro de comunicação com o servidor.', 'error');
        form.dataset.sending = '';
        if (submitButton) { submitButton.disabled = false; submitButton.innerHTML = originalText; }
    }
}

function qs(sel, root = document) {
    return root.querySelector(sel);
}

function qsa(sel, root = document) {
    return Array.from(root.querySelectorAll(sel));
}

function openModal() {
    const modal = qs('#modal');
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
}

function closeModal() {
    const modal = qs('#modal');
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
}

function openStatusModal() {
    const modal = qs('#statusModal');
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
}

function closeStatusModal() {
    const modal = qs('#statusModal');
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
}

function setActiveTab(target) {
    const tabs = qsa('.tabs .tab');
    const panels = qsa('.tab-panel');

    tabs.forEach(btn => {
        btn.classList.toggle('is-active', btn.getAttribute('data-tab-target') === target);
    });

    panels.forEach(panel => {
        panel.classList.toggle('is-active', panel.getAttribute('data-tab-panel') === target);
    });
}

function updateVersionManualUI() {
    const type = qs('#f_version_type');
    const manual = qs('#f_version_manual');
    if (!type || !manual) return;

    const isManual = type.value === 'manual';
    manual.disabled = !isManual;
    if (!isManual) {
        manual.value = '';
    }
}

function setSegmentationUI() {
    const seg = qs('#f_segmentacao');
    if (!seg) return;

    const value = seg.value;
    const map = {
        funcao: '#seg_funcao',
        pessoa: '#seg_pessoa',
        projeto: '#seg_projeto'
    };

    qsa('.segment').forEach(el => {
        el.style.display = 'none';
    });

    if (map[value]) {
        const el = qs(map[value]);
        if (el) el.style.display = '';
    }
}

async function loadStatus(notificacaoId) {
    const summary = qs('#statusSummary');
    const tbody = qs('#statusTable tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="4" class="small">Carregando...</td></tr>';
    if (summary) summary.textContent = '';

    const res = await fetch(`actions/recipients.php?id=${encodeURIComponent(notificacaoId)}`);
    const json = await res.json();

    if (!json.ok) {
        tbody.innerHTML = `<tr><td colspan="4" class="small">Erro: ${json.message || 'falha ao carregar'}</td></tr>`;
        return;
    }

    const data = json.data || [];
    const vistos = data.filter(r => r.visto_em).length;
    const total = data.length;
    if (summary) summary.textContent = `${vistos} / ${total} viram`;

    if (total === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="small">Sem destinatários (verifique a segmentação).</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    for (const r of data) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(r.nome_usuario)}${String(r.ativo) !== '1' ? ' <span class="badge off">inativo</span>' : ''}</td>
            <td class="small">${escapeHtml(r.visto_em || '-')}</td>
            <td class="small">${escapeHtml(r.confirmado_em || '-')}</td>
            <td class="small">${escapeHtml(r.dispensado_em || '-')}</td>
        `;
        tbody.appendChild(tr);
    }
}

function escapeHtml(str) {
    return String(str)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

const NOTIFICACAO_MAX_ARQUIVOS = 10;
const NOTIFICACAO_MAX_TAMANHO_ARQUIVO = 10 * 1024 * 1024;
const NOTIFICACAO_MAX_TAMANHO_TOTAL = 40 * 1024 * 1024;
const NOTIFICACAO_EXTENSOES = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];

function validarArquivosNotificacao() {
    const input = qs('#f_arquivos');
    const feedback = qs('#f_arquivos_feedback');
    if (!input) return true;
    const files = Array.from(input.files || []);
    let erro = '';
    if (files.length > NOTIFICACAO_MAX_ARQUIVOS) erro = `Envie no máximo ${NOTIFICACAO_MAX_ARQUIVOS} arquivos.`;
    const total = files.reduce((sum, file) => sum + file.size, 0);
    if (!erro && total > NOTIFICACAO_MAX_TAMANHO_TOTAL) erro = 'O total dos arquivos não pode exceder 40 MB.';
    for (const file of files) {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if (!erro && !NOTIFICACAO_EXTENSOES.includes(ext)) erro = `Formato não permitido: ${file.name}.`;
        if (!erro && (file.size < 1 || file.size > NOTIFICACAO_MAX_TAMANHO_ARQUIVO)) erro = `Cada arquivo deve ter até 10 MB: ${file.name}.`;
    }
    if (feedback) feedback.textContent = erro || (files.length ? `${files.length} arquivo(s) selecionado(s).` : '');
    if (erro) input.value = '';
    return !erro;
}

// Mantém o contrato atual do formulário: o HTML produzido pelo Quill é enviado
// pelo mesmo campo #f_mensagem usado na criação e edição da notificação.
function initMensagemQuill() {
    const input = qs('#f_mensagem');
    const editor = qs('#mensagem-quill-editor');
    const form = qs('#notificationForm');
    if (!input || !editor || !form || !window.Quill) return;

    const quillMensagem = new Quill('#mensagem-quill-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                [{ header: [1, 2, 3, false] }],
                [{ color: [] }, { background: [] }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link'],
                ['clean'],
            ],
            clipboard: {
                matchVisual: false,
            },
        },
        placeholder: 'Digite a mensagem da notificação...',
    });

    if (input.value.trim()) {
        quillMensagem.clipboard.dangerouslyPasteHTML(input.value);
    }

    const syncMensagem = () => {
        input.value = quillMensagem.root.innerHTML.trim();
    };

    quillMensagem.on('text-change', syncMensagem);

    form.addEventListener('submit', (event) => {
        syncMensagem();
        const mensagemVazia = !quillMensagem.getText().trim();
        if (mensagemVazia || !validarArquivosNotificacao()) {
            event.preventDefault();
            if (mensagemVazia) quillMensagem.focus();
            toastNotificacao(mensagemVazia ? 'Digite a mensagem da notificação.' : 'Revise os arquivos selecionados.', 'warning');
            return;
        }
        event.preventDefault();
        enviarFormularioNotificacao(form);
    });
}

document.addEventListener('click', async (e) => {
    const tabBtn = e.target.closest('.tabs .tab');
    if (tabBtn) {
        const target = tabBtn.getAttribute('data-tab-target');
        if (target) setActiveTab(target);
        return;
    }

    const openBtn = e.target.closest('#btnOpenCreate');
    if (openBtn) {
        openModal();
        setSegmentationUI();
        updateVersionManualUI();
        return;
    }

    if (e.target.closest('[data-close="1"]')) {
        closeModal();
        return;
    }

    if (e.target.closest('[data-close-status="1"]')) {
        closeStatusModal();
        return;
    }

    const statusBtn = e.target.closest('[data-action="status"]');
    if (statusBtn) {
        const id = statusBtn.getAttribute('data-id');
        openStatusModal();
        try {
            await loadStatus(id);
        } catch {
            const tbody = qs('#statusTable tbody');
            if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="small">Erro ao carregar status.</td></tr>';
        }
        return;
    }
});

document.addEventListener('input', (e) => {
    if (e.target && e.target.id === 'userFilter') {
        const q = e.target.value.trim().toLowerCase();
        qsa('#userList .useritem').forEach(item => {
            const name = item.getAttribute('data-name') || '';
            item.style.display = name.includes(q) ? '' : 'none';
        });
    }
});

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!form.matches('[data-async-action]')) return;
    e.preventDefault();
    enviarFormularioNotificacao(form);
});

document.addEventListener('change', (e) => {
    if (e.target && e.target.id === 'f_arquivos') {
        validarArquivosNotificacao();
    }
    if (e.target && e.target.id === 'f_segmentacao') {
        setSegmentationUI();
    }

    if (e.target && e.target.id === 'f_version_type') {
        updateVersionManualUI();
    }
});

// Se estiver em modo edição via ?edit=, abrir modal automaticamente
document.addEventListener('DOMContentLoaded', () => {
    const queuedToast = sessionStorage.getItem('notificacoesToast');
    if (queuedToast) {
        sessionStorage.removeItem('notificacoesToast');
        try {
            const toast = JSON.parse(queuedToast);
            toastNotificacao(toast.message, toast.type);
        } catch (_) {}
    } else if (window.__legacyToast) {
        toastNotificacao(window.__legacyToast, window.__legacyToastType);
    }

    initMensagemQuill();

    if (window.__editOpen) {
        openModal();
        setSegmentationUI();
        updateVersionManualUI();
    }

    updateVersionManualUI();
});
