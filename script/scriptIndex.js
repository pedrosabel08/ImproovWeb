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