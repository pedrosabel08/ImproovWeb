document.querySelector('form').addEventListener('submit', function (e) {
    e.preventDefault(); // Evitar que o formulário seja enviado de forma tradicional

    // Obter os valores dos campos de login e senha
    const login = document.getElementById('login').value;
    const senha = document.getElementById('senha').value;

    // Enviar a requisição POST para o login.php
    fetch('login.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `login=${encodeURIComponent(login)}&senha=${encodeURIComponent(senha)}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                // Mostrar Toastify com sucesso
                Toastify({
                    text: data.message,
                    backgroundColor: "green",
                    duration: 3000
                }).showToast();

                // Redirecionar para a página main.html após 2 segundos
                setTimeout(function () {
                    window.location.href = 'main.html';
                }, 500);
            } else {
                // Mostrar Toastify com erro
                Toastify({
                    text: data.message,
                    backgroundColor: "red",
                    duration: 3000
                }).showToast();
            }
        })
        .catch(error => {
            // Mostrar Toastify com erro em caso de falha na requisição
            Toastify({
                text: "Erro na requisição. Tente novamente.",
                backgroundColor: "red",
                duration: 3000
            }).showToast();
        });
});
