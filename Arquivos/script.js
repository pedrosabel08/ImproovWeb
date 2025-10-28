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
                    ${item.tipo === 'PDF' ? `<i class="fas fa-file-pdf tooltip" data-tooltip="${item.tipo}" style="color:#E74C3C;"></i>` :
                    item.tipo === 'DWG' ? `<i class="fas fa-file tooltip" data-tooltip="${item.tipo}" style="color:#3498DB;"></i>` :
                        item.tipo === 'SKP' ? `<i class="fas fa-cube tooltip" data-tooltip="${item.tipo}" style="color:#2ECC71;"></i>` :
                            item.tipo === 'IMG' ? `<i class="fas fa-image tooltip" data-tooltip="${item.tipo}" style="color:#ebc634"></i>` :
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
const tipoCategoria = document.getElementById('tipo_categoria');
const sufixoSelect = document.getElementById('sufixoSelect');
const labelSufixo = document.getElementById('labelSufixo');

// Mapping of suffix options per file type
const SUFIXOS = {
    'DWG': ['TERREO', 'LAZER', 'COBERTURA', 'MEZANINO', 'CORTES', 'GERAL'],
    'PDF': ['DOCUMENTACAO', 'RELATORIO', 'LOGO'],
    'SKP': ['MODELAGEM', 'REFERENCIA'],
    'IMG': ['FACHADA', 'INTERNA', 'EXTERNA'],
    'IFC': ['BIM'],
    'Outros': ['Geral']
};

tipoArquivoSelect.addEventListener('change', async () => {
    const tipoArquivo = tipoArquivoSelect.value;
    referenciasContainer.innerHTML = '';
    // Mostra o modo para SKP ou REFS
    document.getElementById('refsSkpModo').style.display = (tipoArquivo === 'SKP' || tipoArquivo === 'IMG') ? 'block' : 'none';

    const modo = document.querySelector('input[name="refsSkpModo"]:checked')?.value || 'geral';

    // Se for SKP ou REFS e modo porImagem, mostra inputs por imagem
    if ((tipoArquivo === 'SKP' || tipoArquivo === 'IMG') && modo === 'porImagem') {
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
        arquivoFile.disabled = true;

        const imagens = await res.json();
        imagens.forEach(img => {
            const div = document.createElement('div');
            div.innerHTML = `
                <label>${img.imagem_nome}</label>
                <input type="file" name="arquivos_por_imagem[${img.id}][]" multiple>
            `;
            referenciasContainer.appendChild(div);
        });
    } else {
        // Upload geral
        arquivoFile.style.display = 'block';
        arquivoFile.required = true;
        arquivoFile.disabled = false;
    }

    // Populate suffix select based on type
    const options = SUFIXOS[tipoArquivo] || [];
    if (options.length) {
        sufixoSelect.innerHTML = '';
        options.forEach(opt => {
            const o = document.createElement('option');
            o.value = opt;
            o.textContent = opt;
            sufixoSelect.appendChild(o);
        });
        sufixoSelect.style.display = '';
        labelSufixo.style.display = '';
    } else {
        sufixoSelect.innerHTML = '';
        sufixoSelect.style.display = 'none';
        labelSufixo.style.display = 'none';
    }
});
document.getElementById('refsSkpModo').addEventListener('change', () => {
    tipoArquivoSelect.dispatchEvent(new Event('change'));
});

function buildFormData(form) {
    const formData = new FormData();

    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        if (input.type === 'file') return; // trata separadamente

        if (input.multiple && input.tagName === 'SELECT') {
            Array.from(input.selectedOptions).forEach(option => {
                formData.append(input.name, option.value);
            });
        } else {
            formData.append(input.name, input.value);
        }
    });

    // arquivos
    const fileInputs = form.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        Array.from(input.files).forEach(file => {
            if (file.size > 0) formData.append(input.name, file);
        });
    });

    return formData;
}

document.getElementById("uploadForm").addEventListener("submit", async function (e) {
    e.preventDefault();

    const form = e.target;
    const obra_id = form.obra_id.value;
    const tipo_arquivo = form.tipo_arquivo.value;
    const tipo_categoria = form.tipo_categoria.value;
    const tipo_imagem = Array.from(form['tipo_imagem[]'].selectedOptions).map(o => o.value);

    // Se for refs/skp, checa por imagem
    if (tipo_arquivo === 'IMG' || tipo_arquivo === 'SKP') {
        let imagensInputs = referenciasContainer.querySelectorAll('input[type="file"]');
        let existeAlgum = false;

        for (let input of imagensInputs) {
            // 🔎 Pula inputs sem arquivos
            if (!input.files || input.files.length === 0) continue;

            let imagemIdMatch = input.name.match(/\[(\d+)\]/);
            if (!imagemIdMatch) continue; // segurança caso não bata o regex
            let imagemId = imagemIdMatch[1];

            // Checa se existe para cada imagem que realmente tem arquivo
            const checkRes = await fetch('checkArquivoExistente.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ obra_id, tipo_arquivo, tipo_categoria, tipo_imagem, imagem_id: imagemId, tipo_categoria: tipo_categoria })
            });
            const checkData = await checkRes.json();
            if (checkData.existe) existeAlgum = true;
        }

        if (existeAlgum) {
            const confirm = await Swal.fire({
                title: 'Já existe arquivo para uma ou mais imagens!',
                text: 'Deseja substituir os arquivos existentes?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, substituir',
                cancelButtonText: 'Não, continuar'
            });

            form.querySelector('[name="flag_substituicao"]').checked = confirm.isConfirmed;
        }

    } else {
        // Checagem padrão para outros tipos
        const checkRes = await fetch('checkArquivoExistente.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ obra_id, tipo_arquivo, tipo_imagem, tipo_categoria })
        });
        const checkData = await checkRes.json();

        if (checkData.existe) {
            const confirm = await Swal.fire({
                title: 'Já existe arquivo desse tipo!',
                text: 'Deseja substituir o arquivo existente?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, substituir',
                cancelButtonText: 'Não, continuar'
            });

            if (confirm.isConfirmed) {
                form.querySelector('[name="flag_substituicao"]').checked = true;
            } else {
                // Usuário cancelou, garante que a substituição continue como false
                form.querySelector('[name="flag_substituicao"]').checked = false;
                // Aqui não precisa retornar, o envio continua
            }
        }
    }

    // Agora sim monta o FormData
    const formData = buildFormData(form);

    const modo = document.querySelector('input[name="refsSkpModo"]:checked')?.value || 'geral';
    formData.append('refsSkpModo', modo);

    // Remover arquivos vazios
    for (let [key, value] of formData.entries()) {
        if (value instanceof File && value.size === 0) {
            formData.delete(key);
        }
    }

    // Debug
    for (let [key, value] of formData.entries()) {
        console.log("Final:", key, value);
    }
    try {
        const response = await fetch('https://improov/ImproovWeb/Arquivos/upload.php', {
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

