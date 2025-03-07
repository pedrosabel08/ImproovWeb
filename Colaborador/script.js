fetch('usuarios.php')
    .then(response => response.json())
    .then(data => {
        console.log(data);
        let result = '';

        data.forEach(element => {
            result += `
                <tr class="usuario-row" data-idusuario="${element.idusuario}">
                    <td>${element.idusuario}</td>
                    <td>${element.nome_usuario}</td>
                    <td>${element.cargos}</td>
                    <td>${element.ativo == 1 ? 'Sim' : 'Não'}</td>
                </tr>
            `;
        });

        document.querySelector('#usuarios tbody').innerHTML = result;

        // Inicializa o DataTables
        $('#usuarios').DataTable({
            "paging": false,
            "lengthChange": false,
            "info": false,
            "ordering": true,
            "searching": true,
            "order": [], // Remove a ordenação padrão
            "columnDefs": [{
                "targets": 0, // Aplica a ordenação na primeira coluna
                "orderData": function (row, type, set, meta) {
                    // Retorna o valor do atributo data-id para a ordenação
                    return $(row).attr('ordem');
                }
            }],
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Portuguese.json"
            }
        });

        // Adiciona evento de clique nas linhas da tabela
        document.querySelectorAll('.usuario-row').forEach(row => {
            row.addEventListener('click', function () {
                const modal = document.getElementById('modal');
                modal.style.display = 'block';

                const idusuario = this.getAttribute('data-idusuario');

                fetch(`get_usuario.php?idusuario=${idusuario}`)
                    .then(response => response.json())
                    .then(usuario => {
                        document.getElementById('nome_usuario').value = usuario.primeiro_nome_formatado;
                    });
            });
        });
    });

// Fechar modal ao clicar fora dele
window.onclick = function (event) {
    const modal = document.getElementById('modal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const inputCargo = document.getElementById("cargo");
    const suggestions = document.getElementById("suggestions");

    // Lista de cargos simulada (pode vir de uma API)
    let cargosDisponiveis = ["Gerente", "Desenvolvedor", "Designer", "Analista", "Estagiário"];

    // Função para exibir sugestões
    function mostrarSugestoes(filtro) {
        const filtrados = cargosDisponiveis.filter(cargo => cargo.toLowerCase().includes(filtro.toLowerCase()));
        suggestions.innerHTML = ""; // Limpa sugestões anteriores

        if (filtrados.length > 0) {
            filtrados.forEach(cargo => {
                const div = document.createElement("div");
                div.classList.add("cargo-item");
                div.textContent = cargo;
                div.onclick = () => selecionarCargo(cargo);
                suggestions.appendChild(div);
            });
            suggestions.style.display = "block";
        } else {
            suggestions.innerHTML = `<div class="cargo-item">Pressione Enter para adicionar "${filtro}"</div>`;
            suggestions.style.display = "block";
        }
    }

    // Função para selecionar um cargo existente
    function selecionarCargo(cargo) {
        inputCargo.value = cargo;
        suggestions.style.display = "none";
    }

    // Evento de digitação para buscar sugestões
    inputCargo.addEventListener("input", (e) => {
        const valor = e.target.value.trim();
        if (valor) {
            mostrarSugestoes(valor);
        } else {
            suggestions.style.display = "none";
        }
    });

    // Evento para adicionar novo cargo pressionando Enter
    inputCargo.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
            e.preventDefault();
            const novoCargo = inputCargo.value.trim();
            if (novoCargo && !cargosDisponiveis.includes(novoCargo)) {
                cargosDisponiveis.push(novoCargo); // Adiciona à lista
                selecionarCargo(novoCargo); // Seleciona automaticamente
            }
            suggestions.style.display = "none";
        }
    });

    // Oculta sugestões se clicar fora
    document.addEventListener("click", (e) => {
        if (!inputCargo.contains(e.target) && !suggestions.contains(e.target)) {
            suggestions.style.display = "none";
        }
    });
});
