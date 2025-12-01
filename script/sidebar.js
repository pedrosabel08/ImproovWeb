const sidebar = document.querySelector('.sidebar');
const body = document.querySelector('body');
sidebar.addEventListener('mouseenter', function () {

    if (sidebar) {
        // Verifica se a sidebar tem a classe "mini"
        if (sidebar.classList.contains('mini')) {
            // Remove a classe "mini" e adiciona a classe "complete"
            sidebar.classList.remove('mini');

            sidebar.classList.add('complete');
        }
    }
});

sidebar.addEventListener('mouseleave', function () {


    if (sidebar) {
        // Verifica se a sidebar tem a classe "complete"
        if (sidebar.classList.contains('complete')) {
            // Remove a classe "complete" e adiciona a classe "mini"
            sidebar.classList.remove('complete');
            sidebar.classList.add('mini');
        }
    }
});



document.addEventListener("DOMContentLoaded", () => {
    const favoritosList = document.getElementById("favoritos");
    const obrasList = document.getElementById("obras-list");

    const checkFavoritesVisibility = () => {
        if (favoritosList.children.length === 1) {
            // Só contém o <label>, considera vazia
            favoritosList.style.display = "none";
        } else {
            favoritosList.style.display = "block";
        }
    };

    // Função para carregar os favoritos do localStorage
    const loadFavorites = () => {
        const favoritos = JSON.parse(localStorage.getItem("favoritos")) || [];

        favoritos.forEach((id) => {
            const obra = obrasList.querySelector(`.obra i[data-id="${id}"]`);
            if (obra) {
                // Marca a obra como favoritada
                obra.classList.add("favorited");
                // Move a obra para a lista de favoritos
                const obraItem = obra.parentElement;
                favoritosList.appendChild(obraItem);
            }
        });
        checkFavoritesVisibility();
    };

    // Função para salvar os favoritos no localStorage
    const saveFavorite = (id) => {
        let favoritos = JSON.parse(localStorage.getItem("favoritos")) || [];
        if (!favoritos.includes(id)) {
            favoritos.push(id);
            localStorage.setItem("favoritos", JSON.stringify(favoritos));
        }
    };

    // Função para remover dos favoritos no localStorage
    const removeFavorite = (id) => {
        let favoritos = JSON.parse(localStorage.getItem("favoritos")) || [];
        favoritos = favoritos.filter((fav) => fav !== id);
        localStorage.setItem("favoritos", JSON.stringify(favoritos));
    };

    // Evento de clique para favoritar/desfavoritar
    document.addEventListener("click", (e) => {
        if (e.target.classList.contains("favorite-icon")) {
            const icon = e.target;
            const obraId = icon.getAttribute("data-id");
            const obraItem = icon.parentElement;

            if (icon.classList.contains("favorited")) {
                // Remover dos favoritos
                icon.classList.remove("favorited");
                removeFavorite(obraId);
                obrasList.appendChild(obraItem); // Move de volta para a lista de obras
            } else {
                // Adicionar aos favoritos
                icon.classList.add("favorited");
                saveFavorite(obraId);
                favoritosList.appendChild(obraItem); // Move para a lista de favoritos
            }
            checkFavoritesVisibility();
        }
    });

    // Carrega os favoritos ao inicializar
    loadFavorites();



    const obraItems = document.querySelectorAll('.obra-item');

    obraItems.forEach(item => {
        item.addEventListener('click', function (event) {
            event.preventDefault(); // Impede o comportamento padrão do link

            // Obtém os atributos data-id e data-name do elemento clicado
            const obraId = this.getAttribute('data-id');

            // Salva o data-id no localStorage
            if (obraId) {
                localStorage.setItem('obraId', obraId);
                localStorage.setItem('obraNome', this.getAttribute('data-name'));
            }

            // Redireciona para a URL com o hash desejado
            window.location.href = 'https://improov.com.br/flow/ImproovWeb/Dashboard/obra';
        });
    });
});
