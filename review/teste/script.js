let obraId = new URLSearchParams(window.location.search).get('obra_id');
document.getElementById('obra-id').textContent = obraId || '';

let imagens = []; // [{id, url, sugerida, motivo_sugerida, comentarios: []}]
let swiper; // Swiper instance

async function carregarImagens() {
    // Substitua por sua API real
    const res = await fetch('api_get_imagens.php?obra_id=' + obraId);
    imagens = await res.json();

    const wrapper = document.getElementById('swiper-wrapper');
    wrapper.innerHTML = '';
    imagens.forEach((img, i) => {
        const slide = document.createElement('div');
        slide.className = 'swiper-slide';
        slide.innerHTML = `<img src="../../${img.imagem}" alt="Imagem da obra" id="imagem-${i}" class="${img.sugerida ? 'sugerida' : ''}" style="max-width:100%;max-height:100%;">`;
        wrapper.appendChild(slide);
    });

    if (swiper) swiper.destroy(true, true);
    swiper = new Swiper('.main-swiper', {
        initialSlide: 0,
        slidesPerView: 1.2,
        centeredSlides: true,
        spaceBetween: 32,
        navigation: {
            nextEl: '#next',
            prevEl: '#prev',
        },
        on: {
            slideChange: function () {
                mostrarImagem(swiper.realIndex);
            }
        }
    });

    mostrarImagem(0);
}

// Thumbs
const thumbsSwiper = new Swiper('.myThumbs', {
    spaceBetween: 10,
    slidesPerView: 4,
    freeMode: true,
    watchSlidesProgress: true,
});

const mainSwiper = new Swiper('.mySwiper', {
    loop: false, // se não precisa repetir infinitamente
    centeredSlides: false, // mantém alinhado à esquerda
    slidesPerView: 1, // apenas uma imagem por vez
    spaceBetween: 0,

    // Retire o efeito coverflow se a ideia for preencher:
    effect: 'slide',

    pagination: {
        el: '.swiper-pagination',
        clickable: true,
    },

    keyboard: { enabled: true },
    mousewheel: { forceToAxis: true },

    thumbs: { swiper: thumbsSwiper }
});

// Toast feedback
const btnAprovar = document.getElementById("btnAprovar");
const toast = document.getElementById("toast");
btnAprovar.addEventListener("click", () => {
    toast.classList.add("show");
    setTimeout(() => toast.classList.remove("show"), 3000);
});

function mostrarImagem(i) {
    if (!imagens.length) return;
    if (swiper && swiper.realIndex !== i) swiper.slideTo(i);

    const img = imagens[i];

    // motivo sugerida
    document.getElementById('motivo-sugerida').style.display = img.sugerida ? 'block' : 'none';
    document.getElementById('motivo-sugerida').textContent = img.sugerida
        ? 'Imagem sugerida: ' + (img.motivo_sugerida || 'Sem motivo informado')
        : '';

    carregarComentarios(img.comentarios || []);
}

function carregarComentarios(comentarios) {
    const lista = document.getElementById('lista-comentarios');
    lista.innerHTML = '';
    comentarios.forEach(c => {
        const div = document.createElement('div');
        div.className = 'comentario';
        div.textContent = c.texto + (c.autor ? ` — ${c.autor}` : '');
        lista.appendChild(div);
    });
}

document.getElementById('enviar-comentario').onclick = async () => {
    const texto = document.getElementById('novo-comentario').value.trim();
    if (!texto) return;
    // Substitua por sua API real
    const idx = swiper.realIndex;
    await fetch('api_comentar.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            imagem_id: imagens[idx].id,
            texto
        })
    });
    imagens[idx].comentarios.push({
        texto,
        autor: 'Você'
    });
    carregarComentarios(imagens[idx].comentarios);
    document.getElementById('novo-comentario').value = '';
};

document.getElementById('aprovar-btn').onclick = async () => {
    const idx = swiper.realIndex;
    // Substitua por sua API real
    await fetch('api_aprovar_angulo.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            imagem_id: imagens[idx].id
        })
    });
    alert('Ângulo aprovado!');
};

carregarImagens();