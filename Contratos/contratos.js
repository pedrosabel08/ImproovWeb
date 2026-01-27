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
        const rows = document.querySelectorAll('#contratos-table tbody tr');
        for (const row of rows) {
            const colabId = row.dataset.colaboradorId;
            const resp = await fetch(`./status.php?colaborador_id=${colabId}`, { credentials: 'same-origin' });
            if (!resp.ok) continue;
            const data = await resp.json();
            atualizarLinha(row, data);
        }
    }

    function atualizarLinha(row, data) {
        const competenciaCell = row.querySelector('.competencia');
        const statusCell = row.querySelector('.status');

        competenciaCell.textContent = data.competencia || '-';
        statusCell.innerHTML = `<span class="status-badge status-${data.status}">${data.status}</span>`;
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
                    alert('Contrato enviado com sucesso.');
                    const statusResp = await fetch(`./status.php?colaborador_id=${colabId}`);
                    const statusData = await statusResp.json();
                    atualizarLinha(row, statusData);
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
                    alert('Contrato reenviado.');
                    const statusResp = await fetch(`./status.php?colaborador_id=${colabId}`);
                    const statusData = await statusResp.json();
                    atualizarLinha(row, statusData);
                }
            } catch (e) {
                alert('Erro de rede ao reenviar contrato.');
            } finally {
                btn.disabled = false;
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
                alert(`Status: ${data.status}\nCompetência: ${data.competencia || '-'}\nToken: ${data.zapsign_doc_token || '-'}`);
            } catch (e) {
                alert('Erro ao consultar status.');
            }
        });
    });

    carregarStatus();
});
