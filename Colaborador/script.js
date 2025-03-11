fetch('usuarios.php')
    .then(response => response.json())
    .then(data => {
        console.log(data);
        let result = '';

        data.forEach(element => {
            result += `
                <tr class="usuario-row" data-idusuario="${element.idusuario}">
                    <td>${element.idusuario}</td>
                    <td>${element.nome_usuario}</td>
                    <td>${element.nome_cargo}</td>
                    <td>${element.ativo == 1 ? 'Sim' : 'Não'}</td>
                </tr>
            `;
        });

        document.querySelector('#usuarios tbody').innerHTML = result;

        // Inicializa o DataTables
        $('#usuarios').DataTable({
            "paging": false,
            "lengthChange": false,
            "info": false,
            "ordering": true,
            "searching": true,
            "order": [], // Remove a ordenação padrão
            "columnDefs": [{
                "targets": 0, // Aplica a ordenação na primeira coluna
                "orderData": function (row, type, set, meta) {
                    // Retorna o valor do atributo data-id para a ordenação
                    return $(row).attr('ordem');
                }
            }],
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Portuguese.json"
            }
        });

        // Adiciona evento de clique nas linhas da tabela
        document.querySelectorAll('.usuario-row').forEach(row => {
            row.addEventListener('click', function () {
                const modal = document.getElementById('modal');
                modal.style.display = 'block';

                const idusuario = this.getAttribute('data-idusuario');

                document.getElementById('idusuario').value = idusuario;

                fetch(`get_usuario.php?idusuario=${idusuario}`)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('nome_usuario').value = data.usuario.primeiro_nome_formatado;

                        // Marcar os cargos no Select2
                        $('#cargoSelect').val(data.cargos).trigger('change');
                    });
            });
        });
    });

// Fechar modal ao clicar fora dele
window.onclick = function (event) {
    const modal = document.getElementById('modal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

$(document).ready(function () {
    $('#cargoSelect').select2({
        placeholder: "Selecione os cargos",
        allowClear: true
    });
});

$('#form').on('submit', function (e) {
    e.preventDefault();

    const idusuario = $('#idusuario').val();
    const cargos = $('#cargoSelect').val();

    const formData = {
        idusuario: idusuario,
        cargos: cargos
    };

    console.log(formData);

    $.ajax({
        type: 'POST',
        url: 'salvar_colaborador.php',
        data: formData,
        success: function (response) {
            alert('Cargos atualizados com sucesso!');
            $('#modal').hide();
        },
        error: function () {
            alert('Erro ao salvar os cargos.');
        }
    });
});
