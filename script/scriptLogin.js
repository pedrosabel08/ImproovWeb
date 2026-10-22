// Detecta se estamos no Electron
let ToastifyLib;

try {
  // Se estiver no Electron com nodeIntegration
  ToastifyLib = require("toastify-js");
} catch (e) {
  // Se não estiver, assume que o Toastify está no window (CDN)
  ToastifyLib = window.Toastify;
}

document.querySelector("form").addEventListener("submit", function (e) {
  e.preventDefault();
  const login = document.getElementById("login").value;
  const senha = document.getElementById("senha").value;
  // Only an opaque session-bound context is sent; URLs from the login query are ignored.
  const context = new URLSearchParams(window.location.search).get("login_context");
  const loginContext = context && /^[a-f0-9]{32}$/.test(context) ? context : "";

  fetch("login.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `login=${encodeURIComponent(login)}&senha=${encodeURIComponent(senha)}&login_context=${encodeURIComponent(loginContext)}`,
  })
    .then((response) => response.json())
    .then((data) => {
      // Log response for debugging (temporary)
      console.log("login response:", data);

      if (data.status === "success") {
        ToastifyLib({
          text: data.message,
          backgroundColor: "green",
          duration: 3000,
        }).showToast();

        // // Log cookies available to JS before redirect (temporary)
        // console.log('document.cookie before redirect:', document.cookie);

        // Slightly longer delay to allow inspection in DevTools
        setTimeout(function () {
          const fallback = new URL("inicio.php", window.location.href);
          const destination = new URL(data.redirect_url || fallback.href, window.location.origin);
          const base = fallback.pathname.slice(0, -"inicio.php".length);
          if (destination.origin !== window.location.origin || !destination.pathname.startsWith(base)) {
            window.location.assign(fallback.href);
            return;
          }
          // Browsers inherit the original fragment across the guard's HTTP redirect.
          // Keep it client-side; fragments never enter metadata or server headers.
          if (data.return_restored === true && window.location.hash) destination.hash = window.location.hash;
          window.location.assign(destination.href);
        }, 360);
        localStorage.setItem("tocarSomAoEntrar", "true");
      } else {
        ToastifyLib({
          text: data.message,
          backgroundColor: "red",
          duration: 3000,
        }).showToast();
      }
    })
    .catch((error) => {
      Toastify({
        text: "Erro na requisição. Tente novamente.",
        backgroundColor: "red",
        duration: 3000,
      }).showToast();
    });
});

// Lista de vídeos disponíveis (URLs absolutas apontando para o domínio central)
const videoBase = "https://improov.com.br/flow/ImproovWeb/assets";
const videos = [
  `${videoBase}/11 MSA_SQU_Psicina.mp4`,
  `${videoBase}/6. AYA_CAS_Piscina_Horizontal.mp4`,
  `${videoBase}/AYA_KAR_Rooftop.mp4`,
  // Adicione mais caminhos conforme necessário
];

// Sorteia um índice aleatório
const sorteado = Math.floor(Math.random() * videos.length);

// Seleciona o elemento video e altera o src (usa encodeURI para arquivos com espaços)
const video = document.getElementById("video-bg");
const source = video.querySelector("source");
source.src = encodeURI(videos[sorteado]);
video.load(); // recarrega o vídeo sorteado
