// controleSessao.js

let tempoSessao = 3600 * 1000; 
let avisoAntes = 0; 
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
    fetch("https://improov.com.br/sistema/renova_sessao.php")
        .then(response => response.text())
        .then(() => {
            const modal = document.getElementById("modalSessao");
            if (modal) modal.style.display = "none";
            iniciarContadorSessao(); // Reinicia o contador sem recarregar a página
        });
}

function sair() {
    window.location.href = "https://improov.com.br/sistema/logout.php";
}
