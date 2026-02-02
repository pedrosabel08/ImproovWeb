let dataTable = null;

function carregarUsuarios() {
    fetch('usuarios.php')
        .then(response => response.json())
        .then(data => {
            let result = '';

            data.forEach(element => {
                result += `
                    <tr class="usuario-row" data-idusuario="${element.idusuario}" data-idcolaborador="${element.idcolaborador}" data-ativo="${element.ativo}">
                        <td>${element.idusuario}</td>
                        <td>${element.nome_colaborador || '-'}</td>
                        <td>${element.nome_usuario}</td>
                        <td>${element.login || '-'}</td>
                        <td>${element.nivel_acesso ?? '-'}</td>
                        <td>${element.nome_cargo || '-'}</td>
                        <td>${element.ativo == 1 ? 'Sim' : 'Não'}</td>
                    </tr>
                `;
            });

            document.querySelector('#usuarios tbody').innerHTML = result;

            if (dataTable) {
                dataTable.destroy();
            }

            dataTable = $('#usuarios').DataTable({
                "paging": true,
                "lengthChange": false,
                "info": false,
                "ordering": true,
                "searching": true,
                "pageLength": 15,
                "order": [[0, 'desc']],
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Portuguese.json"
                }
            });

            document.querySelectorAll('.usuario-row').forEach(row => {
                row.addEventListener('click', function () {
                    abrirModalEdicao(this.getAttribute('data-idusuario'));
                });

                row.addEventListener('contextmenu', function (event) {
                    event.preventDefault();
                    abrirMenuStatus(event, this);
                });
            });
        });
}

function abrirModalEdicao(idusuario) {
    const modal = document.getElementById('modal');
    modal.style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Editar colaborador';
    document.getElementById('action').value = 'update';
    document.getElementById('btnExcluir').style.display = 'inline-flex';

    fetch(`get_usuario.php?idusuario=${idusuario}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('idusuario').value = data.usuario.idusuario;
            document.getElementById('idcolaborador').value = data.usuario.idcolaborador;
            document.getElementById('nome_colaborador').value = data.usuario.nome_colaborador || '';
            document.getElementById('nome_usuario').value = data.usuario.nome_usuario || '';
            document.getElementById('login').value = data.usuario.login || '';
            document.getElementById('senha').value = '';
            document.getElementById('nivel_acesso').value = data.usuario.nivel_acesso ?? '';

            $('#cargoSelect').val(data.cargos).trigger('change');
        });
}

function abrirModalNovo() {
    const modal = document.getElementById('modal');
    modal.style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Novo colaborador';
    document.getElementById('action').value = 'create';
    document.getElementById('btnExcluir').style.display = 'none';

    document.getElementById('form').reset();
    document.getElementById('idusuario').value = '';
    document.getElementById('idcolaborador').value = '';
    $('#cargoSelect').val([]).trigger('change');
}

function abrirMenuStatus(event, row) {
    const menu = document.getElementById('statusMenu');
    const btn = document.getElementById('toggleStatusBtn');
    const ativo = row.getAttribute('data-ativo') === '1';

    btn.textContent = ativo ? 'Desativar colaborador' : 'Ativar colaborador';
    btn.onclick = function () {
        toggleStatus(row.getAttribute('data-idusuario'), ativo ? 0 : 1);
    };

    menu.style.display = 'block';
    menu.setAttribute('aria-hidden', 'false');

    const menuRect = menu.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    let left = event.clientX;
    let top = event.clientY;

    if (left + menuRect.width > viewportWidth - 12) {
        left = event.clientX - menuRect.width;
    }

    if (top + menuRect.height > viewportHeight - 12) {
        top = event.clientY - menuRect.height;
    }

    menu.style.left = `${Math.max(12, left)}px`;
    menu.style.top = `${Math.max(12, top)}px`;
}

function esconderMenuStatus() {
    const menu = document.getElementById('statusMenu');
    menu.style.display = 'none';
    menu.setAttribute('aria-hidden', 'true');
}

function toggleStatus(idusuario, ativo) {
    $.ajax({
        type: 'POST',
        url: 'salvar_colaborador.php',
        data: { action: 'toggle_status', idusuario, ativo },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                carregarUsuarios();
                esconderMenuStatus();
            } else {
                alert(response.message || 'Erro ao atualizar status.');
            }
        },
        error: function () {
            alert('Erro ao atualizar status.');
        }
    });
}

carregarUsuarios();

// Fechar modal ao clicar fora dele
window.onclick = function (event) {
    const modal = document.getElementById('modal');
    const menu = document.getElementById('statusMenu');
    if (event.target == modal) {
        modal.style.display = "none";
    }
    if (menu && !menu.contains(event.target)) {
        esconderMenuStatus();
    }
}

$(document).ready(function () {
    $('#cargoSelect').select2({
        placeholder: "Selecione os cargos",
        allowClear: true
    });

    $('#btnAdicionar').on('click', function () {
        abrirModalNovo();
    });

    $('.close, #btnCancelar').on('click', function () {
        $('#modal').hide();
    });

    $(document).on('scroll', function () {
        esconderMenuStatus();
    });

    $(document).on('click', function (event) {
        const menu = document.getElementById('statusMenu');
        if (menu && !menu.contains(event.target)) {
            esconderMenuStatus();
        }
    });
});

$('#form').on('submit', function (e) {
    e.preventDefault();

    const formData = {
        action: $('#action').val(),
        idusuario: $('#idusuario').val(),
        idcolaborador: $('#idcolaborador').val(),
        nome_colaborador: $('#nome_colaborador').val(),
        nome_usuario: $('#nome_usuario').val(),
        login: $('#login').val(),
        senha: $('#senha').val(),
        nivel_acesso: $('#nivel_acesso').val(),
        cargos: $('#cargoSelect').val()
    };

    $.ajax({
        type: 'POST',
        url: 'salvar_colaborador.php',
        data: formData,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                alert(response.message || 'Salvo com sucesso!');
                $('#modal').hide();
                carregarUsuarios();
            } else {
                alert(response.message || 'Erro ao salvar.');
            }
        },
        error: function () {
            alert('Erro ao salvar.');
        }
    });
});

$('#btnExcluir').on('click', function () {
    const idusuario = $('#idusuario').val();
    const idcolaborador = $('#idcolaborador').val();

    if (!idusuario || !idcolaborador) {
        alert('Selecione um colaborador válido.');
        return;
    }

    if (!confirm('Tem certeza que deseja excluir este colaborador?')) {
        return;
    }

    $.ajax({
        type: 'POST',
        url: 'salvar_colaborador.php',
        data: { action: 'delete', idusuario, idcolaborador },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                alert(response.message || 'Colaborador excluído.');
                $('#modal').hide();
                carregarUsuarios();
            } else {
                alert(response.message || 'Erro ao excluir.');
            }
        },
        error: function () {
            alert('Erro ao excluir.');
        }
    });
});
