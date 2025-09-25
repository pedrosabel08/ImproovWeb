let obraId = new URLSearchParams(window.location.search).get('obra_id');
let imagens = []; // [{id, imagem, sugerida, motivo_sugerida, comentarios: []}]
let swiper;       // Swiper principal
let thumbsSwiper; // Thumbs Swiper

async function carregarImagens() {
    const res = await fetch('api_get_imagens.php?obra_id=' + obraId);
    imagens = await res.json();

    const wrapper = document.getElementById('swiper-wrapper');
    const thumbs = document.getElementById('thumbs-wrapper');
    wrapper.innerHTML = '';
    thumbs.innerHTML = '';

    imagens.forEach((img, i) => {
        // Slide principal
        const slide = document.createElement('div');
        slide.className = 'swiper-slide';
        slide.innerHTML = `<img src="../../${img.imagem}" alt="Imagem da obra">`;
        wrapper.appendChild(slide);

        // Thumb
        const thumb = document.createElement('div');
        thumb.className = 'swiper-slide';
        thumb.innerHTML = `<img src="../../${img.imagem}" alt=""><span>${img.nome || ''}</span>`;
        thumbs.appendChild(thumb);
    });

    if (thumbsSwiper) thumbsSwiper.destroy(true, true);
    thumbsSwiper = new Swiper('.myThumbs', {
        spaceBetween: 10,
        slidesPerView: 4,
        freeMode: true,
        watchSlidesProgress: true,
    });

    if (swiper) swiper.destroy(true, true);
    swiper = new Swiper('.mySwiper', {
        loop: false,
        centeredSlides: false,
        slidesPerView: 1,
        spaceBetween: 0,
        effect: 'slide',
        pagination: { el: '.swiper-pagination', clickable: true },
        keyboard: { enabled: true },
        mousewheel: { forceToAxis: true },
        thumbs: { swiper: thumbsSwiper },
        on: {
            slideChange: () => mostrarImagem(swiper.realIndex)
        }
    });

    mostrarImagem(0);
}

function mostrarImagem(i) {
    if (!imagens.length) return;
    const img = imagens[i];
    document.getElementById('imagem_nome').textContent = img.nome || 'Imagem';

    // Prepara lista de comentários, incluindo o motivo sugerido
    const todosComentarios = [];

    if (img.sugerida && img.motivo_sugerida) {
        todosComentarios.push({
            texto: img.motivo_sugerida,
            autor: 'Improov',
            logo: true
        });
    }

    (img.comentarios || []).forEach(c => todosComentarios.push(c));
    carregarComentarios(todosComentarios);
}

function carregarComentarios(comentarios) {
    const lista = document.getElementById('lista-comentarios');
    lista.innerHTML = '';
    comentarios.forEach(c => {
        const div = document.createElement('div');
        div.className = 'comentario';

        if (c.autor === 'Improov' && c.logo) {
            div.innerHTML = `
                <div class="comentario-header">
                    <img src="../../assets/logo.jpg" alt="Improov" class="comentario-icone">
                    <span class="comentario-nome">Improov</span>
                </div>
                <div class="comentario-texto">${c.texto}</div>
            `;
        } else {
            div.textContent = c.texto + (c.autor ? ` — ${c.autor}` : '');
        }

        lista.appendChild(div);
    });
}

document.getElementById('aprovar-btn').onclick = async () => {
    const idx = swiper.realIndex;
    await fetch('api_aprovar_angulo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ imagem_id: imagens[idx].id })
    });

    // Abre modal para comentário
    document.getElementById('modal-comentario').classList.add('show');
};

// Enviar comentário
document.getElementById('enviar-comentario').onclick = async () => {
    const texto = document.getElementById('comentario-texto').value.trim();
    const idx = swiper.realIndex;

    if (texto) {
        await fetch('api_comentar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ imagem_id: imagens[idx].id, texto })
        });
    }

    fecharModalComentario();
    mostrarMoodSelector();
};

// Pular comentário
document.getElementById('pular-comentario').onclick = () => {
    fecharModalComentario();
    mostrarMoodSelector();
};

function fecharModalComentario() {
    document.getElementById('modal-comentario').classList.remove('show');
}

function mostrarMoodSelector() {
    document.getElementById('mood-container').classList.remove('hidden');
}

// Enviar mood
document.getElementById('enviar-mood').onclick = async () => {
    const mood = document.getElementById('mood-select').value;
    if (!mood) return alert('Escolha um mood');
    const idx = swiper.realIndex;

    await fetch('api_mood.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ imagem_id: imagens[idx].id, mood })
    });

    document.getElementById('toast').classList.add('show');
    setTimeout(() => document.getElementById('toast').classList.remove('show'), 3000);

    // Opcional: desabilitar botão aprovar para evitar reenvio
    document.getElementById('aprovar-btn').disabled = true;
};


carregarImagens();
