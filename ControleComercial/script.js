var modal = document.getElementById("modal");
var openModalBtn = document.getElementById("openModalBtn");
var closeModal = document.getElementsByClassName("close")[0];
const form_comercial = document.getElementById('form_comercial');


openModalBtn.onclick = function () {
    modal.style.display = "flex";
};

closeModal.onclick = function () {
    modal.style.display = "none";
};

window.onclick = function (event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

form_comercial.addEventListener('submit', function (e) {
    e.preventDefault();

    var formData = new FormData(this);

    fetch('inserir_orcamento.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.text())
        .then(data => {
            document.getElementById('modal').style.display = 'none';
            atualizarTabela();
            Toastify({
                text: "Dados inseridos com sucesso!",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "left",
                backgroundColor: "green",
                stopOnFocus: true,
            }).showToast();
        })
        .catch(error => console.error('Erro:', error));
});


function atualizarTabela() {
    fetch('atualizar_tabela.php')
        .then(response => response.json())
        .then(data => {
            const tabela = document.getElementById('lista-orcamentos');
            tabela.innerHTML = '';

            data.forEach(orcamento => {
                const tr = document.createElement('tr');
                tr.classList.add('linha-tabela');
                tr.setAttribute('data-id', orcamento.idcontrole);

                tr.innerHTML = `
                    <td>${orcamento.resp}</td>
                    <td>${orcamento.contato}</td>
                    <td>${orcamento.construtora}</td>
                    <td>${orcamento.obra}</td>
                    <td>${orcamento.valor}</td>
                    <td>${orcamento.status}</td>
                    <td>${orcamento.mes}</td>
                `;

                tabela.appendChild(tr);
            });

        });
}

atualizarTabela();