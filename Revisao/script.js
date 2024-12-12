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

// Função para revisar uma tarefa com confirmação e solicitação ao PHP
function revisarTarefa(idfuncao_imagem) {
    if (confirm("Você tem certeza de que deseja marcar esta tarefa como revisada?")) {
        fetch('revisarTarefa.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                idfuncao_imagem: idfuncao_imagem
            }),
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error("Erro ao atualizar a tarefa.");
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert("Tarefa marcada como revisada com sucesso!");
                    location.reload(); // Atualiza a página para refletir a mudança
                } else {
                    alert("Falha ao marcar a tarefa como revisada: " + data.message);
                }
            })
            .catch(error => {
                console.error("Erro:", error);
                alert("Ocorreu um erro ao processar a solicitação.");
            });
    }
    event.stopPropagation(); // Impede o clique na tarefa de abrir os detalhes
}

// Função para alternar a visibilidade dos detalhes da tarefa
function toggleTaskDetails(taskElement) {
    taskElement.classList.toggle('open');
}