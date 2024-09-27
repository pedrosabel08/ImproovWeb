$(document).ready(function () {
    $('#cliente').change(function () {
        const clienteId = $(this).val();

        if (clienteId) {
            $.ajax({
                type: 'POST',
                url: 'buscar_cliente.php',
                data: { id: clienteId },
                success: function (response) {
                    $('#modalBody').html(response);
                    $('#infoClienteModal').modal('show');
                },
                error: function () {
                    alert('Erro ao buscar informações do cliente.');
                }
            });
        }
    });

    // Adicionar novos campos de email
    $('#modalBody').on('click', '.btn-add-email', function () {
        const newEmailInput = "<input type='text' class='form-control' placeholder='Novo email' style='margin-top: 5px;'>";
        $(this).parent().append(newEmailInput); // Adiciona o novo email abaixo do botão
    });

    // Adicionar novos campos de contato
    $('#modalBody').on('click', '.btn-add-contato', function () {
        const newContatoInput = "<div style='margin-top: 5px;'>Telefone: <input type='text' class='form-control' placeholder='Novo telefone'> Endereço: <input type='text' class='form-control' placeholder='Novo endereço'></div>";
        $(this).parent().append(newContatoInput); // Adiciona o novo contato abaixo do botão
    });

    // Adicionar novos responsáveis
    $('#modalBody').on('click', '.btn-add-responsavel', function () {
        const newResponsavelInput = "<div style='margin-top: 5px;'>Nome: <input type='text' class='form-control' placeholder='Nome do responsável'> - Cargo: <input type='text' class='form-control' placeholder='Cargo'></div>";
        $(this).parent().append(newResponsavelInput); // Adiciona o novo responsável abaixo do botão
    });

    $('#modalBody').on('click', '#saveChanges', function () {
        const clienteData = {
            nome: $('#modalBody input[type="text"]').eq(0).val(), // Nome do cliente
            emails: [],
            contatos: [],
            responsaveis: []
        };

        // Coleta os emails
        $('#modalBody input[type="text"][placeholder="Novo email"]').each(function () {
            const email = $(this).val();
            if (email) clienteData.emails.push(email); // Adiciona apenas se não estiver vazio
        });

        // Coleta os contatos
        $('#modalBody div:has(input[placeholder="Novo telefone"])').each(function () {
            const telefone = $(this).find('input[placeholder="Novo telefone"]').val();
            const endereco = $(this).find('input[placeholder="Novo endereço"]').val();
            if (telefone || endereco) { // Adiciona apenas se pelo menos um campo estiver preenchido
                clienteData.contatos.push({
                    telefone: telefone,
                    endereco: endereco
                });
            }
        });

        // Coleta os responsáveis
        $('#modalBody div:has(input[placeholder="Nome do responsável"])').each(function () {
            const nome = $(this).find('input[placeholder="Nome do responsável"]').val();
            const cargo = $(this).find('input[placeholder="Cargo"]').val();
            if (nome || cargo) { // Adiciona apenas se pelo menos um campo estiver preenchido
                clienteData.responsaveis.push({
                    nome: nome,
                    cargo: cargo
                });
            }
        });

        // Aqui você pode adicionar a lógica para salvar as alterações
        console.log(clienteData); // Para verificar os dados no console
        alert('Salvar mudanças (ainda não implementado).');
    });
});