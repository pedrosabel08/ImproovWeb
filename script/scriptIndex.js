function instagramImproov() {
    window.location.href = 'https://www.instagram.com/improovbr/';
}

function visualizarTabela() {
    window.location.href = 'main.php'
}

function listaPos() {
    window.location.href = 'Pos-Producao/index.php'
}

function dashboard() {
    window.location.href = 'Dashboard/index.php'
}

function clientes() {
    window.location.href = 'infoCliente/index.php'
}

function animacao() {
    window.location.href = 'Animacao/index.php'
}

function arquitetura() {
    window.location.href = 'Arquitetura/index.php'
}
function metas() {
    window.location.href = 'Metas/index.php'
}
function acomp() {
    window.location.href = 'Acompanhamento/index.html'
}
function calendar() {
    window.location.href = 'Calendario/index.php'
}


function formatarDataAtual() {
    const opcoes = { weekday: 'long', day: 'numeric', month: 'long' };
    const dataAtual = new Date();
    return dataAtual.toLocaleDateString('pt-BR', opcoes);
}

document.getElementById('data').textContent = formatarDataAtual();

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

    document.getElementById('showMenu').addEventListener('click', function () {
        const menu2 = document.getElementById('menu2');
        menu2.classList.toggle('hidden');
    });

    window.addEventListener('click', function (event) {
        const menu2 = document.getElementById('menu2');
        const button = document.getElementById('showMenu');

        if (!button.contains(event.target) && !menu2.contains(event.target)) {
            menu2.classList.add('hidden');
        }
    });

});


if (idusuario != 1 && idusuario != 2) {
    document.querySelector('.button-container').style.display = 'none';
  }

const toggleButton = document.getElementById('toggleButton');
const iconContainer = document.getElementById('iconContainer');

toggleButton.addEventListener('click', () => {
    iconContainer.classList.toggle('active');
});