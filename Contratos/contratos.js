document.addEventListener('DOMContentLoaded', () => {
    const table = $('#contratos-table').DataTable({
        paging: false,
        info: false,
        order: [],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.10.21/i18n/Portuguese.json'
        }
    });

    async function carregarStatus() {
        const rows = Array.from(document.querySelectorAll('#contratos-table tbody tr'));
        const ids = rows.map(r => r.dataset.colaboradorId).filter(Boolean);
        if (ids.length === 0) return;
        const resp = await fetch(`./status.php?colaborador_ids=${ids.join(',')}`, { credentials: 'same-origin' });
        if (!resp.ok) return;
        const data = await resp.json();
        if (!data.success || !data.items) return;
        for (const row of rows) {
            const colabId = row.dataset.colaboradorId;
            const item = data.items[colabId] || { status: 'nao_gerado' };
            atualizarLinha(row, item);
        }
    }

    function atualizarLinha(row, data) {
        const competenciaCell = row.querySelector('.competencia');
        const statusCell = row.querySelector('.status');
        const btnBaixar = row.querySelector('.baixar');

        competenciaCell.textContent = data.competencia || '-';
        statusCell.innerHTML = `<span class="status-badge status-${data.status}">${data.status}</span>`;

        if (btnBaixar) {
            if (data.download_url) {
                btnBaixar.disabled = false;
                btnBaixar.dataset.url = data.download_url;
            } else {
                btnBaixar.disabled = true;
                btnBaixar.dataset.url = '';
            }
        }

        if (data.arquivo_nome) {
            row.dataset.arquivoNome = data.arquivo_nome;
        }
    }

    document.querySelectorAll('.gerar').forEach(btn => {
        btn.addEventListener('click', async () => {
            const row = btn.closest('tr');
            const colabId = row.dataset.colaboradorId;
            btn.disabled = true;
            try {
                const resp = await fetch('./gerar_contrato.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ colaborador_id: colabId })
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) {
                    alert(data.message || 'Erro ao gerar contrato.');
                } else {
                    alert('Contrato gerado com sucesso.');
                    const statusResp = await fetch(`./status.php?colaborador_id=${colabId}`);
                    const statusData = await statusResp.json();
                    atualizarLinha(row, statusData);
                    if (data.download_url) {
                        window.open(data.download_url, '_blank');
                    }
                }
            } catch (e) {
                alert('Erro de rede ao gerar contrato.');
            } finally {
                btn.disabled = false;
            }
        });
    });

    document.querySelectorAll('.reenviar').forEach(btn => {
        btn.addEventListener('click', async () => {
            const row = btn.closest('tr');
            const colabId = row.dataset.colaboradorId;
            btn.disabled = true;
            try {
                const resp = await fetch('./reenviar_contrato.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ colaborador_id: colabId })
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) {
                    alert(data.message || 'Erro ao reenviar contrato.');
                } else {
                    alert('Contrato gerado novamente.');
                    const statusResp = await fetch(`./status.php?colaborador_id=${colabId}`);
                    const statusData = await statusResp.json();
                    atualizarLinha(row, statusData);
                    if (data.download_url) {
                        window.open(data.download_url, '_blank');
                    }
                }
            } catch (e) {
                alert('Erro de rede ao reenviar contrato.');
            } finally {
                btn.disabled = false;
            }
        });
    });

    document.querySelectorAll('.baixar').forEach(btn => {
        btn.addEventListener('click', () => {
            const url = btn.dataset.url;
            if (url) {
                window.open(url, '_blank');
            }
        });
    });

    document.querySelectorAll('.status').forEach(btn => {
        btn.addEventListener('click', async () => {
            const row = btn.closest('tr');
            const colabId = row.dataset.colaboradorId;
            try {
                const resp = await fetch(`./status.php?colaborador_id=${colabId}`);
                const data = await resp.json();
                alert(`Status: ${data.status}\nCompetência: ${data.competencia || '-'}\nArquivo: ${data.arquivo_nome || '-'}\nToken: ${data.zapsign_doc_token || '-'}`);
            } catch (e) {
                alert('Erro ao consultar status.');
            }
        });
    });

    carregarStatus();
});
