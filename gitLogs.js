document.addEventListener("DOMContentLoaded", () => {
    const changelogDiv = document.getElementById("changelog");

    fetch("CHANGELOG.md")
        .then(response => response.text())
        .then(data => {
            // Converte o Markdown para HTML simples (Básico)
            const htmlContent = data
                .replace(/^### (.*$)/gim, '<h3 class="text-xl font-semibold mt-4">$1</h3>')
                .replace(/^## (.*$)/gim, '<h2 class="text-2xl font-bold mt-6">$1</h2>')
                .replace(/^# (.*$)/gim, '<h1 class="text-3xl font-bold mt-8">$1</h1>')
                .replace(/^\- (.*$)/gim, '<li class="list-disc ml-6">$1</li>')
                .replace(/\n\n/g, '<br>');

            // Exibe o conteúdo convertido no DIV
            changelogDiv.innerHTML = htmlContent;
        })
        .catch(error => {
            changelogDiv.innerHTML = `<p class="text-red-500">Erro ao carregar o changelog: ${error.message}</p>`;
        });
});
