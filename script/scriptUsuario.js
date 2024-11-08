function buscaEndereco(cep) {
    if (cep.length == 8) {
        $.ajax({
            type: "GET",
            dataType: "json",
            url: "https://viacep.com.br/ws/" + cep + "/json/",
            success: function (data) {
                if (data.bairro != null) {
                    document.getElementById('bairro').value = data.bairro;
                    document.getElementById('rua').value = data.logradouro;
                }
            }
        });
    }
}
function buscaEnderecoCNPJ(cep) {
    if (cep.length == 8) {
        $.ajax({
            type: "GET",
            dataType: "json",
            url: "https://viacep.com.br/ws/" + cep + "/json/",
            success: function (data) {
                if (data.bairro != null) {
                    document.getElementById('bairro_cnpj').value = data.bairro;
                    document.getElementById('rua_cnpj').value = data.logradouro;
                }
            }
        });
    }
}

document.getElementById("userForm").addEventListener("submit", function (e) {
    e.preventDefault(); // Impede o envio padrão do formulário

    // Cria um objeto FormData com os dados do formulário
    let formData = new FormData(this);

    for (let [key, value] of formData.entries()) {
        console.log(key, value); // Exibe o nome do campo e o valor
    }
    // Envia os dados via AJAX
    fetch("updateInfos.php", {
        method: "POST",
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            // Exibe a mensagem com Toastify
            Toastify({
                text: data.message,
                duration: 3000,
                close: true,
                gravity: "top", // Posição do toast
                position: "center", // Centralizado na tela
                backgroundColor: data.success ? "green" : "red", // Verde para sucesso, vermelho para erro
            }).showToast();

            // Opcional: limpar os campos ou realizar outras ações após a resposta
            if (data.success) {
                setTimeout(function () {
                    window.location.reload(); // Recarrega a página
                }, 3000); // Aguarda o tempo do Toastify (3 segundos)

            }
        })
        .catch(error => {
            // Exibe erro genérico
            Toastify({
                text: "Erro ao atualizar as informações. Tente novamente.",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "center",
                backgroundColor: "red",
            }).showToast();
        });
});