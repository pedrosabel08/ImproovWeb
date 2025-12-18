(function () {
    const btnOpen = document.getElementById('btnAddClienteObra');
    const modal = document.getElementById('modalAddClienteObra');
    if (!btnOpen || !modal) return;

    const btnClose = document.getElementById('closeAddClienteObra');

    const inputCliente = document.getElementById('acoCliente');
    const inputObra = document.getElementById('acoObra');
    const inputNomenclatura = document.getElementById('acoNomenclatura');
    const inputNomeReal = document.getElementById('acoNomeReal');

    const btnSalvar = document.getElementById('acoSalvarClienteObra');
    const idsBox = document.getElementById('acoIdsBox');
    const idsText = document.getElementById('acoIdsText');

    const fileTxt = document.getElementById('acoTxtFile');
    const btnSalvarImagens = document.getElementById('acoSalvarImagens');

    const state = {
        cliente_id: null,
        obra_id: null,
        nomenclatura: null,
    };

    function open() {
        modal.style.display = 'flex';
    }

    function close() {
        modal.style.display = 'none';
    }

    function setStep2Enabled(enabled) {
        btnSalvarImagens.disabled = !enabled;
        fileTxt.disabled = !enabled;
    }

    function resetState() {
        state.cliente_id = null;
        state.obra_id = null;
        state.nomenclatura = null;
        idsBox.style.display = 'none';
        idsText.textContent = '';
        setStep2Enabled(false);
        if (fileTxt) fileTxt.value = '';
    }

    btnOpen.addEventListener('click', () => {
        resetState();
        open();
    });

    btnClose.addEventListener('click', close);

    modal.addEventListener('click', (e) => {
        if (e.target === modal) close();
    });

    btnSalvar.addEventListener('click', async () => {
        const cliente = (inputCliente.value || '').trim();
        const obra = (inputObra.value || '').trim();
        const nomenclatura = (inputNomenclatura.value || '').trim();
        const nome_real = (inputNomeReal.value || '').trim();

        if (!cliente || !obra || !nomenclatura || !nome_real) {
            alert('Preencha cliente, obra, nomenclatura e nome real.');
            return;
        }

        btnSalvar.disabled = true;
        try {
            const res = await fetch('adicionarClienteObra.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cliente, obra, nomenclatura, nome_real })
            });

            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.success) {
                alert((data && data.message) ? data.message : 'Erro ao criar cliente/obra.');
                return;
            }

            state.cliente_id = data.cliente_id;
            state.obra_id = data.obra_id;
            state.nomenclatura = nomenclatura;

            idsText.textContent = `Cliente ID: ${state.cliente_id} | Obra ID: ${state.obra_id}`;
            idsBox.style.display = 'block';
            setStep2Enabled(true);

            alert('Cliente e obra criados. Agora envie o TXT.');
        } catch (err) {
            console.error(err);
            alert('Erro de rede ao criar cliente/obra.');
        } finally {
            btnSalvar.disabled = false;
        }
    });

    btnSalvarImagens.addEventListener('click', async () => {
        if (!state.cliente_id || !state.obra_id) {
            alert('Crie o cliente/obra primeiro.');
            return;
        }
        if (!fileTxt.files || fileTxt.files.length === 0) {
            alert('Selecione um arquivo TXT.');
            return;
        }

        btnSalvarImagens.disabled = true;
        try {
            const fd = new FormData();
            fd.append('cliente_id', String(state.cliente_id));
            fd.append('obra_id', String(state.obra_id));
            fd.append('nomenclatura', state.nomenclatura || '');
            fd.append('txtFile', fileTxt.files[0]);

            const res = await fetch('importarImagensTxt.php', { method: 'POST', body: fd });
            const data = await res.json().catch(() => null);

            if (!res.ok || !data || !data.success) {
                alert((data && data.message) ? data.message : 'Erro ao importar imagens.');
                return;
            }

            const erros = Array.isArray(data.erros) ? data.erros.length : 0;
            alert(`Importação concluída. Inseridas: ${data.inseridas}. Erros: ${erros}.`);
        } catch (err) {
            console.error(err);
            alert('Erro de rede ao importar imagens.');
        } finally {
            btnSalvarImagens.disabled = false;
        }
    });
})();
