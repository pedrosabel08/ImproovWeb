// Referências aos elementos
const colaboradorSelect = document.getElementById('colaboradorSelect');
const obraSelect = document.getElementById('obraSelect'); // Não pegar o valor aqui
const tabelaImagens = document.getElementById('imagens');
const selecionarTodosBtn = document.getElementById('selecionarTodos');
const definirPrioridadeBtn = document.getElementById('definirPrioridade');

// Variável de controle para o botão "Selecionar Todos"
let todosSelecionados = false;

// Atualizar tabela ao mudar colaborador

colaboradorSelect.addEventListener('change', colaboradoresData);
obraSelect.addEventListener('change', colaboradoresData);

function colaboradoresData() {
    const colaboradorId = colaboradorSelect.value;
    const obraId = obraSelect.value;

    fetch(`colaboradores.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ colaboradorId, obraId }), // Passando o valor correto de obraId
    })
        .then(response => response.json())
        .then(data => atualizarTabela(data.imagens))
        .catch(error => console.error('Erro ao buscar imagens:', error));
};

// Atualizar tabela de imagens
function atualizarTabela(imagens) {
    tabelaImagens.innerHTML = `
        <tr>
            <th>Nome imagem</th>
            <th>Status</th>
            <th>Prioridade</th>
            <th>Selecionar</th>
        </tr>
    `;

    imagens.forEach(imagem => {
        const row = document.createElement('tr');
        let prioridadeText = '';
        let prioridadeClass = ''; // Classe de estilo para a prioridade

        // Definir o texto e a classe com base na prioridade
        if (imagem.prioridade == 1) {
            prioridadeText = 'Alta';
            prioridadeClass = 'prioridade-alta';
        } else if (imagem.prioridade == 2) {
            prioridadeText = 'Média';
            prioridadeClass = 'prioridade-media';
        } else if (imagem.prioridade == 3) {
            prioridadeText = 'Baixa';
            prioridadeClass = 'prioridade-baixa';
        }

        // Construir a linha da tabela
        row.innerHTML = `
            <td>${imagem.imagem_nome}</td>
            <td>${imagem.status}</td>
            <td class="${prioridadeClass}">${prioridadeText}</td>
            <td><input type="checkbox" class="selecionar-imagem" data-id="${imagem.idfuncao_imagem}"></td>
        `;
        tabelaImagens.appendChild(row);
    });

}

// Selecionar ou desmarcar todos os checkboxes
selecionarTodosBtn.addEventListener('click', function () {
    todosSelecionados = !todosSelecionados;
    document.querySelectorAll('.selecionar-imagem').forEach(checkbox => {
        checkbox.checked = todosSelecionados;
    });
    selecionarTodosBtn.textContent = todosSelecionados ? 'Desmarcar Todos' : 'Selecionar Todos';
});

// Definir prioridade para imagens selecionadas
definirPrioridadeBtn.addEventListener('click', function () {
    const selecionados = Array.from(document.querySelectorAll('.selecionar-imagem:checked'));
    if (selecionados.length === 0) {
        alert('Selecione ao menos uma imagem!');
        return;
    }

    const ids = selecionados.map(cb => cb.getAttribute('data-id'));
    const novaPrioridade = prompt('Digite a nova prioridade (1 - Alta, 2 - Média, 3 - Baixa):');
    if (!novaPrioridade || !['1', '2', '3'].includes(novaPrioridade)) {
        alert('Por favor, insira uma prioridade válida (1, 2 ou 3).');
        return;
    }

    fetch('definir_prioridade.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids, novaPrioridade }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Prioridades atualizadas com sucesso!');

                // Atualizar a tabela
                colaboradorSelect.dispatchEvent(new Event('change'));

                // Resetar o botão "Selecionar Todos" e as checkboxes
                document.querySelectorAll('.selecionar-imagem').forEach(cb => cb.checked = false);
                selecionarTodosBtn.textContent = 'Selecionar Todos';
            } else {
                alert('Erro ao atualizar prioridades.');
            }
        })
        .catch(error => console.error('Erro ao definir prioridade:', error));
});
