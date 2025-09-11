function atualizarTabela() {
    fetch('lista_pendentes.php') // Chama o JSON gerado
        .then(response => response.json())
        .then(data => {
            const tabela = document.querySelector('#tabela-arquivos tbody');
            tabela.innerHTML = '';

            data.forEach(arq => {
                const row = tabela.insertRow();
                row.setAttribute('data-id', arq.idarquivo); // Adiciona o ID do arquivo como atributo data-id
                row.setAttribute('data-id-obra', arq.obra_id); // Adiciona o ID do arquivo como atributo data-id
                row.insertCell(0).innerText = arq.nome_original;
                row.insertCell(1).innerText = arq.obra;

                // Status com ícone Font Awesome
                const statusCell = row.insertCell(2);

                let icone = '';
                switch (arq.status) {
                    case 'pendente':
                        icone = '<i class="fas fa-hourglass-half text-warning"></i>';
                        break;
                    case 'atualizado':
                        icone = '<i class="fas fa-check-circle text-success"></i>';
                        break;
                    case 'antigo':
                        icone = '<i class="fas fa-history text-muted"></i>';
                        break;
                    case 'invalido':
                        icone = '<i class="fas fa-times-circle text-danger"></i>';
                        break;
                    default:
                        icone = '<i class="fas fa-question-circle text-secondary"></i>';
                }

                statusCell.innerHTML = `${icone} ${arq.status}`;

                row.insertCell(3).innerText = arq.recebido_por;
                row.insertCell(4).innerText = arq.recebido_em;
            });
        })
        .catch(error => console.error('Erro ao atualizar tabela:', error));
}

function abrirModal() {
    document.getElementById('modalUpload').style.display = 'flex';
}

function fecharModal() {
    document.getElementById('modalUpload').style.display = 'none';
}

// Clique na linha para abrir modal
document.querySelector('#tabela-arquivos tbody').addEventListener('click', function (event) {
    const row = event.target.closest('tr');
    if (!row) return; // Sai se não for uma linha
    const arquivoId = row.getAttribute('data-id');
    if (!arquivoId) return; // Sai se não tiver ID
    const obraId = row.getAttribute('data-id-obra');
    if (!obraId) return; // Sai se não tiver ID
    document.getElementById('arquivoId').value = arquivoId;
    document.getElementById('obraId').value = obraId;
    abrirModal();
});

const tipoArquivo = document.getElementById('tipo_arquivo');
const divSubstitui = document.getElementById('divSubstitui');
const selectSubstitui = document.getElementById('substitui_arquivo');

// Mostra/oculta o campo quando tipo_arquivo muda
tipoArquivo.addEventListener('change', function () {
    if (this.value === 'revisao') {
        divSubstitui.style.display = 'block';
        carregarArquivosDaObra();
    } else {
        divSubstitui.style.display = 'none';
        selectSubstitui.innerHTML = '';
    }
});

// Função que busca arquivos da obra via fetch/PHP
function carregarArquivosDaObra() {
    const obraId = document.getElementById('obraId').value; // ou outro input que contenha a obra
    const categoria = document.getElementById('categoria').value; // ou outro input que contenha a obra
    if (!obraId) return;

    fetch(`buscar_arquivos_obra.php?obra_id=${obraId}&categoria=${categoria}`)
        .then(response => response.json())
        .then(data => {
            selectSubstitui.innerHTML = '';
            if (data.length === 0) {
                selectSubstitui.innerHTML = '<option value="">Nenhum arquivo encontrado</option>';
            } else {
                selectSubstitui.innerHTML = '<option value="">Selecione</option>';
                data.forEach(arq => {
                    const opt = document.createElement('option');
                    opt.value = arq.idarquivo;
                    opt.textContent = arq.nome_original;
                    selectSubstitui.appendChild(opt);
                });
            }
        })
        .catch(err => {
            selectSubstitui.innerHTML = '<option value="">Erro ao carregar arquivos</option>';
            console.error(err);
        });
}

const tipoImagemSelect = document.getElementById('tipo_imagem');
const imagensTipoDiv = document.getElementById('imagensTipo');

tipoImagemSelect.addEventListener('change', function () {
    const obraId = document.getElementById('obraId').value;
    if (!obraId) {
        imagensTipoDiv.innerHTML = '';
        return;
    }

    let selecionados = Array.from(this.selectedOptions).map(opt => opt.value);

    // Se "todas" foi selecionado → substitui por todos os valores (menos "todas")
    if (selecionados.includes('todas')) {
        selecionados = Array.from(this.options)
            .map(opt => opt.value)
            .filter(v => v && v !== 'todas');
    }

    if (selecionados.length === 0) {
        imagensTipoDiv.innerHTML = '';
        return;
    }

    imagensTipoDiv.innerHTML = '<p>Carregando imagens...</p>';

    // Busca imagens de todos os tipos selecionados
    fetch(`buscar_imagens_tipo.php?obra_id=${obraId}&tipos=${encodeURIComponent(JSON.stringify(selecionados))}`)
        .then(res => res.json())
        .then(data => {
            imagensTipoDiv.innerHTML = '';

            if (data.length === 0) {
                imagensTipoDiv.innerHTML = '<p>Nenhuma imagem encontrada.</p>';
                return;
            }

            data.forEach(img => {
                const wrapper = document.createElement('div');
                wrapper.style.textAlign = 'center';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = true;
                checkbox.name = 'batch_imagens[]';
                checkbox.value = img.idimagem;

                const imagem_nome = document.createElement('p');
                imagem_nome.innerText = img.imagem_nome;

                wrapper.appendChild(checkbox);
                wrapper.appendChild(imagem_nome);
                imagensTipoDiv.appendChild(wrapper);
            });
        })
        .catch(err => {
            console.error(err);
            imagensTipoDiv.innerHTML = '<p>Erro ao carregar imagens.</p>';
        });
});


document.getElementById('formRevisaoArquivo').addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    // console.log('Dados do formulário:', Array.from(formData.entries())); // Log dos dados do formulário
    fetch('revisar.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Revisão salva com sucesso!');
                fecharModal();
                this.reset();
            } else {
                alert('Erro: ' + data.error);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Erro inesperado.');
        });
});


atualizarTabela();
