const modal = document.getElementById('uploadModal');
const btnOpen = document.getElementById('btnUpload');
const btnClose = document.getElementById('closeModal');

btnOpen.addEventListener('click', () => modal.style.display = 'flex');
btnClose.addEventListener('click', () => modal.style.display = 'none');

async function carregarArquivos(filtros = {}) {
    try {
        // Monta query string se houver filtros
        let query = new URLSearchParams(filtros).toString();
        let response = await fetch('getArquivos.php?' + query);
        let dados = await response.json();

        const tbody = document.querySelector('.tabelaArquivos tbody');
        tbody.innerHTML = ''; // limpa tabela

        dados.forEach(item => {
            let statusClass = '';
            if (item.status === 'atualizado') statusClass = 'status-atualizado';
            else if (item.status === 'pendente') statusClass = 'status-pendente';
            else if (item.status === 'antigo') statusClass = 'status-antigo';

            let tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.nome_original}</td>
                <td>${item.projeto}</td>
                <td>${item.tipo_imagem}</td>
                    <td class="arquivoTd">
                    ${item.tipo === 'pdf' ? `<i class="fas fa-file-pdf tooltip" data-tooltip="${item.tipo}" style="color:#E74C3C;"></i>` :
                    item.tipo === 'dwg' ? `<i class="fas fa-file tooltip" data-tooltip="${item.tipo}" style="color:#3498DB;"></i>` :
                        item.tipo === 'skp' ? `<i class="fas fa-cube tooltip" data-tooltip="${item.tipo}" style="color:#2ECC71;"></i>` :
                            item.tipo === 'img' ? `<i class="fas fa-image tooltip" data-tooltip="${item.tipo}" style="color:#ebc634"></i>` :
                                `<i class="fas fa-file tooltip" data-tooltip="${item.tipo}"></i>`}
                    </td>
                <td class="statusTd"><span class="${statusClass}">${item.status}</span></td>
                <td>${new Date(item.recebido_em).toLocaleDateString()}</td>
            `;
            tbody.appendChild(tr);
        });

    } catch (err) {
        console.error('Erro ao carregar arquivos:', err);
    }
}

// Carrega na inicialização
carregarArquivos();

const tipoArquivoSelect = document.querySelector('select[name="tipo_arquivo"]');
const tipoImagemSelect = document.querySelector('select[name="tipo_imagem[]"]');
const referenciasContainer = document.getElementById('referenciasContainer');
const arquivoFile = document.getElementById('arquivoFile');

tipoArquivoSelect.addEventListener('change', async () => {
    const tipoArquivo = tipoArquivoSelect.value;
    referenciasContainer.innerHTML = '';

    if (tipoArquivo === 'refs' || tipoArquivo === 'skp') {
        const obraId = document.querySelector('select[name="obra_id"]').value;
        const tipoImagemIds = Array.from(tipoImagemSelect.selectedOptions).map(o => o.value);

        if (!obraId || tipoImagemIds.length === 0) return;

        const res = await fetch('getImagensObra.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ obra_id: obraId, tipo_imagem: tipoImagemIds })
        });

        arquivoFile.style.display = 'none';
        arquivoFile.required = false;

        const imagens = await res.json();
        imagens.forEach(img => {
            const div = document.createElement('div');
            div.innerHTML = `
                <label>${img.imagem_nome}</label>
                <input type="file" name="arquivos_por_imagem[${img.id}][]" multiple>
            `;
            referenciasContainer.appendChild(div);
        });
    }
});


document.getElementById("uploadForm").addEventListener("submit", async function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    try {
        const response = await fetch('upload.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        // Mensagens de sucesso
        if (result.success && result.success.length > 0) {
            result.success.forEach(msg => {
                Toastify({
                    text: msg,
                    duration: 3000,
                    close: true,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "green",
                    stopOnFocus: true,
                }).showToast();
            });

            // Recarrega tabela
            form.reset();
            modal.style.display = 'none';
            carregarArquivos();
        }

        // Mensagens de erro
        if (result.errors && result.errors.length > 0) {
            result.errors.forEach(msg => {
                Toastify({
                    text: msg,
                    duration: 5000,
                    close: true,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "red",
                    stopOnFocus: true,
                }).showToast();
            });
        }

    } catch (err) {
        console.error(err);
        Toastify({
            text: "Erro ao enviar os arquivos.",
            duration: 5000,
            close: true,
            gravity: "top",
            position: "right",
            backgroundColor: "red",
            stopOnFocus: true,
        }).showToast();
    }
});

