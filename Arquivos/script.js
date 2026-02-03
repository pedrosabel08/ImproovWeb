const modal = document.getElementById('uploadModal');
const btnOpen = document.getElementById('btnUpload');
const btnClose = document.getElementById('closeModal');

btnOpen.addEventListener('click', () => modal.style.display = 'flex');
btnClose.addEventListener('click', () => modal.style.display = 'none');

async function carregarArquivos(filtros = {}) {
    try {
        // Monta query string se houver filtros
        // Usando URLSearchParams e set/append para suportar arrays corretamente
        const params = new URLSearchParams();
        Object.entries(filtros).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '') return;
            if (Array.isArray(value)) {
                value.forEach(v => params.append(key, v));
            } else {
                params.set(key, value);
            }
        });

        let query = params.toString();
        let response = await fetch('getArquivos.php?' + query);
        let dados = await response.json();

        const tbody = document.querySelector('.tabelaArquivos tbody');
        tbody.innerHTML = ''; // limpa tabela

        dados.forEach(item => {
            let statusClass = '';
            if (item.status === 'atualizado') statusClass = 'status-atualizado';
            else if (item.status === 'pendente') statusClass = 'status-pendente';
            else if (item.status === 'antigo') statusClass = 'status-antigo';

            const viewPdf = (item.tipo === 'PDF' && item.idarquivo)
                ? `<a class="btn-view-pdf" href="visualizar_pdf.php?idarquivo=${encodeURIComponent(item.idarquivo)}" target="_blank" rel="noopener" title="Visualizar PDF" onclick="event.stopPropagation();">Ver PDF</a>`
                : '';

            let tr = document.createElement('tr');
            // add data attributes so we can act on click
            tr.dataset.idarquivo = item.idarquivo || '';
            tr.dataset.caminho = item.caminho || '';
            tr.innerHTML = `
                <td>${item.nome_interno}</td>
                <td>${item.projeto}</td>
                <td>${item.tipo_imagem}</td>
                    <td class="arquivoTd">
                    ${item.tipo === 'PDF' ? `<i class="fas fa-file-pdf tooltip" data-tooltip="${item.tipo}" style="color:#E74C3C;"></i>` :
                    item.tipo === 'DWG' ? `<i class="fas fa-file tooltip" data-tooltip="${item.tipo}" style="color:#3498DB;"></i>` :
                        item.tipo === 'SKP' ? `<i class="fas fa-cube tooltip" data-tooltip="${item.tipo}" style="color:#2ECC71;"></i>` :
                            item.tipo === 'IMG' ? `<i class="fas fa-image tooltip" data-tooltip="${item.tipo}" style="color:#ebc634"></i>` :
                                `<i class="fas fa-file tooltip" data-tooltip="${item.tipo}"></i>`}
                    ${viewPdf}
                    </td>
                <td class="statusTd"><span class="${statusClass}">${item.status}</span></td>
                <td>${new Date(item.recebido_em).toLocaleDateString()}</td>
            `;

            // click handler: toggle between antigo <-> atualizado
            tr.addEventListener('click', async (ev) => {
                // ignore clicks on inputs or buttons if any
                if (ev.target && (ev.target.tagName === 'BUTTON' || ev.target.tagName === 'A' || ev.target.closest('button'))) return;
                const idarquivo = tr.dataset.idarquivo;
                const caminho = tr.dataset.caminho;
                const statusText = (item.status || '').toLowerCase();
                try {
                    if (statusText === 'atualizado') {
                        const ok = confirm('Marcar arquivo como ANTIGO? Isto moverá o arquivo para a pasta OLD no servidor.');
                        if (!ok) return;
                        const resp = await fetch('moveArquivoStatus.php', {
                            method: 'POST', headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ idarquivo: idarquivo, action: 'antigo' })
                        });
                        const j = await resp.json();
                        if (j.success) {
                            alert('Arquivo movido para OLD com sucesso.');
                            carregarArquivos();
                        } else {
                            alert('Erro: ' + (j.error || 'erro desconhecido'));
                            console.error(j);
                        }
                    } else if (statusText === 'antigo') {
                        const ok = confirm('Marcar arquivo como ATUALIZADO? Isto moverá o arquivo de OLD para a pasta principal.');
                        if (!ok) return;
                        const resp = await fetch('moveArquivoStatus.php', {
                            method: 'POST', headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ idarquivo: idarquivo, action: 'atualizado' })
                        });
                        const j = await resp.json();
                        if (j.success) {
                            alert('Arquivo movido para pasta principal com sucesso.');
                            carregarArquivos();
                        } else {
                            alert('Erro: ' + (j.error || 'erro desconhecido'));
                            console.error(j);
                        }
                    } else {
                        // neutral/pending: offer both options
                        const sel = prompt('Status atual: ' + (item.status || 'N/A') + "\nDigite 'A' para marcar ANTIGO ou 'U' para ATUALIZADO", 'A');
                        if (!sel) return;
                        if (sel.toUpperCase() === 'A') {
                            const resp = await fetch('moveArquivoStatus.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ idarquivo: idarquivo, action: 'antigo' }) });
                            const j = await resp.json();
                            if (j.success) { alert('Arquivo movido para OLD.'); carregarArquivos(); } else { alert('Erro: ' + (j.error || 'erro desconhecido')); }
                        } else if (sel.toUpperCase() === 'U') {
                            const resp = await fetch('moveArquivoStatus.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ idarquivo: idarquivo, action: 'atualizado' }) });
                            const j = await resp.json();
                            if (j.success) { alert('Arquivo movido para principal.'); carregarArquivos(); } else { alert('Erro: ' + (j.error || 'erro desconhecido')); }
                        }
                    }
                } catch (err) {
                    console.error('Erro ao mudar status do arquivo', err);
                    alert('Erro ao alterar status do arquivo');
                }
            });

            tbody.appendChild(tr);
        });

    } catch (err) {
        console.error('Erro ao carregar arquivos:', err);
    }
}

// Carrega na inicialização
carregarArquivos();


// Wire filters to call carregarArquivos with selected values
document.addEventListener('DOMContentLoaded', () => {
    const obraFilter = document.getElementById('filter_obra');
    const tipoFilter = document.getElementById('filter_tipo');
    const tipoArquivoFilter = document.getElementById('filter_tipo_arquivo');

    function aplicarFiltros() {
        const filtros = {};
        if (obraFilter && obraFilter.value) filtros.obra_id = obraFilter.value;
        if (tipoFilter && tipoFilter.value) filtros.tipo = tipoFilter.value;
        if (tipoArquivoFilter && tipoArquivoFilter.value) filtros.tipo_arquivo = tipoArquivoFilter.value;

        carregarArquivos(filtros);
    }

    [obraFilter, tipoFilter, tipoArquivoFilter].forEach(el => {
        if (!el) return;
        el.addEventListener('change', aplicarFiltros);
    });
});

const tipoArquivoSelect = document.querySelector('select[name="tipo_arquivo"]');
const tipoImagemSelect = document.querySelector('select[name="tipo_imagem[]"]');
const referenciasContainer = document.getElementById('referenciasContainer');
const arquivoFile = document.getElementById('arquivoFile');
const tipoCategoria = document.getElementById('tipo_categoria');
const sufixoSelect = document.getElementById('sufixoSelect');
const labelSufixo = document.getElementById('labelSufixo');

// Mapping of suffix options per file type
const SUFIXOS = {
    'DWG': ['TERREO', 'LAZER', 'COBERTURA', 'MEZANINO', 'CORTES', 'GERAL', 'TIPO', 'GARAGEM', 'FACHADA', 'DUPLEX', 'ROOFTOP', 'LOGO', 'ACABAMENTOS', 'ESQUADRIA', 'ARQUITETONICO', 'REFERENCIA', 'IMPLANTACAO', 'SUBSOLO', 'G1', 'G2', 'G3', 'DUPLEX_SUPERIOR', 'DUPLEX_INFERIOR'],
    'PDF': ['DOCUMENTACAO', 'RELATORIO', 'LOGO', 'ARQUITETONICO', 'REFERENCIA', 'ESQUADRIA', 'ACABAMENTOS', 'TIPOLOGIA', 'IMPLANTACAO', 'SUBSOLO', 'G1', 'G2', 'G3', 'DUPLEX_SUPERIOR', 'DUPLEX_INFERIOR'],
    'SKP': ['MODELAGEM', 'REFERENCIA'],
    'IMG': ['FACHADA', 'INTERNA', 'EXTERNA', 'UNIDADE', 'LOGO', 'REFERENCIAS'],
    'IFC': ['BIM'],
    'Outros': ['Geral']
};

tipoArquivoSelect.addEventListener('change', async () => {
    const tipoArquivo = tipoArquivoSelect.value;
    referenciasContainer.innerHTML = '';
    // Mostra o modo para SKP ou REFS
    // Mostrar a opção de modo (geral / porImagem) para todos os tipos — permitir envio por imagem universal
    document.getElementById('refsSkpModo').style.display = 'block';

    const modo = document.querySelector('input[name="refsSkpModo"]:checked')?.value || 'geral';

    // Se modo porImagem, mostrar inputs por imagem para TODOS os tipos configurados
    if (modo === 'porImagem') {
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
            div.className = 'ref-imagem-block';
            div.innerHTML = `
                <label>${img.imagem_nome}</label>
                <input type="file" name="arquivos_por_imagem[${img.id}][]" multiple>
                <textarea name="observacoes_por_imagem[${img.id}]" placeholder="Observação para esta imagem (opcional)" rows="2" style="width:100%;margin-top:6px;"></textarea>
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
        // ignore file inputs here (handled below)
        if (input.type === 'file') return;

        // skip inputs without a name attribute
        if (!input.name) return;

        // checkboxes: only append if checked
        if (input.type === 'checkbox') {
            if (input.checked) formData.append(input.name, input.value || 'on');
            return;
        }

        // radios: only append the checked one
        if (input.type === 'radio') {
            if (input.checked) formData.append(input.name, input.value);
            return;
        }

        // multi-select handling
        if (input.tagName === 'SELECT' && input.multiple) {
            Array.from(input.selectedOptions).forEach(option => formData.append(input.name, option.value));
            return;
        }

        // default for other inputs/selects/textareas
        formData.append(input.name, input.value);
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

    // Se modo porImagem, checar por imagem; caso contrário checagem padrão para outros tipos
    const modoSubmit = document.querySelector('input[name="refsSkpModo"]:checked')?.value || 'geral';
    if (modoSubmit === 'porImagem') {
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
        // Preparar resumo dos parâmetros para exibir no Swal
        const paramsSummary = [];
        for (let [key, value] of formData.entries()) {
            if (value instanceof File) continue; // arquivos listados separadamente
            if (paramsSummary.find(p => p.key === key)) continue; // evita chaves repetidas
            paramsSummary.push({ key, value: String(value) });
        }

        // Lista de nomes de arquivos e tamanho total
        const fileNames = [];
        let totalBytes = 0;
        for (let [k, v] of formData.entries()) {
            if (v instanceof File) {
                fileNames.push(v.name + ' (' + Math.round(v.size/1024) + ' KB)');
                totalBytes += v.size;
            }
        }

        // Mostrar Swal com barra de progresso e detalhes
        const swalHtml = `
            <div style="text-align:left;margin-bottom:8px">
                <strong>Parâmetros:</strong>
                <strong>Arquivos (${fileNames.length}):</strong>
                <ul style="padding-left:18px;margin:6px 0">${fileNames.map(n => `<li>${n}</li>`).join('')}</ul>
            </div>
            <div style="margin-top:8px">
                <div id="swal-upload-progress" style="width:100%;background:#eee;border-radius:6px;overflow:hidden;height:14px">
                    <div id="swal-upload-bar" style="width:0%;height:100%;background:#3085d6"></div>
                </div>
                <div id="swal-upload-info" style="margin-top:6px;font-size:13px;color:#666">0% - 0 KB de ${Math.round(totalBytes/1024)} KB</div>
            </div>`;

        let xhr = new XMLHttpRequest();
        xhr.open('POST', 'upload.php', true);

        // Mostrar modal Swal e iniciar upload sem aguardar sua resolução
        let startTime = null;

        const swalPromise = Swal.fire({
            title: 'Enviando arquivos',
            html: swalHtml,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            allowOutsideClick: false,
            allowEscapeKey: false,
            allowEnterKey: false,
            didOpen: () => {
                // Avoid backdrop clicks bubbling to global handlers (which might close other modals)
                try {
                    const container = Swal.getContainer();
                    if (container) {
                        ['click', 'mousedown', 'touchstart', 'pointerdown'].forEach(evt => {
                            container.addEventListener(evt, (e) => e.stopPropagation(), true);
                        });
                    }
                } catch (e) { }
            },
            willOpen: () => {
                // attach progress handler
                const container = Swal.getHtmlContainer();
                const bar = container ? container.querySelector('#swal-upload-bar') : null;
                const info = container ? container.querySelector('#swal-upload-info') : null;

                startTime = Date.now();

                xhr.upload.onprogress = function (e) {
                    if (e.lengthComputable) {
                        const now = Date.now();
                        const elapsed = (now - startTime) / 1000; // seconds
                        const uploadedMB = e.loaded / (1024 * 1024);
                        const totalMB = e.total / (1024 * 1024);
                        const percent = (e.loaded / e.total) * 100;
                        const speed = uploadedMB / (elapsed || 0.0001); // MB/s
                        const remainingMB = Math.max(0, totalMB - uploadedMB);
                        const estimatedTime = remainingMB / (speed || 0.0001);

                        if (bar) bar.style.width = percent + '%';
                        if (info) {
                            info.textContent = `${percent.toFixed(2)}% - ${Math.round(e.loaded/1024)} KB de ${Math.round(e.total/1024)} KB — Tempo: ${elapsed.toFixed(1)}s — Velocidade: ${speed.toFixed(2)} MB/s — Estimativa: ${estimatedTime.toFixed(1)}s`;
                        }
                    }
                };

                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        // Response handling below after Swal.close()
                    }
                };
            }
        });

        // Start sending immediately so progress events update the open Swal
        xhr.send(formData);

        // Prepare a promise that resolves when upload completes (or errors/aborts)
        const uploadPromise = new Promise((resolve, reject) => {
            xhr.onload = function () {
                try {
                    const json = JSON.parse(xhr.responseText || '{}');
                    resolve(json);
                } catch (err) {
                    reject(new Error('Resposta inválida do servidor'));
                }
            };
            xhr.onerror = function () { reject(new Error('Erro na requisição')); };
            xhr.onabort = function () { reject(new Error('Envio cancelado pelo usuário')); };
        });

        // Race between upload completion and user cancelling the Swal.
        // If user cancels first, abort XHR. If upload completes first, process the response.
        const race = await Promise.race([
            uploadPromise.then(res => ({ type: 'upload', res })).catch(err => ({ type: 'upload_error', err })),
            swalPromise.then(res => ({ type: 'swal', res }))
        ]);

        if (race.type === 'swal' && race.res && race.res.dismiss === Swal.DismissReason.cancel) {
            try { xhr.abort(); } catch (e) {}
            Swal.close();
            throw new Error('Envio cancelado pelo usuário');
        }

        if (race.type === 'upload_error') {
            Swal.close();
            throw race.err;
        }

        // At this point upload finished successfully
        Swal.close();
        const uploadResult = race.res;

        const result = uploadResult;
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
        Swal.close();
        Toastify({
            text: err.message || "Erro ao enviar os arquivos.",
            duration: 5000,
            close: true,
            gravity: "top",
            position: "right",
            backgroundColor: "red",
            stopOnFocus: true,
        }).showToast();
    }
});

