// Função para carregar os renders via AJAX
function loadRenders() {
    $.ajax({
        url: 'ajax.php',
        method: 'GET',
        data: { action: 'getRenders' },
        dataType: 'json',
        success: function (response) {
            $('#renderList').html('');
            if (response.status == 'sucesso') {
                response.renders.forEach(function (render) {
                    $('#renderList').append(`
                        <tr data-id="${render.idrender_alta}">
                            <td>${render.idrender_alta}</td>
                            <td class="imagem-nome">${render.imagem_nome}</td>
                            <td>${render.status}</td>
                            <td>${render.data}</td>
                        </tr>
                    `);
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
                $('#imagem_nome').text(response.render.imagem_nome); // Atualiza o conteúdo do <p> com o nome da imagem
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
});


const myModal = document.getElementById('myModal');

document.addEventListener("DOMContentLoaded", function () {

    document.getElementById('menuButton').addEventListener('click', function () {
        const menu = document.getElementById('menu');
        menu.classList.toggle('hidden');
    });

    window.addEventListener('click', function (event) {
        const menu = document.getElementById('menu');
        const button = document.getElementById('menuButton');

        if (!button.contains(event.target) && !menu.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });

});

window.onclick = function (event) {
    if (event.target == myModal) {
        myModal.style.display = "none";
    }
}