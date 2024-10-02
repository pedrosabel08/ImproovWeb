document.querySelector('form').addEventListener('submit', function (e) {
    e.preventDefault(); 
    const login = document.getElementById('login').value;
    const senha = document.getElementById('senha').value;

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

                Toastify({
                    text: data.message,
                    backgroundColor: "green",
                    duration: 3000
                }).showToast();

                setTimeout(function () {
                    window.location.href = 'inicio.php';
                }, 500);
            } else {
                Toastify({
                    text: data.message,
                    backgroundColor: "red",
                    duration: 3000
                }).showToast();
            }
        })
        .catch(error => {

            Toastify({
                text: "Erro na requisição. Tente novamente.",
                backgroundColor: "red",
                duration: 3000
            }).showToast();
        });
});
