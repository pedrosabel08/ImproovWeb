// Função para carregar os renders via AJAX
function loadRenders() {
    $.ajax({
        url: 'ajax.php',
        method: 'GET',
        data: { action: 'getRenders' },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'sucesso') {
                // Se o DataTable já estiver inicializado, destrói-o primeiro
                if ($.fn.DataTable.isDataTable('#renderTable')) {
                    $('#renderTable').DataTable().clear().destroy();
                }

                $('#renderList').html(''); // Limpa o conteúdo da tabela

                response.renders.forEach(function (render) {
                    let statusStyle = applyStatusStyle(render.status); // Obtém o estilo do status

                    $('#renderList').append(`
                            <tr data-id="${render.idrender_alta}" style="${statusStyle}">
                                <td>${render.idrender_alta}</td>
                                <td>${render.nome_colaborador}</td>
                                <td class="imagem-nome">${render.imagem_nome}</td>
                                <td>${render.nome_status}</td>
                                <td>${render.status}</td>
                                <td>${render.data}</td>
                            </tr>
                        `);

                });

                // Inicializar o DataTable novamente após limpar os dados
                $('#renderTable').DataTable({
                    paging: false,
                    lengthChange: false,
                    info: false,
                    ordering: true,
                    searching: true,
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Portuguese.json"
                    }
                });

                // Adicionar o evento de clique em cada linha da tabela
                $('#renderList tr').click(function () {
                    const idrender_alta = $(this).data('id');
                    editRender(idrender_alta); // Abrir o modal com os dados da linha clicada
                });
            }
        }
    });
}
// Função que retorna o estilo inline para o status
function applyStatusStyle(status) {
    switch (status) {
        case 'Finalizado':
            return 'background-color: green; color: white;';
        case 'Em andamento':
            return 'background-color: orange; color: black;';
        case 'Não iniciado':
            return 'background-color: #58aaff; color: black;';
        default:
            return '';
    }
}

// Função para abrir o modal e carregar os dados para edição
function editRender(idrender_alta) {
    $.ajax({
        url: 'ajax.php',
        method: 'GET',
        data: { action: 'getRender', idrender_alta: idrender_alta },
        dataType: 'json',
        success: function (response) {
            if (response.status == 'sucesso') {
                $('#render_id').val(response.render.idrender_alta);
                $('#imagem_nome').text(response.render.imagem_nome);
                $('#render_status').val(response.render.status);
                $('#myModal').css('display', 'flex');
            }
        }
    });
}

// Fechar o modal
$('#myModal .close').click(function () {
    $('#myModal').css('display', 'none');
});

// Atualizar o render
$('#editForm').submit(function (e) {
    e.preventDefault();
    const idrender_alta = $('#render_id').val();
    const status = $('#render_status').val();
    $.ajax({
        url: 'ajax.php',
        method: 'POST',
        data: {
            action: 'updateRender',
            idrender_alta: idrender_alta,
            status: status
        },
        dataType: 'json',
        success: function (response) {
            if (response.status == 'sucesso') {
                loadRenders();  // Recarrega a lista de renders
                $('#myModal').css('display', 'none');
            }
        }
    });
});

// Excluir o render
$('#deleteRender').click(function () {
    const idrender_alta = $('#render_id').val();
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
