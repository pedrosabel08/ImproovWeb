let allRenders = [];

function renderObraFilter() {
    const obras = [...new Set(allRenders.map(r => r.obra_nomenclatura).filter(Boolean))].sort();
    $('#filterObra').html('<option value="">Todas as Obras</option>');
    obras.forEach(nome => {
        $('#filterObra').append(`<option value="${nome}">${nome}</option>`);
    });
}

function renderCollaboratorFilter() {
    // Extrai nomes únicos dos colaboradores
    const colaboradores = [...new Set(allRenders.map(r => r.nome_colaborador).filter(Boolean))].sort();
    $('#filterColaborador').html('<option value="">Todos os Responsáveis</option>');
    colaboradores.forEach(nome => {
        $('#filterColaborador').append(`<option value="${nome}">${nome}</option>`);
    });
}

function renderStatusFilter() {
    const status = [...new Set(allRenders.map(r => r.status).filter(Boolean))].sort();
    $('#filterStatus').html('<option value="">Todos os Status</option>');
    status.forEach(nome => {
        $('#filterStatus').append(`<option value="${nome}">${nome}</option>`);
    });
}

function formatarData(data) {
    const dataObj = data instanceof Date ? data : new Date(data);

    const pad = num => num.toString().padStart(2, '0');

    const dia = pad(dataObj.getDate());
    const mes = pad(dataObj.getMonth() + 1);
    const ano = dataObj.getFullYear();

    const hora = pad(dataObj.getHours());
    const min = pad(dataObj.getMinutes());
    const seg = pad(dataObj.getSeconds());

    return `${dia}/${mes}/${ano} ${hora}:${min}:${seg}`;
}

function renderCards(renders) {
    // Build HTML in a single string to reduce DOM thrashing
    let html = '';
    $('#renderGrid').html('');
    renders.forEach(function (render) {
        // Prefer server-generated thumbnails to avoid loading full-size images on the grid
        const imgUrl = render.previa_jpg
            ? `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURI('uploads/renders/' + render.previa_jpg)}&w=360&q=75`
            : '../assets/logo.jpg';

        let statusBadgeClass = '';
        if (render.status === 'Finalizado') {
            statusBadgeClass = 'render-status-finalizado';
        } else if (render.status === 'Em andamento') {
            statusBadgeClass = 'render-status-andamento';
        } else if (render.status === 'Erro') {
            statusBadgeClass = 'render-status-erro';
        } else if (render.status === 'Reprovado') {
            statusBadgeClass = 'render-status-reprovado';
        } else if (render.status === 'Aprovado') {
            statusBadgeClass = 'render-status-aprovado';
        } else if (render.status === 'Em aprovação') {
            statusBadgeClass = 'render-status-aprovacao';
        } else {
            statusBadgeClass = 'render-status-outro';
        }

        // Use native lazy loading attribute to avoid downloading all images at once
        html += `
            <div class="render-card" data-id="${render.idrender_alta}">
                <img loading="lazy" decoding="async" width="270" height="160" src="${imgUrl}" alt="Preview" class="card-preview-img">
                <div class="render-card-content">
                    <p class="render-card-title">${render.imagem_nome}</p>
                    <p class="render-card-responsavel">Responsável: ${render.nome_colaborador}</p>
                    <p class="render-card-prazo">Prazo: ${formatarData(render.data)}</p>
                    <p class="render-card-status">Status: ${render.nome_status}</p>
                    <p class="render-card-obra">Obra: ${render.obra_nomenclatura}</p>
                    <p class="render-status-badge ${statusBadgeClass}">${render.status}</p>
                </div>
            </div>
        `;
    });

    // Append once
    $('#renderGrid').append(html);
}

// Use event delegation to avoid re-attaching handlers on every re-render
$('#renderGrid').off('click.renderClick').on('click.renderClick', '.render-card', function () {
    const idrender_alta = $(this).data('id');
    editRender(idrender_alta);
});
function loadRenders() {
    $.ajax({
        url: 'ajax.php',
        method: 'GET',
        data: { action: 'getRenders' },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'sucesso') {
                allRenders = response.renders;
                renderObraFilter();         // Alimenta o select de obra
                renderCollaboratorFilter(); // Alimenta o select de colaborador
                renderStatusFilter();       // Alimenta o select de status
                renderCards(allRenders);
            }
        }
    });
}

function filterRenders() {
    const status = $('#filterStatus').val();
    const colaborador = $('#filterColaborador').val();
    const obra = $('#filterObra').val();
    const filtered = allRenders.filter(r =>
        (status === '' || r.status === status) &&
        (colaborador === '' || r.nome_colaborador === colaborador) &&
        (obra === '' || r.nome_obra === obra)
    );
    renderCards(filtered);
}

// Eventos dos filtros
$('#filterStatus').on('change', filterRenders);
$('#filterColaborador').on('change', filterRenders);
$('#filterObra').on('change', filterRenders);


// Função para abrir o modal e carregar os dados para edição
function editRender(idrender_alta) {
    $.ajax({
        url: 'ajax.php',
        method: 'GET',
        data: { action: 'getRender', idrender_alta: idrender_alta },
        dataType: 'json',
        success: function (response) {
            if (response.status == 'sucesso') {
                const r = response.render;
                $('#render_id').val(r.idrender_alta);
                $('#modal_idrender').text(r.idrender_alta);
                $('#modal_imagem_id').text(r.imagem_nome);
                $('#modal_status').text(r.status);
                $('#modal_responsavel_id').text(r.nome_colaborador);
                $('#modal_status_id').text(r.nome_status);
                $('#modal_computer').text(r.computer);
                $('#modal_submitted').text(formatarData(r.submitted));
                $('#modal_last_updated').text(formatarData(r.last_updated));
                $('#modal_has_error').text(r.has_error == 1 ? 'Sim' : 'Não');
                // $('#modal_errors').text(r.errors || '');
                $('#modal_job_folder').text(r.job_folder);
                $('#modal_previa_jpg').text(r.previa_jpg);
                $('#modal_numero_bg').text(r.numero_bg);

                const errors = r.errors || '';
                if (errors) {
                    $('#errorsContainer').show();
                    $('#modal_errors').text(errors).hide(); // começa fechada
                } else {
                    $('#errorsContainer').hide();
                    $('#modal_errors').hide();
                }

                // Toggle da gaveta de erros
                $('#toggleErrors').off('click').on('click', function (event) {
                    event.preventDefault(); // evita recarregar a página
                    $('#modal_errors').slideToggle();
                    const btn = $(this);
                    btn.text(btn.text().includes('▼') ? 'Ocultar erros ▲' : 'Mostrar erros ▼');
                });

                // Prefer previews array if available. If previews exist, show gallery and
                // set the main image to the first preview. Do NOT show render.previa_jpg
                // when previews are present.
                const previews = response.previews || [];
                const $imgPreviewContainer = $('.imagem-preview');

                // Remove any existing gallery to avoid duplicates
                $imgPreviewContainer.find('#modalGallery').remove();

                if (previews.length > 0) {
                    const first = previews[0];
                    // Main modal image: use a larger thumbnail to balance quality and speed
                    const mainUrl = first.filename
                        ? `https://improov.com.br/flow/ImproovWeb/uploads/renders/${encodeURIComponent(first.filename)}`
                        : '../assets/logo.jpg';

                    // Set main image src to first preview (original full-size)
                    $('#modalPreviewImg').attr('src', mainUrl);

                    // Build gallery node
                    const $gallery = $('<div id="modalGallery" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"></div>');
                    previews.forEach(function (p, idx) {
                        const thumbUrl = p.filename
                            ? `https://improov.com.br/flow/ImproovWeb/uploads/renders/${encodeURIComponent(p.filename)}`
                            : '../assets/logo.jpg';
                        const $thumb = $(`<img class="modal-thumb" loading="lazy" decoding="async" data-filename="${p.filename}" data-idx="${idx}" src="${thumbUrl}" alt="Preview ${idx + 1}">`);
                        // style the thumbnail a bit (you can move to CSS file)
                        $thumb.css({ width: '60px', height: '60px', objectFit: 'cover', cursor: 'pointer', borderRadius: '4px', border: '2px solid transparent' });
                        if (idx === 0) $thumb.css('border-color', '#4caf50');
                        $gallery.append($thumb);
                    });

                    // Insert gallery after the main image inside .imagem-preview
                    if ($imgPreviewContainer.length) {
                        $imgPreviewContainer.append($gallery);
                    } else {
                        // Fallback: append to body
                        $('body').append($gallery);
                    }

                    // Thumbnail click handler: set main image and active state
                    $gallery.find('.modal-thumb').off('click').on('click', function () {
                        const src = $(this).attr('src');
                        $('#modalPreviewImg').attr('src', src);
                        $gallery.find('.modal-thumb').css('border-color', 'transparent');
                        $(this).css('border-color', '#4caf50');
                    });
                } else {
                    // No previews: fallback to previsa_jpg if available
                    const imgUrl = r.previa_jpg
                        ? `https://improov.com.br/flow/ImproovWeb/uploads/renders/${encodeURIComponent(r.previa_jpg)}`
                        : '../assets/logo.jpg';
                    $('#modalPreviewImg').attr('src', imgUrl);
                }

                $('#myModal').css('display', 'flex');

                // Aqui escondemos os botões se o status for Aprovado, Reprovado ou Erro
                if (['Aprovado', 'Reprovado'].includes(r.status)) {
                    $('#aprovarRender').hide();
                    $('#reprovarRender').hide();
                } else {
                    $('#aprovarRender').show();
                    $('#reprovarRender').show();
                }
            }
        }
    });
}

$('#modalPreviewImg').off('click').on('click', function () {
    const src = $(this).attr('src');

    // Criar modal fullscreen
    const fullScreenDiv = $(`
        <div id="fullscreenImgDiv">
            <div id="image_wrapper">
                <img id="fullscreenImg" src="${src}">
            </div>
        </div>
    `);

    $('body').append(fullScreenDiv);

    // scope the elements inside the newly created fullScreenDiv to avoid global selectors
    const $imageWrapper = fullScreenDiv.find('#image_wrapper');
    const $img = fullScreenDiv.find('#fullscreenImg');

    // Zoom & Pan variables
    let currentZoom = 1;
    const zoomStep = 0.1;
    const maxZoom = 5;
    const minZoom = 0.1;

    let isDragging = false;
    let startX, startY;
    let currentTranslateX = 0;
    let currentTranslateY = 0;
    let dragMoved = false;

    // Função para aplicar transformações
    function applyTransforms() {
        $imageWrapper.css('transform', `scale(${currentZoom}) translate(${currentTranslateX}px, ${currentTranslateY}px)`);
    }

    // Zoom com Ctrl + scroll
    fullScreenDiv.on('wheel', function (event) {
        if (event.ctrlKey) {
            event.preventDefault();
            if (event.originalEvent.deltaY < 0) {
                currentZoom = Math.min(currentZoom + zoomStep, maxZoom);
            } else {
                currentZoom = Math.max(currentZoom - zoomStep, minZoom);
            }

            if (currentZoom === minZoom) {
                currentTranslateX = 0;
                currentTranslateY = 0;
            }

            applyTransforms();
        }
    });


    // Iniciar drag
    $imageWrapper.on('mousedown.fullscreen', function (e) {
        if (e.button === 0 && !e.ctrlKey) {
            isDragging = true;
            dragMoved = false;
            startX = e.clientX - currentTranslateX;
            startY = e.clientY - currentTranslateY;
            $imageWrapper.css('cursor', 'grabbing').css('transition', 'none');
        }
    });

    // Use namespaced document handlers so we can remove them when closing
    const mouseMoveHandler = function (e) {
        if (!isDragging) return;
        e.preventDefault();
        const dx = e.clientX - startX;
        const dy = e.clientY - startY;

        if (Math.abs(dx) > 3 || Math.abs(dy) > 3) dragMoved = true;

        currentTranslateX = dx;
        currentTranslateY = dy;
        applyTransforms();
    };

    const mouseUpHandler = function () {
        if (isDragging) {
            isDragging = false;
            $imageWrapper.css('cursor', 'grab').css('transition', 'transform 0.1s ease-out');
        }
    };

    $(document).on('mousemove.fullscreen', mouseMoveHandler);
    $(document).on('mouseup.fullscreen', mouseUpHandler);

    // Fechar modal clicando no fundo - limpar handlers namespaced
    fullScreenDiv.on('click', function (e) {
        if (e.target.id === 'fullscreenImgDiv') {
            $(document).off('.fullscreen');
            $(this).remove();
        }
    });

    // Ensure handlers are cleaned up if the element is removed programmatically
    fullScreenDiv.on('remove', function () {
        $(document).off('.fullscreen');
    });

    applyTransforms();
});


// Fechar o modal
$('#myModal .close').click(function () {
    $('#myModal').css('display', 'none');
});

$('#aprovarRender').click(function () {
    const idrender_alta = $('#modal_idrender').text();

    $.post('ajax.php', {
        action: 'updateRender',
        idrender_alta: idrender_alta,
        status: 'Aprovado'
    }, function (response) {
        console.log('Resposta updateRender:', response);
        if (response.status === 'sucesso') {
            // Atualiza os renders
            loadRenders();
            Toastify({
                text: "Render aprovado com sucesso!",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "#4caf50", // verde
            }).showToast();

            // Abre o modal POS
            $('#modalPOS').css('display', 'flex');
            $('#pos_render_id').val(idrender_alta);

            // NÃO fechamos o modal principal
        } else {
            Toastify({
                text: "Erro ao atualizar Render!",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "#f44336", // vermelho
            }).showToast();
        }
    }, 'json')
        .fail(function (xhr, status, error) {
            console.error('AJAX error:', error, xhr.responseText);
            Toastify({
                text: "Erro de comunicação com o servidor!",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "#f44336",
            }).showToast();
        });
});

// Fechar modal POS
$('#fecharPOS').click(function () {
    $('#modalPOS').hide();
    $('#pos_caminho').val('');
    $('#pos_referencias').val('');
});

// Enviar dados do POS
$('#enviarPOS').click(function () {
    const render_id = $('#pos_render_id').val();
    const refs = $('#pos_caminho').val();
    const obs = $('#pos_referencias').val();

    if (!render_id) {
        Toastify({
            text: "ID do render não definido!",
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: "#f44336", // vermelho
        }).showToast();
        return;
    }

    $.post('ajax.php', {
        action: 'updatePOS',
        render_id: render_id,
        refs: refs,
        obs: obs
    }, function (response) {
        if (response.status === 'sucesso') {
            Toastify({
                text: "Pós-produção atualizada com sucesso!",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "#4caf50", // verde
            }).showToast();

            $('#modalPOS').hide();
            $('#pos_caminho').val('');
            $('#pos_referencias').val('');
            $('#myModal').css('display', 'none');
        } else {
            Toastify({
                text: "Erro ao atualizar pós-produção!",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "#f44336", // vermelho
            }).showToast();
        }
    }, 'json')
        .fail(function (xhr, status, error) {
            console.error('AJAX error:', error, xhr.responseText);
            Toastify({
                text: "Erro de comunicação com o servidor!",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "#f44336",
            }).showToast();
        });
});

$('#reprovarRender').click(function () {
    const idrender_alta = $('#modal_idrender').text();
    $.post('ajax.php', {
        action: 'updateRender',
        idrender_alta: idrender_alta,
        status: 'Reprovado'
    }, function (response) {
        if (response.status === 'sucesso') {
            loadRenders();
            $('#myModal').hide();
            Toastify({
                text: "Render reprovado com sucesso!",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "#4ca8afff", // verde
            }).showToast();
        }
        else {
            Toastify({
                text: "Erro ao reprovar Render!",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "#f44336", // vermelho
            }).showToast();
        }
    }, 'json')
        .fail(function (xhr, status, error) {
            console.error('AJAX error:', error, xhr.responseText);
            Toastify({
                text: "Erro de comunicação com o servidor!",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "#f44336",
            }).showToast();
        });
});

// Excluir o render
$('#deleteRender').off('click').on('click', function (e) {
    e.preventDefault(); // Evita submit do formulário se for button type="submit"
    const idrender_alta = $('#modal_idrender').text();
    $.ajax({
        url: 'ajax.php',
        method: 'POST',
        data: {
            action: 'deleteRender',
            idrender_alta: idrender_alta
        },
        dataType: 'json',
        success: function (response) {
            if (response.status == 'sucesso') {
                loadRenders();  // Recarrega a lista de renders
                $('#myModal').css('display', 'none');

                Toastify({
                    text: "Render excluído com sucesso!",
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#f15e1aff", // verde
                }).showToast();
            }
        }
    });
});

// Carregar os renders quando a página for carregada
$(document).ready(function () {
    loadRenders();

    // Iniciar tutorial ao clicar no botão
    $('#startTutorial').on('click', function () {
        startIntroWithStepCallback();
    });
});

// Função para iniciar o Intro.js e simular o clique no step 2
function startIntroWithStepCallback() {
    $('#myModal').css('display', 'flex'); // Abre o modal ANTES do tutorial

    setTimeout(() => {
        var intro = introJs();
        intro.setOptions({
            nextLabel: 'Próximo',
            prevLabel: 'Anterior',
            doneLabel: 'Finalizar'
        });

        intro.onchange(function (targetElement) {
            if (this._currentStep === 2) {
                const statusElement = document.getElementById('render_status');
                if (statusElement) {
                    statusElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });

        intro.oncomplete(function () {
            $('#myModal').css('display', 'none');
        });

        intro.onexit(function () {
            $('#myModal').css('display', 'none');
        });

        intro.start();
    }, 1); // tempo suficiente para o modal renderizar
}
