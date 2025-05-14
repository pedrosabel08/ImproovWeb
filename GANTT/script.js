
const etapaParaFuncao = {
    "Caderno": 1,
    "Modelagem": 2,
    "Composição": 3,
    "Finalização": 4,
    "Pós-Produção": 5,
    "Alteração": 6,
    "Planta Humanizada": 7,
    "Filtro de assets": 8
};

function atualizarTabela() {
    const obraId = localStorage.getItem('obraId'); // ou o nome que você usou no localStorage

    fetch(`tabela.php?id_obra=${obraId}`)
        .then(response => response.json())
        .then(data => {
            const { imagens, etapas, primeiraData, ultimaData, obra } = data;

            document.getElementById('nomenclatura').textContent = obra.nomenclatura;

            // Lista de feriados fixos
            const feriadosFixos = [
                '01/01', '21/04', '01/05', '07/09', '12/10',
                '11/02', '15/11', '25/12', '31/12',
            ];

            const anoAtual = new Date().getFullYear();
            const feriadosMoveis = calcularFeriadosMoveis(anoAtual);

            const feriados = [
                ...feriadosFixos,
                feriadosMoveis.pascoa,
                feriadosMoveis.sextaFeiraSanta,
                feriadosMoveis.corpusChristi,
                feriadosMoveis.carnaval,
                feriadosMoveis.segundaCarnaval
            ].map(d => d instanceof Date ? d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) : d);

            const datas = [];
            const startDate = new Date(primeiraData);
            startDate.setDate(startDate.getDate() + 1);
            const endDate = new Date(ultimaData);
            endDate.setDate(endDate.getDate() + 2);
            while (startDate <= endDate) {
                datas.push(new Date(startDate));
                startDate.setDate(startDate.getDate() + 1);
            }

            const table = document.getElementById('gantt');
            table.innerHTML = ''; // Limpar conteúdo anterior

            const thead = document.createElement('thead');
            const monthRow = document.createElement('tr'); // Linha dos meses
            const dayRow = document.createElement('tr');   // Linha dos dias

            // Primeira célula vazia para alinhar com "Tipo de Imagem"
            const monthHeader = document.createElement('th');
            monthHeader.textContent = '';
            monthHeader.rowSpan = 2; // Ocupa as duas linhas do cabeçalho
            monthRow.appendChild(monthHeader);

            let currentMonth = '';
            let currentMonthStartIndex = 0;

            datas.forEach((data, index) => {
                const mes = data.toLocaleDateString('pt-BR', { month: 'long' });

                // Se for o primeiro ou mudou o mês
                if (index === 0 || mes !== currentMonth) {
                    if (index !== 0) {
                        const monthCell = document.createElement('th');
                        monthCell.textContent = currentMonth.charAt(0).toUpperCase() + currentMonth.slice(1);
                        monthCell.colSpan = index - currentMonthStartIndex;
                        monthRow.appendChild(monthCell);
                        currentMonthStartIndex = index;
                    }
                    currentMonth = mes;
                }

                const dateCell = document.createElement('th');
                const formattedDate = data.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                dateCell.textContent = formattedDate;
                // Armazena a data completa no atributo
                const formattedDateAtt = data.toLocaleDateString('pt-BR'); // dd/mm/yyyy
                dateCell.setAttribute('data-date', formattedDateAtt);

                // Mostra apenas o dia no conteúdo visível
                dateCell.textContent = data.getDate(); // Apenas o número do dia

                // Verifica se é fim de semana
                const dayOfWeek = data.getDay();
                if (dayOfWeek === 0 || dayOfWeek === 6) {
                    dateCell.style.backgroundColor = '#ff0500';
                }

                // Verifica se é feriado com base no atributo completo
                const formattedHoliday = formattedDateAtt.slice(0, 5); // dd/mm
                if (feriados.includes(formattedHoliday)) {
                    dateCell.style.backgroundColor = '#00ff3d';
                }

                const hoje = new Date(); // Data atual
                if (data.getDate() === hoje.getDate() && data.getMonth() === hoje.getMonth() && data.getFullYear() === hoje.getFullYear()) {
                    dateCell.style.backgroundColor = '#ffff30'; // Cor de fundo para o dia atual
                    dateCell.style.fontWeight = 'bold'; // Para destacar mais
                }

                let isSelecting = false;
                let startCell = null;
                let endCell = null;

                // Para guardar a referência das células selecionadas
                const selectedDayCells = [];

                dateCell.addEventListener('mousedown', (e) => {
                    isSelecting = true;
                    startCell = dateCell;
                    selectedDayCells.length = 0;
                    selectedDayCells.push(dateCell);
                    dateCell.classList.add('selecionado');
                });

                dayRow.addEventListener('mousemove', (e) => {
                    if (!isSelecting || !startCell) return;

                    const target = e.target;
                    if (target.tagName !== 'TH' || !target.hasAttribute('data-date')) return;

                    // Evita refazer seleção se o target for o mesmo
                    if (selectedDayCells.includes(target)) return;

                    // Limpa seleção anterior
                    selectedDayCells.forEach(cell => cell.classList.remove('selecionado'));
                    selectedDayCells.length = 0;

                    const allDayCells = Array.from(dayRow.children);
                    const startIndex = allDayCells.indexOf(startCell);
                    const currentIndex = allDayCells.indexOf(target);

                    const [from, to] = startIndex < currentIndex
                        ? [startIndex, currentIndex]
                        : [currentIndex, startIndex];

                    for (let i = from; i <= to; i++) {
                        allDayCells[i].classList.add('selecionado');
                        selectedDayCells.push(allDayCells[i]);
                    }
                });

                document.addEventListener('mouseup', () => {
                    if (isSelecting) {
                        isSelecting = false;

                        // Se não teve mouseenter suficiente, garante ao menos a célula de início
                        if (selectedDayCells.length === 0 && startCell) {
                            selectedDayCells.push(startCell);
                            startCell.classList.add('selecionado');
                        }

                        endCell = selectedDayCells[selectedDayCells.length - 1];

                        const dataInicio = selectedDayCells[0].getAttribute('data-date');
                        const dataFim = endCell.getAttribute('data-date');

                        abrirModalEtapaCoringa(dataInicio, dataFim);

                        // Limpa seleção visual
                        selectedDayCells.forEach(cell => cell.classList.remove('selecionado'));
                        selectedDayCells.length = 0;
                        startCell = null;
                        endCell = null;
                    }
                });


                dayRow.appendChild(dateCell);
            });

            // Adiciona o último mês
            if (currentMonth) {
                const monthCell = document.createElement('th');
                monthCell.textContent = currentMonth.charAt(0).toUpperCase() + currentMonth.slice(1);
                monthCell.colSpan = datas.length - currentMonthStartIndex;
                monthRow.appendChild(monthCell);
            }

            thead.appendChild(monthRow);
            thead.appendChild(dayRow);
            table.appendChild(thead);

            const tbody = document.createElement('tbody');

            // Corpo da tabela
            Object.keys(imagens).forEach(tipoImagem => {
                const nomesImagens = imagens[tipoImagem];
                const rowSpan = nomesImagens.length;

                let firstRow = true;
                nomesImagens.forEach(imagemNome => {
                    const row = document.createElement('tr');

                    if (firstRow) {
                        const tipoCell = document.createElement('td');
                        tipoCell.textContent = tipoImagem;
                        tipoCell.setAttribute('rowspan', rowSpan);
                        row.appendChild(tipoCell);
                        tipoCell.style.writingMode = 'sideways-lr';
                    }

                    // Adicionar as etapas, se existirem
                    if (etapas[tipoImagem] && firstRow) {
                        const etapasTipo = etapas[tipoImagem];

                        if (etapasTipo.length > 0) {
                            const primeiraEtapa = etapasTipo[0];
                            const dataInicioPrimeiraEtapa = new Date(primeiraEtapa.data_inicio);
                            const indexInicioEtapa = datas.findIndex(d => d.getTime() === dataInicioPrimeiraEtapa.getTime());

                            if (indexInicioEtapa > 0) {
                                const emptyBefore = document.createElement('td');
                                emptyBefore.setAttribute('colspan', indexInicioEtapa + 1);
                                emptyBefore.setAttribute('rowspan', rowSpan);
                                row.appendChild(emptyBefore);
                            }

                            etapasTipo.forEach(etapa => {
                                const dataInicio = new Date(etapa.data_inicio);
                                const dataFim = new Date(etapa.data_fim);

                                const indexInicio = datas.findIndex(d => d.getTime() === dataInicio.getTime());
                                const indexFim = datas.findIndex(d => d.getTime() === dataFim.getTime());

                                const colspan = indexFim - indexInicio + 1;

                                const etapaCell = document.createElement('td');
                                etapaCell.setAttribute('colspan', colspan);
                                etapaCell.setAttribute('rowspan', rowSpan);
                                etapaCell.setAttribute('data-inicio', etapa.data_inicio);
                                etapaCell.setAttribute('data-fim', etapa.data_fim);
                                etapaCell.className = etapa.etapa
                                    .toLowerCase()
                                    .normalize('NFD')
                                    .replace(/[\u0300-\u036f]/g, '')
                                    .replace(/\s/g, '')
                                    .replace(/[^a-z0-9]/g, '');

                                // Inclui o nome do colaborador, se existir
                                if (etapa.nome_colaborador) {
                                    etapaCell.textContent = `${etapa.etapa} - ${etapa.nome_colaborador}`;
                                } else {
                                    etapaCell.textContent = etapa.etapa;
                                }

                                etapaCell.contentEditable = false;
                                const tooltip = document.getElementById('tooltip');

                                etapaCell.addEventListener('mouseenter', (event) => {
                                    if (etapa.porcentagem_conclusao != null) {
                                        tooltip.textContent = `${etapa.porcentagem_conclusao}%`;
                                    }
                                    tooltip.style.display = 'block';
                                    tooltip.style.left = event.clientX + 'px';
                                    tooltip.style.top = event.clientY - 30 + 'px';
                                });

                                etapaCell.addEventListener('mouseleave', () => {
                                    tooltip.style.display = 'none';
                                });

                                etapaCell.addEventListener('mousemove', (event) => {
                                    tooltip.style.left = event.clientX + 'px';
                                    tooltip.style.top = event.clientY - 30 + 'px';
                                });

                                etapaCell.oncontextmenu = async (event) => {
                                    const btnAtribuir = document.getElementById("confirmarBtn");
                                    btnAtribuir.style.display = "block";
                                    event.preventDefault();

                                    etapaAtual = etapa;

                                    const colaboradorAtualId = etapa.colaborador_id;
                                    const nomeEtapa = etapa.etapa;
                                    const tipoImagem = etapa.tipo_imagem; // <== Certifique-se de que `etapa` tem isso

                                    const funcaoId = etapaParaFuncao[nomeEtapa];
                                    const dataInicio = etapaCell.getAttribute('data-inicio');
                                    const dataFim = etapaCell.getAttribute('data-fim');

                                    if (!funcaoId) {
                                        console.warn(`Função não encontrada para a etapa: ${nomeEtapa}`);
                                        return;
                                    }

                                    const isModalSimples = tipoImagem === "Fachada" &&
                                        (nomeEtapa === "Modelagem" || nomeEtapa === "Pós-Produção");

                                    const modalSimples = document.getElementById("colaboradorModal");
                                    const modalAvancado = document.getElementById("modalAvancado");

                                    if (isModalSimples) {
                                        preencherSelectComColaboradores({
                                            selectId: "colaborador_id",
                                            funcaoId,
                                            dataInicio,
                                            dataFim,
                                            colaboradorAtualId,
                                            onConflitoSelecionado: (selected) => {
                                                const nome = selected.textContent;
                                                const obra = selected.dataset.obra;
                                                const etapa = selected.dataset.etapa;
                                                const inicio = formatarData(selected.dataset.inicio);
                                                const fim = formatarData(selected.dataset.fim);
                                                const etapaId = selected.dataset.ganttId;

                                                abrirModalConflito({
                                                    colaboradorId: selected.value,
                                                    nome,
                                                    obra,
                                                    etapa,
                                                    inicio,
                                                    fim,
                                                    etapaId
                                                });
                                            }
                                        });

                                        modalSimples.style.display = "block";
                                        modalAvancado.style.display = "none";

                                    } else {
                                        // Carregar imagens via fetch
                                        const response = await fetch(`buscar_imagens.php?tipo_imagem=${encodeURIComponent(tipoImagem)}&obra_id=${obraId}&funcao_id=${funcaoId}`);
                                        const imagens = await response.json();

                                        const imagensContainer = document.getElementById("listaImagens");
                                        imagensContainer.innerHTML = ""; // Limpa conteúdo anterior


                                        const coresColaboradores = new Map();

                                        function gerarCorAleatoria() {
                                            const letras = "0123456789ABCDEF";
                                            let cor = "#";
                                            for (let i = 0; i < 6; i++) {
                                                cor += letras[Math.floor(Math.random() * 16)];
                                            }
                                            return cor;
                                        }

                                        function obterCorColaborador(colaboradorId) {
                                            if (!coresColaboradores.has(colaboradorId)) {
                                                coresColaboradores.set(colaboradorId, gerarCorAleatoria());
                                            }
                                            return coresColaboradores.get(colaboradorId);
                                        }

                                        const imagensPorColaborador = new Map();

                                        // Criar imagens com dropzones
                                        imagens.forEach(img => {
                                            const imgDiv = document.createElement("div");
                                            imgDiv.dataset.imagemId = img.idimagens_cliente_obra;
                                            imgDiv.style.padding = "10px";
                                            imgDiv.style.border = "1px solid #ccc";
                                            imgDiv.style.marginBottom = "5px";
                                            imgDiv.style.transition = "background-color 0.3s";

                                            const nome = document.createElement("p");
                                            nome.textContent = img.imagem_nome;

                                            // Se já tiver colaborador atribuído, aplica a cor
                                            if (img.colaborador_id) {
                                                const cor = obterCorColaborador(img.colaborador_id);
                                                nome.style.backgroundColor = cor;

                                                if (!imagensPorColaborador.has(img.colaborador_id)) {
                                                    imagensPorColaborador.set(img.colaborador_id, []);
                                                }

                                                imagensPorColaborador.get(img.colaborador_id).push(cor);
                                            }

                                            imgDiv.appendChild(nome);
                                            imagensContainer.appendChild(imgDiv);

                                            // Dropzone
                                            imgDiv.addEventListener("dragover", (e) => e.preventDefault());
                                            imgDiv.addEventListener("drop", async (e) => {
                                                e.preventDefault();
                                                const colaboradorId = e.dataTransfer.getData("text/plain");
                                                const imagemId = imgDiv.dataset.imagemId;

                                                await fetch("atribuir_colab.php", {
                                                    method: "POST",
                                                    headers: { "Content-Type": "application/json" },
                                                    body: JSON.stringify({ colaborador_id: colaboradorId, imagem_id: imagemId, funcao_id: funcaoId })
                                                });

                                                const cor = obterCorColaborador(colaboradorId);
                                                nome.style.backgroundColor = cor;

                                                alert("Colaborador atribuído com sucesso!");
                                                console.log(`Colab: ${colaboradorId}, imagem: ${imagemId}, funcao: ${funcaoId}`)
                                            });
                                        });

                                        preencherColaboradoresArrastaveis({
                                            funcaoId,
                                            dataInicio,
                                            dataFim,
                                            colaboradorAtualId,
                                            imagensPorColaborador
                                        });



                                        modalAvancado.style.display = "block";
                                        modalSimples.style.display = "none";
                                    }

                                    // Posicionar modal (pode aplicar em ambos)
                                    const rect = event.target.getBoundingClientRect();
                                    const modal = isModalSimples ? modalSimples : modalAvancado;
                                    const isRightSpace = rect.right + modal.offsetWidth < window.innerWidth;

                                    modal.style.position = "absolute";
                                    modal.style.left = isRightSpace
                                        ? `${rect.right + 10}px`
                                        : `${rect.left - modal.offsetWidth - 10}px`;
                                    modal.style.top = `${rect.top + window.scrollY}px`;
                                    const modalConflito = document.getElementById("modalConflito");
                                    modalConflito.style.display = 'none';
                                };

                                // Implementação do arrasto horizontal
                                let isDragging = false;
                                let startX = 0;

                                etapaCell.onmousedown = (e) => {
                                    isDragging = true;
                                    startX = e.clientX;
                                    document.body.style.cursor = 'ew-resize';
                                    etapaCell.classList.add('arrastando');

                                    document.onmousemove = (eMove) => {
                                        if (!isDragging) return;

                                        const diffX = eMove.clientX - startX;
                                        etapaCell.style.transform = `translateX(${diffX}px)`;
                                    };

                                    document.onmouseup = (eUp) => {
                                        if (!isDragging) return;

                                        const diffX = eUp.clientX - startX;
                                        const cellWidth = etapaCell.offsetWidth / etapaCell.colSpan;
                                        const daysMoved = Math.round(diffX / cellWidth);

                                        // Reset visual
                                        etapaCell.style.transform = 'translateX(0)';
                                        etapaCell.classList.remove('arrastando');
                                        document.body.style.cursor = 'default';
                                        document.onmousemove = null;
                                        isDragging = false;

                                        if (daysMoved !== 0) {
                                            etapas[tipoImagem].forEach(et => {
                                                et.data_inicio = novaData(et.data_inicio, daysMoved);
                                                et.data_fim = novaData(et.data_fim, daysMoved);
                                            });

                                            console.log(`Tipo Imagem: ${tipoImagem}`);
                                            etapas[tipoImagem].forEach(et => {
                                                console.log(`Etapa: ${et.etapa}, Início: ${et.data_inicio}, Fim: ${et.data_fim}`);
                                            });

                                            // Envia as novas datas para o back-end
                                            fetch('atualizar_datas.php', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json'
                                                },
                                                body: JSON.stringify({
                                                    tipoImagem: tipoImagem,
                                                    etapas: etapas[tipoImagem]
                                                })
                                            })
                                                .then(response => response.json())
                                                .then(data => {
                                                    if (data.success) {
                                                        console.log('Datas atualizadas com sucesso no banco.');
                                                        atualizarTabela();
                                                    } else {
                                                        console.error('Erro ao atualizar no banco:', data.message);
                                                    }
                                                })
                                                .catch(error => {
                                                    console.error('Erro na requisição:', error);
                                                });
                                        }
                                    };
                                };


                                row.appendChild(etapaCell);
                            });
                        }
                    }

                    if (firstRow) {
                        const diasUsados = etapas[tipoImagem]?.reduce((total, etapa) => {
                            const dataInicio = new Date(etapa.data_inicio);
                            const dataFim = new Date(etapa.data_fim);
                            const indexInicio = datas.findIndex(d => d.getTime() === dataInicio.getTime());
                            const indexFim = datas.findIndex(d => d.getTime() === dataFim.getTime());
                            return total + (indexFim - indexInicio + 1);
                        }, 0) || 0;

                        const diasRestantes = datas.length - diasUsados;
                        if (diasRestantes > 0) {
                            const emptyCell = document.createElement('td');
                            emptyCell.setAttribute('colspan', diasRestantes);
                            emptyCell.setAttribute('rowspan', rowSpan);
                            row.appendChild(emptyCell);
                        }
                    }

                    tbody.appendChild(row);
                    firstRow = false;
                });
            });

            table.appendChild(tbody);

        })
        .catch(error => console.error('Erro ao carregar os dados:', error));
}

let dataInicioGlobal = '';
let dataFimGlobal = '';
let obraSelecionadaId = localStorage.getItem('obraId'); // ou o nome que você usou no localStorage

async function preencherColaboradoresArrastaveis({ funcaoId, dataInicio, dataFim, colaboradorAtualId, imagensPorColaborador }) {
    const response = await fetch(`get_colaboradores_por_funcao.php?funcao_id=${funcaoId}&data_inicio=${dataInicio}&data_fim=${dataFim}`);
    const colaboradores = await response.json();

    const container = document.getElementById("colaboradoresArrastaveis");
    container.innerHTML = "";

    colaboradores.forEach(colab => {
        const div = document.createElement("div");
        div.classList.add("colaborador-draggable");
        div.draggable = true;
        div.dataset.id = colab.idcolaborador;
        div.textContent = colab.nome_colaborador;
        div.style.border = "1px solid #888";
        div.style.margin = "5px 0";
        div.style.padding = "5px";
        div.style.cursor = "grab";

        // Definir o background da div com a cor da imagem atribuída (se houver)
        if (imagensPorColaborador.has(colab.idcolaborador)) {
            const cores = imagensPorColaborador.get(colab.idcolaborador);
            if (cores.length > 0) {
                div.style.backgroundColor = cores[0]; // Usa a primeira cor
                div.style.color = "#fff"; // Opcional: melhora a leitura se fundo for escuro
            }
        }

        div.addEventListener("dragstart", (e) => {
            e.dataTransfer.setData("text/plain", div.dataset.id);
        });

        container.appendChild(div);
    });
}



function abrirModalEtapaCoringa(dataInicio, dataFim) {
    dataInicioGlobal = dataInicio;
    dataFimGlobal = dataFim;
    console.log('Data Inicio' + dataInicioGlobal)
    console.log('Data Fim' + dataFimGlobal)
    document.getElementById('modalEtapa').style.display = 'block';
}

function fecharModalEtapa() {
    document.getElementById('modalEtapa').style.display = 'none';
    document.getElementById('nomeEtapa').value = '';
}

function confirmarEtapaCoringa() {
    const nomeEtapa = document.getElementById('nomeEtapa').value.trim();
    if (!nomeEtapa) {
        alert('Digite o nome da etapa.');
        return;
    }

    fetch('inserir_etapa_coringa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            etapa: nomeEtapa,
            data_inicio: dataInicioGlobal,
            data_fim: dataFimGlobal,
            obra_id: obraSelecionadaId // supondo que você tenha a obra selecionada em algum lugar
        })
    })
        .then(resp => resp.json())
        .then(data => {
            if (data.success) {
                alert('Etapa coringa adicionada com sucesso!');
                atualizarTabela(); // Atualiza o Gantt
            } else {
                alert('Erro: ' + data.message);
            }
            fecharModalEtapa();
        })
        .catch(err => {
            console.error(err);
            alert('Erro ao inserir etapa.');
            fecharModalEtapa();
        });
}


function preencherSelectComColaboradores({
    selectId,
    funcaoId,
    dataInicio,
    dataFim,
    colaboradorAtualId = null,
    onConflitoSelecionado = null
}) {
    const select = document.getElementById(selectId);
    select.innerHTML = "";

    const defaultOption = document.createElement("option");
    defaultOption.value = "";
    defaultOption.textContent = "Selecione";
    defaultOption.disabled = true;
    defaultOption.selected = true;
    select.appendChild(defaultOption);

    if (!funcaoId) {
        console.warn(`Função ID inválido: ${funcaoId}`);
        return;
    }

    fetch(`get_colaboradores_por_funcao.php?funcao_id=${funcaoId}&data_inicio=${dataInicio}&data_fim=${dataFim}`)
        .then(res => res.json())
        .then(colaboradores => {
            let colaboradorSelecionado = null;

            colaboradores.forEach(colab => {
                const option = document.createElement("option");
                option.value = colab.idcolaborador;
                option.textContent = colab.nome_colaborador + (colab.ocupado ? ` (${colab.obra_conflitante})` : "");

                if (colab.ocupado) {
                    option.style.color = "red";
                    option.dataset.ocupado = true;
                    option.dataset.obra = colab.obra_conflitante;
                    option.dataset.etapa = colab.etapa_conflitante;
                    option.dataset.inicio = colab.data_inicio_conflito;
                    option.dataset.fim = colab.data_fim_conflito;
                    option.dataset.ganttId = colab.gantt_id;
                }

                if (colab.idcolaborador == colaboradorAtualId) {
                    option.selected = true;
                    colaboradorSelecionado = option;
                }

                select.appendChild(option);
            });

            // Abre o modal automaticamente se o colaborador selecionado estiver em conflito
            if (colaboradorSelecionado && colaboradorSelecionado.dataset.ocupado && typeof onConflitoSelecionado === 'function') {
                onConflitoSelecionado(colaboradorSelecionado);
            }

            // Adiciona onchange se quiser tratar conflito após seleção manual
            select.onchange = function () {
                const selected = select.options[select.selectedIndex];
                if (selected.dataset.ocupado && typeof onConflitoSelecionado === 'function') {
                    onConflitoSelecionado(selected);
                }
            };
        });
}



function formatarData(data) {
    const partes = data.split("-");
    const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
    return dataFormatada;
}

document.getElementById('opcao_obra').addEventListener('change', (e) => {
    localStorage.setItem('obraId', e.target.value); // armazena novo valor
    atualizarTabela();
});

let colaboradorIdAtual = null;
let etapaIdAtual = null;
let nomeEtapaAtual = null;
let dataInicioAtual = null;
let dataFimAtual = null;

function abrirModalConflito({ colaboradorId, nome, obra, etapa, inicio, fim, etapaId }) {
    colaboradorIdAtual = colaboradorId;
    etapaIdAtual = etapaId;
    nomeEtapaAtual = etapa;
    dataInicioAtual = inicio;
    dataFimAtual = fim;


    const modal = document.getElementById("modalConflito");
    const texto = document.getElementById("textoConflito");

    const divTrocar = document.querySelector(".trocar");

    const btnAtribuir = document.getElementById("confirmarBtn");
    btnAtribuir.style.display = "none";

    // reset visual
    document.getElementById("btnTrocar").classList.remove("active");
    divTrocar.style.display = "none";
    document.getElementById("btnVoltar").style.display = "none";

    texto.innerHTML = `
        <p><strong>${nome}</strong> já está na obra <strong>${obra}</strong>, etapa <strong>${etapa}</strong>, de <strong>${inicio}</strong> a <strong>${fim}</strong>.</p>
        <p>O que deseja fazer?</p>
    `;

    modal.style.display = "block";
}

// botão "Trocar"
document.getElementById("btnTrocar").onclick = () => {
    document.getElementById("btnTrocar").classList.add("active");
    document.querySelector(".trocar").style.display = "flex";
    document.getElementById("btnVoltar").style.display = "inline-block";
    document.getElementById("btnRemoverEAlocar").classList.remove("active");

    // Pegando dados da etapa atual
    const nomeEtapa = nomeEtapaAtual;
    const funcaoId = etapaParaFuncao[nomeEtapa];
    const dataInicio = dataInicioAtual;
    const dataFim = dataFimAtual;
    const colaboradorAtualId = colaboradorIdAtual;

    if (!funcaoId) {
        console.warn(`Função não encontrada para a etapa: ${nomeEtapa}`);
        return;
    }

    preencherSelectComColaboradores({
        selectId: "colaborador_id_troca", // ID do <select> de troca
        funcaoId,
        dataInicio,
        dataFim,
        colaboradorAtualId,
        onConflitoSelecionado: (selected) => {
            const nome = selected.textContent;
            const obra = selected.dataset.obra;
            const etapa = selected.dataset.etapa;
            const inicio = formatarData(selected.dataset.inicio);
            const fim = formatarData(selected.dataset.fim);
            const etapaId = selected.dataset.ganttId;

            abrirModalConflito({
                colaboradorId: selected.value,
                nome,
                obra,
                etapa,
                inicio,
                fim,
                etapaId
            });
        }
    });
};

document.getElementById("btnRemoverEAlocar").onclick = () => {
    const nomeEtapa = nomeEtapaAtual;
    const funcaoId = etapaParaFuncao[nomeEtapa];
    const dataInicio = dataInicioAtual;
    const dataFim = dataFimAtual;

    if (!funcaoId) {
        console.warn(`Função não encontrada para a etapa: ${nomeEtapa}`);
        return;
    }

    preencherSelectComColaboradores({
        selectId: "colaborador_id_troca",
        funcaoId,
        dataInicio,
        dataFim,
        colaboradorAtualId: colaboradorIdAtual,
        onConflitoSelecionado: (selected) => {
            const novoColaboradorId = selected.value;
            const novoColaboradorNome = selected.textContent;

            Swal.fire({
                title: "Confirmar alocação?",
                html: `Deseja <strong>remover</strong> o colaborador atual (ID ${colaboradorIdAtual}) e <strong>alocar</strong> <strong>${novoColaboradorNome}</strong> (ID ${novoColaboradorId}) nesta etapa?`,
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Sim, alocar",
                cancelButtonText: "Cancelar"
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('remover_e_alocar.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            antigoId: colaboradorIdAtual,
                            novoId: novoColaboradorId,
                            etapaId: etapaIdAtual
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.sucesso) {
                                Swal.fire("Sucesso", "Colaborador removido e novo alocado!", "success");
                                atualizarTabela();
                                document.getElementById("modalConflito").style.display = "none";
                            } else {
                                Swal.fire("Erro", "Não foi possível alocar o novo colaborador.", "error");
                            }
                        })
                        .catch(err => {
                            console.error("Erro no fetch:", err);
                            Swal.fire("Erro", "Erro de comunicação com o servidor.", "error");
                        });
                }
            });
        }
    });

    // Exibir visual do select
    document.getElementById("btnRemoverEAlocar").classList.add("active");
    document.querySelector(".trocar").style.display = "flex";
    document.getElementById("btnVoltar").style.display = "inline-block";
    document.getElementById("btnTrocar").classList.remove("active");

};

// botão "Trocar" dentro do bloco .trocar
document.getElementById("confirmarBtnTroca").onclick = () => {
    const novoId = document.getElementById("colaborador_id_troca").value;
    const novoNome = document.getElementById("colaborador_id_troca").options[document.getElementById("colaborador_id_troca").selectedIndex].text;

    if (!colaboradorIdAtual || !novoId) {
        Swal.fire("Erro", "Dados incompletos para a troca.", "error");
        return;
    }

    Swal.fire({
        title: "Confirmar troca?",
        html: `Deseja trocar o colaborador <strong>ID ${colaboradorIdAtual}</strong> pelo <strong>${novoNome} (ID ${novoId})</strong>?`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Sim, trocar",
        cancelButtonText: "Cancelar"
    }).then((result) => {
        if (result.isConfirmed) {
            // Aqui entra sua lógica para fazer a troca, como via AJAX ou formulário oculto
            console.log(`Trocando ${colaboradorIdAtual} por ${novoId}`);

            fetch('trocar_colaborador.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ antigoId: colaboradorIdAtual, novoId: novoId, etapaId: etapaIdAtual })
            }).then(response => response.json())
                .then(data => {
                    Swal.fire("Sucesso", "Colaborador trocado!", "success");
                });
            atualizarTabela();
            document.getElementById("modalConflito").style.display = "none";
        }
    });
};

// Função para somar dias a uma data
function novaData(dataStr, dias) {
    const data = new Date(dataStr);
    data.setDate(data.getDate() + dias);
    return data.toISOString().split('T')[0];
}


const modal = document.getElementById("colaboradorModal");
const confirmarBtn = document.getElementById("confirmarBtn");
const select = document.getElementById("colaborador_id");
let etapaAtual = null;


confirmarBtn.onclick = () => {
    const colaboradorId = select.value;
    if (colaboradorId && etapaAtual) {
        fetch('atribuir_colaborador.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                gantt_id: etapaAtual.id,
                colaborador_id: colaboradorId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Etapa atribuída com sucesso!',
                        text: data.message,
                    });
                    atualizarTabela();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro ao atribuir função.',
                        text: data.message,
                    });
                }
                modal.style.display = "none";
            })
            .catch(error => alert("Erro ao atribuir colaborador."));
    }
};

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        modal.style.display = 'none';

    }
});

window.addEventListener('click', function (event) {
    if (event.target == modal) {
        modal.style.display = 'none';
    }
});




function calcularFeriadosMoveis(ano) {
    const a = ano % 19;
    const b = Math.floor(ano / 100);
    const c = ano % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7;
    const m = Math.floor((a + 11 * h + 22 * l) / 451);
    const mes = Math.floor((h + l - 7 * m + 114) / 31) - 1;
    const dia = ((h + l - 7 * m + 114) % 31) + 1;

    const pascoa = new Date(ano, mes, dia);

    const sextaFeiraSanta = new Date(pascoa);
    sextaFeiraSanta.setDate(pascoa.getDate() - 2);

    const corpusChristi = new Date(pascoa);
    corpusChristi.setDate(pascoa.getDate() + 60);

    const carnaval = new Date(pascoa);
    carnaval.setDate(pascoa.getDate() - 47);

    const segundaCarnaval = new Date(pascoa);
    segundaCarnaval.setDate(pascoa.getDate() - 48);

    return {
        pascoa,
        sextaFeiraSanta,
        corpusChristi,
        carnaval,
        segundaCarnaval,
    };
}
