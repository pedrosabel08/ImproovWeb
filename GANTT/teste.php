<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <title>Gantt por Obra</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        th,
        td {
            border: 1px solid #ccc;
            padding: 4px;
            text-align: center;
        }

        th.month {
            background-color: #eee;
        }

        th.day {
            background-color: #f9f9f9;
            width: 30px;
        }

        td.etapas {
            text-align: left;
            white-space: nowrap;
        }

        .fim-de-semana {
            background-color: #ffdada !important;
        }

        .bar {
            height: 20px;
            margin: 2px 0;
        }

        /* Estilos específicos para cada tipo de imagem */
        .posproducao {
            background-color: #e3f2fd;
            border: none !important;
        }

        .finalizacao {
            background-color: #e8f5e9;
            border: none !important;
        }

        .modelagem {
            background-color: #fff3e0;
            border: none !important;
        }

        .caderno {
            background-color: #fce4ec;
            border: none !important;
        }

        .composicao {
            background-color: #f9ffc6;
            border: none !important;
        }

        .plantahumanizada {
            background-color: #d0edf5;
            border: none !important;
        }

        .filtrodeassets {
            background-color: #dcffec;
            border: none !important;
        }
    </style>
</head>

<body>

    <h2>Gantt - Obra: <span id="obraNome"></span></h2>

    <table id="ganttTable">
        <thead>
            <tr id="headerMeses"></tr>
            <tr id="headerDias"></tr>
        </thead>
        <tbody id="ganttBody"></tbody>
    </table>

    <div id="colaboradorModal" class="modal" style="display:none;">
        <div class="modal-content">
            <div>
                <label for="colaboradorInput">ID do Colaborador:</label>
                <select name="colaborador_id" id="colaborador_id">
                    <?php foreach ($colaboradores as $colab): ?>
                        <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                            <?= htmlspecialchars($colab['nome_colaborador']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="imagemId" id="imagemId">
                <input type="hidden" name="etapaNome" id="etapaNome">
                <input type="hidden" name="funcaoId" id="funcaoId">
            </div>
            <button id="confirmarBtn">Atribuir</button>
        </div>
    </div>

    <div id="modalConflito" class="modal"
        style="display:none; position:fixed; top:30%; left:50%; transform:translate(-50%, -30%); background:#fff; padding:20px; border:1px solid #ccc; z-index:999;">
        <div id="textoConflito"></div>
        <div style="margin-top:15px;">
            <div class="buttons">
                <button id="btnTrocar">🔁 Trocar</button>
                <button id="btnRemoverEAlocar">🚫 Remover e alocar</button>
                <button id="btnAddForcado">✅ Adicionar Forçado!</button>
                <button id="btnVoltar" style="display:none;">🔙 Voltar</button>
            </div>

            <div class="trocar" style="display: none; margin-top: 10px; align-items: center; flex-direction: column;">
                <select name="colaborador_id_troca" id="colaborador_id_troca">
                    <?php foreach ($colaboradores as $colab): ?>
                        <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                            <?= htmlspecialchars($colab['nome_colaborador']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button id="confirmarBtnTroca">Trocar</button>
            </div>
        </div>
    </div>

    <div id="modalEtapa" style="display:none; position:fixed; top:30%; left:50%; transform:translate(-50%,-50%);
     background:white; padding:20px; border:1px solid #ccc; z-index:1000;">
        <label for="nomeEtapa">Etapa Coringa:</label>
        <input type="text" id="nomeEtapa" placeholder="Nome da etapa">
        <br><br>
        <button onclick="confirmarEtapaCoringa()">Confirmar</button>
        <button onclick="fecharModalEtapa()">Cancelar</button>
    </div>

    <div id="modalConflitoData" style="display:none; position:fixed; z-index:1000; top:0; left:0; width:100%; height:100%; background-color: rgba(0,0,0,0.5);">
        <div style="background:white; padding:20px; margin:100px auto; width:80%; max-width:600px; border-radius:10px;">
            <h2>Conflito de Etapas</h2>
            <p id="periodoConflitante"></p>
            <div id="conflitosDetalhes"></div>
            <button onclick="document.getElementById('modalConflitoData').style.display='none'">Fechar</button>
            <button id="verAgendaBtn">Ver agenda</button>

            <input type="text" id="calendarioDatasDisponiveis" style="display:none;" />

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
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
                        option.textContent = colab.nome_colaborador + (colab.ocupado ? ` (${colab.obras_conflitantes})` : "");

                        if (colab.ocupado) {
                            option.style.color = "red";
                            option.dataset.ocupado = true;
                            option.dataset.obra = colab.obras_conflitantes;
                            option.dataset.etapa = colab.etapas_conflitantes;
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
                    select.onchange = function() {
                        const selected = select.options[select.selectedIndex];
                        if (selected.dataset.ocupado && typeof onConflitoSelecionado === 'function') {
                            onConflitoSelecionado(selected);
                        }
                    };
                });
        }

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
        // Função para gerar array de datas do período
        function gerarDatas(primeiraData, ultimaData) {
            const datas = [];
            let atual = new Date(primeiraData);
            let fim = new Date(ultimaData);

            // Garantir que ambas estão com hora zero
            atual.setHours(0, 0, 0, 0);
            fim.setHours(0, 0, 0, 0);

            while (atual <= fim) {
                datas.push(new Date(atual)); // nova instância da data
                atual.setDate(atual.getDate() + 1);
            }

            return datas;
        }
        // Função para formatar mês "Abr 2025"
        function formatarMes(data) {
            return data.toLocaleDateString('pt-BR', {
                month: 'short',
                year: 'numeric'
            });
        }

        // Função para montar cabeçalho com meses e dias
        function montarCabecalho(datas) {
            const headerMeses = document.getElementById('headerMeses');
            const headerDias = document.getElementById('headerDias');
            headerMeses.innerHTML = '';
            headerDias.innerHTML = '';

            const cellBranco = document.createElement('th');
            headerMeses.appendChild(cellBranco);
            headerDias.appendChild(cellBranco);

            let mesAtual = '';
            let mesContador = 0;

            datas.forEach((data, i) => {
                const mesFormat = formatarMes(data);
                if (mesFormat !== mesAtual) {
                    if (mesAtual !== '') {
                        const th = document.createElement('th');
                        th.className = 'month';
                        th.colSpan = mesContador;
                        th.innerText = mesAtual;
                        headerMeses.appendChild(th);
                    }
                    mesAtual = mesFormat;
                    mesContador = 1;
                } else {
                    mesContador++;
                }

                if (i === datas.length - 1) {
                    const th = document.createElement('th');
                    th.className = 'month';
                    th.colSpan = mesContador;
                    th.innerText = mesAtual;
                    headerMeses.appendChild(th);
                }
            });

            // Preencher linha de dias
            datas.forEach(data => {
                const th = document.createElement('th');
                th.className = 'day';

                const diaSemana = data.getDay(); // 0 = domingo, 6 = sábado
                if (diaSemana === 0 || diaSemana === 6) {
                    th.style.backgroundColor = '#ffe0e0'; // destaque para final de semana
                    th.style.fontWeight = 'bold';
                }

                th.innerText = data.getDate();
                headerDias.appendChild(th);
            });
        }

        // Montar o corpo da tabela com imagens e etapas
        function montarCorpo(imagens, etapas, datas) {
            const tbody = document.getElementById('ganttBody');
            tbody.innerHTML = '';

            function criarDataLocal(dataStr) {
                const [ano, mes, dia] = dataStr.split('-').map(Number);
                return new Date(ano, mes - 1, dia); // mes é zero-based no JS
            }

            function zerarHorario(data) {
                return new Date(data.getFullYear(), data.getMonth(), data.getDate());
            }

            function calcularDataFim(dataInicio, diasUteis, datas) {
                let contador = 0;
                const inicio = criarDataLocal(dataInicio);

                for (let i = 0; i < datas.length; i++) {
                    const d = datas[i];
                    d.setHours(0, 0, 0, 0);

                    if (d >= inicio) {
                        const diaSemana = d.getDay();
                        if (diaSemana !== 0 && diaSemana !== 6) { // útil
                            contador++;
                        }
                        if (contador === diasUteis) {
                            return d;
                        }
                    }
                }
                return datas[datas.length - 1];
            }


            Object.keys(imagens).forEach(tipo => {
                imagens[tipo].forEach(img => {
                    const tr = document.createElement('tr');

                    // Info da linha
                    const tdInfo = document.createElement('td');
                    tdInfo.className = 'etapas';
                    tdInfo.innerHTML = `<strong>${tipo}</strong> | ${img.nome} <br>`;
                    tr.appendChild(tdInfo);

                    // Pega as etapas dessa imagem
                    const etapasTipo = (etapas[tipo] || []).filter(e => e.imagem_id === img.imagem_id);
                    etapasTipo.sort((a, b) => new Date(a.data_inicio) - new Date(b.data_inicio));

                    // Para cada dia do período
                    datas.forEach((data, diaIdx) => {
                        const diaSemana = data.getDay();

                        let etapaDoDia = null;
                        for (let i = 0; i < etapasTipo.length; i++) {
                            const etapa = etapasTipo[i];
                            const inicioData = zerarHorario(criarDataLocal(etapa.data_inicio));
                            const fimDataCalculada = calcularDataFim(etapa.data_inicio, etapa.dias, datas);

                            const dataAtual = zerarHorario(data);

                            if (dataAtual >= inicioData && dataAtual <= fimDataCalculada && diaSemana !== 0 && diaSemana !== 6) {
                                etapaDoDia = {
                                    etapa,
                                    index: i
                                };
                                break;
                            }
                        }

                        const td = document.createElement('td');
                        if (etapaDoDia) {
                            td.classList.add('bar');
                            td.className = `${etapaDoDia.etapa.etapa}`
                                .toLowerCase()
                                .normalize("NFD")
                                .replace(/[\u0300-\u036f]/g, "")
                                .replace(/\s/g, "")
                                .replace(/[^a-z0-9]/g, "");

                            td.setAttribute('data-inicio', etapaDoDia.etapa.data_inicio);
                            td.setAttribute('data-fim', etapaDoDia.etapa.data_fim);
                            td.setAttribute('dias-uteis', etapaDoDia.etapa.dias);
                            td.setAttribute('data-etapa', etapaDoDia.etapa.etapa);
                            td.setAttribute('imagem_id', img.imagem_id);

                            if (etapaDoDia.etapa.nome_etapa_colaborador) {
                                td.innerHTML = `${etapaDoDia.etapa.nome_etapa_colaborador}`;
                            }

                            if (etapaDoDia.etapa.etapa_colaborador_id == 15) {
                                td.innerHTML = ""; // Deixa a célula vazia
                                td.className = ""; // Remove classes de cor, se quiser
                            }

                            // ⬇️ Adiciona clique direito
                            td.oncontextmenu = (event) => {
                                event.preventDefault();
                                etapaAtual = etapaDoDia.etapa;

                                const colaboradorAtualId = etapaAtual.colaborador_id;
                                const nomeEtapa = etapaAtual.etapa;
                                const funcaoId = etapaParaFuncao[nomeEtapa];
                                const dataInicio = td.getAttribute('data-inicio');
                                const dataFim = td.getAttribute('data-fim');

                                document.getElementById('imagemId').value = etapaAtual.imagem_id;
                                document.getElementById('etapaNome').value = nomeEtapa;
                                document.getElementById('funcaoId').value = funcaoId;

                                preencherSelectComColaboradores({
                                    selectId: "colaborador_id",
                                    funcaoId,
                                    dataInicio,
                                    dataFim,
                                    colaboradorAtualId,
                                    onConflitoSelecionado: (selected) => {
                                        abrirModalConflito({
                                            colaboradorId: selected.value,
                                            nome: selected.textContent,
                                            obra: selected.dataset.obra,
                                            etapa: selected.dataset.etapa,
                                            inicio: formatarData(selected.dataset.inicio),
                                            fim: formatarData(selected.dataset.fim),
                                            etapaId: selected.dataset.ganttId
                                        });
                                    }
                                });

                                const rect = td.getBoundingClientRect();
                                const modal = document.getElementById('colaboradorModal');
                                const isRightSpace = rect.right + modal.offsetWidth < window.innerWidth;

                                modal.style.position = "absolute";
                                modal.style.left = isRightSpace ?
                                    `${rect.right + 10}px` :
                                    `${rect.left - modal.offsetWidth - 10}px`;
                                modal.style.top = `${rect.top + window.scrollY}px`;

                                document.getElementById("modalConflito").style.display = 'none';
                                modal.style.display = "flex";
                            };

                            // ⬇️ Adiciona arrasto
                            td.onmousedown = (e) => {
                                let isDragging = true;
                                let startX = e.clientX;
                                const imagemId = td.getAttribute('imagem_id');
                                const etapaAtualNome = td.getAttribute('data-etapa');
                                const tipoImagem = tipo;

                                td.classList.add('arrastando');
                                document.body.style.cursor = 'ew-resize';

                                document.onmousemove = (eMove) => {
                                    if (!isDragging) return;
                                    const diffX = eMove.clientX - startX;
                                    td.style.transform = `translateX(${diffX}px)`;
                                };

                                document.onmouseup = (eUp) => {
                                    if (!isDragging) return;
                                    const diffX = eUp.clientX - startX;
                                    const cellWidth = td.offsetWidth;
                                    const daysMoved = Math.round(diffX / cellWidth);

                                    td.style.transform = 'translateX(0)';
                                    td.classList.remove('arrastando');
                                    document.body.style.cursor = 'default';
                                    document.onmousemove = null;
                                    isDragging = false;

                                    if (Math.abs(diffX) < 5) {
                                        td.style.transform = 'translateX(0)';
                                        return; // ignora clique sem intenção de arrastar
                                    }

                                    if (daysMoved !== 0) {
                                        const etapasImagemOrdenadas = etapas[tipoImagem]
                                            .filter(et => et.imagem_id == imagemId)
                                            .sort((a, b) => {
                                                const ordem = ['Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Finalização', 'Pós-Produção'];
                                                return ordem.indexOf(a.etapa) - ordem.indexOf(b.etapa);
                                            });
                                        let etapasParaAtualizar = [];

                                        if (daysMoved > 0) {
                                            // Movendo para frente →: etapa atual + próximas, se estiverem "grudadas"
                                            etapasParaAtualizar = [];
                                            let encontrouAtual = false;
                                            let podeMover = true;

                                            for (let i = 0; i < etapasImagemOrdenadas.length; i++) {
                                                const et = etapasImagemOrdenadas[i];

                                                if (et.etapa === etapaAtualNome) {
                                                    encontrouAtual = true;
                                                    etapasParaAtualizar.push(et);
                                                } else if (encontrouAtual && podeMover) {
                                                    const etapaAnterior = etapasParaAtualizar[etapasParaAtualizar.length - 1];
                                                    const fimAnterior = new Date(etapaAnterior.data_fim);
                                                    const inicioAtual = new Date(et.data_inicio);

                                                    // Se a nova etapa começar exatamente no dia seguinte à anterior
                                                    const diffDias = (inicioAtual - fimAnterior) / (1000 * 60 * 60 * 24);

                                                    if (diffDias <= 1) {
                                                        etapasParaAtualizar.push(et);
                                                    } else {
                                                        podeMover = false; // Parar de mover se houver "quebra"
                                                    }
                                                }
                                            }
                                        } else if (daysMoved < 0) {
                                            etapasImagemOrdenadas.reverse(); // começa da última para a primeira
                                            let encontrouAtual = false;
                                            let podeMover = true;
                                            etapasParaAtualizar = [];

                                            for (let i = 0; i < etapasImagemOrdenadas.length; i++) {
                                                const et = etapasImagemOrdenadas[i];

                                                if (et.etapa === etapaAtualNome) {
                                                    encontrouAtual = true;
                                                    etapasParaAtualizar.push(et);
                                                } else if (encontrouAtual && podeMover) {
                                                    const etapaPosterior = etapasParaAtualizar[etapasParaAtualizar.length - 1];
                                                    const inicioPosterior = new Date(etapaPosterior.data_inicio);
                                                    const fimAtual = new Date(et.data_fim);

                                                    // Verifica se o fim da etapa atual é exatamente um dia antes do início da próxima
                                                    const diffDias = (inicioPosterior - fimAtual) / (1000 * 60 * 60 * 24);

                                                    if (diffDias <= 1) {
                                                        etapasParaAtualizar.push(et);
                                                    } else {
                                                        podeMover = false; // Interrompe caso haja quebra
                                                    }
                                                }
                                            }

                                            etapasParaAtualizar.reverse(); // retorna para ordem original
                                        } else {
                                            // Movimento zero (sem arrasto real)
                                            etapasParaAtualizar = [];
                                        }

                                        etapasParaAtualizar.forEach(et => {
                                            et.data_inicio = novaData(et.data_inicio, daysMoved);
                                            et.data_fim = novaData(et.data_fim, daysMoved);
                                        });

                                        console.log('Etapas para atualizar:', etapasParaAtualizar);

                                        fetch('atualizar_datas.php', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json'
                                                },
                                                body: JSON.stringify({
                                                    tipoImagem: tipoImagem,
                                                    imagemId: imagemId,
                                                    etapas: etapasParaAtualizar
                                                })
                                            })
                                            .then(res => res.json())
                                            .then(data => {
                                                if (data.success) {
                                                    console.log('Datas atualizadas com sucesso no banco.');
                                                    // atualizarTabela();
                                                } else if (data.message === 'O colaborador já atingiu o limite de etapas simultâneas para essa função nesse período.') {
                                                    // Preencher modal com os dados retornados
                                                    const conflitosDiv = document.getElementById('conflitosDetalhes');
                                                    conflitosDiv.innerHTML = '';

                                                    if (Array.isArray(data.obras_conflitantes)) {
                                                        data.obras_conflitantes.forEach(conflito => {
                                                            const item = document.createElement('div');
                                                            item.innerHTML = `
                                                        <strong>Obra ID:</strong> ${conflito.obra_id || 'N/A'}<br>
                                                        <strong>Período:</strong> ${conflito.data_inicio} até ${conflito.data_fim}<br><br>
                                                    `;
                                                            conflitosDiv.appendChild(item);
                                                        });
                                                    }

                                                    // Exibir o período que causou o conflito
                                                    const periodo = document.getElementById('periodoConflitante');
                                                    periodo.innerText = `Tentativa de inserir no período: ${data.periodo_conflitante.data_inicio} até ${data.periodo_conflitante.data_fim}`;

                                                    // Mostrar modal
                                                    const modal = document.getElementById('modalConflitoData');
                                                    modal.style.display = 'block';

                                                    document.getElementById('verAgendaBtn').addEventListener('click', () => {
                                                        // Exibe o calendário
                                                        const input = document.getElementById('calendarioDatasDisponiveis');
                                                        input.style.display = 'block';

                                                        if (input._flatpickr) {
                                                            input._flatpickr.destroy();
                                                        }

                                                        flatpickr(input, {
                                                            dateFormat: "Y-m-d",
                                                            disable: data.datas_ocupadas,
                                                            onChange: function(selectedDates, dateStr) {
                                                                console.log('Nova data escolhida:', dateStr);
                                                                console.log('Gantt ID:', data.gantt_id);

                                                                fetch('update_data.php', {
                                                                        method: 'POST',
                                                                        headers: {
                                                                            'Content-Type': 'application/x-www-form-urlencoded'
                                                                        },
                                                                        body: `gantt_id=${data.gantt_id}&data_inicio=${dateStr}`
                                                                    })
                                                                    .then(response => response.json())
                                                                    .then(result => {
                                                                        if (result.success) {
                                                                            alert('Etapa atualizada com sucesso! Nova data fim: ' + result.data_fim);
                                                                            // Aqui você pode atualizar a interface se necessário
                                                                        } else {
                                                                            alert(result.message);
                                                                        }
                                                                    })
                                                                    .catch(error => {
                                                                        console.error('Erro ao atualizar etapa:', error);
                                                                    });
                                                            }
                                                        });
                                                    });
                                                } else {
                                                    console.error('Erro ao atualizar:', data.message);
                                                }
                                            })
                                            .catch(error => console.error('Erro na requisição:', error));
                                    }
                                };
                            };
                        } else {
                            // Se for fim de semana, pode adicionar uma classe para destacar se quiser
                            if (diaSemana === 0 || diaSemana === 6) {
                                td.className = 'fim-de-semana';
                            }
                            // td vazio
                        }
                        tr.appendChild(td);
                    });

                    tbody.appendChild(tr);
                });
            });
        }

        let colaboradorIdAtual = null;
        let etapaIdAtual = null;
        let nomeEtapaAtual = null;
        let dataInicioAtual = null;
        let dataFimAtual = null;

        function abrirModalConflito({
            colaboradorId,
            nome,
            obra,
            etapa,
            inicio,
            fim,
            etapaId
        }) {
            colaboradorIdAtual = colaboradorId;
            etapaIdAtual = etapaId;
            nomeEtapaAtual = etapa;
            dataInicioAtual = inicio;
            dataFimAtual = fim;

            console.log(nomeEtapaAtual);

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
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
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
                                        // atualizarTabela();
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
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                antigoId: colaboradorIdAtual,
                                novoId: novoId,
                                etapaId: etapaIdAtual
                            })
                        }).then(response => response.json())
                        .then(data => {
                            Swal.fire("Sucesso", "Colaborador trocado!", "success");
                        });
                    // atualizarTabela();
                    document.getElementById("modalConflito").style.display = "none";
                }
            });
        };

        function formatarData(data) {
            const partes = data.split("-");
            const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
            return dataFormatada;
        }

        // Função para somar dias a uma data
        function novaData(dataOriginal, diasParaMover) {
            let data = new Date(dataOriginal);
            data.setDate(data.getDate() + diasParaMover);

            // if (data.getDay() === 0 || data.getDay() === 6) {
            //     alert("A data caiu em um final de semana. Será ajustada para o próximo dia útil.");
            //     while (data.getDay() === 0 || data.getDay() === 6) {
            //         data.setDate(data.getDate() + 1);
            //     }
            // }

            return data.toISOString().split('T')[0];
        }



        // Buscar dados do PHP (substitua pela URL correta e id_obra real)
        const obraId = localStorage.getItem('obraId'); // ou o nome que você usou no localStorage

        fetch(`tabela.php?id_obra=${obraId}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('obraNome').innerText = data.obra.nome_obra || 'Sem nome';
                const datas = gerarDatas(data.primeiraData, data.ultimaData);
                montarCabecalho(datas);

                // O backend precisa retornar 'data_inicio' e 'data_fim' dentro das etapas para funcionar corretamente
                montarCorpo(data.imagens, data.etapas, datas);
            })
            .catch(e => {
                console.error('Erro ao carregar dados:', e);
            });
    </script>

</body>

</html>