// script.js - busca imagens e controla UI

const API = 'get_images.php';

document.addEventListener('DOMContentLoaded', () => {
    fetch(API)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                console.error('API error', data);
                return;
            }
            // mostrar nomenclatura da obra no header
            if (data.obra_nomenclatura) {
                const h1 = document.querySelector('.greeting h3');
                h1.textContent = `${data.obra_nomenclatura} — FollowUp - Escolha de Ângulos`;
            }
            window._followup_images = data.imagens || [];
            populateTable(window._followup_images);
            // render metrics
            if (data.metrics) {
                document.getElementById('metric-chosen').textContent = data.metrics.chosen || 0;
                document.getElementById('metric-pending').textContent = data.metrics.pending || 0;
            }
        })
        .catch(err => console.error('Fetch error', err));

    document.getElementById('voltar').addEventListener('click', () => {
        hideVisualizador();
    });

    document.getElementById('escolher-angulo').addEventListener('click', () => {
        alert('Função de escolher ângulo (a implementar)');
    });
});

function populateTable(imagens) {
    const tbody = document.querySelector('#tabela-imagens tbody');
    tbody.innerHTML = '';
    imagens.forEach(img => {
        const tr = document.createElement('tr');
        tr.dataset.id = img.id;
        tr.innerHTML = `<td>${escapeHtml(img.nome_imagem)}</td><td>${escapeHtml(img.followup_status || '')}</td>`;
        tr.addEventListener('click', () => onSelectImage(img));
        tbody.appendChild(tr);
    });
}

function clearSelection() {
    document.querySelectorAll('#tabela-imagens tr, #tabela-status tr').forEach(r => r.classList.remove('selected'));
}

function onSelectImage(img) {
    // preencher esquerda
    document.getElementById('nome-imagem').textContent = img.nome_imagem;
    // transformar layout: mostrar visualizador (80/20)
    document.body.classList.add('visualizer-open');
    document.getElementById('visualizador').classList.remove('hidden');
    // garantir que a tabela principal continue visível na direita (já estará dentro do visualizador)
    const car = document.getElementById('carrossel');
    car.innerHTML = '';

    // Buscar ângulos via endpoint (imagem_id)
    fetch(`get_angles.php?imagem_id=${encodeURIComponent(img.id)}`)
        .then(r => r.json())
        .then(data => {
            car.innerHTML = '';
            const angles = (data.angles && data.angles.length) ? data.angles.filter(a => a.status === 'pendente') : [];

            // criando controles prev/next
            const prevBtn = document.createElement('button');
            prevBtn.className = 'carousel-control prev';
            prevBtn.textContent = '<';
            const nextBtn = document.createElement('button');
            nextBtn.className = 'carousel-control next';
            nextBtn.textContent = '>';

            car.appendChild(prevBtn);

            if (angles.length) {
                angles.forEach((a, idx) => {
                    const imgEl = document.createElement('img');
                    imgEl.src = a.filename ? `https://improov.com.br/sistema/uploads/renders/${a.filename}` : '../assets/logo.jpg';
                    imgEl.alt = (a.filename || img.nome_imagem) + ' - ângulo ' + (idx + 1);
                    imgEl.className = 'carrossel-item';
                    if (idx === 0) imgEl.classList.add('active');
                    imgEl.dataset.angleId = a.id;
                    car.appendChild(imgEl);
                });
                car.appendChild(nextBtn);

                // carousel navigation
                let current = 0;
                const items = car.querySelectorAll('.carrossel-item');
                const updateActive = (i) => {
                    items.forEach(it => it.classList.remove('active'));
                    if (items[i]) items[i].classList.add('active');
                };
                prevBtn.addEventListener('click', () => {
                    current = (current - 1 + items.length) % items.length;
                    updateActive(current);
                });
                nextBtn.addEventListener('click', () => {
                    current = (current + 1) % items.length;
                    updateActive(current);
                });

                // escolher-angulo habilitado
                document.getElementById('escolher-angulo').disabled = false;
            } else {
                // sem ângulos pendentes
                const span = document.createElement('div');
                span.textContent = 'Nenhum ângulo pendente para esta imagem.';
                car.appendChild(span);
                car.appendChild(nextBtn);
                document.getElementById('escolher-angulo').disabled = true;
            }
        })
        .catch(err => {
            console.error('Erro ao buscar ângulos', err);
        });

    // preencher tabela direita com um único registro (nome + status)
    // preencher tabela lateral com todas as imagens (para alternar rapidamente)
    const tbody = document.querySelector('#tabela-status tbody');
    tbody.innerHTML = '';
    (window._followup_images || []).forEach(item => {
        const tr = document.createElement('tr');
        tr.dataset.id = item.id;
        tr.innerHTML = `<td>${escapeHtml(item.nome_imagem)}</td><td>${escapeHtml(item.followup_status || '')}</td>`;
        tr.addEventListener('click', () => {
            // alterar o visualizador para essa imagem
            clearSelection();
            tr.classList.add('selected');
            onSelectImage(item);
        });
        tbody.appendChild(tr);
    });

    showVisualizador();
}

function showVisualizador() {
    document.getElementById('visualizador').classList.remove('hidden');
}
function hideVisualizador() {
    document.getElementById('visualizador').classList.add('hidden');
    document.body.classList.remove('visualizer-open');
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Mantemos carrossel estático: clique não altera o ângulo (requisito atual).
// Se quiser ativar navegação por clique, descomente o bloco abaixo e remova este comentário.

document.addEventListener('click', (e) => {
    if (e.target.classList.contains('carrossel-item')) {
        const items = Array.from(document.querySelectorAll('.carrossel-item'));
        const idx = items.indexOf(e.target);
        items.forEach(it => it.classList.remove('active'));
        const next = items[(idx + 1) % items.length];
        next.classList.add('active');
    }
});

