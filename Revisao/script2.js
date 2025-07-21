document.addEventListener("DOMContentLoaded", function () {

    fetchObrasETarefas();

});

function revisarTarefa(idfuncao_imagem, nome_colaborador, imagem_nome, nome_funcao, colaborador_id, tipoRevisao) {
    const idcolaborador = localStorage.getItem('idcolaborador');

    let actionText = "";
    switch (tipoRevisao) {
        case "aprovado":
            actionText = "aprovar esta tarefa";
            break;
        case "ajuste":
            actionText = "marcar esta tarefa como necessitando de ajustes";
            break;
        case "aprovado_com_ajustes":
            actionText = "aprovar com ajustes";
            break;
    }

    if (confirm(`Você tem certeza de que deseja ${actionText}?`)) {
        fetch('revisarTarefa.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                idfuncao_imagem,
                nome_colaborador,
                imagem_nome,
                nome_funcao,
                colaborador_id,
                responsavel: idcolaborador,
                tipoRevisao
            }),
        })
            .then(response => {
                if (!response.ok) throw new Error("Erro ao atualizar a tarefa.");
                return response.json();
            })
            .then(data => {
                console.log("Resposta do servidor:", data);

                let message = "";
                let bgColor = "";

                switch (tipoRevisao) {
                    case "aprovado":
                        message = "Tarefa aprovada com sucesso!";
                        bgColor = "green";
                        break;
                    case "ajuste":
                        message = "Tarefa marcada como necessitando de ajustes!";
                        bgColor = "orange";
                        break;
                    case "aprovado_com_ajustes":
                        message = "Tarefa aprovada com ajustes!";
                        bgColor = "blue";
                        break;
                }

                Toastify({
                    text: data.success ? message : "Falha ao atualizar a tarefa: " + data.message,
                    duration: 3000,
                    backgroundColor: data.success ? bgColor : "red",
                    close: true,
                    gravity: "top",
                    position: "right"
                }).showToast();

                if (data.success) {
                    const obraSelecionada = document.getElementById('filtro_obra').value;

                    filtrarTarefasPorObra(obraSelecionada);
                }
            })
            .catch(error => {
                console.error("Erro:", error);
                Toastify({
                    text: "Ocorreu um erro ao processar a solicitação. " + error.message,
                    duration: 3000,
                    backgroundColor: "red",
                    close: true,
                    gravity: "top",
                    position: "right"
                }).showToast();
            });
    }

    event.stopPropagation();
}

// Função para alternar a visibilidade dos detalhes da tarefa
function toggleTaskDetails(taskElement) {
    taskElement.classList.toggle('open');
}

let dadosTarefas = [];
let todasAsObras = new Set();
let todosOsColaboradores = new Set();
let todasAsFuncoes = new Set();
let funcaoGlobalSelecionada = null;

async function fetchObrasETarefas() {
    try {
        const response = await fetch(`atualizar.php`);
        if (!response.ok) throw new Error("Erro ao buscar tarefas");

        dadosTarefas = await response.json();

        todasAsObras = new Set(dadosTarefas.map(t => t.nome_obra));
        todosOsColaboradores = new Set(dadosTarefas.map(t => t.nome_colaborador));
        todasAsFuncoes = new Set(dadosTarefas.map(t => t.nome_funcao)); // ou o nome do campo correspondente

        exibirCardsDeObra(dadosTarefas); // Mostra os cards

        const filtroSelect = document.getElementById('filtroFuncao');
        filtroSelect.style.display = 'block'; // Exibe o filtro de função
        filtroSelect.innerHTML = '<option value="">Todas as funções</option>';

        todasAsFuncoes.forEach(funcao => {
            const option = document.createElement('option');
            option.value = funcao;
            option.textContent = funcao;
            filtroSelect.appendChild(option);
        });

        document.getElementById('filtroFuncao').addEventListener('change', (event) => {
            funcaoGlobalSelecionada = event.target.value || null;

            const tarefasFiltradas = funcaoGlobalSelecionada
                ? dadosTarefas.filter(t => t.nome_funcao === funcaoGlobalSelecionada)
                : dadosTarefas;

            exibirCardsDeObra(tarefasFiltradas);
        });

    } catch (error) {
        console.error(error);
    }
}

async function buscarMencoesDoUsuario() {
    const response = await fetch('buscar_mencoes.php');
    return await response.json();
}

async function exibirCardsDeObra(tarefas) {
    const mencoes = await buscarMencoesDoUsuario();

    if (mencoes.total_mencoes > 0) {
        Swal.fire({
            title: '📣 Você foi mencionado!',
            text: `Há ${mencoes.total_mencoes} menção(ões) nas tarefas.`,
            icon: 'info',
            confirmButtonText: 'Ver cards'
        });
    }

    const container = document.querySelector('.containerObra');
    container.innerHTML = '';

    const obrasMap = new Map();
    tarefas.forEach(tarefa => {
        if (!obrasMap.has(tarefa.nome_obra)) {
            obrasMap.set(tarefa.nome_obra, []);
        }
        obrasMap.get(tarefa.nome_obra).push(tarefa);
    });

    obrasMap.forEach((tarefasDaObra, nome_obra) => {
        tarefasDaObra.sort((a, b) => new Date(b.data_aprovacao) - new Date(a.data_aprovacao));
        const tarefaComImagem = tarefasDaObra.find(t => t.imagem);
        const imagemPreview = tarefaComImagem ? `../${tarefaComImagem.imagem}` : '../assets/logo.jpg';

        const mencoesNaObra = mencoes.mencoes_por_obra[nome_obra] || 0;

        const card = document.createElement('div');
        card.classList.add('obra-card');

        card.innerHTML = `
        ${mencoesNaObra > 0 ? `<div class="mencao-badge">${mencoesNaObra}</div>` : ''}
        <div class="obra-img-preview">
            <img src="${imagemPreview}" alt="Imagem da obra ${nome_obra}">
        </div>
        <div class="obra-info">
            <h3>${tarefasDaObra[0].nomenclatura}</h3>
            <p>${tarefasDaObra.length} aprovações</p>
        </div>
    `;

        card.addEventListener('click', () => {
            filtrarTarefasPorObra(nome_obra);
        });

        container.appendChild(card);
    });
}

function filtrarTarefasPorObra(obraSelecionada) {

    document.getElementById('filtro_obra').value = obraSelecionada;

    // Filtra todas as tarefas da obra
    const tarefasDaObra = dadosTarefas.filter(t => t.nome_obra === obraSelecionada);

    // Atualiza os filtros dinamicamente com base nessa obra
    atualizarFiltrosDinamicos(tarefasDaObra);

    // Captura os novos valores dos selects após atualização
    const colaboradorSelecionado = document.getElementById('filtro_colaborador').value;
    let funcaoSelecionada = document.getElementById('nome_funcao').value;

    // Se houver filtro global ativo, aplica e reflete visualmente
    if (funcaoGlobalSelecionada) {
        funcaoSelecionada = funcaoGlobalSelecionada;

        const selectFuncao = document.getElementById('nome_funcao');
        const opcoes = Array.from(selectFuncao.options).map(opt => opt.value);
        if (opcoes.includes(funcaoGlobalSelecionada)) {
            selectFuncao.value = funcaoGlobalSelecionada;
        }
    }

    // Aplica os filtros adicionais (colaborador e função)
    const tarefasFiltradas = tarefasDaObra.filter(t => {
        const matchColaborador = !colaboradorSelecionado || t.nome_colaborador === colaboradorSelecionado;
        const matchFuncao = funcaoSelecionada === 'Todos' || t.nome_funcao === funcaoSelecionada;
        return matchColaborador && matchFuncao;
    });

    // Exibe as tarefas filtradas
    exibirTarefas(tarefasFiltradas);
}

function atualizarSelectColaborador(tarefas) {
    const selectColaborador = document.getElementById('filtro_colaborador');
    const valorAnterior = selectColaborador.value;

    const colaboradores = [...new Set(tarefas.map(t => t.nome_colaborador))];

    selectColaborador.innerHTML = '<option value="">Todos</option>';
    colaboradores.forEach(colab => {
        const option = document.createElement('option');
        option.value = colab;
        option.textContent = colab;
        selectColaborador.appendChild(option);
    });

    if ([...selectColaborador.options].some(o => o.value === valorAnterior)) {
        selectColaborador.value = valorAnterior;
    }
}

function atualizarSelectFuncao(tarefas) {
    const selectFuncao = document.getElementById('nome_funcao');
    const valorAnterior = selectFuncao.value;

    const funcoes = [...new Set(tarefas.map(t => t.nome_funcao))];

    selectFuncao.innerHTML = '<option value="Todos">Todos</option>';
    funcoes.forEach(funcao => {
        const option = document.createElement('option');
        option.value = funcao;
        option.textContent = funcao;
        selectFuncao.appendChild(option);
    });

    if ([...selectFuncao.options].some(o => o.value === valorAnterior)) {
        selectFuncao.value = valorAnterior;
    }
}

// Eventos para os filtros
function atualizarFiltrosDinamicos(tarefas) {
    const selectColaborador = document.getElementById('filtro_colaborador');
    const selectFuncao = document.getElementById('nome_funcao');

    // Salva os valores antes de atualizar
    const valorAnteriorColaborador = selectColaborador.value;
    const valorAnteriorFuncao = selectFuncao.value;

    const colaboradores = [...new Set(tarefas.map(t => t.nome_colaborador))];
    const funcoes = [...new Set(tarefas.map(t => t.nome_funcao))];

    // Atualiza select de colaborador
    selectColaborador.innerHTML = '<option value="">Todos</option>';
    colaboradores.forEach(colaborador => {
        const option = document.createElement('option');
        option.value = colaborador;
        option.textContent = colaborador;
        selectColaborador.appendChild(option);
    });

    // Atualiza select de função
    selectFuncao.innerHTML = '<option value="Todos">Todos</option>';
    funcoes.forEach(funcao => {
        const option = document.createElement('option');
        option.value = funcao;
        option.textContent = funcao;
        selectFuncao.appendChild(option);
    });

    // Reatribui os valores anteriores (se ainda existirem nas opções)
    if ([...selectColaborador.options].some(o => o.value === valorAnteriorColaborador)) {
        selectColaborador.value = valorAnteriorColaborador;
    }

    if ([...selectFuncao.options].some(o => o.value === valorAnteriorFuncao)) {
        selectFuncao.value = valorAnteriorFuncao;
    }
}

document.getElementById('filtro_colaborador').addEventListener('change', () => {
    const obraSelecionada = document.getElementById('filtro_obra').value;
    const colaboradorSelecionado = document.getElementById('filtro_colaborador').value;

    const tarefasDaObra = dadosTarefas.filter(t => t.nome_obra === obraSelecionada);
    const tarefasFiltradas = tarefasDaObra.filter(t =>
        !colaboradorSelecionado || t.nome_colaborador === colaboradorSelecionado
    );

    atualizarSelectFuncao(tarefasFiltradas); // atualiza o outro filtro com base nesse

    filtrarTarefasPorObra(obraSelecionada);
});

document.getElementById('nome_funcao').addEventListener('change', () => {
    const obraSelecionada = document.getElementById('filtro_obra').value;
    const funcaoSelecionada = document.getElementById('nome_funcao').value;

    const tarefasDaObra = dadosTarefas.filter(t => t.nome_obra === obraSelecionada);
    const tarefasFiltradas = tarefasDaObra.filter(t =>
        funcaoSelecionada === 'Todos' || t.nome_funcao === funcaoSelecionada
    );

    atualizarSelectColaborador(tarefasFiltradas); // atualiza o outro filtro com base nesse

    filtrarTarefasPorObra(obraSelecionada);
});

// Função para exibir as tarefas e abastecer os filtros
function exibirTarefas(tarefas, nomeObra = 'Obra Selecionada') {
    const drawer = document.getElementById('drawerTarefas');
    const tarefasImagensObra = drawer.querySelector('.tarefasImagensObra');
    const titulo = document.getElementById('obraTitulo');

    titulo.textContent = nomeObra;
    tarefasImagensObra.innerHTML = '';

    if (tarefas.length > 0) {
        tarefas.forEach(tarefa => {
            const taskItem = document.createElement('div');
            taskItem.classList.add('task-item');
            taskItem.setAttribute('onclick', `historyAJAX(${tarefa.idfuncao_imagem}, '${tarefa.nome_funcao}', '${tarefa.imagem_nome}', '${tarefa.nome_colaborador}')`);

            const bgColor = tarefa.status_novo === 'Em aprovação' ? 'green' :
                tarefa.status_novo === 'Ajuste' ? 'red' :
                    tarefa.status_novo === 'Aprovado com ajustes' ? 'blue' :
                        'transparent';

            taskItem.innerHTML = `
                <div class="task-info">
                    <h3 class="nome_funcao">${tarefa.nome_funcao}</h3><span class="colaborador">${tarefa.nome_colaborador}</span>
                    <p class="imagem_nome" data-obra="${tarefa.nome_obra}">${tarefa.imagem_nome}</p>
                    <p class="data_aprovacao">${formatarDataHora(tarefa.data_aprovacao)}</p>       
                    <p id="status_funcao" style="background-color: ${bgColor}; color: white; padding: 4px 8px; border-radius: 4px;">${tarefa.status_novo}</p>
                </div>
            `;

            tarefasImagensObra.appendChild(taskItem);
        });
    } else {
        tarefasImagensObra.innerHTML = '<p style="text-align: center; color: #888;">Não há tarefas de revisão no momento.</p>';
    }

    // Exibe o drawer
    drawer.classList.remove('hidden');
    drawer.classList.add('visible');
}

// Fecha o drawer
document.getElementById('fecharDrawer').addEventListener('click', () => {
    const drawer = document.getElementById('drawerTarefas');
    drawer.classList.remove('visible');
    setTimeout(() => drawer.classList.add('hidden'), 300);
});

function formatarData(data) {
    const [ano, mes, dia] = data.split('-'); // Divide a string no formato 'YYYY-MM-DD'
    return `${dia}/${mes}/${ano}`; // Retorna o formato 'DD/MM/YYYY'
}

function formatarDataHora(data) {
    const date = new Date(data); // Cria um objeto Date a partir da string datetime

    const dia = String(date.getDate()).padStart(2, '0'); // Pega o dia e formata com 2 dígitos
    const mes = String(date.getMonth() + 1).padStart(2, '0'); // Pega o mês e formata com 2 dígitos (mes começa do 0)
    const ano = date.getFullYear(); // Pega o ano
    const horas = String(date.getHours()).padStart(2, '0'); // Pega a hora e formata com 2 dígitos
    const minutos = String(date.getMinutes()).padStart(2, '0'); // Pega os minutos e formata com 2 dígitos

    return `${dia}/${mes}/${ano} ${horas}:${minutos}`; // Retorna o formato desejado
}



const modalComment = document.getElementById('modalComment');

const idusuario = parseInt(localStorage.getItem('idusuario')); // Obtém o idusuario do localStorage

let funcaoImagemId = null; // armazenado globalmente

function historyAJAX(idfuncao_imagem, funcao_nome, imagem_nome, colaborador_nome) {
    fetch(`historico.php?ajid=${idfuncao_imagem}`)
        .then(response => response.json())
        .then(response => {

            const titulo = document.getElementById('funcao_nome');
            titulo.textContent = `${colaborador_nome} - ${funcao_nome}`;
            // document.getElementById("id_funcao").value = idfuncao_imagem;
            document.getElementById("imagem_nome").textContent = imagem_nome;
            // document.getElementById("funcao_nome").textContent = funcao_nome;
            // document.getElementById("colaborador_nome").textContent = colaborador_nome;

            // Exibir o modal
            // const modal = document.getElementById('historico_modal');
            // modal.style.display = 'grid';
            const main = document.querySelector('.main');
            main.classList.add('hidden');

            const container_aprovacao = document.querySelector('.container-aprovacao');
            container_aprovacao.classList.remove('hidden');


            const { historico, imagens } = response;

            historico.forEach(historico => {

                if ([1, 2, 9, 20, 3].includes(idusuario)) { // Verifica se o idusuario é 1, 2 ou 9
                    document.getElementById('buttons-task').innerHTML = `
                    <button class="action-btn tooltip" id="check" data-tooltip="Aprovar"
                        onclick="revisarTarefa(${historico.funcao_imagem_id}, '${historico.colaborador_nome}', '${historico.imagem_nome}', '${historico.nome_funcao}', '${historico.colaborador_id}', 'aprovado')">
                        <i class="fa-solid fa-check"></i>
                    </button>
                    <button class="action-btn tooltip" id="xmark" data-tooltip="Rejeitar"
                        onclick="revisarTarefa(${historico.funcao_imagem_id}, '${historico.colaborador_nome}', '${historico.imagem_nome}', '${historico.nome_funcao}', '${historico.colaborador_id}', 'ajuste')">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <button class="action-btn tooltip" id="check_ajuste" data-tooltip="Aprovar com ajustes"
                        onclick="revisarTarefa(${historico.funcao_imagem_id}, '${historico.colaborador_nome}', '${historico.imagem_nome}', '${historico.nome_funcao}', '${historico.colaborador_id}', 'aprovado_com_ajustes')">
                        <i class="fa-solid fa-pen-ruler"></i>
                    </button>
                `;
                } else {
                    document.getElementById('buttons-task').innerHTML = ''; // Não exibe os botões para outros usuários
                }

                document.getElementById('add-imagem').addEventListener('click', () => {
                    funcaoImagemId = historico.funcao_imagem_id; // você já tem esse objeto
                    document.getElementById('imagem-modal').style.display = 'flex';
                });
            });
            // Renderizar as imagens
            const imageContainer = document.getElementById('imagens');
            imageContainer.innerHTML = ''; // Limpa as imagens anteriores
            // const imagemWrapperDiv = document.getElementById("imagem_wrapper");
            // imagemWrapperDiv.innerHTML = '';
            const commentDiv = document.querySelector('.sidebar-direita');
            commentDiv.style.display = 'none';

            const indiceSelect = document.getElementById('indiceSelect');
            indiceSelect.innerHTML = ''; // Limpa o select anterior
            const dataEnvio = document.getElementById('dataEnvio');

            // 1. Agrupar imagens por indice_envio
            const imagensAgrupadas = imagens.reduce((acc, img) => {
                if (!acc[img.indice_envio]) {
                    acc[img.indice_envio] = [];
                }
                acc[img.indice_envio].push(img);
                return acc;
            }, {});

            // 2. Popular o <select> com os índices de envio (ordenado desc)
            const indicesOrdenados = Object.keys(imagensAgrupadas).sort((a, b) => b - a);

            // Verifica se há índices disponíveis
            if (indicesOrdenados.length === 0) {
                indiceSelect.style.display = 'none'; // Oculta o select se não houver índices
                dataEnvio.textContent = ''; // Limpa a data de envio
            } else {
                indiceSelect.style.display = 'block'; // Exibe o select se houver índices

                // Preenche o select
                indicesOrdenados.forEach(indice => {
                    const option = document.createElement('option');
                    option.value = indice;
                    option.textContent = `Envio ${indice}`;
                    indiceSelect.appendChild(option);
                });

                // Já seleciona o mais recente e mostra as imagens
                indiceSelect.value = indicesOrdenados[0]; // pega o mais recente
                indiceSelect.dispatchEvent(new Event('change')); // já mostra as imagens
            }

            // 3. Evento de mudança no select
            indiceSelect.addEventListener('change', () => {
                const indiceSelecionado = indiceSelect.value;
                imageContainer.innerHTML = '';

                const imagensDoIndice = imagensAgrupadas[indiceSelecionado];

                if (imagensDoIndice && imagensDoIndice.length > 0) {
                    // ⏰ Atualiza a data de envio
                    const data = imagensDoIndice[0].data_envio;
                    dataEnvio.textContent = `${formatarDataHora(data)}`;

                    imagensDoIndice.forEach(img => {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'imageWrapper';

                        const imgElement = document.createElement('img');
                        imgElement.src = `../${img.imagem}`;
                        imgElement.alt = img.imagem;
                        imgElement.className = 'image';
                        imgElement.setAttribute('data-id', img.id);

                        imgElement.addEventListener('click', () => {
                            mostrarImagemCompleta(imgElement.src, img.id);
                        });

                        imgElement.addEventListener('contextmenu', (event) => {
                            event.preventDefault();
                            abrirMenuContexto(event.pageX, event.pageY, img.id, imgElement.src);
                        });

                        if (img.has_comments == "1" || img.has_comments === 1) {
                            const notificationDot = document.createElement('div');
                            notificationDot.className = 'notification-dot';
                            notificationDot.textContent = `${img.comment_count}`;
                            wrapper.appendChild(notificationDot);
                        }

                        wrapper.appendChild(imgElement);
                        imageContainer.appendChild(wrapper);
                    });
                } else {
                    dataEnvio.textContent = ''; // caso não tenha imagens
                }
            });
            // Já seleciona o mais recente e mostra as imagens
            if (indicesOrdenados.length > 0) {
                indiceSelect.value = indicesOrdenados[0]; // pega o mais recente
                indiceSelect.dispatchEvent(new Event('change')); // já mostra as imagens
            }

        })
        .catch(error => console.error("Erro ao buscar dados:", error));
}




document.querySelector('.close').addEventListener('click', () => {
    document.getElementById('imagem-modal').style.display = 'none';
    document.getElementById('input-imagens').value = '';
    document.getElementById('preview').innerHTML = '';
});

document.getElementById('input-imagens').addEventListener('change', function () {
    const preview = document.getElementById('preview');
    preview.innerHTML = '';

    const arquivos = this.files;

    for (let i = 0; i < arquivos.length; i++) {
        const reader = new FileReader();
        reader.onload = function (e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            preview.appendChild(img);
        };
        reader.readAsDataURL(arquivos[i]);
    }
});

document.getElementById('btn-enviar-imagens').addEventListener('click', () => {
    const input = document.getElementById('input-imagens');
    const arquivos = input.files;
    if (arquivos.length === 0 || !funcaoImagemId) return;

    const formData = new FormData();
    for (let i = 0; i < arquivos.length; i++) {
        formData.append('imagens[]', arquivos[i]);
    }

    formData.append('dataIdFuncoes', JSON.stringify([funcaoImagemId]));

    fetch('../uploadArquivos.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert(res.success);
                document.getElementById('imagem-modal').style.display = 'none';
                document.getElementById('input-imagens').value = '';
                document.getElementById('preview').innerHTML = '';
            } else {
                alert(res.error || 'Erro ao enviar imagens.');
            }
        })
        .catch(e => {
            console.error(e);
            alert('Erro na comunicação com o servidor.');
        });
});

function abrirMenuContexto(x, y, id, src) {
    const menu = document.getElementById('menuContexto');

    // Coloca info da imagem (caso precise usar depois)
    menu.setAttribute('data-id', id);
    menu.setAttribute('data-src', src);

    menu.style.top = `${y}px`;
    menu.style.left = `${x}px`;
    menu.style.display = 'block';
}

function excluirImagem() {
    const menu = document.getElementById('menuContexto');
    const idImagem = menu.getAttribute('data-id');

    if (!idImagem) {
        alert("ID da imagem não encontrado!");
        return;
    }

    if (confirm("Tem certeza que deseja excluir esta imagem?")) {
        fetch('excluir_imagem.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${idImagem}`
        })
            .then(response => response.text())
            .then(data => {
                console.log(data);
                // Remove a imagem da tela também, se quiser
                const imgElement = document.querySelector(`img[data-id='${idImagem}']`);
                if (imgElement) {
                    imgElement.parentElement.remove(); // Remove o wrapper da imagem
                }
                // Esconde o menu
                menu.style.display = 'none';
            })
            .catch(error => {
                console.error('Erro ao excluir imagem:', error);
                alert("Erro ao excluir imagem.");
            });
    } else {
        // Fecha o menu caso cancele
        menu.style.display = 'none';
    }
}

document.addEventListener('click', (e) => {
    const menu = document.getElementById('menuContexto');
    if (!menu.contains(e.target)) {
        menu.style.display = 'none';
    }
});

let tribute; // variável global
let mencionadosIds = []; // armazenar os IDs dos mencionados

document.addEventListener('DOMContentLoaded', async () => {
    try {
        const response = await fetch('buscar_usuarios.php');
        const users = await response.json();

        tribute = new Tribute({
            values: users.map(user => ({
                key: user.nome_colaborador,
                value: user.nome_colaborador,
                id: user.idcolaborador
            })),
            selectTemplate: item => {
                // Evita duplicados
                if (!mencionadosIds.includes(item.original.id)) {
                    mencionadosIds.push(item.original.id);
                }
                return `@${item.original.value}`; // Aparece só o nome no texto
            },
            menuItemTemplate: item => item.string
        });

        tribute.attach(document.getElementById('comentarioTexto'));
    } catch (error) {
        console.error('Erro ao carregar usuários:', error);
    }

    // Modal: fechar
    document.getElementById('fecharComentarioModal').onclick = () => {
        document.getElementById('comentarioModal').style.display = 'none';
    };
});

let ap_imagem_id = null; // Variável para armazenar o ID da imagem atual

// Mostra imagem e abre modal
function mostrarImagemCompleta(src, id) {
    ap_imagem_id = id;

    const imageWrapper = document.getElementById("image_wrapper");
    const sidebar = document.querySelector(".sidebar-direita");
    sidebar.style.display = "block";

    while (imageWrapper.firstChild) {
        imageWrapper.removeChild(imageWrapper.firstChild);
    }

    const imgElement = document.createElement("img");
    imgElement.id = "imagem_atual";
    imgElement.src = src;
    imgElement.style.width = "100%";
    imgElement.style.borderRadius = "10px";

    imageWrapper.appendChild(imgElement);
    document.querySelector('#imagem_atual').scrollIntoView({ behavior: 'smooth' });
    renderComments(id);

    imgElement.addEventListener('click', function (event) {
        if (![1, 2, 9, 20, 3].includes(idusuario)) return;

        const rect = imgElement.getBoundingClientRect();
        relativeX = ((event.clientX - rect.left) / rect.width) * 100;
        relativeY = ((event.clientY - rect.top) / rect.height) * 100;

        document.getElementById('comentarioTexto').value = '';
        document.getElementById('imagemComentario').value = '';
        document.getElementById('comentarioModal').style.display = 'flex';

        // Limpa os mencionados quando abre um novo comentário
        mencionadosIds = [];
    });
}

// Capturar colagem de imagem no campo de texto
document.getElementById('comentarioTexto').addEventListener('paste', function (event) {
    const items = (event.clipboardData || event.originalEvent.clipboardData).items;

    for (let index in items) {
        const item = items[index];
        if (item.kind === 'file') {
            const blob = item.getAsFile();
            if (blob && blob.type.startsWith('image/')) {
                const fileInput = document.getElementById('imagemComentario');

                // Cria um objeto DataTransfer para injetar o arquivo no input
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(new File([blob], 'imagem_colada.png', { type: blob.type }));

                fileInput.files = dataTransfer.files;

                Toastify({
                    text: 'Imagem colada com sucesso!',
                    duration: 3000,
                    backgroundColor: 'linear-gradient(to right, #00b09b, #96c93d)',
                    close: true,
                    gravity: "top",
                    position: "right"
                }).showToast();
            }
        }
    }
});

// Função para enviar o comentário
document.getElementById('enviarComentario').onclick = async () => {
    const texto = document.getElementById('comentarioTexto').value.trim();
    const imagemFile = document.getElementById('imagemComentario').files[0];

    if (!texto && !imagemFile) {
        Toastify({
            text: 'Escreva um comentário ou anexe uma imagem!',
            duration: 3000,
            backgroundColor: 'orange',
            close: true,
            gravity: "top",
            position: "right"
        }).showToast();
        return;
    }

    const formData = new FormData();
    formData.append('ap_imagem_id', ap_imagem_id);
    formData.append('x', relativeX);
    formData.append('y', relativeY);
    formData.append('texto', texto);
    formData.append('mencionados', JSON.stringify(mencionadosIds));

    if (imagemFile) {
        formData.append('imagem', imagemFile);
    }

    try {
        const response = await fetch('salvar_comentario.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        document.getElementById('comentarioModal').style.display = 'none';

        if (result.sucesso) {
            Toastify({
                text: 'Comentário adicionado com sucesso!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();

            // Atualiza comentários
            renderComments(ap_imagem_id);
        } else {
            Toastify({
                text: result.mensagem || 'Erro ao salvar comentário!',
                duration: 3000,
                backgroundColor: 'red',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
        }

        // Limpa os mencionados depois do envio
        mencionadosIds = [];

    } catch (error) {
        console.error('Erro na requisição:', error);
        Toastify({
            text: 'Erro de conexão! Tente novamente.',
            duration: 3000,
            backgroundColor: 'red',
            close: true,
            gravity: "top",
            position: "left"
        }).showToast();
    }
};

function addComment(x, y) {
    const imagemCompletaDiv = document.getElementById("imagem_completa");

    // Cria o div do comentário
    const commentDiv = document.createElement('div');
    commentDiv.classList.add('comment');
    commentDiv.style.left = `${x}%`;
    commentDiv.style.top = `${y}%`;

    imagemCompletaDiv.appendChild(commentDiv);
}

const image = document.getElementById("imagem_atual");


// ---- CONFIGURAÇÃO ---------------------------------------------------------
const USERS_PERMITIDOS = [1, 2, 3, 9, 20];   // quem pode editar / excluir
// --------------------------------------------------------------------------

async function renderComments(id) {
    const comentariosDiv = document.querySelector(".comentarios");
    const imagemCompletaDiv = document.getElementById("image_wrapper");
    const response = await fetch(`buscar_comentarios.php?id=${id}`);
    const comentarios = await response.json();

    comentariosDiv.innerHTML = '';
    imagemCompletaDiv.querySelectorAll('.comment').forEach(c => c.remove());

    const users = await fetch('buscar_usuarios.php').then(res => res.json());

    const tribute = new Tribute({
        values: users.map(user => ({ key: user.nome_colaborador, value: user.nome_colaborador })),
        selectTemplate: function (item) {
            return `@${item.original.value}`;
        }
    });

    comentarios.forEach(comentario => {
        const commentCard = document.createElement('div');
        commentCard.classList.add('comment-card');
        commentCard.setAttribute('data-id', comentario.id);

        const header = document.createElement('div');
        header.classList.add('comment-header');
        header.innerHTML = `
            <div class="comment-number">${comentario.numero_comentario}</div>
            <div class="comment-user">${comentario.nome_responsavel}</div>
        `;

        const commentBody = document.createElement('div');
        commentBody.classList.add('comment-body');

        const p = document.createElement('p');
        p.classList.add('comment-input');
        p.textContent = comentario.texto;

        commentBody.appendChild(p);

        const footer = document.createElement('div');
        footer.classList.add('comment-footer');
        footer.innerHTML = `
            <div class="comment-date">${comentario.data}</div>
            <div class="comment-actions">
                <button class="comment-resp">&#8617</button>
                <button class="comment-edit">✏️</button>
                <button class="comment-delete" onclick="deleteComment(${comentario.id})">🗑️</button>
            </div>
        `;

        const respostas = document.createElement('div');
        respostas.classList.add('respostas-container');
        respostas.id = `respostas-${comentario.id}`;

        commentCard.appendChild(header);
        if (comentario.imagem) {
            const imagemDiv = document.createElement('div');
            imagemDiv.classList.add('comment-image');
            imagemDiv.innerHTML = `
                <img src="${comentario.imagem}" class="comment-img-thumb" onclick="abrirImagemModal('${comentario.imagem}')">
            `;
            commentCard.appendChild(imagemDiv);
        }
        commentCard.appendChild(commentBody);
        commentCard.appendChild(footer);
        commentCard.appendChild(respostas);

        // Permissões
        if (!USERS_PERMITIDOS.includes(idusuario)) {
            footer.querySelector('.comment-delete').style.display = 'none';
            footer.querySelector('.comment-edit').style.display = 'none';
        }

        const editButton = footer.querySelector('.comment-edit');

        editButton.addEventListener('click', () => {
            p.contentEditable = true;
            p.focus();

            const handleKeyDown = async function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();

                    const novoTexto = p.textContent.trim();

                    p.contentEditable = false;

                    updateComment(comentario.id, novoTexto);

                    // Remove o listener pra não acumular
                    p.removeEventListener('keydown', handleKeyDown);
                }
            };

            p.addEventListener('keydown', handleKeyDown);
        });

        const commentDiv = document.createElement('div');
        commentDiv.classList.add('comment');
        commentDiv.setAttribute('data-id', comentario.id);
        commentDiv.innerText = comentario.numero_comentario;
        commentDiv.style.left = `${comentario.x}%`;
        commentDiv.style.top = `${comentario.y}%`;

        commentDiv.addEventListener('click', () => {
            document.querySelectorAll('.comment-number').forEach(n => n.classList.remove('highlight'));
            const number = document.querySelector(`.comment-card[data-id="${comentario.id}"] .comment-number`);
            if (number) {
                number.classList.add('highlight');
                number.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        commentCard.addEventListener('click', () => {
            // Remove o highlight de todas as bolinhas
            document.querySelectorAll('.comment.highlight').forEach(n => n.classList.remove('highlight'));

            // Pega a bolinha correspondente ao comentário
            const number = document.querySelector(`.comment[data-id="${comentario.id}"]`);

            if (number) {
                number.classList.add('highlight');
            }
        });


        const respButton = commentCard.querySelector('.comment-resp');

        respButton.addEventListener('click', async () => {
            const textoResposta = prompt("Digite sua resposta:");
            if (textoResposta && textoResposta.trim() !== '') {
                const respostaSalva = await salvarResposta(comentario.id, textoResposta);
                if (respostaSalva) {
                    adicionarRespostaDOM(comentario.id, respostaSalva);

                    const mencoes = textoResposta.match(/@(\w+)/g);
                    if (mencoes) {
                        for (const mencao of mencoes) {
                            const nome = mencao.replace('@', '');
                            const colaborador = users.find(u => u.nome_colaborador === nome);
                            if (colaborador) {
                                await fetch('registrar_mencao.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        comentario_id: comentario.id,
                                        mencionado_id: colaborador.idcolaborador
                                    })
                                });
                            }
                        }
                    }
                }
            }
        });

        imagemCompletaDiv.appendChild(commentDiv);
        comentariosDiv.appendChild(commentCard);

        if (comentario.respostas && comentario.respostas.length > 0) {
            comentario.respostas.forEach(resposta => {
                adicionarRespostaDOM(comentario.id, resposta);
            });
        }
    });
}

// Função para enviar resposta pro backend
async function salvarResposta(comentarioId, texto) {
    const response = await fetch('responder_comentario.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            comentario_id: comentarioId,
            texto: texto
        })
    });
    return await response.json();
}

// Função pra adicionar resposta no DOM
function adicionarRespostaDOM(comentarioId, resposta) {
    const container = document.getElementById(`respostas-${comentarioId}`);
    const respostaDiv = document.createElement('div');
    respostaDiv.classList.add('resposta');
    respostaDiv.innerHTML = `
        <div class="resposta-nome"><span class="reply-icon">&#8617;</span>  ${resposta.nome_responsavel}</div>
        <div class="corpo-resposta">
            <div class="resposta-texto">${resposta.texto}</div>
            <div class="resposta-data">${resposta.data}</div>
        </div>
    `;
    container.appendChild(respostaDiv);
}

// Função para atualizar o comentário no banco de dados
async function updateComment(commentId, novoTexto) {
    try {
        const response = await fetch('atualizar_comentario.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: commentId, texto: novoTexto })
        });

        const result = await response.json();
        if (result.sucesso) {
            Toastify({
                text: 'Comentário atualizado com sucesso!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
        } else {
            Toastify({
                text: 'Erro ao atualizar comentário!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
        }
    } catch (error) {
        console.error('Erro ao atualizar comentário:', error);
        alert('Ocorreu um erro ao tentar atualizar o comentário.');
    }
}

// Função para excluir o comentário do banco de dados
async function deleteComment(commentId) {
    try {
        const response = await fetch('excluir_comentario.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: commentId })
        });

        const result = await response.json();
        if (result.sucesso) {
            Toastify({
                text: 'Comentário excluído com sucesso!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
            renderComments(ap_imagem_id); // Atualiza a lista de comentários
        } else {
            Toastify({
                text: 'Erro ao excluir comentário!',
                duration: 3000,
                backgroundColor: 'green',
                close: true,
                gravity: "top",
                position: "left"
            }).showToast();
        }
    } catch (error) {
        console.error('Erro ao excluir comentário:', error);
        alert('Ocorreu um erro ao tentar excluir o comentário.');
    }
}

function abrirImagemModal(src) {
    const modal = document.getElementById('modal-imagem');
    const imagem = document.getElementById('imagem-ampliada');
    imagem.src = src;
    modal.style.display = 'flex';
}

function fecharImagemModal() {
    const modal = document.getElementById('modal-imagem');
    modal.style.display = 'none';
}



const btnBack = document.getElementById('btnBack');
btnBack.addEventListener('click', function () {
    const main = document.querySelector('.main');
    main.classList.remove('hidden');

    const container_aprovacao = document.querySelector('.container-aprovacao');
    container_aprovacao.classList.add('hidden');

    const imagemWrapperDiv = document.querySelector(".image_wrapper");
    imagemWrapperDiv.innerHTML = '';

    const comentariosDiv = document.querySelector(".comentarios");
    comentariosDiv.innerHTML = '';
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        const comentarioModal = document.getElementById("comentarioModal");

        if (comentarioModal.style.display === 'flex') {
            comentarioModal.style.display = 'none';
            return; // Interrompe aqui se o modal estava visível
        }

        const main = document.querySelector('.main');
        main.classList.remove('hidden');

        const container_aprovacao = document.querySelector('.container-aprovacao');
        container_aprovacao.classList.add('hidden');

        const imagemWrapperDiv = document.querySelector(".image_wrapper");
        imagemWrapperDiv.innerHTML = '';

        const comentariosDiv = document.querySelector(".comentarios");
        comentariosDiv.innerHTML = '';
    }
});


const id_revisao = document.getElementById('id_revisao');

// function addObservacao(id) {
//     const modal = document.getElementById('historico_modal');
//     const idRevisao = document.getElementById('id_revisao');
//     const historicoAdd = modal.querySelector('.historico-add');

//     historicoAdd.classList.toggle('hidden');

//     if (historicoAdd.classList.contains('hidden')) {
//         modal.classList.remove('complete');
//     } else {
//         modal.classList.add('complete');
//     }

//     idRevisao.innerText = `${id}`;
// }

// Inicializa o editor Quill
// var quill = new Quill('#text_obs', {
//     theme: 'snow',  // Tema claro
//     modules: {
//         toolbar: [
//             ['bold', 'italic', 'underline'], // Negrito, itálico, sublinhado
//             [{ 'header': 1 }, { 'header': 2 }], // Títulos
//             [{ 'list': 'ordered' }, { 'list': 'bullet' }], // Listas
//             [{ 'color': [] }, { 'background': [] }], // Cores
//             ['clean'] // Limpar formatação
//         ]
//     }
// });


// const historico_modal = document.getElementById('historico_modal');
// const historicoAdd = historico_modal.querySelector('.historico-add');

// window.addEventListener('click', function (event) {
//     if (event.target == historico_modal) {
//         historico_modal.style.display = "none"
//         historico_modal.classList.remove('complete');
//         historicoAdd.classList.add('hidden');
//     }
// });

// window.addEventListener('touchstart', function (event) {
//     if (event.target == historico_modal) {
//         historico_modal.style.display = "none"
//         historico_modal.classList.remove('complete');
//         historicoAdd.classList.add('hidden');

//     }
// });


// Captura o evento de envio do formulário
// document.getElementById('adicionar_obs').addEventListener('submit', function (event) {
//     event.preventDefault(); // Previne o comportamento padrão do envio do formulário

//     // Exibe um prompt para o usuário digitar o número da revisão
//     const numeroRevisao = document.getElementById('id_revisao').textContent;
//     const idfuncao_imagem = document.getElementById("id_funcao").value;

//     if (numeroRevisao) {
//         // Captura o conteúdo do editor Quill
//         const observacao = quill.root.innerHTML;

//         // Exibe os valores no console (você pode remover esta parte depois)
//         console.log("Número da Revisão: " + numeroRevisao);
//         console.log("Observação: " + observacao);

//         // Envia os dados para o servidor via fetch
//         fetch('atualizar_historico.php', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json'
//             },
//             body: JSON.stringify({
//                 revisao: numeroRevisao,
//                 observacao: observacao
//             })
//         })
//             .then(response => response.json())
//             .then(data => {
//                 // Verifica se a atualização foi bem-sucedida
//                 if (data.success) {
//                     Toastify({
//                         text: 'Observação adicionada com sucesso!',
//                         duration: 3000,
//                         backgroundColor: 'green',
//                         close: true,
//                         gravity: "top",
//                         position: "right"
//                     }).showToast();

//                     historico_modal.classList.remove('complete');
//                     historicoAdd.classList.toggle('hidden');
//                     historyAJAX(idfuncao_imagem)
//                 } else {
//                     Toastify({
//                         text: "Falha ao atualizar a tarefa: " + data.message,
//                         duration: 3000,
//                         backgroundColor: "red",
//                         close: true,
//                         gravity: "top",
//                         position: "right"
//                     }).showToast();
//                 }
//             })
//             .catch(error => {
//                 console.error("Erro ao enviar dados para o servidor:", error);
//                 alert("Ocorreu um erro ao tentar adicionar a observação.");
//             });
//     } else {
//         alert("Número de revisão é obrigatório!");
//     }
// });

