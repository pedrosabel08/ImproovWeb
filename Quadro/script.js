// Variáveis para o dragging
let isDragging = false;
let startX;
let scrollLeft;

const container = document.getElementById('quadro-container');

// Evento de início do dragging
container.addEventListener('mousedown', (e) => {
    isDragging = true;
    container.classList.add('dragging');
    startX = e.pageX - container.offsetLeft;
    scrollLeft = container.scrollLeft;
});

// Evento de movimento durante o dragging
container.addEventListener('mousemove', (e) => {
    if (!isDragging) return;
    e.preventDefault();
    const x = e.pageX - container.offsetLeft;
    const walk = (x - startX) * 1; // Controle da velocidade do dragging
    container.scrollLeft = scrollLeft - walk;
});

// Evento de término do dragging
container.addEventListener('mouseup', () => {
    isDragging = false;
    container.classList.remove('dragging');
});

container.addEventListener('mouseleave', () => {
    isDragging = false;
    container.classList.remove('dragging');
});

// Fazer a requisição ao arquivo PHP
fetch('colaboradores.php')
    .then(response => response.json())
    .then(data => {
        // Organizar as tarefas por colaborador
        const colaboradores = {};
        data.forEach(tarefa => {
            if (!colaboradores[tarefa.nome_colaborador]) {
                colaboradores[tarefa.nome_colaborador] = [];
            }
            colaboradores[tarefa.nome_colaborador].push({
                imagem_nome: tarefa.imagem_nome,
                prioridade: tarefa.prioridade,
                nome_funcao: tarefa.nome_funcao,
                data_mais_recente: tarefa.data_mais_recente
            });
        });

        // Criar os quadros para cada colaborador
        for (const [colaborador, tarefas] of Object.entries(colaboradores)) {
            const quadro = document.createElement('div');
            quadro.className = 'colaborador-quadro';

            const title = document.createElement('h3');
            title.textContent = colaborador;
            quadro.appendChild(title);

            // Criar os cards para cada tarefa
            tarefas.forEach(tarefa => {
                const card = document.createElement('div');
                card.className = 'card';

                const imagemNome = document.createElement('p');
                imagemNome.textContent = tarefa.imagem_nome;
                card.appendChild(imagemNome);

                // Div para prioridade e função
                const prioridadeFuncaoDiv = document.createElement('div');
                prioridadeFuncaoDiv.className = 'prioridade-funcao';

                const prioridade = document.createElement('p');

                if (tarefa.prioridade == 1) {
                    prioridadeText = 'Alta';
                    bgColor = '#FF6347'; // Vermelho
                    color = '#f1f1f1';
                } else if (tarefa.prioridade == 2) {
                    prioridadeText = 'Média';
                    bgColor = '#FFD700'; // Amarelo
                    color = '#000';

                } else if (tarefa.prioridade == 3) {
                    prioridadeText = 'Baixa';
                    bgColor = '#90EE90'; // Verde
                    color = '#000';

                }

                prioridade.textContent = prioridadeText;
                prioridade.className = 'prioridade';
                prioridade.style.backgroundColor = bgColor;
                prioridade.style.color = color;
                prioridadeFuncaoDiv.appendChild(prioridade);

                const nomeFuncao = document.createElement('p');
                nomeFuncao.textContent = tarefa.nome_funcao;
                nomeFuncao.className = 'funcao';
                prioridadeFuncaoDiv.appendChild(nomeFuncao);

                console.log(tarefa.data_mais_recente)
                const data = document.createElement('p');
                data.textContent = tarefa.data_mais_recente;
                data.className = 'data';
                card.appendChild(data);

                card.appendChild(prioridadeFuncaoDiv);

                quadro.appendChild(card);
            });

            // Adicionar o quadro ao container principal
            container.appendChild(quadro);
        }
    })
    .catch(error => console.error('Erro ao buscar tarefas:', error));


