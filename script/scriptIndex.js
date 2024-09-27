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

document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('theme-toggle');
    const currentTheme = localStorage.getItem('theme');

    if (currentTheme) {
        document.body.classList.add(currentTheme);
        themeToggle.checked = currentTheme === 'dark-mode';
    }

    themeToggle.addEventListener('change', () => {
        if (themeToggle.checked) {
            document.body.classList.add('dark-mode');
            document.body.classList.remove('light-mode');
            localStorage.setItem('theme', 'dark-mode');
        } else {
            document.body.classList.add('light-mode');
            document.body.classList.remove('dark-mode');
            localStorage.setItem('theme', 'light-mode');
        }
    });
});