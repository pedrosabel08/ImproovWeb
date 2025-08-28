let allRenders = [];

function renderCollaboratorFilter() {
    // Extrai nomes únicos dos colaboradores
    const colaboradores = [...new Set(allRenders.map(r => r.nome_colaborador).filter(Boolean))];
    $('#filterColaborador').html('<option value="">Todos os Responsáveis</option>');
    colaboradores.forEach(nome => {
        $('#filterColaborador').append(`<option value="${nome}">${nome}</option>`);
    });
}

function renderStatusFilter() {
    // Extrai nomes únicos dos colaboradores
    const status = [...new Set(allRenders.map(r => r.status).filter(Boolean))];
    $('#filterStatus').html('<option value="">Todos os Responsáveis</option>');
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
    $('#renderGrid').html('');
    renders.forEach(function (render) {
        const imgUrl = render.previa_jpg
            ? `https://improov.com.br/sistema/uploads/renders/${render.previa_jpg}`
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

        $('#renderGrid').append(`
            <div class="render-card" data-id="${render.idrender_alta}">
                <img src="${imgUrl}" alt="Preview" class="card-preview-img">
                <div class="render-card-content">
                    <p class="render-card-title">${render.imagem_nome}</p>
                    <p class="render-card-responsavel">Responsável: ${render.nome_colaborador}</p>
                    <p class="render-card-prazo">Prazo: ${formatarData(render.data)}</p>
                    <p class="render-card-status">Status: ${render.nome_status}</p>
                    <p class="render-status-badge ${statusBadgeClass}">${render.status}</p>
                </div>
            </div>
        `);
    });
    $('.render-card').off('click').on('click', function () {
        const idrender_alta = $(this).data('id');
        editRender(idrender_alta);
    });
}
function loadRenders() {
    $.ajax({
        url: 'ajax.php',
        method: 'GET',
        data: { action: 'getRenders' },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'sucesso') {
                allRenders = response.renders;
                renderCollaboratorFilter(); // Alimenta o select de colaborador
                renderStatusFilter(); // Alimenta o select de colaborador
                renderCards(allRenders);
            }
        }
    });
}

function filterRenders() {
    const status = $('#filterStatus').val();
    const colaborador = $('#filterColaborador').val();
    const filtered = allRenders.filter(r =>
        (status === '' || r.status === status) &&
        (colaborador === '' || r.nome_colaborador === colaborador)
    );
    renderCards(filtered);
}

// Eventos dos filtros
$('#filterStatus').on('change', filterRenders);
$('#filterColaborador').on('change', filterRenders);


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

                // Toggle da gaveta
                $('#toggleErrors').off('click').on('click', function () {
                    $('#modal_errors').slideToggle();
                    const btn = $(this);
                    btn.text(btn.text().includes('▼') ? 'Ocultar erros ▲' : 'Mostrar erros ▼');
                });

                const imgUrl = r.previa_jpg
                    ? `https://improov.com.br/sistema/uploads/renders/${r.previa_jpg}`
                    : '../assets/logo.jpg';
                $('#modalPreviewImg').attr('src', imgUrl);

                $('#myModal').css('display', 'flex');

                // Aqui escondemos os botões se o status for Aprovado, Reprovado ou Erro
                if (['Aprovado', 'Reprovado', 'Erro'].includes(r.status)) {
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
    const fullScreenDiv = $(`
        <div id="fullscreenImgDiv" style="
            position:fixed;top:0;left:0;width:100vw;height:100vh;
            background:rgba(0,0,0,0.95);display:flex;align-items:center;justify-content:center;z-index:9999;">
            <img src="${src}" style="max-width:90vw;max-height:90vh;border-radius:12px;">
        </div>
    `);
    $('body').append(fullScreenDiv);
    fullScreenDiv.click(function () { $(this).remove(); });
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
