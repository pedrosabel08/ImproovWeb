// Detecta se estamos no Electron
let ToastifyLib;

try {
    // Se estiver no Electron com nodeIntegration
    ToastifyLib = require('toastify-js');
} catch (e) {
    // Se não estiver, assume que o Toastify está no window (CDN)
    ToastifyLib = window.Toastify;
}



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

                ToastifyLib({
                    text: data.message,
                    backgroundColor: "green",
                    duration: 3000
                }).showToast();

                setTimeout(function () {
                    window.location.href = 'inicio.php';
                }, 500);
                localStorage.setItem('tocarSomAoEntrar', 'true');

            } else {
                ToastifyLib({
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

// Lista de vídeos disponíveis
const videos = [
    "assets/11 MSA_SQU_Psicina.mp4",
    "assets/6. AYA_CAS_Piscina_Horizontal.mp4",
    "assets/AYA_KAR_Rooftop.mp4"
    // Adicione mais caminhos conforme necessário
];

// Sorteia um índice aleatório
const sorteado = Math.floor(Math.random() * videos.length);

// Seleciona o elemento video e altera o src
const video = document.getElementById('video-bg');
const source = video.querySelector('source');
source.src = videos[sorteado];
video.load(); // recarrega o vídeo sorteado