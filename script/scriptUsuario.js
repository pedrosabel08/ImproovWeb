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
                    document.getElementById('uf').value = data.uf;
                    document.getElementById('localidade').value = data.localidade;
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
                    document.getElementById('uf_cnpj').value = data.uf;
                    document.getElementById('localidade_cnpj').value = data.localidade;
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
                // setTimeout(function () {
                //     window.location.reload(); // Recarrega a página
                // }, 3000); // Aguarda o tempo do Toastify (3 segundos)

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

// Preview para o input de thumb
const thumbInput = document.getElementById('thumb');
if (thumbInput) {
    thumbInput.addEventListener('change', function (e) {
        const file = this.files && this.files[0];
        const preview = document.getElementById('avatarPreview');
        if (file && preview) {
            const reader = new FileReader();
            reader.onload = function (ev) {
                if (preview.tagName.toLowerCase() === 'img') {
                    preview.src = ev.target.result;
                } else {
                    // replace inner HTML with img
                    preview.innerHTML = '';
                    const img = document.createElement('img');
                    img.src = ev.target.result;
                    img.style.width = '84px';
                    img.style.height = '84px';
                    img.style.borderRadius = '50%';
                    preview.parentNode.replaceChild(img, preview);
                    img.id = 'avatarPreview';
                }
            };
            reader.readAsDataURL(file);
        }
    });
}