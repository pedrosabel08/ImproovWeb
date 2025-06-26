// Função para buscar imagens da obra e renderizar

const obraId = 1;
async function carregarImagens() {
    try {
        const res = await fetch(`getImagens.php?obraId=${obraId}`);
        const data = await res.json();

        const container = document.getElementById('imageList');
        container.innerHTML = '';

        // Verifica se houve erro na resposta do PHP
        if (data.error) {
            container.innerHTML = `<p class="error-message">Erro: ${data.error}</p>`;
            return;
        }

        // Verifica se existem imagens para exibir
        if (!data.imagens || data.imagens.length === 0) {
            container.innerHTML = `<p>Nenhuma imagem encontrada para esta obra.</p>`;
            return;
        }


        // Agrupar imagens por imagem_id
        const agrupadas = data.imagens.reduce((acc, img) => {
            if (!acc[img.imagem_id]) {
                acc[img.imagem_id] = {
                    // Pega o nome da imagem da primeira ocorrência encontrada para aquele ID
                    nome_da_imagem: img.imagem_nome,
                    arquivos: []
                };
            }
            acc[img.imagem_id].arquivos.push({
                nome_arquivo: img.nome_arquivo,
                idreview: img.id,
                lock: img.lock,
                hide: img.hide,
                data_envio: img.data_envio,
                versao: img.versao
            }); return acc;
        }, {});

        for (const imagemId in agrupadas) {
            const group = agrupadas[imagemId];
            const div = document.createElement('div');
            div.className = 'image-card';


            // Ordena por versão (do maior para o menor)
            const arquivosOrdenados = group.arquivos
                .filter(imagem => imagem.nome_arquivo)
                .sort((a, b) => (b.versao || 0) - (a.versao || 0));

            if (arquivosOrdenados.length === 0) continue;


            const ultima = arquivosOrdenados[0];
            const anteriores = arquivosOrdenados.slice(1);

            let imagensHTML = `
    <div class="imagem-row">
        <img src="../uploads/imagens/${ultima.nome_arquivo}" 
            alt="Preview de ${group.nome_da_imagem}" 
            class="imagem-preview"
            style="cursor:pointer"
            onclick="selecionarImagem(${ultima.idreview}, '../uploads/imagens/${ultima.nome_arquivo}')"
        />
        <span class="image-versao">
            Versão ${ultima.versao || 1}
            ${anteriores.length > 0 ? `<button class="toggle-versoes-btn" onclick="toggleVersoes(this)" title="Ver versões anteriores" style="background:none;border:none;cursor:pointer;font-size:16px;">&#9660;</button>` : ''}
        </span>
        <span class="image-label">
            ${ultima.data_envio
                    ? new Date(ultima.data_envio.replace(' ', 'T')).toLocaleDateString('pt-BR')
                    : 'Data não disponível'
                }
        </span>
        <div class="imagem-menu-col">
            <button onclick="toggleMenu(this)" class="menu-btn">⋮</button>
            <div class="menu-popup hidden">
                <button onclick="bloquearImagem(${ultima.idreview})">${ultima.lock ? '🔒' : '🔓'} Bloquear</button>
                <button onclick="ocultarImagem(${ultima.idreview})">${ultima.hide ? '🙈' : '👁️'} Ocultar</button>
                <button onclick="deletarImagem(${ultima.idreview}, '${ultima.nome_arquivo}')">🗑️ Excluir</button>
            </div>
        </div>
    </div>
`;

            if (anteriores.length > 0) {
                imagensHTML += `
        <div class="versoes-anteriores hidden">
            ${anteriores.map(imagem => `
                <div class="imagem-row">
                    <img src="../uploads/imagens/${imagem.nome_arquivo}" 
                        alt="Preview de ${group.nome_da_imagem}" 
                        class="imagem-preview"
                        style="cursor:pointer"
                        onclick="selecionarImagem(${imagem.idreview}, '../uploads/imagens/${imagem.nome_arquivo}')"
                    />
                    <span class="image-versao">Versão ${imagem.versao || ''}</span>
                    <span class="image-label">
                        ${imagem.data_envio
                        ? new Date(imagem.data_envio.replace(' ', 'T')).toLocaleDateString('pt-BR')
                        : 'Data não disponível'
                    }
                    </span>
                    <div class="imagem-menu-col">
                        <button onclick="toggleMenu(this)" class="menu-btn">⋮</button>
                        <div class="menu-popup hidden">
                            <button onclick="bloquearImagem(${imagem.idreview})">${imagem.lock ? '🔒' : '🔓'} Bloquear</button>
                            <button onclick="ocultarImagem(${imagem.idreview})">${imagem.hide ? '🙈' : '👁️'} Ocultar</button>
                            <button onclick="deletarImagem(${imagem.idreview}, '${imagem.nome_arquivo}')">🗑️ Excluir</button>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
            }

            div.innerHTML = `
        <div class="image-card-header">
            <p class="titulo">${group.nome_da_imagem}</p>
            <button onclick="adicionarImagem(${imagemId})">+</button>
        </div>
        <div>${imagensHTML}</div>
    `;
            container.appendChild(div);
        }



    } catch (error) {
        console.error('Erro ao carregar imagens:', error);
        container.innerHTML = `<p class="error-message">Não foi possível carregar as imagens. Tente novamente mais tarde.</p>`;
    }
}

function selecionarImagem(id, src) {
    localStorage.setItem('imagem_id_selecionada', id);
    localStorage.setItem('imagem_src_selecionada', src);
    window.location.href = 'arquivo.php';
}


// Função para expandir/ocultar versões anteriores
function toggleVersoes(btn) {
    const versoesDiv = btn.closest('.imagem-row').nextElementSibling;
    versoesDiv.classList.toggle('hidden');
    btn.innerHTML = versoesDiv.classList.contains('hidden') ? '&#9660;' : '&#9650;';
    btn.title = versoesDiv.classList.contains('hidden') ? 'Ver versões anteriores' : 'Ocultar versões';
}
function toggleMenu(btn) {
    // Fecha outros menus abertos
    document.querySelectorAll('.menu-popup').forEach(menu => {
        if (menu !== btn.nextElementSibling) menu.classList.add('hidden');
    });
    // Alterna o menu clicado
    btn.nextElementSibling.classList.toggle('hidden');
}

// Fecha o menu ao clicar fora
document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('menu-btn')) {
        document.querySelectorAll('.menu-popup').forEach(menu => menu.classList.add('hidden'));
    }
});

async function bloquearImagem(id) {
    await fetch(`atualizarImagem.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, acao: 'lock' })
    });
    carregarImagens(); // recarrega
}

async function ocultarImagem(id) {
    await fetch(`atualizarImagem.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, acao: 'hide' })
    });
    carregarImagens(); // recarrega
}

async function deletarImagem(id, nomeArquivo) {
    if (!confirm("Tem certeza que deseja deletar esta imagem?")) return;

    await fetch(`delete.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, nomeArquivo })
    });
    carregarImagens(); // recarrega
}



// Função para adicionar imagem relacionada
function adicionarImagem(imagemId) {
    // Cria input file dinâmico
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = () => {
        const file = input.files[0];
        if (!file) return;

        // Aqui envia para backend via fetch com FormData
        const formData = new FormData();
        formData.append('imagem_id', imagemId);
        formData.append('imagem', file);

        fetch('upload_imagem.php', {
            method: 'POST',
            body: formData
        }).then(res => res.json())
            .then(data => {
                if (data.sucesso) {
                    alert('Imagem enviada com sucesso!');
                    carregarImagens(); // Atualiza lista
                } else {
                    alert('Falha ao enviar imagem.');
                }
            })
            .catch(() => alert('Erro no upload.'));
    };

    input.click();
}

carregarImagens();