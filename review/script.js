// Função para buscar imagens da obra e renderizar

const obraId = 1;

async function carregarImagens() {
    try {
        const res = await fetch(`getImagens.php?obraId=${obraId}`);
        const data = await res.json();

        if (data.error) {
            alert("Erro: " + data.error);
            return;
        }
        if (!data.imagens || data.imagens.length === 0) {
            alert("Nenhuma imagem encontrada.");
            return;
        }

        // Formata para cada versão ser uma linha
        const linhas = data.imagens.map(img => {
            return {
                nome_da_imagem: img.imagem_nome,
                preview: `../uploads/imagens/${img.nome_arquivo}`,
                versao: img.versao || 1,
                data_envio: img.data_envio ? new Date(img.data_envio.replace(' ', 'T')).toLocaleDateString('pt-BR') : '',
                selecionado: false,
                imagem_id: img.imagem_id,
                status: img.status
            };
        });

        // Cria tabela com Tabulator
        new Tabulator("#tabelaImagens", {
            data: linhas,
            layout: "fitColumns",
            groupBy: "nome_da_imagem",
            groupStartOpen: true, // Começa fechado
            placeholder: "Nenhuma imagem encontrada",
            columns: [
                { formatter: "rowSelection", titleFormatter: "rowSelection", hozAlign: "center", headerSort: false, width: 50 },

                {
                    title: "Preview",
                    field: "preview",
                    formatter: function (cell) {
                        const url = cell.getValue();
                        const rowData = cell.getData();
                        return `<img src="${url}" style="max-height:80px;cursor:pointer" onclick="selecionarImagem('${rowData.imagem_id}','${url}','${rowData.status}')">`;
                    }
                },
                { title: "Versão", field: "versao", hozAlign: "center" },
                { title: "Status", field: "status", hozAlign: "center" },
                { title: "Data de Envio", field: "data_envio", hozAlign: "center" }
            ]
        });

    } catch (error) {
        console.error('Erro ao carregar imagens:', error);
    }
}

// document.getElementById("batchActionBtn").addEventListener("click", () => {
//     const selecionadas = tabela.getSelectedData();
//     if (selecionadas.length === 0) {
//         alert("Nenhuma imagem selecionada.");
//         return;
//     }

//     console.log("Selecionadas para batch:", selecionadas);

//     // Aqui você pode mandar via fetch/AJAX para fazer bloqueio, exclusão etc.
//     // fetch('batchAction.php', { method: 'POST', body: JSON.stringify(selecionadas) })
// });

function selecionarImagem(id, src, status) {
    console.log("Selecionando imagem:", id, src, status);
    if (status.toLowerCase() === 'wait') {
        alert("Esta imagem ainda está em processamento. Por favor, aguarde.");
        return;
    } else {
        localStorage.setItem('imagem_id_selecionada', id);
        localStorage.setItem('imagem_src_selecionada', src);
        window.location.href = 'arquivo.php';
    }

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
        body: JSON.stringify({ id, acao: 'block' })
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