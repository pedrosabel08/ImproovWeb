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
            container.innerHTML = `<p class="text-center text-gray-500">Nenhuma imagem encontrada para esta obra.</p>`;
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
                hide: img.hide
            }); return acc;
        }, {});

        for (const imagemId in agrupadas) {
            const group = agrupadas[imagemId];
            const div = document.createElement('div');
            div.className = 'image-card';

            // Monta o HTML das miniaturas de prévia para cada imagem do grupo
            let imagensHTML = '';

            if (group.arquivos.length > 0) {
                imagensHTML = group.arquivos.filter(imagem => imagem.nome_arquivo) // <-- ignora arquivos sem nome
                    .map(imagem => {
                        return `
            <div class="relative m-1">
                <img src="../uploads/imagens/${imagem.nome_arquivo}" 
                     alt="Preview de ${group.nome_da_imagem}" 
                     class="w-24 h-24 object-cover rounded-md shadow" />

                <div class="absolute top-1 right-1 flex flex-col gap-1">
                    <button onclick="bloquearImagem(${imagem.idreview})" title="Bloquear Comentários" class="text-xs bg-blue-500 text-white px-1 py-0.5 rounded">${imagem.lock ? '🔒' : '🔓'}</button>
                    <button onclick="ocultarImagem(${imagem.idreview})" title="Ocultar Imagem" class="text-xs bg-yellow-500 text-white px-1 py-0.5 rounded">${imagem.hide ? '🙈' : '👁️'}</button>
                    <button onclick="deletarImagem(${imagem.idreview}, '${imagem.nome_arquivo}')" title="Excluir Imagem" class="text-xs bg-red-600 text-white px-1 py-0.5 rounded">🗑️</button>
                </div>
            </div>
        `;
                    }).join('');
            }

            // Define o conteúdo HTML do card, agora usando 'group.nome_da_imagem'
            div.innerHTML = `
                    <div class="image-card-header">
                    <p class="text-lg font-semibold text-gray-800">${group.nome_da_imagem}</p>
                    <button onclick="adicionarImagem(${imagemId})" class="button self-end">+</button>
                    </div>
                        <div class="image-list-flex">${imagensHTML}</div>
                    `;
            container.appendChild(div);
        }

    } catch (error) {
        console.error('Erro ao carregar imagens:', error);
        container.innerHTML = `<p class="error-message">Não foi possível carregar as imagens. Tente novamente mais tarde.</p>`;
    }
}

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