// controleSessao.js

let tempoSessao = 3600 * 1000; // 1 hora em ms
let avisoAntes = 0; // exibir exatamente quando expirar
let timeoutSessao;

iniciarContadorSessao();

function iniciarContadorSessao() {
    clearTimeout(timeoutSessao); // Limpa o anterior se existir
    timeoutSessao = setTimeout(() => {
        mostrarModalSessaoExpirada();
    }, tempoSessao - avisoAntes);
}

function mostrarModalSessaoExpirada() {
    const modal = document.getElementById("modalSessao");
    if (modal) modal.style.display = "flex";
}

function renovarSessao() {
    // Usa caminho relativo para garantir mesma origem e envio de cookies
    fetch("/renova_sessao.php", { credentials: "same-origin" })
        .then(response => response.text())
        .then(() => {
            const modal = document.getElementById("modalSessao");
            if (modal) modal.style.display = "none";
            iniciarContadorSessao(); // Reinicia o contador sem recarregar a página
        });
}

function sair() {
    // Usa caminho relativo para finalizar sessão na mesma origem
    window.location.href = "/logout.php";
}
