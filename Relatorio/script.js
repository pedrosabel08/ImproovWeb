async function carregarDados() {
    const res = await fetch('relatorio.php');
    const dados = await res.json();

    const listaHold = document.getElementById('holdObras');
    listaHold.innerHTML = '';

    dados.hold.forEach(obra => {
        // Nível 1: Obra
        const obraItem = document.createElement('li');
        obraItem.textContent = obra.nome_obra;
        obraItem.style.cursor = 'pointer';

        const gaveta = document.createElement('ul');
        gaveta.style.display = 'none';

        obraItem.addEventListener('click', () => {
            gaveta.style.display = gaveta.style.display === 'none' ? 'block' : 'none';
        });

        // Nível 2: Tipos de imagem
        obra.tipos.forEach(tipo => {
            const tipoItem = document.createElement('li');
            tipoItem.classList.add('tipo-imagem');
            tipoItem.textContent = `${tipo.tipo_imagem} (${tipo.count})`;

            tipoItem.addEventListener('click', (e) => {
                e.stopPropagation(); // não fecha a gaveta
                abrirPainelDetalhes(obra.nome_obra, tipo);
            });

            gaveta.appendChild(tipoItem);
        });

        listaHold.appendChild(obraItem);
        listaHold.appendChild(gaveta);
    });
}

function abrirPainelDetalhes(nomeObra, tipo) {
    const painel = document.getElementById('painelDetalhes');
    const conteudo = document.getElementById('conteudoPainel');

    conteudo.innerHTML = `<h3>${nomeObra} - ${tipo.tipo_imagem}</h3>`;
    // Botão/Toggle "Aplicar para todas"
    const divAplicar = document.createElement('div');
    divAplicar.innerHTML = `
        <h4>Aplicar para todas as imagens deste tipo</h4>
        <label class="switch">
            <input type="checkbox" id="toggleBotoes">
            <span class="slider"></span>
        </label>
        <label>Justificativa:</label>
        <input type="text" id="just_todas">
        <label>Prazo:</label>
        <input type="date" id="prazo_todas">
        <button onclick="aplicarParaTodas('${tipo.tipo_imagem}')">Aplicar</button>
    `;
    conteudo.appendChild(divAplicar);

    // Evento do switch para mostrar/esconder botões
    divAplicar.querySelector('#toggleBotoes').addEventListener('change', function () {
        const botoes = conteudo.querySelectorAll('.buttons');
        botoes.forEach(b => {
            b.style.display = this.checked ? 'block' : 'none';
        });
    });

    painel.classList.add('ativo');
    tipo.imagens.forEach(img => {
        const div = document.createElement('div');
        div.classList.add('imagem_hold');
        div.innerHTML = `
            <p><strong>Nome:</strong> ${img.nome}</p>
            <label>Justificativa:</label>
            <input type="text" id="just_${img.id}" value="${img.justificativa || ''}">
            <label>Prazo:</label>
            <input type="date" id="prazo_${img.id}" value="${img.prazo_hold || ''}">
            <div class='buttons' style="display:none">
            <button onclick="salvarImagem(${img.id})" style="background-color:#00c500">Salvar</button>
            <button onclick="excluirImagem(${img.id})" style="background-color:#c50000">Excluir</button>
            </div>
        `;
        conteudo.appendChild(div);
    });


}

async function salvarImagem(id) {
    const justificativa = document.getElementById(`just_${id}`).value;
    const prazo = document.getElementById(`prazo_${id}`).value;

    await fetch('crud_hold.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            acao: 'editar',
            id: id,
            justificativa: justificativa,
            prazo: prazo
        })
    });

    alert('Imagem atualizada');
    carregarDados();
}

async function excluirImagem(id) {
    if (!confirm('Excluir esta imagem?')) return;
    await fetch('crud_hold.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ acao: 'excluir', id: id })
    });

    alert('Imagem excluída');
    carregarDados();
}

async function aplicarParaTodas(tipo_imagem) {
    const justificativa = document.getElementById(`just_todas`).value;
    const prazo = document.getElementById(`prazo_todas`).value;

    await fetch('crud_hold.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            acao: 'aplicar_todas',
            tipo_imagem: tipo_imagem,
            justificativa: justificativa,
            prazo: prazo
        })
    });

    alert('Justificativa/Prazo aplicados para todas as imagens do tipo');
    carregarDados();
}

carregarDados();
