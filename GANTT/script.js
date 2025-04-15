document.addEventListener("DOMContentLoaded", function () {
    fetch("tabela.php")
        .then(response => response.text())
        .then(data => {
            document.getElementById("tabela-container").innerHTML = data;
        })
        .catch(error => {
            document.getElementById("tabela-container").innerHTML = "Erro ao carregar tabela.";
            console.error("Erro:", error);
        });
});
