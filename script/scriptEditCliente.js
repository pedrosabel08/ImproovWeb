function buscarCliente() {
    var select = document.getElementById("cliente");
    var clienteId = select.value;

    if (clienteId === "") {
        document.getElementById('modalBody').innerHTML = "<p>Por favor, selecione um cliente.</p>";
        return;
    }

    fetch('buscar_cliente.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'id=' + clienteId
    })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('modalBody').innerHTML = "<p>" + data.error + "</p>";
            } else {

                const form = document.getElementById('formCliente');
                form.reset();
                limparCampos();
                const contatosContainer = document.getElementById('contatosContainer');
                contatosContainer.classList.add('hidden');

                let nomeClienteInput = document.getElementById('nomeCliente');
                let idClienteInput = document.getElementById('idcliente');

                if (nomeClienteInput && idClienteInput) {
                    nomeClienteInput.value = data.nome_cliente || '';
                    idClienteInput.value = data.idcliente || '';
                }

                const contatosContainer2 = document.getElementById('contatosContainer2');
                contatosContainer2.innerHTML = "";

                const emails = data.emails ? data.emails.split(';') : [];
                const nomesContato = data.nomes_contato ? data.nomes_contato.split(';') : [];
                const cargos = data.cargos ? data.cargos.split(';') : [];

                const maxContatos = Math.max(emails.length, nomesContato.length, cargos.length);
                for (let i = 0; i < maxContatos; i++) {

                    let contatoDiv = document.createElement('div');
                    contatoDiv.className = 'form-group contato-group';
                    contatoDiv.style.marginBottom = '15px';

                    let labelEmail = document.createElement('label');
                    labelEmail.innerText = "Email " + (i + 1);
                    contatoDiv.appendChild(labelEmail);

                    let inputEmail = document.createElement('input');
                    inputEmail.type = "text";
                    inputEmail.value = emails[i] || '';
                    inputEmail.name = "email_" + i;
                    inputEmail.className = 'form-control';
                    contatoDiv.appendChild(inputEmail);

                    let labelContato = document.createElement('label');
                    labelContato.innerText = "Contato " + (i + 1);
                    contatoDiv.appendChild(labelContato);

                    let inputContato = document.createElement('input');
                    inputContato.type = "text";
                    inputContato.value = nomesContato[i] || '';
                    inputContato.name = "contato_" + i;
                    inputContato.className = 'form-control';
                    contatoDiv.appendChild(inputContato);

                    let labelCargo = document.createElement('label');
                    labelCargo.innerText = "Cargo " + (i + 1);
                    contatoDiv.appendChild(labelCargo);

                    let inputCargo = document.createElement('input');
                    inputCargo.type = "text";
                    inputCargo.value = cargos[i] || '';
                    inputCargo.name = "cargo_" + i;
                    inputCargo.className = 'form-control';
                    contatoDiv.appendChild(inputCargo);

                    contatosContainer2.appendChild(contatoDiv);


                }

                $('#infoClienteModal').modal('show');
            }
        })
        .catch(error => console.error('Erro:', error));
}


const formCliente = document.getElementById('formCliente');
formCliente.addEventListener('submit', function (e) {
    e.preventDefault();

    var formData = new FormData(this);

    fetch('insert_contato.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.text())
        .then(data => {
            Toastify({
                text: data,
                duration: 3000,
                close: true,
                gravity: "top",
                position: "left",
                backgroundColor: "green",
                stopOnFocus: true,
            }).showToast();

            limparCampos();
            document.getElementById('cliente').value = '0';

            $('#infoClienteModal').modal('hide');
        })
        .catch(error => console.error('Erro:', error));
});

document.getElementById('addContatoButton').addEventListener('click', function () {

    limparCampos();
    const contatosContainer = document.getElementById('contatosContainer');
    contatosContainer.classList.remove('hidden');

    const contatosContainer2 = document.getElementById('contatosContainer2');
    contatosContainer2.innerHTML = "";
});

function limparCampos() {
    document.getElementById('email').value = '';
    document.getElementById('nome_contato').value = '';
    document.getElementById('cargo').value = '';
    document.getElementById('idcontato').value = '';
}


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