function confirmDelete() {
    return confirm('Excluir esta notificação?');
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

function updatePreview() {
    const title = (qs('#f_titulo')?.value || '').trim();
    const text = (qs('#f_mensagem')?.value || '').trim();
    const tipo = (qs('#f_tipo')?.value || 'info').trim();
    const canal = (qs('#f_canal')?.value || 'banner').trim();
    const seg = (qs('#f_segmentacao')?.value || 'geral').trim();
    const ctaLabel = (qs('#f_cta_label')?.value || '').trim();
    const ctaUrl = (qs('#f_cta_url')?.value || '').trim();

    const banner = qs('#previewBanner');
    const badge = qs('#previewBadge');
    const pTitle = qs('#previewTitle');
    const pText = qs('#previewText');
    const pCta = qs('#previewCta');
    const pCtaLink = qs('#previewCtaLink');
    const meta = qs('#previewMeta');

    if (banner) {
        banner.dataset.tipo = tipo;
        banner.dataset.canal = canal;
    }
    if (badge) badge.textContent = tipo;
    if (pTitle) pTitle.textContent = title || 'Título';
    if (pText) pText.textContent = text || 'Mensagem';

    if (pCta && pCtaLink) {
        if (ctaLabel && ctaUrl) {
            pCta.style.display = '';
            pCtaLink.textContent = ctaLabel;
        } else {
            pCta.style.display = 'none';
        }
    }

    if (meta) {
        const segLabel = seg === 'geral' ? 'Geral' : seg === 'funcao' ? 'Por função' : seg === 'pessoa' ? 'Por pessoa' : 'Por projeto';
        meta.textContent = `Canal: ${canal} · Segmentação: ${segLabel}`;
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

document.addEventListener('click', async (e) => {
    const openBtn = e.target.closest('#btnOpenCreate');
    if (openBtn) {
        openModal();
        updatePreview();
        setSegmentationUI();
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
    if (e.target && (e.target.id || '').startsWith('f_')) {
        updatePreview();
    }

    if (e.target && e.target.id === 'userFilter') {
        const q = e.target.value.trim().toLowerCase();
        qsa('#userList .useritem').forEach(item => {
            const name = item.getAttribute('data-name') || '';
            item.style.display = name.includes(q) ? '' : 'none';
        });
    }
});

document.addEventListener('change', (e) => {
    if (e.target && e.target.id === 'f_segmentacao') {
        setSegmentationUI();
        updatePreview();
    }
});

// Se estiver em modo edição via ?edit=, abrir modal automaticamente
document.addEventListener('DOMContentLoaded', () => {
    if (window.__editOpen) {
        openModal();
        setSegmentationUI();
        updatePreview();
    }
});
