function formatarDataAtual() {
    const opcoes = { weekday: 'long', day: 'numeric', month: 'long' };
    const dataAtual = new Date();
    return dataAtual.toLocaleDateString('pt-BR', opcoes);
}


document.addEventListener('DOMContentLoaded', function () {
    // ====== Resumo (dashboard) ======
    const mesResumo = document.getElementById('mes-resumo');
    const anoResumo = document.getElementById('ano-resumo');
    const mesTarefas = document.getElementById('mes');
    const anoTarefas = document.getElementById('ano');
    const btnResumo = document.getElementById('btn-carregar-resumo');
    // statusNovo is defined per item inside carregarResumo's loop
    function setDefaultMesAnoResumo() {
        if (!mesResumo || !anoResumo) return;
        const today = new Date();
        const prev = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        mesResumo.value = (prev.getMonth() + 1).toString();
        anoResumo.value = prev.getFullYear().toString();
        if (mesTarefas && anoTarefas) {
            mesTarefas.value = (prev.getMonth() + 1).toString();
            anoTarefas.value = prev.getFullYear().toString();
        }
    }

    function currencyBRL(n) {
        return (n || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    async function carregarResumo() {
        if (!mesResumo || !anoResumo) return;
        const mes = parseInt(mesResumo.value, 10);
        const ano = parseInt(anoResumo.value, 10);
        const tbody = document.querySelector('#tabela-resumo tbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="7">Carregando...</td></tr>';
        try {
            const res = await fetch(`getResumo.php?mes=${encodeURIComponent(mes)}&ano=${encodeURIComponent(ano)}`);
            const json = await res.json();
            if (!tbody) return;
            tbody.innerHTML = '';
            if (!json.items || json.items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7">Sem dados para o período.</td></tr>';
                return;
            }
            json.items.forEach(item => {
                // determine canonical key and next action
                const normalize = s => (s || '').toString().trim().toLowerCase().replace(/\s+/g, '_');
                const key = normalize(item.status);

                let displayLabel = 'Desconhecido';
                if (key === 'adendo_gerado' || key === 'adendo') displayLabel = 'Adendo gerado';
                else if (key === 'pago') displayLabel = 'Pago';
                else if (key === 'aguardando_retorno' || key === 'enviado' || key === 'confirmando') displayLabel = 'Aguardando retorno';
                else if (key === 'pendente_envio' || key === 'pendente') displayLabel = 'Pendente envio';
                else if (key === 'validado' || key === 'confirmado') displayLabel = 'Validado';

                // next action mapping: returns { label, nextStatus, btnClass }
                function nextActionFor(key) {
                    switch (key) {
                        case 'pendente_envio':
                        case 'pendente':
                            return { label: 'Enviar lista', nextStatus: 'aguardando_retorno', btnClass: 'send' };
                        case 'aguardando_retorno':
                        case 'enviado':
                        case 'confirmando':
                            return { label: 'Marcar lista respondida', nextStatus: 'validado', btnClass: 'validate' };
                        case 'validado':
                        case 'confirmado':
                            return { label: 'Gerar adendo', nextStatus: 'adendo_gerado', btnClass: 'adendo' };
                        case 'adendo_gerado':
                        case 'adendo':
                            return { label: 'Marcar pago', nextStatus: 'pago', btnClass: 'pay' };
                        case 'pago':
                            return { label: null, nextStatus: null, btnClass: 'pay' };
                        default:
                            return { label: 'Enviar lista', nextStatus: 'aguardando_retorno', btnClass: 'send' };
                    }
                }

                const action = nextActionFor(key);

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.nome}</td>
                    <td>${item.mes_ref}</td>
                    <td data-fixo="${item.valor_fixo}">${currencyBRL(item.valor_fixo)}</td>
                    <td data-valor="${item.valor}">${currencyBRL(item.valor)}</td>
                    <td><span class="badge status-${(key || '').toLowerCase()}">${displayLabel}</span></td>
                    <td>${item.ultima_atualizacao ? item.ultima_atualizacao : '-'}</td>
                    <td class="action-group">
                        ${action.nextStatus ? `<button class="action-btn ${action.btnClass} btn-acao" data-colab="${item.colaborador_id}" data-next="${action.nextStatus}">${action.label}</button>` : `<span class="badge status-pago">Pago</span>`}
                        <button class="action-btn small btn-detalhes" data-colab="${item.colaborador_id}">Detalhes</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            // Wire actions: primary next-action buttons and detalhes
            tbody.querySelectorAll('.btn-acao').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const colab = parseInt(btn.dataset.colab, 10);
                    const next = btn.dataset.next;
                    let confirmMsg = `Executar ação "${btn.textContent.trim()}" para este colaborador?`;
                    if (next === 'pago') confirmMsg = 'Confirmar pagamento para todas as tarefas do mês selecionado deste colaborador?';
                    const ok = confirm(confirmMsg);
                    if (!ok) return;
                    await atualizarStatusResumo(colab, next);
                });
            });
            tbody.querySelectorAll('.btn-detalhes').forEach(btn => {
                btn.addEventListener('click', () => abrirDetalhesColaborador(parseInt(btn.dataset.colab, 10)));
            });
        } catch (e) {
            console.error('Erro ao carregar resumo', e);
            if (tbody) tbody.innerHTML = '<tr><td colspan="7">Erro ao carregar</td></tr>';
        }
    }

    async function atualizarStatusResumo(colaboradorId, status) {
        const mes = parseInt(mesResumo.value, 10);
        const ano = parseInt(anoResumo.value, 10);
        try {
            const res = await fetch('updateStatusPagamento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ colaborador_id: colaboradorId, mes, ano, status })
            });
            const json = await res.json();
            if (!json.success) {
                alert('Falha ao atualizar status: ' + (json.error || 'erro desconhecido'));
            } else {
                carregarResumo();
            }
        } catch (e) {
            console.error('Erro ao atualizar status', e);
            alert('Erro ao atualizar status');
        }
    }

    function abrirDetalhesColaborador(colaboradorId) {
        const selColab = document.getElementById('colaborador');
        const selMes = document.getElementById('mes');
        const selAno = document.getElementById('ano');
        if (selColab && selMes && selAno) {
            selColab.value = String(colaboradorId);
            selMes.value = mesResumo.value;
            selAno.value = anoResumo.value;
            if (typeof window.carregarDadosColab === 'function') {
                window.carregarDadosColab();
                const detalheSec = document.getElementById('table-list');
                if (detalheSec) detalheSec.scrollIntoView({ behavior: 'smooth' });
            }
        }
    }

    if (btnResumo) {
        btnResumo.addEventListener('click', carregarResumo);
    }
    if (mesResumo && anoResumo) {
        mesResumo.addEventListener('change', carregarResumo);
        anoResumo.addEventListener('change', carregarResumo);
        setDefaultMesAnoResumo();
        carregarResumo();
    }
    document.getElementById('colaborador').addEventListener('change', function () {
        carregarDadosColab();
    });
    document.getElementById('mes').addEventListener('change', carregarDadosColab);
    document.getElementById('ano').addEventListener('change', carregarDadosColab);

    function carregarDadosColab() {
        var colaboradorId = document.getElementById('colaborador').value;
        var mesId = document.getElementById('mes').value;
        var anoId = document.getElementById('ano').value;

        const confirmarPagamentoButton = document.getElementById('confirmar-pagamento');
        // confirmarPagamentoButton.disabled = true;

        if (colaboradorId) {
            var url = 'getColaborador.php?colaborador_id=' + encodeURIComponent(colaboradorId);

            if (mesId) {
                url += '&mes_id=' + encodeURIComponent(mesId);
            }
            if (anoId) {
                url += '&ano=' + encodeURIComponent(anoId)
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {

                    var infoColaborador = document.getElementById('info-colaborador');
                    var colaborador = data.dadosColaborador;
                    if (colaborador) {
                        infoColaborador.innerHTML = `
                            <p id='nomeColaborador'>${colaborador.nome_usuario}</p>
                            <p id='nomeEmpresarial'>${colaborador.nome_empresarial}</p>
                            <p id='cnpjColaborador'>${colaborador.cnpj}</p>
                            <p id='enderecoColaborador'>${colaborador.rua}, ${colaborador.numero}, ${colaborador.bairro}</p>
                            <p id='estadoCivil'>${colaborador.estado_civil}</p>
                            <p id='cpfColaborador'>${colaborador.cpf}</p>
                            <p id='enderecoCNPJ'>${colaborador.rua_cnpj} , ${colaborador.numero_cnpj} , ${colaborador.bairro_cnpj}</p>
                            <p id='cep'>${colaborador.cep}</p>
                            <p id='cepCNPJ'>${colaborador.cep_cnpj}</p>
                        `;
                    }

                    // Atualiza a tabela
                    var tabela = document.querySelector('#tabela-faturamento tbody');
                    tabela.innerHTML = '';
                    let totalValor = 0;

                    document.querySelectorAll('.tipo-imagem input[type="checkbox"]').forEach(checkbox => {
                        checkbox.checked = false;
                    });

                    data.funcoes.forEach(function (item) {
                        var row = document.createElement('tr');
                        row.setAttribute('data-id', item.identificador);

                        var cellNomeImagem = document.createElement('td');
                        var cellStatusFuncao = document.createElement('td');
                        var cellFuncao = document.createElement('td');
                        var cellValor = document.createElement('td');
                        var cellCheckbox = document.createElement('td');
                        var cellData = document.createElement('td');
                        var checkbox = document.createElement('input');

                        checkbox.type = 'checkbox';
                        checkbox.classList.add('pagamento-checkbox');
                        checkbox.checked = item.pagamento === 1;
                        checkbox.setAttribute('pagamento', item.pagamento);
                        checkbox.setAttribute('data-id', item.identificador);
                        checkbox.setAttribute('data-origem', item.origem);
                        checkbox.setAttribute('funcao', item.funcao_id);
                        // include function name so backend can detect 'Finalização parcial'
                        checkbox.setAttribute('data-funcao-name', item.nome_funcao || '');
                        // counts to allow 2nd confirmation (pago parcial -> pago completa)
                        checkbox.setAttribute('data-pago-parcial-count', item.pago_parcial_count != null ? String(item.pago_parcial_count) : '0');
                        checkbox.setAttribute('data-pago-completa-count', item.pago_completa_count != null ? String(item.pago_completa_count) : '0');

                        // If this item already has a recorded full payment, lock it to prevent edits and re-registering.
                        const pagoCompletaCount = item.pago_completa_count ? parseInt(item.pago_completa_count, 10) : 0;
                        if (pagoCompletaCount > 0) {
                            checkbox.disabled = true;
                            checkbox.title = 'Pagamento completo já registrado; não é possível alterar.';
                        }

                        checkbox.addEventListener('change', function () {
                            if (checkbox.checked) {
                                row.classList.add('row-selected');
                                row.classList.remove('checked');
                            } else {
                                row.classList.remove('row-selected');
                                row.classList.remove('checked');
                            }
                            // Atualiza contagens quando o usuário altera seleção
                            contarLinhasTabela();
                        });
                        cellCheckbox.appendChild(checkbox);

                        // Verificar a origem e preencher os dados de acordo
                        if (item.origem === 'funcao_imagem') {
                            cellNomeImagem.textContent = item.imagem_nome;
                            cellFuncao.textContent = item.nome_funcao;
                            cellStatusFuncao.textContent = item.status;
                            cellValor.textContent = item.valor;
                            cellData.textContent = item.data_pagamento ? item.data_pagamento : '';

                            // Mostrar indicador: prioriza 'Pago Completa' sobre 'Pago Parcial'
                            if (item.pago_completa_count && parseInt(item.pago_completa_count, 10) > 0) {
                                const badge = document.createElement('span');
                                badge.textContent = 'Pago Completa';
                                badge.style.background = '#ffdf99';
                                badge.style.color = '#663c00';
                                badge.style.padding = '2px 6px';
                                badge.style.borderRadius = '12px';
                                badge.style.fontSize = '11px';
                                badge.style.marginLeft = '8px';
                                badge.title = 'Este item já teve pagamento parcial e foi pago por completo';
                                cellFuncao.appendChild(badge);
                            } else if (item.pago_parcial_count && parseInt(item.pago_parcial_count, 10) > 0) {
                                const badge = document.createElement('span');
                                badge.textContent = 'Pago Parcial';
                                badge.style.background = '#ffdf99';
                                badge.style.color = '#663c00';
                                badge.style.padding = '2px 6px';
                                badge.style.borderRadius = '12px';
                                badge.style.fontSize = '11px';
                                badge.style.marginLeft = '8px';
                                badge.title = 'Este item já foi pago anteriormente como Finalização Parcial';
                                cellFuncao.appendChild(badge);
                            }

                            totalValor += parseFloat(item.valor) || 0;
                        } else if (item.origem === 'acompanhamento') {
                            cellNomeImagem.textContent = item.imagem_nome;
                            cellFuncao.textContent = 'Acompanhamento';
                            cellStatusFuncao.textContent = 'Finalizado';
                            cellValor.textContent = item.valor;
                            cellData.textContent = item.data_pagamento ? item.data_pagamento : '';

                            totalValor += parseFloat(item.valor) || 0;
                        } else if (item.origem === 'animacao') {
                            cellNomeImagem.textContent = item.imagem_nome;
                            cellFuncao.textContent = 'Animação';
                            cellStatusFuncao.textContent = item.status;
                            cellValor.textContent = item.valor;
                            cellData.textContent = item.data_pagamento ? item.data_pagamento : '';
                        }

                        row.appendChild(cellNomeImagem);
                        row.appendChild(cellStatusFuncao);
                        row.appendChild(cellFuncao);
                        row.appendChild(cellValor);
                        row.appendChild(cellCheckbox);
                        row.appendChild(cellData);

                        tabela.appendChild(row);

                        if (checkbox.checked) {
                            row.classList.add('checked');
                        }

                        document.querySelectorAll('.tipo-imagem input[type="checkbox"]').forEach(funcaoCheckbox => {
                            if (funcaoCheckbox.name === item.nome_funcao) {
                                funcaoCheckbox.checked = true;
                            }
                        });
                    });

                    contarLinhasTabela();


                })
                .catch(error => {
                    console.error('Erro ao carregar dados do colaborador:', error);
                });
        } else {
            document.querySelector('#tabela-faturamento tbody').innerHTML = '';
            var totalValorLabel = document.getElementById('totalValor');
            totalValorLabel.textContent = 'Total: R$ 0,00';
        }
    }

    // Expor para o dashboard
    window.carregarDadosColab = carregarDadosColab;



    document.getElementById('marcar-todos').addEventListener('click', function (event) {
        var checkboxes = Array.from(document.querySelectorAll('.pagamento-checkbox')).filter(checkbox => {
            return checkbox.closest('tr').offsetParent !== null; // Checa se a linha está visível
        });

        if (event.shiftKey) {
            // Se a tecla Shift estiver pressionada, marcar/desmarcar todos
            var allChecked = checkboxes.every(checkbox => checkbox.checked);
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = !allChecked; // Marca/desmarca todos os checkboxes
                var row = checkbox.closest('tr');
                if (checkbox.checked) {
                    row.classList.add('row-selected');
                } else {
                    row.classList.remove('row-selected');
                }
            });
        } else {
            // Inverte o estado de cada checkbox individualmente
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = !checkbox.checked; // Inverte o estado do checkbox
                var row = checkbox.closest('tr');
                if (checkbox.checked) {
                    row.classList.add('row-selected');
                } else {
                    row.classList.remove('row-selected');
                }
            });
        }
        contarLinhasTabela();
    });

    document.getElementById('confirmar-pagamento').addEventListener('click', function () {
        var colaboradorId = parseInt(document.getElementById('colaborador').value, 10);
        // Apenas checkboxes visíveis e marcadas
        var checkboxes = Array.from(document.querySelectorAll('.pagamento-checkbox:checked'))
            .filter(cb => cb.closest('tr').offsetParent !== null)
            // Do not re-register items already paid, except when it is a Finalização Completa with a previous pagamento parcial.
            .filter(cb => {
                if (cb.disabled === true) return false;

                const isPago = cb.getAttribute('pagamento') === '1';
                if (!isPago) return true;

                const parcialCount = parseInt(cb.getAttribute('data-pago-parcial-count') || '0', 10) || 0;
                const completaCount = parseInt(cb.getAttribute('data-pago-completa-count') || '0', 10) || 0;
                const rawName = (cb.getAttribute('data-funcao-name') || '').toString().trim();
                const norm = (s) => (s || '').toString().trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                const funcaoName = norm(rawName);

                const needsPagoCompleta = completaCount === 0 && parcialCount > 0 && funcaoName === norm('Finalização Completa');
                return needsPagoCompleta;
            });
        var ids = checkboxes.map(cb => ({
            id: parseInt(cb.getAttribute('data-id'), 10),
            origem: cb.getAttribute('data-origem'), // Coletando o atributo origem
            funcao_id: parseInt(cb.getAttribute('funcao'), 10), // Coletando o atributo funcao_id
            funcao_name: cb.getAttribute('data-funcao-name') || ''
        }));

        if (ids.length > 0) {
            // include selected month/year so backend can group itens into pagamentos (mes_ref)
            const mes = document.getElementById('mes').value;
            const ano = document.getElementById('ano').value;
            fetch('updatePagamento.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ ids: ids, colaborador_id: colaboradorId, mes: mes, ano: ano })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Pagamentos atualizados com sucesso!');
                        // Inserir no histórico
                        fetch('insertHistorico.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ ids: ids, colaborador_id: colaboradorId })
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert('Histórico atualizado com sucesso!');
                                } else {
                                    alert('Erro ao atualizar histórico.');
                                }
                            })
                            .catch(error => {
                                console.error('Erro ao atualizar histórico:', error);
                            });
                        carregarDadosColab();
                    } else {
                        alert('Erro ao atualizar pagamentos.');
                    }
                })
                .catch(error => {
                    console.error('Erro ao confirmar pagamentos:', error);
                });
        } else {
            alert('Selecione pelo menos uma imagem para confirmar o pagamento.');
        }
    });

    document.getElementById('adicionar-valor').addEventListener('click', function () {
        // Apenas checkboxes visíveis e marcadas
        var checkboxes = Array.from(document.querySelectorAll('.pagamento-checkbox:checked')).filter(cb => cb.closest('tr').offsetParent !== null);
        var ids = checkboxes.map(cb => ({
            id: cb.getAttribute('data-id'),
            origem: cb.getAttribute('data-origem'),
            funcao_id: cb.getAttribute('funcao')
        }));

        var valor = document.getElementById('valor').value;

        if (ids.length > 0 && valor) {
            fetch('updateValor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ ids: ids, valor: valor })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Valores atualizados com sucesso!');
                        carregarDadosColab();
                    } else {
                        alert('Erro ao atualizar valores: ' + (data.error || 'Erro desconhecido.'));
                    }
                })
                .catch(error => {
                    console.error('Erro ao adicionar valores:', error);
                });
        } else {
            alert('Selecione pelo menos uma imagem e insira um valor.');
        }
    });
});


function contarLinhasTabela() {
    const tabela = document.getElementById("tabela-faturamento");
    const tbody = tabela.getElementsByTagName("tbody")[0];
    const linhas = tbody.getElementsByTagName("tr");
    let totalImagens = 0;
    let totalValor = 0;
    // Mantém o comportamento anterior: contar imagens visíveis e somar valores
    for (let i = 0; i < linhas.length; i++) {
        const linha = linhas[i];
        if (linha.style.display !== "none") {
            totalImagens++;
            const valorCell = linha.getElementsByTagName("td")[3]; // Supondo que o valor está na quarta coluna (índice 3)
            const raw = valorCell ? valorCell.textContent : '';
            // Normaliza o valor: remove tudo que não seja número, ponto ou vírgula, transforma milhares
            const numero = parseFloat(raw.replace(/[^0-9,.-]+/g, '').replace(/\./g, '').replace(',', '.'));
            totalValor += !isNaN(numero) ? numero : 0; // Soma o valor se for um número
        }
    }

    // Atualiza totais gerais
    const elTotalImagens = document.getElementById("total-imagens");
    const elTotalValor = document.getElementById("totalValor");
    if (elTotalImagens) elTotalImagens.innerText = totalImagens;
    if (elTotalValor) elTotalValor.innerText = totalValor.toFixed(2).replace('.', ','); // Atualiza o total

    // --- Contagem por função (atualiza cada label dentro de .tipo-imagem) ---
    // A marcação usa um único container .tipo-imagem com vários <label class="checkbox-label">;
    // vamos contar as funções nas linhas visíveis e atualizar cada label individualmente.
    const mapaContagem = {};
    for (let i = 0; i < linhas.length; i++) {
        const linha = linhas[i];
        if (linha.style.display === 'none') continue; // apenas linhas visíveis
        const funcaoCell = linha.cells[2];
        const funcaoText = funcaoCell ? (funcaoCell.textContent || funcaoCell.innerText).trim() : '';
        if (!funcaoText) continue;
        mapaContagem[funcaoText] = (mapaContagem[funcaoText] || 0) + 1;
    }

    // Seleciona cada label dentro o container e atualiza seu contador
    const labels = document.querySelectorAll('.tipo-imagem .checkbox-label');
    labels.forEach(label => {
        const input = label.querySelector('input[type="checkbox"]');
        let nomeFuncao = '';
        if (input && input.name) {
            nomeFuncao = input.name.trim();
        } else {
            // fallback: texto do próprio label (ex.: <span>...)</
            const span = label.querySelector('span');
            nomeFuncao = span ? span.textContent.trim() : (label.textContent || '').trim();
        }

        const count = mapaContagem[nomeFuncao] || 0;

        // Atualiza ou cria o span .tipo-count dentro do label
        let spanCount = label.querySelector('.tipo-count');
        // Mostrar o contador apenas quando for maior que 0
        if (count > 0) {
            if (!spanCount) {
                spanCount = document.createElement('span');
                spanCount.className = 'tipo-count';
                spanCount.style.marginLeft = '6px';
                spanCount.style.color = '#666';
                label.appendChild(spanCount);
            }
            spanCount.textContent = `(${count})`;
            spanCount.style.display = '';
        } else {
            // Se existir e o count for zero, remove o elemento para não mostrar
            if (spanCount) spanCount.remove();
        }
    });
}


function filtrarTabela() {
    const tabela = document.querySelector('#tabela-faturamento tbody');
    const linhas = tabela.getElementsByTagName('tr');

    // Obter todas as checkboxes marcadas
    const checkboxes = document.querySelectorAll('.tipo-imagem input[type="checkbox"]:checked');
    const funcoesSelecionadas = Array.from(checkboxes).map(checkbox => checkbox.name);

    for (let i = 0; i < linhas.length; i++) {
        const linha = linhas[i];
        const funcaoCell = linha.cells[2];

        if (funcaoCell) {
            const funcaoText = funcaoCell.textContent || funcaoCell.innerText;
            if (funcoesSelecionadas.length === 0 || funcoesSelecionadas.includes(funcaoText)) {
                linha.style.display = "";
            } else {
                linha.style.display = "none";
            }
        }
    }

    contarLinhasTabela();
}

// Função para converter números para texto
document.getElementById('generate-adendo').addEventListener('click', async function () {
    const colaboradorId = (() => {
        const el = document.getElementById('colaborador');
        const v = el ? parseInt(el.value, 10) : NaN;
        return Number.isFinite(v) ? v : null;
    })();

    if (!colaboradorId) {
        alert('Selecione um colaborador antes de gerar o adendo.');
        return;
    }

    const mesEl = document.getElementById('mes');
    const anoEl = document.getElementById('ano');
    const mes = mesEl ? parseInt(mesEl.value, 10) : NaN;
    const ano = anoEl ? parseInt(anoEl.value, 10) : NaN;

    if (!Number.isFinite(mes) || !Number.isFinite(ano)) {
        alert('Selecione mês e ano antes de gerar o adendo.');
        return;
    }

    const valorFixo = prompt("Digite o valor fixo (somente número):");
    if (!valorFixo || isNaN(valorFixo)) {
        alert("Por favor, insira um valor numérico válido.");
        return;
    }

    const btn = this;
    btn.disabled = true;
    try {
        const res = await fetch('gerar_adendo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                colaborador_id: colaboradorId,
                mes: mes,
                ano: ano,
                valor_fixo: valorFixo
            })
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            alert(data.message || 'Erro ao gerar adendo.');
            return;
        }
        if (data.download_url) {
            window.open(data.download_url, '_blank');
        } else {
            alert('Adendo gerado com sucesso.');
        }
    } catch (e) {
        console.error('Erro ao gerar adendo', e);
        alert('Erro ao gerar adendo.');
    } finally {
        btn.disabled = false;
    }
});



document.getElementById('generate-lista').addEventListener('click', function () {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({
        orientation: 'landscape',
    });

    const colaborador = document.getElementById('colaborador').options[document.getElementById('colaborador').selectedIndex].text;
    const mesNome = document.getElementById('mes').options[document.getElementById('mes').selectedIndex].text;
    const ano = new Date().getFullYear();
    let currentY = 20;

    const title = `Relatório completo de ${colaborador}, ${mesNome} de ${ano}`;

    const imgPath = '../assets/logo.jpg';

    fetch(imgPath)
        .then(response => response.blob())
        .then(blob => {
            const reader = new FileReader();
            reader.onloadend = function () {
                const imgData = reader.result;
                doc.addImage(imgData, 'PNG', 14, currentY, 40, 40);
                currentY += 50;

                doc.setFontSize(16);
                doc.setTextColor(0, 0, 0);
                doc.text(title, 14, currentY);
                currentY += 10;

                // ==== Agrupamento por função ====
                const table = document.getElementById('tabela-faturamento');
                const selectedColumnIndexes = [0, 1, 2]; // colunas que vão para o PDF
                const funcaoColumnIndex = 2; // Ajuste para o índice da coluna "função"
                const dataPagamentoColumnIndex = 5;

                const headers = [];
                const rows = [];
                const agrupamentoFuncoes = {};

                table.querySelectorAll('thead tr th').forEach((header, index) => {
                    if (selectedColumnIndexes.includes(index)) {
                        headers.push(header.innerText);
                    }
                });

                table.querySelectorAll('tbody tr').forEach(row => {
                    const cells = row.querySelectorAll('td');
                    const cell = cells[dataPagamentoColumnIndex];

                    // Protege contra cell undefined e normaliza espaços/nbsp
                    const rawText = cell?.innerText?.replace(/\u00A0/g, ' ').trim() ?? '';

                    // Converte string vazia para null para sua lógica ficar consistente
                    const dataPagamento = rawText === '' ? null : rawText;

                    // Detecta se a célula de função contém a marca 'Pago Parcial' (case-insensitive)
                    const funcaoTextoRaw = (cells[funcaoColumnIndex]?.innerText || '').trim();
                    const funcaoTextoLower = funcaoTextoRaw.toLowerCase();
                    const hasPagoParcial = funcaoTextoLower.indexOf('pago parcial') !== -1;

                    // Observação: usar getComputedStyle caso a visibilidade seja controlada por classe/CSS
                    const visible = (row.style.display !== 'none') && (getComputedStyle(row).display !== 'none');

                    // Incluir linhas com data '0000-00-00' OU que tenham a marca 'Pago Parcial'
                    if ((dataPagamento === '0000-00-00' || dataPagamento === null || hasPagoParcial) && visible) {

                        // === Conta por função (removendo o rótulo 'Pago Parcial' para agregação) ===
                        const funcao = (funcaoTextoRaw.replace(/Pago Parcial/ig, '').trim()) || "Sem função";
                        agrupamentoFuncoes[funcao] = (agrupamentoFuncoes[funcao] || 0) + 1;

                        // === Monta linhas para o PDF ===
                        const rowData = [];
                        cells.forEach((cell, index) => {
                            if (selectedColumnIndexes.includes(index)) {
                                rowData.push(cell.innerText);
                            }
                        });
                        rows.push(rowData);
                    }
                });

                // ==== Adiciona resumo das funções no PDF ====
                doc.setFontSize(12);
                doc.text("Quantidade de tarefas por função:", 14, currentY + 10);

                let yResumo = currentY + 16;
                for (let funcao in agrupamentoFuncoes) {
                    doc.text(`${funcao}: ${agrupamentoFuncoes[funcao]}`, 14, yResumo);
                    yResumo += 6;
                }

                currentY = yResumo + 10; // avança Y para tabela

                if (rows.length > 0) {
                    doc.autoTable({
                        head: [headers],
                        body: rows,
                        startY: currentY
                    });

                    doc.save(`Relatório_Completo_${colaborador}_${mesNome}_${ano}.pdf`);
                } else {
                    alert("Nenhum dado disponível para gerar a lista.");
                }
            };
            reader.readAsDataURL(blob);
        })
        .catch(error => console.error('Erro ao carregar a imagem:', error));
});


// Função para converter números para texto
function numeroPorExtenso(num) {
    const unidades = [
        '', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove',
        'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis',
        'dezessete', 'dezoito', 'dezenove'
    ];
    const dezenas = [
        '', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta',
        'setenta', 'oitenta', 'noventa'
    ];
    const centenas = [
        '', 'cem', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos',
        'seiscentos', 'setecentos', 'oitocentos', 'novecentos'
    ];

    if (num === 0) return 'zero';

    let resultado = '';

    // Tratando milhares
    if (num >= 1000) {
        let milhar = Math.floor(num / 1000);
        resultado += milhar === 1 ? 'mil ' : `${unidades[milhar]} mil `;
        num %= 1000;
    }

    // Tratando centenas
    if (num >= 100) {
        let centena = Math.floor(num / 100);
        resultado += `${centenas[centena]} `;
        num %= 100;
    }

    // Tratando dezenas
    if (num >= 20) {
        let dezena = Math.floor(num / 10);
        resultado += `${dezenas[dezena]} `;
        num %= 10;
    }

    // Tratando unidades
    if (num > 0) {
        if (resultado.trim() !== '') {
            resultado += 'e '; // Adiciona "e" se já houver dezenas ou centenas
        }
        resultado += `${unidades[num]} `;
    }

    return resultado.trim(); // Remove espaços em branco no início e no fim
}


function exportToExcel() {
    // Seleciona a tabela HTML
    var tabela = document.getElementById('tabela-faturamento');

    // Converte a tabela para uma planilha usando SheetJS
    var planilha = XLSX.utils.table_to_sheet(tabela);

    // Cria um novo workbook e adiciona a planilha
    var wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, planilha, "Dados");

    // Pega as informações do colaborador, mês e ano
    const colaborador = document.getElementById('colaborador').options[document.getElementById('colaborador').selectedIndex].text;
    const mes = document.getElementById('mes').options[document.getElementById('mes').selectedIndex].text;
    const ano = new Date().getFullYear();

    // Define o nome do arquivo
    const nomeArquivo = `Relatório_${colaborador}_${mes}_${ano}.xlsx`;

    // Gera o arquivo Excel e faz o download com o nome personalizado
    XLSX.writeFile(wb, nomeArquivo);
}


// // ---- UI enhancement: convert status text into styled badges and keep them updated ----
// (function () {
//     const statusClassMap = {
//         'pendente_envio': 'status-pendente_envio',
//         'aguardando_retorno': 'status-aguardando_retorno',
//         'validado': 'status-validado',
//         'adendo_gerado': 'status-adendo_gerado',
//         'pago': 'status-pago'
//     };

//     function normalizeText(t) {
//         return (t || '').toString().trim().toLowerCase().replace(/\s+/g, '_');
//     }

//     function transformCell(td) {
//         if (!td) return;
//         const text = td.textContent.trim();
//         const key = normalizeText(text);
//         if (statusClassMap[key]) {
//             // avoid double-wrapping
//             if (td.querySelector('.status-badge')) return;
//             td.innerHTML = `<span class="status-badge ${statusClassMap[key]}">${text}</span>`;
//         }
//     }

//     function transformAll() {
//         // scan resumo and faturamento tables
//         const tds = Array.from(document.querySelectorAll('#tabela-resumo tbody td, #tabela-faturamento tbody td'));
//         tds.forEach(td => {
//             const txt = td.textContent.trim().toLowerCase();
//             // quick check: if cell text contains one of the known status words
//             if (txt.length === 0) return;
//             const normalized = normalizeText(txt);
//             if (Object.keys(statusClassMap).includes(normalized)) transformCell(td);
//         });
//     }

//     // Observe changes on the two tables and re-run transform when content changes
//     function observeTable(selector) {
//         const el = document.querySelector(selector);
//         if (!el) return;
//         const tbody = el.querySelector('tbody');
//         if (!tbody) return;
//         const mo = new MutationObserver(mutations => {
//             transformAll();
//         });
//         mo.observe(tbody, { childList: true, subtree: true, characterData: true });
//     }

//     document.addEventListener('DOMContentLoaded', function () {
//         // initial transform (in case server rendered statuses exist)
//         setTimeout(transformAll, 200);
//         observeTable('#tabela-resumo');
//         observeTable('#tabela-faturamento');
//     });

//     // also expose for manual invocation after dynamic updates
//     window._transformPagamentoStatusBadges = transformAll;
// })();

