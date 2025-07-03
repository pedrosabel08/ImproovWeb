// Obtém o 'obra_id' do localStorage
var obraId = localStorage.getItem('obraId');
var usuarioId = localStorage.getItem('idusuario');

usuarioId = Number(usuarioId);

if (usuarioId !== 1 && usuarioId !== 2 && usuarioId !== 9) {
    document.getElementById('acomp').classList.add('hidden')
    document.getElementById('obsAdd').classList.add('hidden')

    document.querySelectorAll(".campo input[type='text']").forEach(input => {
        input.readOnly = true;
    });
    document.querySelectorAll(".campo input[type='checkbox']").forEach(checkbox => {
        checkbox.disabled = true;
    });
} else {
    document.getElementById('acomp').style.display = 'block';
    document.getElementById('obsAdd').style.display = 'block';
    document.querySelectorAll(".campo input[type='text']").forEach(input => {
        input.readOnly = false;
    });
    document.querySelectorAll(".campo input[type='checkbox']").forEach(checkbox => {
        checkbox.disabled = false;
    });
}


let chartInstance = null;

document.querySelectorAll('.titulo').forEach(titulo => {
    titulo.addEventListener('click', () => {
        const opcoes = titulo.nextElementSibling;
        if (opcoes.style.display === 'none') {
            opcoes.style.display = 'flex';
            titulo.querySelector('i').classList.remove('fa-chevron-down');
            titulo.querySelector('i').classList.add('fa-chevron-up');
            opcoes.classList.add('show-in');
        } else {
            opcoes.style.display = 'none';
            titulo.querySelector('i').classList.remove('fa-chevron-up');
            titulo.querySelector('i').classList.add('fa-chevron-down');
        }
    });
});


function formatarData(data) {
    const partes = data.split("-");
    const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
    return dataFormatada;
}
function formatarDataDiaMes(data) {
    const partes = data.split("-");
    const dataFormatada = `${partes[2]}/${partes[1]}`;
    return dataFormatada;
}


function limparCampos() {
    document.getElementById("campoNomeImagem").textContent = "";

    document.getElementById("status_caderno").value = "";
    document.getElementById("prazo_caderno").value = "";
    document.getElementById("obs_caderno").value = "";
    document.getElementById("status_modelagem").value = "";
    document.getElementById("prazo_modelagem").value = "";
    document.getElementById("obs_modelagem").value = "";
    document.getElementById("status_comp").value = "";
    document.getElementById("prazo_comp").value = "";
    document.getElementById("obs_comp").value = "";
    document.getElementById("status_pre").value = "";
    document.getElementById("prazo_pre").value = "";
    document.getElementById("obs_pre").value = "";
    document.getElementById("status_finalizacao").value = "";
    document.getElementById("prazo_finalizacao").value = "";
    document.getElementById("obs_finalizacao").value = "";
    document.getElementById("status_pos").value = "";
    document.getElementById("prazo_pos").value = "";
    document.getElementById("obs_pos").value = "";
    document.getElementById("status_alteracao").value = "";
    document.getElementById("prazo_alteracao").value = "";
    document.getElementById("obs_alteracao").value = "";
    document.getElementById("status_planta").value = "";
    document.getElementById("prazo_planta").value = "";
    document.getElementById("obs_planta").value = "";
    document.getElementById("status_filtro").value = "";
    document.getElementById("prazo_filtro").value = "";
    document.getElementById("obs_filtro").value = "";

    document.getElementById("opcao_caderno").value = "";
    document.getElementById("opcao_model").value = "";
    document.getElementById("opcao_comp").value = "";
    document.getElementById("opcao_final").value = "";
    document.getElementById("opcao_pos").value = "";
    document.getElementById("opcao_alteracao").value = "";
    document.getElementById("opcao_planta").value = "";
    document.getElementById("opcao_filtro").value = "";
    document.getElementById("opcao_status").value = "";
    document.getElementById("opcao_pre").value = "";
    document.getElementById('opcao_finalizador').selectedIndex = 0; // Resetar select
    document.getElementById('opcao_obra_pos').selectedIndex = 0; // Resetar select
    document.getElementById('imagem_id_pos').value = ''; // Limpar campo de texto
    document.getElementById('id-pos').value = ''; // Limpar campo de texto
    document.getElementById('caminhoPasta').value = ''; // Limpar campo de texto
    document.getElementById('numeroBG').value = ''; // Limpar campo de texto
    document.getElementById('referenciasCaminho').value = ''; // Limpar campo de texto
    document.getElementById('observacao').value = ''; // Limpar campo de texto
}

let idsImagensObra = []; // Array para armazenar os IDs das imagens da obra
let indiceImagemAtual = 0; // Índice da imagem atualmente exibida no modal
let linhasTabela = [];

function addEventListenersToRows() {

    linhasTabela = document.querySelectorAll(".linha-tabela");

    linhasTabela.forEach(function (linha) {
        linha.addEventListener("click", function () {

            const statusImagem = linha.getAttribute("status");

            if (statusImagem === "STOP") {
                alert("Linha bloqueada, ação não permitida.");
                return;
            }

            linhasTabela.forEach(function (outraLinha) {
                outraLinha.classList.remove("selecionada");
            });

            linha.classList.add("selecionada");

            const idImagemSelecionada = linha.getAttribute("data-id");
            document.getElementById("imagem_id").value = idImagemSelecionada;

            // Encontrar o índice da imagem clicada no array de IDs
            indiceImagemAtual = idsImagensObra.indexOf(parseInt(idImagemSelecionada));

            console.log("Linha selecionada: ID da imagem = " + idImagemSelecionada);

            atualizarModal(idImagemSelecionada);
        });
    });
}

function atualizarModal(idImagem) {
    let nomePdf = '';
    // Limpar campos do formulário de edição
    limparCampos();

    // Fazer requisição AJAX para `buscaLinhaAJAX.php` usando Fetch
    fetch(`../buscaLinhaAJAX.php?ajid=${idImagem}`)
        .then(response => response.json())
        .then(response => {
            document.getElementById('form-edicao').style.display = 'flex';

            if (response.funcoes && response.funcoes.length > 0) {
                document.getElementById("campoNomeImagem").textContent = response.funcoes[0].imagem_nome;
                document.getElementById("mood").textContent = `Mood da cena: ${response.funcoes[0].clima || ''}`;

                const statusHoldSelect = document.getElementById('status_hold'); // Seleciona o elemento <select>

                statusHoldSelect.value = '';

                response.funcoes.forEach(function (funcao) {

                    if (funcao.nome_pdf && funcao.nome_pdf.trim() !== '') {
                        nomePdf = funcao.nome_pdf;
                    }
                    let selectElement;
                    let checkboxElement;
                    let revisaoImagemElement;
                    switch (funcao.nome_funcao) {
                        case "Caderno":
                            selectElement = document.getElementById("opcao_caderno");
                            document.getElementById("status_caderno").value = funcao.status;
                            document.getElementById("prazo_caderno").value = funcao.prazo;
                            document.getElementById("obs_caderno").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_caderno");
                            break;
                        case "Modelagem":
                            selectElement = document.getElementById("opcao_model");
                            document.getElementById("status_modelagem").value = funcao.status;
                            document.getElementById("prazo_modelagem").value = funcao.prazo;
                            document.getElementById("obs_modelagem").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_model");
                            break;
                        case "Composição":
                            selectElement = document.getElementById("opcao_comp");
                            document.getElementById("status_comp").value = funcao.status;
                            document.getElementById("prazo_comp").value = funcao.prazo;
                            document.getElementById("obs_comp").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_comp");

                            break;
                        case "Finalização":
                            selectElement = document.getElementById("opcao_final");
                            document.getElementById("status_finalizacao").value = funcao.status;
                            document.getElementById("prazo_finalizacao").value = funcao.prazo;
                            document.getElementById("obs_finalizacao").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_final");
                            break;
                        case "Pós-produção":
                            selectElement = document.getElementById("opcao_pos");
                            document.getElementById("status_pos").value = funcao.status;
                            document.getElementById("prazo_pos").value = funcao.prazo;
                            document.getElementById("obs_pos").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_pos");
                            break;
                        case "Alteração":
                            selectElement = document.getElementById("opcao_alteracao");
                            document.getElementById("status_alteracao").value = funcao.status;
                            document.getElementById("prazo_alteracao").value = funcao.prazo;
                            document.getElementById("obs_alteracao").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_alt");
                            break;
                        case "Planta Humanizada":
                            selectElement = document.getElementById("opcao_planta");
                            document.getElementById("status_planta").value = funcao.status;
                            document.getElementById("prazo_planta").value = funcao.prazo;
                            document.getElementById("obs_planta").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_ph");
                            break;
                        case "Filtro de assets":
                            selectElement = document.getElementById("opcao_filtro");
                            document.getElementById("status_filtro").value = funcao.status;
                            document.getElementById("prazo_filtro").value = funcao.prazo;
                            document.getElementById("obs_filtro").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_filtro");
                            break;
                        case "Pré-Finalização":
                            selectElement = document.getElementById("opcao_pre");
                            document.getElementById("status_pre").value = funcao.status;
                            document.getElementById("prazo_pre").value = funcao.prazo;
                            document.getElementById("obs_pre").value = funcao.observacao;
                            revisaoImagemElement = document.getElementById("revisao_imagem_pre");
                            break;
                    }


                    if (revisaoImagemElement) {
                        revisaoImagemElement.setAttribute('data-id-funcao', funcao.id);
                    }
                    if (selectElement) {
                        selectElement.value = funcao.colaborador_id;

                        // Verifica se o botão de limpar já existe
                        if (!selectElement.parentElement.querySelector('.clear-button')) {
                            // Adiciona o botão de limpar se o selectElement tiver um valor
                            if (selectElement.value) {
                                const clearButton = document.createElement('button');
                                clearButton.type = 'button'; // Define o tipo do botão como "button"
                                clearButton.innerHTML = '❌';
                                clearButton.classList.add('clear-button', 'tooltip');
                                clearButton.setAttribute('data-id', funcao.id); // Adiciona o ID da função ao botão
                                clearButton.setAttribute('data-tooltip', 'Excluir função'); // Adiciona o tooltip
                                clearButton.addEventListener('click', function (event) {
                                    event.preventDefault(); // Previne o comportamento padrão do botão
                                    const funcaoId = this.getAttribute('data-id');
                                    excluirFuncao(funcaoId, selectElement);
                                });
                                selectElement.parentElement.appendChild(clearButton);
                            }
                        }

                        // // Adiciona o botão de log se o selectElement tiver um valor
                        // if (!selectElement.parentElement.querySelector('.log-button')) {
                        //     if (selectElement.value) {
                        //         const logButton = document.createElement('button');
                        //         logButton.type = 'button'; // Define o tipo do botão como "button"
                        //         logButton.innerHTML = '<i class="fas fa-file-alt"></i>';
                        //         logButton.classList.add('log-button', 'tooltip');
                        //         logButton.setAttribute('data-id', funcao.id); // Adiciona o ID da função ao botão
                        //         logButton.setAttribute('data-tooltip', 'Exibir log'); // Adiciona o tooltip
                        //         logButton.addEventListener('click', function (event) {
                        //             event.preventDefault(); // Previne o comportamento padrão do botão
                        //             const funcaoId = this.getAttribute('data-id');
                        //             exibirLog(funcaoId);
                        //         });
                        //         selectElement.parentElement.appendChild(logButton);
                        //     }
                        // }
                    }
                    if (checkboxElement) {
                        checkboxElement.title = funcao.responsavel_aprovacao || '';
                    }
                    // Suponha que 'response.descricao' contenha os valores concatenados do GROUP_CONCAT
                    if (funcao.descricao) {
                        const statusHoldValues = funcao.descricao.split(','); // Converte a string em um array
                        const statusHoldSelect = document.getElementById('status_hold'); // Seleciona o elemento <select>

                        statusHoldSelect.value = '';
                        statusHoldSelect.style.display = 'block';

                        // Itera sobre os valores e marca os <option>s correspondentes
                        Array.from(statusHoldSelect.options).forEach(option => {
                            option.selected = statusHoldValues.includes(option.value);
                        });
                    }

                    if (!funcao.descricao || response.status_id != 9) {
                        const statusHoldSelect = document.getElementById('status_hold'); // Seleciona o elemento <select>
                        statusHoldSelect.style.display = 'none';
                    }

                });
            }
            const btnVerPdf = document.getElementById('ver-pdf');
            if (btnVerPdf) {
                if (nomePdf) {
                    btnVerPdf.setAttribute('data-nome-pdf', nomePdf);
                    btnVerPdf.style.display = 'inline-block';
                } else {
                    btnVerPdf.removeAttribute('data-nome-pdf');
                    btnVerPdf.style.display = 'none';
                }
            }

            const statusSelect = document.getElementById("opcao_status");
            if (response.status_id !== null) {
                statusSelect.value = response.status_id;
            }
        })
        .catch(error => console.error("Erro ao buscar dados da linha:", error));
}

const modalLogs = document.getElementById("modalLogs");


// Função para exibir o log, passando o ID da função
function exibirLog(funcaoId) {
    // Aqui você pode realizar uma requisição AJAX para pegar o log relacionado à função
    fetch(`../log_por_funcao.php?funcao_imagem_id=${funcaoId}`)
        .then(response => response.json())
        .then(data => {
            modalLogs.style.display = 'flex';
            const tabelaLogsBody = document.querySelector('#tabela-logs tbody');
            tabelaLogsBody.innerHTML = '';


            if (data && data.length > 0) {
                document.getElementById('nome_funcao_log').textContent = data[0].nome_funcao;
                data.forEach(log => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${log.status_anterior}</td>
                        <td>${log.status_novo}</td>
                        <td>${log.data}</td>
                    `;
                    tabelaLogsBody.appendChild(row);
                });
            } else {
                const row = document.createElement('tr');
                row.innerHTML = '<td colspan="5">Nenhum log encontrado.</td>';
                tabelaLogsBody.appendChild(row);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar os logs:', error);
        });
}

function excluirFuncao(funcaoId, selectElement) {
    fetch(`../excluirFuncao.php?id=${funcaoId}`, {
        method: 'POST'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                selectElement.value = '';
                selectElement.dispatchEvent(new Event('change')); // Dispara o evento de mudança

                // Remove os botões associados ao selectElement
                const clearButton = selectElement.parentElement.querySelector('.clear-button');
                const logButton = selectElement.parentElement.querySelector('.log-button');
                if (clearButton) clearButton.remove();
                if (logButton) logButton.remove();

                alert('Função excluída com sucesso!');
            } else {
                alert('Erro ao excluir função.');
            }
        })
        .catch(error => console.error('Erro ao excluir função:', error));
}


function updateWidth(input) {
    const hiddenText = input.parentElement.querySelector(".hidden-text"); // Encontra o span correto
    hiddenText.textContent = input.value || " "; // Evita colapso quando vazio
    input.style.width = hiddenText.offsetWidth + "px";
}

// Função para ajustar a altura do textarea com base nas quebras de linha
function adjustHeight(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = `${textarea.scrollHeight + 10}px`; // Aumenta 10px para cada linha adicional
}


const totaisPorFuncao = {};
const funcoes = ['caderno', 'filtro', 'modelagem', 'composicao', 'pre', 'finalizacao', 'pos_producao', 'alteracao', 'planta'];

funcoes.forEach(func => {
    totaisPorFuncao[func] = { total: 0, validos: 0 };
});

// Verifica se obraId está presente no localStorage
if (obraId) {
    infosObra(obraId);
    carregarEventos(obraId);
}
function infosObra(obraId) {

    fetch(`infosObra.php?obraId=${obraId}`)
        .then(response => response.json())
        .then(data => {
            // Verifica se os dados são válidos e não vazios
            if (!Array.isArray(data.imagens) || data.imagens.length === 0) {
                console.warn('Nenhuma função encontrada para esta obra.');
                data.imagens = [{ // Exemplo de dados padrão para evitar que a tabela fique vazia
                    imagem_nome: 'Sem imagem',
                    substatus: '-',
                    status: '-',
                    prazo: '-',
                    tipo_imagem: 'N/A',
                    caderno_colaborador: '-',
                    caderno_status: '-',
                    filtro_colaborador: '-',
                    filtro_status: '-',
                    modelagem_colaborador: '-',
                    modelagem_status: '-',
                    composicao_colaborador: '-',
                    composicao_status: '-',
                    pre_colaborador: '-',
                    pre_status: '-',
                    finalizacao_colaborador: '-',
                    finalizacao_status: '-',
                    pos_producao_colaborador: '-',
                    pos_producao_status: '-',
                    alteracao_colaborador: '-',
                    alteracao_status: '-',
                    planta_colaborador: '-',
                    planta_status: '-'
                }];
            }

            var tabela = document.querySelector('#tabela-obra tbody');
            tabela.innerHTML = ''; // Limpa a tabela antes de adicionar os novos dados

            let antecipada = 0;
            let imagens = 0;

            // Seleciona o elemento select
            const statusSelect = document.getElementById("imagem_status_filtro");
            const tipoImagemSelect = document.getElementById("tipo_imagem"); // Certifique-se de ter um <select> com id="tipo_imagem" no HTML

            tipoImagemSelect.innerHTML = '<option value="0">Todos</option>';
            statusSelect.innerHTML = '<option value="">Selecione um status</option>';

            // Objeto para armazenar os status únicos
            const statusUnicos = new Set();
            const tipoImagemUnicos = new Set();


            data.imagens.forEach(function (item) {
                idsImagensObra.push(parseInt(item.imagem_id));
                var row = document.createElement('tr');
                row.classList.add('linha-tabela');
                row.setAttribute('data-id', item.imagem_id);
                row.setAttribute('tipo-imagem', item.tipo_imagem)
                row.setAttribute('status', item.imagem_status)

                var cellStatus = document.createElement('td');
                cellStatus.textContent = item.imagem_status;
                row.appendChild(cellStatus);
                applyStatusImagem(cellStatus, item.imagem_status, item.descricao);

                var cellNomeImagem = document.createElement('td');
                cellNomeImagem.textContent = item.imagem_nome;
                cellNomeImagem.setAttribute('antecipada', item.antecipada);
                row.appendChild(cellNomeImagem);

                cellNomeImagem.addEventListener('mouseenter', (event) => {
                    tooltip.textContent = item.imagem_nome;
                    tooltip.style.display = 'block';
                    tooltip.style.left = event.clientX + 'px';
                    tooltip.style.top = event.clientY - 30 + 'px';
                });

                cellNomeImagem.addEventListener('mouseleave', () => {
                    tooltip.style.display = 'none';
                });

                cellNomeImagem.addEventListener('mousemove', (event) => {
                    tooltip.style.left = event.clientX + 'px';
                    tooltip.style.top = event.clientY - 30 + 'px';
                });

                imagens++;

                if (Boolean(parseInt(item.antecipada))) {
                    cellNomeImagem.style.backgroundColor = '#ff9d00';
                    antecipada++;
                }


                var cellSubStatus = document.createElement('td');
                cellSubStatus.textContent = item.imagem_sub_status;
                row.appendChild(cellSubStatus);
                applyStatusImagem(cellSubStatus, item.imagem_sub_status, item.descricao);

                statusUnicos.add(item.imagem_status);
                tipoImagemUnicos.add(item.tipo_imagem);


                var cellPrazo = document.createElement('td');
                cellPrazo.textContent = formatarDataDiaMes(item.prazo);
                row.appendChild(cellPrazo);

                var colunas = [
                    { col: 'caderno', label: 'Caderno' },
                    { col: 'filtro', label: 'Filtro' },
                    { col: 'modelagem', label: 'Modelagem' },
                    { col: 'composicao', label: 'Composição' },
                    { col: 'pre', label: 'Pré-Finalização' },
                    { col: 'finalizacao', label: 'Finalização' },
                    { col: 'pos_producao', label: 'Pós Produção' },
                    { col: 'alteracao', label: 'Alteração' },
                    { col: 'planta', label: 'Planta' }
                ];


                colunas.forEach(coluna => {
                    const colaborador = item[`${coluna.col}_colaborador`] || '-';
                    const status = item[`${coluna.col}_status`] || '-';

                    const cellColaborador = document.createElement('td');
                    cellColaborador.textContent = colaborador;

                    const cellStatus = document.createElement('td');
                    cellStatus.textContent = status;

                    row.appendChild(cellColaborador);
                    row.appendChild(cellStatus);

                    applyStyleNone(cellColaborador, cellStatus, colaborador);
                    applyStatusStyle(cellStatus, status, colaborador);


                    const statusNormalizado = status.trim().toLowerCase();
                    const statusValidos = ['em aprovação', 'aprovado', 'ajuste', 'finalizado', 'aprovado com ajustes'];

                    if (colaborador !== '-' && colaborador !== 'Não se aplica') {
                        totaisPorFuncao[coluna.col].total++;
                        if (statusValidos.includes(statusNormalizado)) {
                            totaisPorFuncao[coluna.col].validos++;
                        }
                    }

                });


                tabela.appendChild(row);
            });

            // Adiciona os valores únicos de status ao statusSelect
            statusUnicos.forEach(status => {
                let option = document.createElement("option");
                option.value = status;
                option.textContent = status;
                statusSelect.appendChild(option);
            });

            // Adiciona os valores únicos de tipo_imagem ao tipoImagemSelect
            tipoImagemUnicos.forEach(tipoImagem => {
                let tipoOption = document.createElement("option");
                tipoOption.value = tipoImagem;
                tipoOption.textContent = tipoImagem;
                tipoImagemSelect.appendChild(tipoOption);
            });

            filtrarTabela();


            const revisoes = document.getElementById('revisoes');
            revisoes.textContent = `Total de alterações: ${data.alt}`

            const alteracao = document.getElementById('altBtn')
            if (data.alt == 0) {
                alteracao.style.display = 'none';
            }

            // Determina o número de estrelas com base nas alterações
            let estrelas = 5;

            if (data.alt >= 41) {
                estrelas = 1;  // Para mais de 40 alterações, 1 estrela
            } else if (data.alt >= 31) {
                estrelas = 2;  // Para 31 a 40 alterações, 2 estrelas
            } else if (data.alt >= 21) {
                estrelas = 3;  // Para 21 a 30 alterações, 3 estrelas
            } else if (data.alt >= 11) {
                estrelas = 4;  // Para 11 a 20 alterações, 4 estrelas
            } else {
                estrelas = 5;  // Para 0 a 10 alterações, 5 estrelas
            }

            // Preenche as estrelas de acordo com o número calculado
            // Preenche as estrelas de acordo com o número calculado
            for (let i = 1; i <= 5; i++) {
                const estrela = document.getElementById(`estrela${i}`);
                if (estrela) {  // Verifica se a estrela existe
                    if (i <= estrelas) {
                        estrela.classList.add('preenchida');
                    } else {
                        estrela.classList.remove('preenchida');
                    }
                }
            }


            const btnAnterior = document.getElementById("btnAnterior");
            const btnProximo = document.getElementById("btnProximo");

            // Remover event listeners antes de adicionar para evitar duplicação
            btnAnterior.removeEventListener("click", navegarAnterior);
            btnProximo.removeEventListener("click", navegarProximo);
            btnAnterior.removeEventListener("touchstart", navegarAnterior);
            btnProximo.removeEventListener("touchstart", navegarProximo);
            document.removeEventListener("keydown", navegarTeclado);

            // Adiciona novamente os eventos com funções nomeadas para poderem ser removidas
            btnAnterior.addEventListener("click", navegarAnterior);
            btnProximo.addEventListener("click", navegarProximo);
            btnAnterior.addEventListener("touchstart", navegarAnterior);
            btnProximo.addEventListener("touchstart", navegarProximo);
            document.addEventListener("keydown", navegarTeclado);


            addEventListenersToRows();
            if (data.briefing && data.briefing.length > 0) {
                const br = data.briefing[0];

                document.getElementById('nivel').value = br.nivel;
                document.getElementById('conceito').value = br.conceito;
                document.getElementById('valor_media').value = br.valor_media;
                document.getElementById('outro_padrao').value = br.outro_padrao;
                document.getElementById('vidro').value = br.vidro;
                document.getElementById('esquadria').value = br.esquadria;
                document.getElementById('soleira').value = br.soleira;
                document.getElementById('assets').value = br.assets;
                document.getElementById('comp_planta').value = br.comp_planta;
                document.getElementById('acab_calcadas').value = br.acab_calcadas;
            }
            else {
                console.warn("Briefing não encontrado ou vazio."); // Apenas um aviso, sem erro no console
            }

            const obra = data.obra;
            document.getElementById('nomenclatura').textContent = obra.nomenclatura || "Nome não disponível";
            document.title = obra.nomenclatura || "Nome não disponível";
            document.getElementById('data_inicio_obra').textContent = `Data de Início: ${formatarData(obra.data_inicio)}`;
            document.getElementById('prazo_obra').textContent = `Prazo: ${formatarData(obra.prazo)}`;
            document.getElementById('dias_trabalhados').innerHTML = obra.dias_trabalhados ? `<strong>${obra.dias_trabalhados}</strong> dias` : '';
            document.getElementById('total_imagens').textContent = `Total de Imagens: ${obra.total_imagens}`;
            document.getElementById('total_imagens_antecipadas').textContent = `Imagens Antecipadas: ${obra.total_imagens_antecipadas}`;
            document.getElementById('local').value = `${obra.local}`;
            document.getElementById('altura_drone').value = `${obra.altura_drone}`;
            document.getElementById('link_drive').value = `${obra.link_drive}`;

            // const infosDiv = document.getElementById('infos');

            // // Limpa o conteúdo da div
            // infosDiv.innerHTML = "";

            // Verifica se há dados no array
            if (data.infos.length === 0) {
                document.querySelector('.infos-container').style.display = 'none';

            } else {

                // Seleciona a tabela onde as informações serão inseridas
                const tabela = document.getElementById("tabelaInfos");

                // Limpa a tabela antes de adicionar os novos dados
                tabela.querySelector("tbody").innerHTML = "";

                // Preenche a tabela com as informações
                data.infos.forEach(info => {
                    const linha = document.createElement('tr'); // Cria uma linha para cada info

                    linha.innerHTML = `
                        <td>${info.descricao}</td>
                        <td>${formatarData(info.data)}</td>
                    `;

                    linha.setAttribute("data-id", info.id); // Adiciona o ID da imagem à linha
                    linha.setAttribute("ordem", info.ordem); // Adiciona o ID da imagem à linha

                    tabela.querySelector("tbody").appendChild(linha); // Adiciona a linha na tabela

                    // Adiciona evento de clique a cada linha da tabelaInfos
                    linha.addEventListener('click', function () {
                        const descricaoId = this.getAttribute('data-id');
                        const descricao = this.querySelector('td:nth-child(1)').textContent;

                        // Preenche o modal com os dados da linha clicada
                        document.getElementById('descricaoId').value = descricaoId;
                        document.getElementById('desc').value = descricao;

                        const deleteObs = document.getElementById('deleteObs');
                        deleteObs.setAttribute('data-id', descricaoId);
                        deleteObs.addEventListener('click', function () {
                            const id = this.getAttribute('data-id');
                            fetch(`deleteObs.php?id=${id}`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',  // Forma correta de enviar dados via POST
                                },
                                body: `id=${id}`  // Envia o id no corpo da requisição
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        alert('Observação excluída com sucesso!');
                                        document.getElementById('modalObservacao').style.display = 'none';
                                    } else {
                                        alert('Erro ao excluir observação.');
                                    }
                                })
                                .catch(error => console.error('Erro ao excluir observação:', error));
                        });

                        // Exibe o modal
                        document.getElementById('modalObservacao').style.display = 'block';
                    });


                });

                // Inicializa o DataTables se ainda não foi inicializado
                if (!$.fn.DataTable.isDataTable('#tabelaInfos')) {
                    $(document).ready(function () {
                        $('#tabelaInfos').DataTable({
                            "paging": false,
                            "lengthChange": false,
                            "info": false,
                            "ordering": true,
                            "searching": true,
                            "order": [], // Remove a ordenação padrão
                            "columnDefs": [{
                                "targets": 0, // Aplica a ordenação na primeira coluna
                                "orderData": function (row, type, set, meta) {
                                    // Retorna o valor do atributo data-id para a ordenação
                                    return $(row).attr('ordem');
                                }
                            }],
                            "language": {
                                "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Portuguese.json"
                            }
                        });
                    });
                }


                // Inicializa o SortableJS na tabela
                new Sortable(tabela.querySelector("tbody"), {
                    animation: 150,
                    onEnd: function (evt) {
                        // Obtém a nova ordem das linhas
                        const linhas = Array.from(tabela.querySelectorAll("tbody tr"));
                        const novaOrdem = linhas.map(linha => linha.getAttribute("data-id"));

                        // Envia a nova ordem para o servidor (opcional)
                        fetch('atualizarOrdem.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ ordem: novaOrdem }),
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Toastify({
                                        text: "Ordem atualizada com sucesso!",
                                        duration: 3000,
                                        gravity: "top",
                                        position: "right",
                                        backgroundColor: "#4caf50", // Cor de sucesso
                                    }).showToast();
                                } else {
                                    Toastify({
                                        text: "Erro ao atualizar ordem.",
                                        duration: 3000,
                                        gravity: "top",
                                        position: "right",
                                        backgroundColor: "#f44336", // Cor de erro
                                    }).showToast();
                                }
                            })
                            .catch(error => {
                                console.error("Erro ao atualizar ordem:", error);
                                Toastify({
                                    text: "Erro ao atualizar ordem.",
                                    duration: 3000,
                                    gravity: "top",
                                    position: "right",
                                    backgroundColor: "#f44336", // Cor de erro
                                }).showToast();
                            });
                    }
                });

            }

            if (data.recebimentos && data.recebimentos.length > 0) {
                data.recebimentos.forEach(recebimento => {
                    const tipoImagem = recebimento.tipo_imagem;
                    const datasRecebimento = recebimento.datas_recebimento.split(', '); // Divide as datas por vírgula
                    const primeiraData = datasRecebimento[0]; // Pega a primeira data

                    // Mapeia os IDs dos campos de data com base no tipo de imagem
                    const campoDataMap = {
                        "Fachada": "data-fachada",
                        "Imagem Externa": "data-imagens-externas",
                        "Imagem Interna": "data-internas-comuns",
                        "Unidades": "data-unidades",
                        "Planta Humanizada": "data-ph"
                    };

                    // Preenche o campo de data correspondente
                    const campoData = document.getElementById(campoDataMap[tipoImagem]);
                    if (campoData) {
                        campoData.value = primeiraData; // Define a primeira data no campo
                    }
                });
            }
            const prazosDiv = document.getElementById('prazos-list');

            // Limpa o conteúdo da div
            prazosDiv.innerHTML = "";

            // // Agrupa os prazos por status
            // const groupedPrazos = data.prazos.reduce((acc, prazo) => {
            //     if (!acc[prazo.nome_status]) {
            //         acc[prazo.nome_status] = [];
            //     }
            //     acc[prazo.nome_status].push({
            //         prazo: prazo.prazo,
            //         idsImagens: prazo.idImagens || [] // Use idImagens conforme o JSON retornado
            //     });
            //     return acc;
            // }, {});

            // // Renderiza os cards agrupados
            // Object.entries(groupedPrazos).forEach(([status, prazos]) => {
            //     const prazoList = document.createElement('div');
            //     prazoList.classList.add('prazos');

            //     prazoList.innerHTML = `
            //     <div class="prazo-card">
            //         <p class="nome_status">${status}</p>
            //         <ul>
            //         ${prazos.map(prazo => `
            //             <li 
            //                 data-ids="${(prazo.idsImagens || []).join(',')}" 
            //                 class="prazo-item">
            //                 ${formatarData(prazo.prazo)}
            //             </li>`).join("")}
            //         </ul>
            //     </div>
            // `;

            //     const prazoCard = prazoList.querySelector('.prazo-card');
            //     applyStatusImagem(prazoCard, status);
            //     prazosDiv.appendChild(prazoList);
            // });

            // // Adiciona eventos de mouse para estilizar linhas da tabela
            // prazosDiv.addEventListener('mouseover', (event) => {
            //     const target = event.target.closest('.prazo-item');
            //     if (target) {
            //         const ids = target.getAttribute('data-ids').split(',');
            //         ids.forEach(id => {
            //             const linha = document.querySelector(`tr[data-id="${id}"]`);
            //             if (linha) linha.classList.add('highlight');
            //         });
            //     }
            // });

            // prazosDiv.addEventListener('mouseout', (event) => {
            //     const target = event.target.closest('.prazo-item');
            //     if (target) {
            //         const ids = target.getAttribute('data-ids').split(',');
            //         ids.forEach(id => {
            //             const linha = document.querySelector(`tr[data-id="${id}"]`);
            //             if (linha) linha.classList.remove('highlight');
            //         });
            //     }
            // });

        })
        .catch(error => console.error('Erro ao carregar funções:', error));
}

var colunas = [
    { col: 'caderno', label: 'Caderno' },
    { col: 'filtro', label: 'Filtro' },
    { col: 'modelagem', label: 'Modelagem' },
    { col: 'composicao', label: 'Composição' },
    { col: 'pre', label: 'Pré-Finalização' },
    { col: 'finalizacao', label: 'Finalização' },
    { col: 'pos_producao', label: 'Pós Produção' },
    { col: 'alteracao', label: 'Alteração' },
    { col: 'planta', label: 'Planta' }
];

function inicializarLinhaPorcentagem() {
    const linhaPorcentagem = document.getElementById('linha-porcentagem');
    linhaPorcentagem.innerHTML = '';

    // 3 colunas fixas (imagem, status geral, prazo)
    for (let i = 0; i < 3; i++) {
        linhaPorcentagem.appendChild(document.createElement('td'));
    }

    // Duas <td> para cada função (colaborador e status)
    colunas.forEach(() => {
        linhaPorcentagem.appendChild(document.createElement('td')); // colaborador
        linhaPorcentagem.appendChild(document.createElement('td')); // status
    });

    // linhaPorcentagem.style.display = 'table-row';
}
inicializarLinhaPorcentagem();


function mostrarPorcentagem(funcaoSelecionada) {
    const linhaPorcentagem = document.getElementById('linha-porcentagem');
    if (!linhaPorcentagem) {
        console.error('Elemento linha-porcentagem não encontrado.');
        return;
    }

    const totais = totaisPorFuncao[funcaoSelecionada];
    if (!totais) {
        console.error(`totaisPorFuncao[${funcaoSelecionada}] indefinido`);
        return;
    }
    const { total, validos } = totais;
    const porcentagem = total > 0 ? Math.round((validos / total) * 100) : 0;

    const indexFuncao = colunas.findIndex(c => c.col === funcaoSelecionada);
    if (indexFuncao === -1) {
        console.error(`Função '${funcaoSelecionada}' não encontrada no array colunas.`);
        return;
    }
    const indexTd = 3 + (indexFuncao * 2) + 1;

    linhaPorcentagem.querySelectorAll('td').forEach(td => td.textContent = '');

    const tdAlvo = linhaPorcentagem.children[indexTd];
    if (!tdAlvo) {
        console.error(`tdAlvo undefined no índice ${indexTd}`);
        return;
    }

    tdAlvo.textContent = porcentagem + '%';
    tdAlvo.style.fontWeight = 'bold';
    tdAlvo.style.color = '#007bff';
}


// Criar funções separadas para evitar problemas de referência
function navegarAnterior() {
    navegar(-1);
}

function navegarProximo() {
    navegar(1);
}

function navegarTeclado(event) {
    if (form_edicao && form_edicao.style.display === "flex") {
        if (event.key === "ArrowLeft") {
            navegar(-1);
        } else if (event.key === "ArrowRight") {
            navegar(1);
        }
    }
}


function navegar(direcao) {
    // Atualiza o índice da imagem atual
    indiceImagemAtual += direcao;

    // Garante que o índice está dentro dos limites
    if (indiceImagemAtual < 0) {
        indiceImagemAtual = idsImagensObra.length - 1;
    } else if (indiceImagemAtual >= idsImagensObra.length) {
        indiceImagemAtual = 0;
    }

    // Obtém o ID da imagem atual
    const idImagem = idsImagensObra[indiceImagemAtual];
    atualizarModal(idImagem);
    document.getElementById("imagem_id").value = idImagem;

    // Atualiza a seleção na tabela
    linhasTabela.forEach(linha => linha.classList.remove("selecionada"));
    let linhaSelecionada = document.querySelector(`tr[data-id="${idImagem}"]`);
    if (linhaSelecionada) {
        linhaSelecionada.classList.add("selecionada");
    }
}

const tooltip = document.getElementById('tooltip');

function applyStatusImagem(cell, status, descricao = '') {
    switch (status) {
        case 'P00':
            cell.style.backgroundColor = '#ffc21c'
            break;
        case 'R00':
            cell.style.backgroundColor = '#1cf4ff'
            break;
        case 'R01':
            cell.style.backgroundColor = '#ff6200'
            break;
        case 'R02':
            cell.style.backgroundColor = '#ff3c00'
            break;
        case 'R03':
            cell.style.backgroundColor = '#ff0000'
            break;
        case 'EF':
            cell.style.backgroundColor = '#0dff00'
            break;
        case 'HOLD':
            cell.style.backgroundColor = '#ff0000';
            cell.classList.add('tool'); // Adiciona a classe tooltip
            if (descricao) {
                cell.addEventListener('mouseenter', (event) => {
                    tooltip.textContent = descricao;
                    tooltip.style.display = 'block';
                    tooltip.style.left = event.clientX + 'px';
                    tooltip.style.top = event.clientY - 30 + 'px';
                });

                cell.addEventListener('mouseleave', () => {
                    tooltip.style.display = 'none';
                });

                cell.addEventListener('mousemove', (event) => {
                    tooltip.style.left = event.clientX + 'px';
                    tooltip.style.top = event.clientY - 30 + 'px';
                });
            }
            break;
        case 'TEA':
            cell.style.backgroundColor = '#f7eb07';
            break;
        case 'REN':
            cell.style.backgroundColor = '#0c9ef2';
            break;
        case 'APR':
            cell.style.backgroundColor = '#0c45f2';
            cell.style.color = 'white';
            break;
        case 'APP':
            cell.style.backgroundColor = '#7d36f7';
        case 'RVW':
            cell.style.backgroundColor = 'green';
            cell.style.color = 'white';
            break;
        case 'OK':
            cell.style.backgroundColor = 'cornflowerblue';
            cell.style.color = 'white';
            break;
        case 'FIN':
            cell.style.backgroundColor = 'green';
            cell.style.color = 'white';
            break;
    }
};



function filtrarTabela() {
    var tipoImagemFiltro = document.getElementById("tipo_imagem").value.toLowerCase();
    var antecipadaFiltro = document.getElementById("antecipada_obra").value;
    var statusImagemFiltro = document.getElementById("imagem_status_filtro").value;
    var tabela = document.getElementById("tabela-obra");
    var tbody = tabela.getElementsByTagName("tbody")[0];
    var linhas = tbody.getElementsByTagName("tr");

    let imagensFiltradas = 0;
    let antecipadasFiltradas = 0;

    for (var i = 0; i < linhas.length; i++) {
        var tipoImagemColuna = linhas[i].getAttribute("tipo-imagem");
        var isAntecipada = linhas[i].querySelector('td[antecipada]').getAttribute("antecipada") === '1';
        var statusColuna = linhas[i].querySelector("td:nth-child(2)").textContent.trim(); // Pegando o status da terceira coluna (ajuste conforme necessário)
        var mostrarLinha = true;

        if (tipoImagemFiltro && tipoImagemFiltro !== "0" && tipoImagemColuna.toLowerCase() !== tipoImagemFiltro.toLowerCase()) {
            mostrarLinha = false;
        }

        if (antecipadaFiltro === "1" && !isAntecipada) {
            mostrarLinha = false;
        }


        // Filtra pelo status da imagem
        if (statusImagemFiltro && statusImagemFiltro !== "0" && statusColuna !== statusImagemFiltro) {
            mostrarLinha = false;
        }

        if (mostrarLinha) {
            imagensFiltradas++;
            if (isAntecipada) antecipadasFiltradas++;
        }

        linhas[i].style.display = mostrarLinha ? "" : "none";
    }

    const imagens_totais = document.getElementById('imagens-totais')
    imagens_totais.textContent = `Total de imagens: ${imagensFiltradas}`
    const antecipadas = document.getElementById('antecipadas')
    antecipadas.textContent = `Antecipadas: ${antecipadasFiltradas}`;
}

// Adiciona evento para filtrar sempre que o filtro mudar
document.getElementById("tipo_imagem").addEventListener("change", filtrarTabela);
document.getElementById("antecipada_obra").addEventListener("change", filtrarTabela);
document.getElementById("imagem_status_filtro").addEventListener("change", filtrarTabela);

function applyStatusStyle(cell, status, colaborador) {
    if (colaborador === 'Não se aplica') {
        return;
    }

    switch (status) {
        case 'Finalizado':
            cell.style.backgroundColor = 'green';
            cell.style.color = 'white';
            break;
        case 'Em andamento':
            cell.style.backgroundColor = '#f7eb07';
            cell.style.color = 'black';
            break;
        case 'Em aprovação':
            cell.style.backgroundColor = '#0c45f2';
            cell.style.color = 'white';
            break;
        case 'Aprovado':
            cell.style.backgroundColor = 'lightseagreen';
            cell.style.color = 'black';
            break;
        case 'Ajuste':
            cell.style.backgroundColor = 'orangered';
            cell.style.color = 'black';
            break;
        case 'Aprovado com ajustes':
            cell.style.backgroundColor = 'mediumslateblue';
            cell.style.color = 'black';
            break;
        default:
            cell.style.backgroundColor = '';
            cell.style.color = '';
    }
}

function applyStyleNone(cell, cell2, nome) {
    if (nome === 'Não se aplica') {
        cell.style.backgroundColor = '#b4b4b4';
        cell.style.color = 'black';
        cell2.style.backgroundColor = '#b4b4b4';
        cell2.style.color = 'black';
    } else {
        cell.style.backgroundColor = '';
        cell.style.color = '';
        cell2.style.backgroundColor = '';
        cell2.style.color = '';
    }
}

// Seleciona todos os selects com id que começam com 'status_'
const statusSelects = document.querySelectorAll("select[id^='status_']");

statusSelects.forEach(select => {
    select.addEventListener("change", function () {
        // Pega o próximo elemento irmão que possui a classe 'revisao_imagem'
        const revisaoImagem = this.closest('.funcao').querySelector('.revisao_imagem');

        if (this.value === "Em aprovação") {
            revisaoImagem.style.display = "block";
        } else {
            revisaoImagem.style.display = "none";
        }

        // Pega o próximo elemento de prazo
        const prazoInput = this.closest('.funcao').querySelector('input[type="date"]');

        if (this.value === "Em andamento") {
            prazoInput.required = true;
        } else {
            prazoInput.required = false;
        }
    });
});

const selectStatus = document.getElementById("opcao_status");
const statusHold = document.getElementById("status_hold");

selectStatus.addEventListener("change", function () {
    if (parseInt(this.value) === 9) {
        statusHold.style.display = "block";
    } else {
        statusHold.style.display = "none";
    }
});

$(document).ready(function () {
    $('#status_hold option').on('mousedown', function (e) {
        e.preventDefault(); // Evita o comportamento padrão do mousedown

        const $option = $(this);
        const imagemId = $('#imagem_id').val();
        const valor = $option.val();

        if ($option.prop('selected')) {
            // Se já está selecionado, vamos desmarcar e deletar do banco
            $option.prop('selected', false);

            $.ajax({
                url: 'delete_status_hold.php',
                method: 'POST',
                data: {
                    imagem_id: imagemId,
                    status: valor
                },
                success: function (response) {
                    console.log('Deletado com sucesso:', response);
                },
                error: function () {
                    console.error('Erro ao deletar o status.');
                }
            });
        } else {
            // Se não está selecionado, apenas marca (sem salvar no banco ainda)
            $option.prop('selected', true);
        }

        return false;
    });
});


document.getElementById("salvar_funcoes").addEventListener("click", function (event) {
    event.preventDefault();

    var linhaSelecionada = document.querySelector(".linha-tabela.selecionada");
    if (!linhaSelecionada) {
        Toastify({
            text: "Nenhuma imagem selecionada",
            duration: 3000,
            close: true,
            gravity: "top",
            position: "left",
            backgroundColor: "red",
            stopOnFocus: true,
        }).showToast();
        return;
    }

    var idImagemSelecionada = linhaSelecionada.getAttribute("data-id");

    var form = document.getElementById("form-add");
    var camposPrazo = form.querySelectorAll("input[type='date'][required]");
    var camposVazios = Array.from(camposPrazo).filter(input => !input.value);

    if (camposVazios.length > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção!',
            text: 'Coloque a data de quando irá terminar a tarefa!',
            confirmButtonText: 'Ok',
            confirmButtonColor: '#f39c12',
        });
        return;
    }

    const statusAnteriorAjuste = [
        "status_caderno", "status_comp", "status_modelagem", "status_finalizacao",
        "status_pre", "status_pos", "status_alteracao", "status_planta", "status_filtro"
    ].some(id => {
        const el = document.getElementById(id);
        return el && el.value === "Aprovado com ajustes";
    });

    var textos = {};
    document.querySelectorAll(".form-edicao p").forEach(function (p) {
        textos[p.id] = p.textContent.trim();
    });

    const dados = {
        imagem_id: idImagemSelecionada,
        caderno_id: document.getElementById("opcao_caderno").value || "",
        status_caderno: document.getElementById("status_caderno").value || "",
        prazo_caderno: document.getElementById("prazo_caderno").value || "",
        obs_caderno: document.getElementById("obs_caderno").value || "",
        comp_id: document.getElementById("opcao_comp").value || "",
        status_comp: document.getElementById("status_comp").value || "",
        prazo_comp: document.getElementById("prazo_comp").value || "",
        obs_comp: document.getElementById("obs_comp").value || "",
        modelagem_id: document.getElementById("opcao_model").value || "",
        status_modelagem: document.getElementById("status_modelagem").value || "",
        prazo_modelagem: document.getElementById("prazo_modelagem").value || "",
        obs_modelagem: document.getElementById("obs_modelagem").value || "",
        finalizacao_id: document.getElementById("opcao_final").value || "",
        status_finalizacao: document.getElementById("status_finalizacao").value || "",
        prazo_finalizacao: document.getElementById("prazo_finalizacao").value || "",
        obs_finalizacao: document.getElementById("obs_finalizacao").value || "",
        pre_id: document.getElementById("opcao_pre").value || "",
        status_pre: document.getElementById("status_pre").value || "",
        prazo_pre: document.getElementById("prazo_pre").value || "",
        obs_pre: document.getElementById("obs_pre").value || "",
        pos_id: document.getElementById("opcao_pos").value || "",
        status_pos: document.getElementById("status_pos").value || "",
        prazo_pos: document.getElementById("prazo_pos").value || "",
        obs_pos: document.getElementById("obs_pos").value || "",
        alteracao_id: document.getElementById("opcao_alteracao").value || "",
        status_alteracao: document.getElementById("status_alteracao").value || "",
        prazo_alteracao: document.getElementById("prazo_alteracao").value || "",
        obs_alteracao: document.getElementById("obs_alteracao").value || "",
        planta_id: document.getElementById("opcao_planta").value || "",
        status_planta: document.getElementById("status_planta").value || "",
        prazo_planta: document.getElementById("prazo_planta").value || "",
        obs_planta: document.getElementById("obs_planta").value || "",
        filtro_id: document.getElementById("opcao_filtro").value || "",
        status_filtro: document.getElementById("status_filtro").value || "",
        prazo_filtro: document.getElementById("prazo_filtro").value || "",
        obs_filtro: document.getElementById("obs_filtro").value || "",
        textos: textos,
        status_id: document.getElementById("opcao_status").value || ""
    };

    const loadingBar = document.getElementById('loadingBar');
    loadingBar.style.display = 'block'; // mostra a barra

    function enviarFormulario() {
        $.ajax({
            type: "POST",
            url: "https://www.improov.com.br/sistema/insereFuncao.php",
            data: dados,
            success: function (response) {
                Toastify({
                    text: "Dados salvos com sucesso!",
                    duration: 3000,
                    close: true,
                    gravity: "top",
                    position: "left",
                    backgroundColor: "green",
                    stopOnFocus: true,
                }).showToast();
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Erro ao salvar dados: " + textStatus, errorThrown);
                Toastify({
                    text: "Erro ao salvar dados.",
                    duration: 3000,
                    close: true,
                    gravity: "top",
                    position: "left",
                    backgroundColor: "red",
                    stopOnFocus: true,
                }).showToast();
            },
            complete: function () {
                loadingBar.style.display = 'none'; // mostra a barra
            }
        });
    }

    if (statusAnteriorAjuste) {
        Swal.fire({
            title: "Atenção!",
            text: "Há uma função anterior com o status 'Aprovado com ajustes'. Você já conferiu?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Sim, já conferi",
            cancelButtonText: "Não, revisar agora"
        }).then((result) => {
            if (result.isConfirmed) {
                enviarFormulario();
            }
        });
    } else {
        enviarFormulario();
    }
    // Segundo fetch - agora enviando como JSON
    var fileInputs = document.querySelectorAll("input[type='file']");
    var filesExistem = Array.from(fileInputs).some(input => input.files.length > 0);
    const dataIdFuncoes = [];

    const formData = new FormData();

    console.log("Arquivos existem?", filesExistem);

    fileInputs.forEach(input => {
        // Verifica se o input tem arquivos
        if (input.files.length > 0) {
            const dataIdFuncao = input.getAttribute('data-id-funcao');

            // Adiciona apenas se o data-id-funcao existir e o input tiver arquivos
            if (dataIdFuncao && dataIdFuncao.trim() !== '') {
                dataIdFuncoes.push(dataIdFuncao);
            }

            // Adiciona os arquivos ao FormData
            for (let i = 0; i < input.files.length; i++) {
                formData.append('imagens[]', input.files[i]);
            }
        }
    });

    if (filesExistem) {
        // Adicionando apenas os valores válidos de dataIdFuncoes
        formData.append('dataIdFuncoes', JSON.stringify(dataIdFuncoes));

        console.log("Funções válidas: ", dataIdFuncoes);  // Para ver o array filtrado

        fetch('../uploadArquivos.php', {
            method: "POST",
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log(data);
                // Aqui você pode adicionar lógica para lidar com a resposta do servidor
            })
            .catch(error => {
                console.error('Erro:', error);
            })
            .finally(() => {
                loadingBar.style.display = 'none'; // mostra a barra

            });

    }

    // Obtém os valores selecionados no campo status_hold
    const selectedOptions = Array.from(statusHold.selectedOptions).map(option => option.value);

    const obraId = localStorage.getItem("obraId");


    if (selectedOptions.length > 0) {
        // Dados a serem enviados para o backend
        const data = {
            status_hold: selectedOptions,
            imagem_id: idImagemSelecionada,
            obra_id: obraId
        };

        // Envia os dados para o backend via fetch
        fetch("../atualizarStatusHold.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(data)
        })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    Toastify({
                        text: "Status HOLD atualizado com sucesso!",
                        duration: 3000,
                        backgroundColor: "green",
                        close: true,
                        gravity: "top",
                        position: "right"
                    }).showToast();
                } else {
                    Toastify({
                        text: "Erro ao atualizar o status HOLD.",
                        duration: 3000,
                        backgroundColor: "red",
                        close: true,
                        gravity: "top",
                        position: "right"
                    }).showToast();
                }
            })
            .catch(error => {
                console.error("Erro ao atualizar o status HOLD:", error);
                Toastify({
                    text: "Erro ao atualizar o status HOLD.",
                    duration: 3000,
                    backgroundColor: "red",
                    close: true,
                    gravity: "top",
                    position: "right"
                }).showToast();
            })
            .finally(() => {
                loadingBar.style.display = 'none'; // mostra a barra

            });
    }
});

const addImagemModal = document.getElementById('add-imagem');
const addImagem = document.getElementById('addImagem');
addImagem.addEventListener('click', function () {
    addImagemModal.style.display = 'flex';
})

const editArquivos = document.getElementById('editArquivos');
const editImagesBtn = document.getElementById('editImagesBtn');
const labelSwitch = document.querySelectorAll('.switch');
const iduser = parseInt(localStorage.getItem('idusuario'));

if (![1, 2, 9].includes(iduser)) {
    editArquivos.style.display = 'none';
    editImagesBtn.style.display = 'none';
    addImagem.style.display = 'none';

    labelSwitch.forEach(label => {
        label.style.display = 'none';
    });
}

const modalArquivos = document.getElementById('modalArquivos');

editArquivos.addEventListener('click', function () {
    modalArquivos.style.display = 'flex';
});

document.getElementById("salvarArquivo").addEventListener("click", function () {
    const obraId = localStorage.getItem("obraId");
    const dataArquivos = document.getElementById("data_arquivos").value;
    const tiposSelecionados = [];

    document.querySelectorAll(".tipo-imagem").forEach(checkbox => {
        if (checkbox.checked) {
            const tipo = checkbox.getAttribute("data-tipo");

            // Correto: pegar o container pai do checkbox, depois localizar os inputs relacionados
            const arquivoItem = checkbox.closest(".arquivo-item");

            // Agora sim: pegar corretamente a data relacionada
            const data_arquivosInput = document.getElementById("data_arquivos");
            const data_arquivos = data_arquivosInput ? data_arquivosInput.value : "";

            // E pegar os subtipos relacionados
            const subtipos = {};
            const subtipoContainer = arquivoItem.querySelector(".subtipos");

            if (subtipoContainer) {
                subtipoContainer.querySelectorAll("input[type='checkbox']").forEach(subCheckbox => {
                    const nomeSubtipo = subCheckbox.parentNode.textContent.trim();
                    subtipos[nomeSubtipo] = subCheckbox.checked;
                });
            }

            tiposSelecionados.push({
                tipo: tipo,
                dataRecebimento: data_arquivos,
                subtipos: subtipos
            });
        }
    });

    if (tiposSelecionados.length === 0) {
        alert("Selecione pelo menos um tipo de imagem!");
        return;
    }

    console.log(tiposSelecionados); // Agora deve vir completinho!

    // Enviar pro backend
    fetch("atualizar_prazo.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ obraId, dataArquivos, tiposSelecionados })
    })
        .then(response => response.text())
        .then(data => {
            Swal.fire({
                icon: 'success',
                text: 'Prazo atualizado com sucesso!',
                showConfirmButton: false,
                timer: 1000
            });
            modalArquivos.style.display = 'none';
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                text: 'Erro ao atualizar o prazo. Tente novamente!',
                showConfirmButton: true
            });
            console.error("Erro:", error);
        });
});

function showModal() {
    document.getElementById('modal-meta').style.display = 'block';
}

function fecharModal() {
    document.getElementById('modal-meta').style.display = 'none';
}


const modalInfos = document.getElementById('modalInfos')
const modalOrcamento = document.getElementById('modalOrcamento')
const modal = document.getElementById('modalAcompanhamento');
const modalObs = document.getElementById('modalObservacao');
const modalImages = document.getElementById('editImagesModal');
const infosModal = document.getElementById('infosModal');
const form_edicao = document.getElementById('form-edicao');


const idObra = localStorage.getItem('obraId');

if (idObra) {
    abrirModalAcompanhamento(idObra); // Carrega os acompanhamentos automaticamente
} else {
    console.warn('ID da obra não encontrado no localStorage.');
}

function abrirModalAcompanhamento(obraId) {
    fetch(`../Obras/getAcompanhamentoEmail.php?idobra=${obraId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erro ao carregar dados: ${response.status}`);
            }
            return response.json(); // Converte a resposta para JSON
        })
        .then(acompanhamentos => {
            // Limpa o conteúdo anterior
            acompanhamentoConteudo.innerHTML = '';

            if (acompanhamentos.length > 0) {
                acompanhamentos.forEach(acomp => {

                    const item = document.createElement('p');
                    item.innerHTML = `
                        <div class="acomp-conteudo">
                            <p class="acomp-assunto"><strong>Assunto:</strong> ${acomp.assunto}</p>
                            <p class="acomp-data"><strong>Data:</strong> ${formatarData(acomp.data)}</p>
                        </div>
                    `;
                    acompanhamentoConteudo.appendChild(item);
                });
            } else {
                acompanhamentoConteudo.innerHTML = '<p>Nenhum acompanhamento encontrado.</p>';
            }

        })
        .catch(error => {
            console.error('Erro:', error);
        });
}



document.getElementById('acomp').addEventListener('click', function () {
    modal.style.display = 'block';
});

document.getElementById('obsAdd').addEventListener('click', function () {
    modalObs.style.display = 'block';
    limparCamposFormulario();

});

function limparCamposFormulario() {
    document.getElementById('descricaoId').value = '';
    document.getElementById('desc').value = '';
}

document.querySelectorAll('.close-modal').forEach(closeButton => {
    closeButton.addEventListener('click', function () {
        const modal = this.closest('.modal');
        if (modal) {
            modal.style.display = 'none';
        }
    });

    closeButton.addEventListener('touchstart', function () {
        const modal = this.closest('.modal');
        if (modal) {
            modal.style.display = 'none';
        }
    });
});

document.querySelectorAll('.close').forEach(closeButton => {
    closeButton.addEventListener('click', function () {
        const modal = this.closest('.modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    });

    closeButton.addEventListener('touchstart', function () {
        const modal = this.closest('.modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    });
});

const closeModalImages = document.querySelector('.close-modal-images');
closeModalImages.addEventListener('click', function () {
    editImagesModal.style.display = 'none';
});

closeModalImages.addEventListener('touchstart', function () {
    editImagesModal.style.display = 'none';
});



document.getElementById("adicionar_acomp").addEventListener("submit", function (e) {
    e.preventDefault(); // Previne o envio padrão do formulário

    // Obtendo os dados do formulário
    const assunto = document.getElementById("assunto").value.trim(); // Valor do textarea assunto
    const data = document.getElementById("data_acomp").value; // Data selecionada
    const acompanhamentoSelecionado = document.querySelector('input[name="acompanhamento"]:checked');

    console.log(assunto, data, obraId)

    if (acompanhamentoSelecionado && acompanhamentoSelecionado.value === "prazo_alteracao") {
        const confirmacao = confirm("Você selecionou 'Prazo de alteração'. Lembre-se de preencher a data corretamente!");
        if (!confirmacao) {
            return; // Cancela o envio do formulário
        }
    }

    // Validações simples
    if (!obraId || !assunto || !data) {
        Toastify({
            text: "Preencha todos os campos corretamente.",
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: "#f44336", // Cor de erro
        }).showToast();
        return;
    }

    // Enviando os dados via AJAX
    fetch("addAcompanhamento.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
            idobra: obraId,
            assunto: assunto,
            data: data,
        }),
    })
        .then(response => response.json()) // Converte a resposta para JSON
        .then(data => {
            // Exibe o Toastify com base na resposta
            if (data.success) {
                Toastify({
                    text: data.message,
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#4caf50", // Cor de sucesso
                }).showToast();
                document.getElementById("adicionar_acomp").reset(); // Reseta o formulário
                modal.style.display = 'none';
                abrirModalAcompanhamento(obraId);
            } else {
                Toastify({
                    text: data.message,
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#f44336", // Cor de erro
                }).showToast();
            }
        })
        .catch(error => {
            console.error("Erro ao enviar acompanhamento:", error);
            Toastify({
                text: "Erro ao adicionar acompanhamento.",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "#f44336", // Cor de erro
            }).showToast();
        });
});

document.getElementById("adicionar_observacao").addEventListener("submit", function (e) {
    e.preventDefault(); // Previne o envio padrão do formulário

    // Obtendo os dados do formulário
    const desc = document.getElementById("desc").value.trim();
    const descricaoId = document.getElementById("descricaoId").value;

    // Validações simples
    if (!desc) {
        Toastify({
            text: "Preencha todos os campos corretamente.",
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: "#f44336", // Cor de erro
        }).showToast();
        return;
    }

    // Enviando os dados via AJAX
    fetch("addAcompanhamento.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
            idobra: obraId,
            desc: desc,
            id: descricaoId
        }),
    })
        .then(response => response.json()) // Converte a resposta para JSON
        .then(data => {
            // Exibe o Toastify com base na resposta
            if (data.success) {
                Toastify({
                    text: data.message,
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#4caf50", // Cor de sucesso
                }).showToast();
                document.getElementById("adicionar_observacao").reset(); // Reseta o formulário
                modalObs.style.display = 'none';
            } else {
                Toastify({
                    text: data.message,
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#f44336", // Cor de erro
                }).showToast();
            }
        })
        .catch(error => {
            console.error("Erro ao enviar acompanhamento:", error);
            Toastify({
                text: "Erro ao adicionar acompanhamento.",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "#f44336", // Cor de erro
            }).showToast();
        });
});

const modalPos = document.getElementById("modal_pos");
const eventModal = document.getElementById("eventModal");
const calendarModal = document.getElementById("calendarModal");
const editImagesModal = document.getElementById("editImagesModal");
const modalPdf = document.getElementById("modal_pdf");

['click', 'touchstart', 'keydown'].forEach(eventType => {
    window.addEventListener(eventType, function (event) {
        // Fecha os modais ao clicar fora ou pressionar Esc
        if (eventType === 'keydown' && event.key !== 'Escape') return;

        // PRIORIDADE: fecha modalPdf primeiro se estiver aberto
        if (modalPdf && modalPdf.style.display === "flex") {
            if (event.target == modalPdf || (eventType === 'keydown' && event.key === 'Escape')) {
                modalPdf.style.display = "none";
                return; // Sai da função, não fecha outros modais
            }
        }

        if (event.target == form_edicao || (eventType === 'keydown' && event.key === 'Escape')) {
            form_edicao.style.display = "none";
            infosObra(obraId);
        }
        if (event.target == modal || (eventType === 'keydown' && event.key === 'Escape')) {
            modal.style.display = "none";
        }
        if (event.target == modalOrcamento || (eventType === 'keydown' && event.key === 'Escape')) {
            modalOrcamento.style.display = "none";
        }
        if (event.target == editImagesModal || (eventType === 'keydown' && event.key === 'Escape')) {
            editImagesModal.style.display = "none";
            infosObra(obraId);
        }
        if (event.target == addImagemModal || (eventType === 'keydown' && event.key === 'Escape')) {
            addImagemModal.style.display = "none";
        }
        if (event.target == infosModal || (eventType === 'keydown' && event.key === 'Escape')) {
            infosModal.style.display = "none";
        }
        if (event.target == modalObs || (eventType === 'keydown' && event.key === 'Escape')) {
            modalObs.style.display = "none";
        }
        if (event.target == modalLogs || (eventType === 'keydown' && event.key === 'Escape')) {
            modalLogs.style.display = "none";
        }
        if (event.target == modalArquivos || (eventType === 'keydown' && event.key === 'Escape')) {
            modalArquivos.style.display = "none";
            infosObra(obraId);
        }
        if (event.target == eventModal || (eventType === 'keydown' && event.key === 'Escape')) {
            eventModal.style.display = "none";
        }
        if (event.target == calendarModal || (eventType === 'keydown' && event.key === 'Escape')) {
            calendarModal.style.display = "none";
        }
    });
});



document.getElementById('formOrcamento').addEventListener('submit', function (e) {
    e.preventDefault();

    const idObra = document.getElementById('idObraOrcamento').value;
    const tipo = document.getElementById('tipo').value;
    const valor = document.getElementById('valor').value;
    const data = document.getElementById('data').value;

    // Enviar os dados para o backend
    fetch('salvarOrcamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ idObra, tipo, valor, data }),
    })
        .then(response => response.json())
        .then(data => {
            alert('Orçamento salvo com sucesso!');
            document.getElementById('modalOrcamento').style.display = 'none'; // Fecha o modal
        })
        .catch(error => {
            console.error('Erro ao salvar orçamento:', error);
        });
});



window.addEventListener('touchstart', function (event) {
    if (event.target == modalInfos) {
        modalInfos.style.display = "none";
    }
    if (event.target == form_edicao) {
        form_edicao.style.display = "none"
    }
    if (event.target == modal) {
        modal.style.display = "none"
    }
});

document.querySelectorAll('.titulo_imagem').forEach(titulo_imagem => {
    titulo_imagem.addEventListener('click', () => {
        const conteudo_imagens = titulo_imagem.nextElementSibling;
        if (conteudo_imagens.style.display === 'none') {
            conteudo_imagens.style.display = 'block';
            titulo_imagem.querySelector('i').classList.remove('fa-chevron-down');
            titulo_imagem.querySelector('i').classList.add('fa-chevron-up');
            conteudo_imagens.classList.add('show-in');
        } else {
            conteudo_imagens.style.display = 'none';
            titulo_imagem.querySelector('i').classList.remove('fa-chevron-up');
            titulo_imagem.querySelector('i').classList.add('fa-chevron-down');
        }
    });
});



let modifiedImages = new Set(); // Armazena IDs das imagens alteradas

document.getElementById("editImagesBtn").addEventListener("click", () => {
    // Obtém o 'obraId' do localStorage
    const obraId = localStorage.getItem("obraId");

    if (!obraId) {
        alert("ID da obra não encontrado!");
        return;
    }

    // Faz a requisição para buscar imagens relacionadas à obra
    fetch("infosImagens.php", {
        method: "POST", // Usa POST para enviar dados ao servidor
        headers: {
            "Content-Type": "application/json", // Especifica que o corpo da requisição será JSON
        },
        body: JSON.stringify({ obraId }), // Envia o 'obraId' como JSON
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error("Erro ao buscar imagens");
            }
            return response.json();
        })
        .then((images) => {
            const imageList = document.getElementById("imageList");
            imageList.innerHTML = ""; // Limpa o conteúdo existente

            images.forEach((image) => {
                const imageContainer = document.createElement("div");
                imageContainer.innerHTML = `
                    <div class="image-item">
                        <div class="titulo_imagem">
                            <h4>${image.imagem_nome}</h4>
                            <i class="fas fa-chevron-down toggle-options"></i>
                        </div>

                        <div class="conteudo_imagens" id="conteudo_imagens">
                            <label>Imagem: <input type="text" data-id="${image.idimagem}" name="imagem_nome" value="${image.imagem_nome}"></label><br>
                            <label>Recebimento Arquivos: <input type="date" data-id="${image.idimagem}" name="recebimento_arquivos" value="${image.recebimento_arquivos}"></label><br>
                            <label>Data de Início: <input type="date" data-id="${image.idimagem}" name="data_inicio" value="${image.data_inicio}"></label><br>
                            <label>Prazo: <input type="date" data-id="${image.idimagem}" name="prazo" value="${image.prazo}"></label><br>
                            <label>Tipo de Imagem: <input type="text" data-id="${image.idimagem}" name="tipo_imagem" value="${image.tipo_imagem}"></label>
                            <label>Antecipada: <input type="checkbox" data-id="${image.idimagem}" name="antecipada" ${image.antecipada == 1 ? "checked" : ""}></label>
                            <label>Terá animação?: <input type="checkbox" data-id="${image.idimagem}" name="animacao" value="1" ${image.animacao == 1 ? "checked" : ""}></label>
                            <label>Clima: <input type="text" data-id="${image.idimagem}" name="clima" value="${image.clima}"></label>
                        </div>
                    </div>
                `;
                imageList.appendChild(imageContainer);


                // Adiciona o evento de clique para mostrar/esconder o conteúdo e trocar o ícone
                const tituloImagem = imageContainer.querySelector(".titulo_imagem");
                const conteudoImagens = imageContainer.querySelector(".conteudo_imagens");
                const toggleIcon = tituloImagem.querySelector(".toggle-options");

                tituloImagem.addEventListener("click", () => {
                    if (conteudoImagens.style.display === "none") {
                        conteudoImagens.classList.add('show-in')
                        conteudoImagens.style.display = "block";
                        toggleIcon.classList.remove("fa-chevron-down");
                        toggleIcon.classList.add("fa-chevron-up");
                    } else {
                        conteudoImagens.style.display = "none";
                        toggleIcon.classList.remove("fa-chevron-up");
                        toggleIcon.classList.add("fa-chevron-down");
                    }
                });
            });

            // Exibe o modal
            document.getElementById("editImagesModal").style.display = "block";
        })
        .catch((error) => {
            console.error("Erro:", error);
            alert("Não foi possível carregar as imagens.");
        });
});


// Detecta alterações nos campos
document.getElementById("imageList").addEventListener("input", event => {
    const imageId = event.target.getAttribute("data-id");
    modifiedImages.add(imageId); // Marca a imagem como alterada
    document.getElementById("unsavedChanges").style.display = "flex"; // Mostra a mensagem de aviso
});

// Salva as alterações
document.getElementById("saveChangesBtn").addEventListener("click", () => {
    const updates = Array.from(modifiedImages).map(id => {
        return {
            idimagem: id,
            imagem_nome: document.querySelector(`input[name="imagem_nome"][data-id="${id}"]`).value,
            recebimento_arquivos: document.querySelector(`input[name="recebimento_arquivos"][data-id="${id}"]`).value,
            data_inicio: document.querySelector(`input[name="data_inicio"][data-id="${id}"]`).value,
            prazo: document.querySelector(`input[name="prazo"][data-id="${id}"]`).value,
            tipo_imagem: document.querySelector(`input[name="tipo_imagem"][data-id="${id}"]`).value,
            antecipada: document.querySelector(`input[name="antecipada"][data-id="${id}"]`).checked ? "1" : "0",
            animacao: document.querySelector(`input[name="animacao"][data-id="${id}"]`).checked ? "1" : "0",
            clima: document.querySelector(`input[name="clima"][data-id="${id}"]`).value,
        };
    });

    fetch("saveImages.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(updates)
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert("Alterações salvas com sucesso!");
                modifiedImages.clear();
                document.getElementById("unsavedChanges").style.display = "none"; // Esconde a mensagem
            } else {
                alert("Erro ao salvar alterações.");
            }
        })
        .catch(error => {
            console.error("Erro ao salvar alterações:", error);
            alert("Erro ao salvar alterações. Por favor, tente novamente.");
        });
});


function submitFormImagem(event) {
    event.preventDefault();

    const opcaoCliente = document.getElementById('opcao_cliente').value;
    const opcaoObra = document.getElementById('opcao_obra').value;
    const arquivo = document.getElementById('arquivos').value;
    const data_inicio = document.getElementById('data_inicio').value;
    const prazo = document.getElementById('prazo').value;
    const imagem = document.getElementById('nome-imagem').value;
    const tipo = document.getElementById('tipo-imagem').value;


    const data = {
        opcaoCliente: opcaoCliente,
        opcaoObra: opcaoObra,
        arquivo: arquivo,
        data_inicio: data_inicio,
        prazo: prazo,
        imagem: imagem,
        tipo: tipo
    };

    fetch('inserir_imagem.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                Toastify({
                    text: result.message,
                    duration: 3000,
                    close: true,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "green",
                    stopOnFocus: true,
                }).showToast();

                infosObra(obraId);
            } else {
                Toastify({
                    text: result.message,
                    duration: 3000,
                    close: true,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "red",
                    stopOnFocus: true,
                }).showToast();
            }

        })
        .catch(error => {
            console.error('Erro:', error);
            Toastify({
                text: "Erro ao tentar salvar. Tente novamente.",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: "red",
                stopOnFocus: true,
            }).showToast();
        });
}



document.querySelectorAll(".campo input[type='text']").forEach(input => {
    input.addEventListener("keydown", function (event) {
        if (event.key === "Enter" && this.value.trim() !== "") {
            event.preventDefault(); // Evita o comportamento padrão

            // Coleta os dados do input
            const campo = this.name;
            const valor = this.value.trim();

            salvarNoBanco(campo, valor, obraId);
        }
    });
});


function salvarNoBanco(campo, valor, obraId) {
    fetch("salvar.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `campo=${encodeURIComponent(campo)}&valor=${encodeURIComponent(valor)}&obraId=${encodeURIComponent(obraId)}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.sucesso) {
                Toastify({
                    text: 'Dados salvos com sucesso!',
                    duration: 1000,
                    close: true,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "green",
                    stopOnFocus: true,
                }).showToast();
            } else {
                Toastify({
                    text: 'Erro ao salvar',
                    duration: 1000,
                    close: true,
                    gravity: "top",
                    position: "center",
                    backgroundColor: "red",
                    stopOnFocus: true,
                }).showToast();
            }
        })
        .catch(error => console.error("Erro na requisição:", error));
}


// Adiciona o botão de mostrar todos
const btnMostrarAcomps = document.getElementById('btnMostrarAcomps');
const acompanhamentoConteudo = document.getElementById('list_acomp');
// Ao clicar no botão "Mostrar Todos"
btnMostrarAcomps.addEventListener('click', () => {
    acompanhamentoConteudo.classList.toggle('expanded');
    const isExpanded = acompanhamentoConteudo.classList.contains('expanded');
    btnMostrarAcomps.innerHTML = isExpanded ?
        '<i class="fas fa-chevron-up"></i>' :
        '<i class="fas fa-chevron-down"></i>';
});





document.querySelectorAll('input[name="acompanhamento"]').forEach(radio => {
    radio.addEventListener('change', function () {
        if (this.value === "Prazo de alteração") {
            const confirmacao = confirm("Tem certeza que deseja selecionar 'Prazo de alteração'?");
            if (!confirmacao) {
                this.checked = false; // Desmarca a opção se o usuário cancelar
                return;
            }
        }
        document.getElementById("assunto").value = this.value;
    });
});



document.getElementById("copyColumn").addEventListener("click", function () {
    const table = document.getElementById("tabela-obra");
    const rows = table.querySelectorAll("tbody tr");
    const columnData = [];

    rows.forEach(row => {
        // Verifica se a linha está visível (não tem display: none)
        if (window.getComputedStyle(row).display !== "none") {
            columnData.push(row.cells[0].innerText);
        }
    });

    // Formata como lista
    const listText = columnData.join("\n");

    navigator.clipboard.writeText(listText)
        .then(() => {
            alert("Coluna copiada como lista!");
        })
        .catch(err => {
            console.error("Erro ao copiar a coluna: ", err);
        });
});



document.getElementById("addRender").addEventListener("click", function (event) {
    event.preventDefault();

    var linhaSelecionada = document.querySelector(".linha-tabela.selecionada");
    if (!linhaSelecionada) {
        Toastify({
            text: "Nenhuma imagem selecionada",
            duration: 3000,
            close: true,
            gravity: "top",
            position: "left",
            backgroundColor: "red",
            stopOnFocus: true,
        }).showToast();
        return;
    }

    var idImagemSelecionada = linhaSelecionada.getAttribute("data-id");

    const statusId = document.getElementById("opcao_status").value;

    // Lista de status permitidos
    const statusPermitidos = ["2", "3", "4", "5", "6", "14", "15"];

    if (!statusPermitidos.includes(statusId)) {
        Swal.fire({
            icon: 'error',
            title: 'Status inválido',
            text: 'Este status não é permitido. Selecione um status válido.'
        });
        return;
    }

    const notificar = document.getElementById("notificar").checked;

    const xhr = new XMLHttpRequest();
    xhr.open("POST", "../addRender.php", true);
    xhr.setRequestHeader("Content-Type", "application/json");

    xhr.onload = function () {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            const idRenderAdicionado = response.idrender;

            if (response.status === "erro") {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro ao adicionar render',
                    text: response.message
                }).then(() => {
                    if (response.message.includes("Sessão expirada")) {
                        window.location.href = "../index.html"; // redireciona imediatamente ao clicar em OK
                    }
                });
                return;

            } else if (response.status === "sucesso") {
                if (!notificar) {
                    // Quando "notificar" não está marcado → mostra modal de pós-produção
                    Swal.fire({
                        icon: 'success',
                        title: 'Render adicionado!',
                        text: 'Agora você pode preencher os dados da pós-produção.',
                        confirmButtonText: 'Continuar'
                    }).then(() => {
                        const modal = document.getElementById("modal_pos");
                        modal.classList.remove("hidden");

                        // Preenche os selects com os valores salvos/localizados
                        const finalizador = localStorage.getItem("idcolaborador");
                        if (finalizador) {
                            document.getElementById("opcao_finalizador").value = finalizador;
                        }

                        const obra = localStorage.getItem("obraId");
                        if (obra) {
                            document.getElementById("opcao_obra_pos").value = obra;
                        }

                        document.getElementById("imagem_id_pos").value = idImagemSelecionada;

                        const statusSelecionado = document.getElementById("opcao_status");
                        if (statusSelecionado) {
                            const statusValue = statusSelecionado.value;
                            document.getElementById("opcao_status_pos").value = statusValue;
                        }

                        const pos = document.getElementById("opcao_pos").value;
                        if (pos) {
                            document.getElementById("responsavel_id").value = pos;

                        }

                        document.getElementById("render_id_pos").value = idRenderAdicionado;

                        const form_edicao = document.getElementById("form-edicao");
                        form_edicao.style.display = "none";
                    });

                } else {
                    // Quando "notificar" está marcado → apenas exibe mensagem de notificação
                    Swal.fire({
                        icon: 'success',
                        title: 'Notificação enviada!',
                        text: response.mensagem_notificacao || 'Notificação enviada com sucesso.',
                        confirmButtonText: 'OK'
                    });
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro ao enviar',
                    text: 'Tente novamente ou avise a NASA.'
                });
            }
        }
    };

    const opcaoAlt = document.getElementById("opcao_alteracao").value;
    const opcaoFinal = opcaoAlt.trim() !== "" ? opcaoAlt : document.getElementById("opcao_final").value;

    const data = {
        imagem_id: idImagemSelecionada,
        status_id: statusId,
        notificar: notificar ? "1" : "0",
        finalizador: opcaoFinal,
    };

    xhr.send(JSON.stringify(data));

});


// document.getElementById('opcao_obra_pos').addEventListener('change', function () {
//     var obraId = this.value;
//     buscarImagens(obraId);
// });

// function buscarImagens(obraId) {
//     var imagemSelect = document.getElementById('imagem_id_pos');

//     // Verifica se o valor selecionado é 0, então busca todas as imagens
//     var url = '../Pos-Producao/buscar_imagens.php';
//     if (obraId != "0") {
//         url += '?obra_id=' + obraId;
//     }

//     var xhr = new XMLHttpRequest();
//     xhr.open('GET', url, true);
//     xhr.onreadystatechange = function () {
//         if (xhr.readyState === 4 && xhr.status === 200) {
//             var response = JSON.parse(xhr.responseText);

//             // Limpa as opções atuais
//             imagemSelect.innerHTML = '';

//             // Adiciona as novas opções com base na resposta
//             response.forEach(function (imagem) {
//                 var option = document.createElement('option');
//                 option.value = imagem.idimagens_cliente_obra;
//                 option.text = imagem.imagem_nome;
//                 imagemSelect.add(option);
//             });
//         }
//     };
//     xhr.send();
// }


formPosProducao.addEventListener('submit', function (e) {
    e.preventDefault();

    var formData = new FormData(this);

    fetch('../Pos-Producao/inserir_pos_producao.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.text())
        .then(data => {

            document.getElementById('form-edicao').style.display = 'none';
            limparCampos();
            Toastify({
                text: "Dados inseridos com sucesso!",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "left",
                backgroundColor: "green",
                stopOnFocus: true,
            }).showToast();

            const modal = document.getElementById("modal_pos");
            modal.classList.add("hidden");
        })
        .catch(error => console.error('Erro:', error));
});


document.getElementById("addRevisao").addEventListener("click", function (event) {
    event.preventDefault();

    // Captura os valores
    const imagemId = document.getElementById("imagem_id").value;
    const opcaoAlteracao = document.getElementById("opcao_alteracao").value;
    const obraId = localStorage.getItem("obraId");

    // Verifica se opcao_alteracao está preenchido
    if (!opcaoAlteracao.trim()) {
        alert("Por favor, selecione uma opção antes de enviar.");
        return; // Interrompe a execução se estiver vazio
    }

    // Configuração do AJAX
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "addRevisao.php", true);
    xhr.setRequestHeader("Content-Type", "application/json");

    // Define o que fazer após a resposta
    xhr.onload = function () {
        if (xhr.status === 200) {
            Toastify({
                text: 'Alteração enviada com sucesso!',
                duration: 3000,
                backgroundColor: "green",
                close: true,
                gravity: "top",
                position: "right"
            }).showToast();
        } else {
            Toastify({
                text: 'Erro ao enviar alteração.',
                duration: 3000,
                backgroundColor: "red",
                close: true,
                gravity: "top",
                position: "right"
            }).showToast();
        }
    };

    // Dados a serem enviados como JSON
    const data = {
        imagem_id: imagemId,
        colaborador_id: opcaoAlteracao,
        obra_id: obraId
    };

    console.log(data);

    // Envia os dados como JSON
    xhr.send(JSON.stringify(data));
});

// Atualiza o campo quando o botão for clicado
function atualizarRevisao(event, id, campo, valor) {
    fetch('atualizarObs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, campo, valor })
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === "sucesso") {
                Toastify({
                    text: 'Campo atualizado com sucesso',
                    duration: 3000,
                    backgroundColor: "green",
                    close: true,
                    gravity: "top",
                    position: "right"
                }).showToast();
                document.querySelectorAll('.save-button').forEach(button => {
                    button.style.display = 'none';
                });
            } else {
                console.error(`Erro ao atualizar ${campo}:`, data.mensagem);
                Toastify({
                    text: `Erro ao atualizar ${campo}: ${data.mensagem}`,
                    duration: 3000,
                    backgroundColor: "red",
                    close: true,
                    gravity: "top",
                    position: "right"
                }).showToast();
            }
        })
        .catch(error => console.error('Erro na requisição:', error));
}


let events = [];

function carregarEventos(obraId) {
    fetch(`./Calendario/getEventos.php?obraId=${obraId}`)
        .then(res => res.json())
        .then(data => {
            console.log("Eventos recebidos do PHP:", data); // 👈 Verifique isso

            events = data.map(evento => {

                delete evento.eventDate;

                const colors = getEventColors(evento); // 👈 adiciona o título
                return {
                    id: evento.id,
                    title: evento.descricao,
                    start: evento.start,
                    end: evento.end && evento.end !== evento.start ? evento.end : null,
                    allDay: evento.end ? true : false,
                    tipo_evento: evento.tipo_evento, // 👈 necessário para o eventDidMount
                    backgroundColor: colors.backgroundColor,
                    color: colors.color
                };
            });


            if (!miniCalendar) {
                criarMiniCalendar();
            } else {
                miniCalendar.removeAllEvents();
                miniCalendar.addEventSource(events);
            }

            if (fullCalendar) {
                fullCalendar.removeAllEvents();
                fullCalendar.addEventSource(events);
            }

            // 👇 Notificar se for colaborador 1 ou 2
            const colaboradorId = localStorage.getItem("idcolaborador"); // implemente essa função ou defina a variável

            if (colaboradorId === '1' || colaboradorId === '9' || colaboradorId === '21') {
                notificarEventosDaSemana(events);
            }
        });
}

function notificarEventosDaSemana(eventos) {
    console.log(eventos);
    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);

    const inicioSemana = new Date(hoje);
    inicioSemana.setDate(hoje.getDate() - hoje.getDay()); // Domingo
    inicioSemana.setHours(0, 0, 0, 0);

    const fimSemana = new Date(inicioSemana);
    fimSemana.setDate(inicioSemana.getDate() + 6); // Sábado
    fimSemana.setHours(23, 59, 59, 999);

    function parseDateLocal(dateStr) {
        const [ano, mes, dia] = dateStr.split('-');
        return new Date(ano, mes - 1, dia); // mês é 0-based
    }

    const eventosSemana = eventos.filter(evento => {
        const dataReferencia = evento.end ? parseDateLocal(evento.end) : parseDateLocal(evento.start);
        return dataReferencia >= inicioSemana && dataReferencia <= fimSemana;
    });

    if (eventosSemana.length > 0) {
        const listaEventos = eventosSemana
            .map(ev => {
                const dataLocal = ev.end ? parseDateLocal(ev.end) : parseDateLocal(ev.start);
                return `<li><strong>${ev.title}</strong> em ${dataLocal.toLocaleDateString()}</li>`;
            }).join('');

        Swal.fire({
            icon: 'info',
            title: 'Eventos desta semana',
            html: `<ul style="text-align: left; padding: 0 20px">${listaEventos}</ul>`,
            confirmButtonText: 'Entendi'
        });
    }
}

// Função para definir as cores com base no tipo_evento
function getEventColors(event) {
    const { id, descricao, tipo_evento } = event;
    const normalizedTitle = (descricao || '').toUpperCase().trim();

    if (normalizedTitle.includes('R00')) {
        return { backgroundColor: '#1cf4ff', color: '#000000' };
    }
    if (normalizedTitle.includes('R01')) {
        return { backgroundColor: '#ff6200', color: '#000000' };
    }
    if (normalizedTitle.includes('R02')) {
        return { backgroundColor: '#ff3c00', color: '#000000' };
    }
    if (normalizedTitle.includes('R02')) {
        return { backgroundColor: '#ff0000', color: '#000000' };
    }
    if (normalizedTitle.includes('EF')) {
        return { backgroundColor: '#0dff00', color: '#000000' };
    }

    // Se não encontrou no título, usa o tipoEvento
    switch (tipo_evento) {
        case 'Reunião':
            return { backgroundColor: '#ffd700', color: '#000000' };
        case 'Entrega':
            return { backgroundColor: '#ff9f89', color: '#000000' };
        case 'Arquivos':
            return { backgroundColor: '#90ee90', color: '#000000' };
        case 'Outro':
            return { backgroundColor: '#87ceeb', color: '#000000' };
        case 'P00':
            return { backgroundColor: '#ffc21c', color: '#000000' };
        case 'R00':
            return { backgroundColor: '#1cf4ff', color: '#000000' };
        case 'R01':
            return { backgroundColor: '#ff6200', color: '#ffffff' };
        case 'R02':
            return { backgroundColor: '#ff3c00', color: '#ffffff' };
        case 'R03':
            return { backgroundColor: '#ff0000', color: '#ffffff' };
        case 'EF':
            return { backgroundColor: '#0dff00', color: '#000000' };
        case 'HOLD':
            return { backgroundColor: '#ff0000', color: '#ffffff' };
        case 'TEA':
            return { backgroundColor: '#f7eb07', color: '#000000' };
        case 'REN':
            return { backgroundColor: '#0c9ef2', color: '#ffffff' };
        case 'APR':
            return { backgroundColor: '#0c45f2', color: '#ffffff' };
        case 'APP':
            return { backgroundColor: '#7d36f7', color: '#ffffff' };
        case 'RVW':
            return { backgroundColor: 'green', color: '#ffffff' };
        case 'OK':
            return { backgroundColor: 'cornflowerblue', color: '#ffffff' };
        case 'Pós-Produção':
            return { backgroundColor: '#e3f2fd', color: '#000000' };
        case 'Finalização':
            return { backgroundColor: '#e8f5e9', color: '#000000' };
        case 'Modelagem':
            return { backgroundColor: '#fff3e0', color: '#000000' };
        case 'Caderno':
            return { backgroundColor: '#fce4ec', color: '#000000' };
        case 'Composição':
            return { backgroundColor: '#f9ffc6', color: '#000000' };
        default:
            return { backgroundColor: '#d3d3d3', color: '#000000' };
    }
}



let miniCalendar;

function criarMiniCalendar() {
    miniCalendar = new FullCalendar.Calendar(document.getElementById('calendarMini'), {
        initialView: 'dayGridWeek',
        height: 'auto',
        headerToolbar: {
            left: '',
            center: 'title',
            right: ''
        },
        navLinks: false,
        selectable: false,
        editable: false,
        displayEventTime: false,
        locale: 'pt-br',
        events: events,
        eventDidMount: function (info) {
            const eventProps = {
                id: info.event.id,
                descricao: info.event.title || '', // título do evento (pode ser usado como descrição)
                tipo_evento: info.event.extendedProps.tipo_evento || ''
            };

            const colors = getEventColors(eventProps);

            info.el.style.backgroundColor = colors.backgroundColor;
            info.el.style.color = colors.color;
            info.el.style.borderColor = colors.backgroundColor;
        },
        dateClick: () => openFullCalendar(),

        // Apenas o nome do dia da semana (ex: Seg, Ter, Qua...)
        dayHeaderFormat: { weekday: 'short' },
        // FORMATA O TÍTULO DA SEMANA
        titleFormat: {
            day: '2-digit',
            month: 'long'  // Ex: "27 de março"
        }
    });

    miniCalendar.render();
}

let fullCalendar;

function openFullCalendar() {

    calendarModal.style.display = 'flex';

    if (!fullCalendar) {
        fullCalendar = new FullCalendar.Calendar(document.getElementById('calendarFull'), {
            initialView: 'dayGridMonth',
            editable: true,
            selectable: true,
            locale: 'pt-br',
            displayEventTime: false,
            events: events, // Usa os eventos já formatados corretamente
            eventDidMount: function (info) {
                const eventProps = {
                    id: info.event.id,
                    descricao: info.event.title || '', // título do evento (pode ser usado como descrição)
                    tipo_evento: info.event.extendedProps.tipo_evento || ''
                };

                const colors = getEventColors(eventProps);

                info.el.style.backgroundColor = colors.backgroundColor;
                info.el.style.color = colors.color;
                info.el.style.borderColor = colors.backgroundColor;
            },
            datesSet: function (info) {
                const tituloOriginal = info.view.title;
                const partes = tituloOriginal.replace('de ', '').split(' ');
                const mes = partes[0];
                const ano = partes[1];
                const mesCapitalizado = mes.charAt(0).toUpperCase() + mes.slice(1);
                document.querySelector('#calendarFull .fc-toolbar-title').textContent = `${mesCapitalizado} ${ano}`;
            },

            dateClick: function (info) {
                const clickedDate = new Date(info.date);
                const formattedDate = clickedDate.toISOString().split('T')[0];

                document.getElementById('eventId').value = '';
                document.getElementById('eventTitle').value = '';
                document.getElementById('eventDate').value = formattedDate;
                document.getElementById('eventModal').style.display = 'flex';

            },

            eventClick: function (info) {
                const clickedDate = new Date(info.event.start);
                const formattedDate = clickedDate.toISOString().split('T')[0];


                document.getElementById('eventId').value = info.event.id;
                document.getElementById('eventTitle').value = info.event.title;
                document.getElementById('eventDate').value = formattedDate;
                document.getElementById('eventModal').style.display = 'flex';
            },

            eventDrop: function (info) {
                const event = info.event;
                updateEvent(event);
            }
        });

        fullCalendar.render();
    } else {
        fullCalendar.refetchEvents();
    }
}

function closeModal() {
    document.getElementById('calendarModal').style.display = 'none';
}

function closeEventModal() {
    document.getElementById('eventModal').style.display = 'none';
    const obraId = localStorage.getItem("obraId");
    carregarEventos(obraId); // Recarrega os eventos após fechar o modal
}

function showToast(message, type = 'success') {
    let backgroundColor;

    switch (type) {
        case 'create':
            backgroundColor = 'linear-gradient(to right, #00b09b, #96c93d)'; // verde limão
            break;
        case 'update':
            backgroundColor = 'linear-gradient(to right, #2193b0, #6dd5ed)'; // azul claro
            break;
        case 'delete':
            backgroundColor = 'linear-gradient(to right, #ff416c, #ff4b2b)'; // vermelho/rosa
            break;
        case 'error':
            backgroundColor = 'linear-gradient(to right, #e53935, #e35d5b)'; // vermelho
            break;
        default:
            backgroundColor = 'linear-gradient(to right, #00b09b, #96c93d)'; // sucesso padrão
    }

    Toastify({
        text: message,
        duration: 4000,
        gravity: "top",
        position: "right",
        backgroundColor: backgroundColor,
    }).showToast();
}

document.getElementById('eventForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const id = document.getElementById('eventId').value;
    const title = document.getElementById('eventTitle').value;
    const start = document.getElementById('eventDate').value;
    const type = document.getElementById('eventType').value;
    const obraId = localStorage.getItem("obraId");

    if (id) {
        fetch('./Calendario/eventoController.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, title, start, type })
        })
            .then(res => res.json())
            .then(res => {
                if (res.error) throw new Error(res.message);
                closeEventModal(); // ✅ fecha o modal após excluir
                showToast(res.message, 'update'); // para PUT
            })
            .catch(err => showToast(err.message, 'error'));
    } else {
        fetch('./Calendario/eventoController.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, start, type, obra_id: obraId })
        })
            .then(res => res.json())
            .then(res => {
                if (res.error) throw new Error(res.message);
                closeEventModal(); // ✅ fecha o modal após excluir
                showToast(res.message, 'create'); // para POST
            })
            .catch(err => showToast(err.message, 'error'));
    }
});

function deleteEvent() {
    const id = document.getElementById('eventId').value;
    if (!id) return;

    fetch('./Calendario/eventoController.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
        .then(res => res.json())
        .then(res => {
            if (res.error) throw new Error(res.message);
            closeEventModal(); // ✅ fecha o modal após excluir

            showToast(res.message, 'delete');
        })
        .catch(err => showToast(err.message, 'error'));
}

function updateEvent(event) {
    fetch('./Calendario/eventoController.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: event.id,
            title: event.title,
            start: event.start.toISOString().substring(0, 10),
            type: event.extendedProps?.tipo_evento // 👈 forma segura de acessar

        })
    })
        .then(res => res.json())
        .then(res => {
            if (res.error) throw new Error(res.message);
            showToast(res.message);
        })
        .catch(err => showToast(err.message, false));
}


async function gerarFollowUpPDF() {
    const { jsPDF } = window.jspdf;

    const doc = new jsPDF({
        orientation: 'landscape',
    });

    const pageWidth = doc.internal.pageSize.getWidth();
    const margin = 14;
    const usableWidth = pageWidth - 2 * margin;
    let currentY = 20;

    const nomenclatura = document.getElementById('nomenclatura').textContent;

    const title = `Olá pessoal do ${nomenclatura},\nSeguem as informações atualizadas sobre o status do seu projeto. Qualquer dúvida ou necessidade de ajuste, estamos à disposição.\n\n`;
    const legenda = `P00 - Envio em Toon: Primeira versão conceitual do projeto, enviada com estilo gráfico simplificado para avaliação inicial.
\nR00 - Primeiro Envio: Primeira entrega completa, após ajustes da versão inicial.
\nR01, R02, etc. - Revisão Enviada: Número de revisões enviadas, indicando cada versão revisada do projeto.
\nEF - Entrega Final: Projeto concluído e aprovado em sua versão final.
\nHOLD - Falta de Arquivos: O projeto está temporariamente parado devido à ausência de arquivos ou informações necessárias. O prazo de entrega também ficará pausado até o recebimento dos arquivos para darmos continuidade ao trabalho.
\nREN - Imagem sendo renderizada: O processo de geração da imagem está em andamento.
\nAPR - Imagem em aprovação: A imagem foi gerada e está aguardando aprovação.
\nOK - Imagem pronta para o desenvolvimento: A imagem foi aprovada e está pronta para a fase de desenvolvimento.
`;

    const imgPath = '../assets/logo.jpg';

    fetch(imgPath)
        .then(response => response.blob())
        .then(blob => {
            const reader = new FileReader();
            reader.onloadend = function () {
                const imgData = reader.result;

                // Logo
                doc.addImage(imgData, 'PNG', margin, currentY, 40, 40);
                currentY += 50;

                // Title
                doc.setFontSize(14);
                doc.setTextColor(0, 0, 0);
                const titleLines = doc.splitTextToSize(title, usableWidth);
                doc.text(titleLines, margin, currentY);
                currentY += titleLines.length * 6;

                // Legenda
                doc.setFontSize(10);
                const legendaLines = doc.splitTextToSize(legenda, usableWidth);
                legendaLines.forEach(line => {
                    if (currentY >= doc.internal.pageSize.getHeight() - margin) {
                        doc.addPage();
                        currentY = margin;
                    }
                    doc.text(line, margin, currentY);
                    currentY += 6;
                });

                const table = document.getElementById('tabela-obra');
                const rows = [];
                const headers = [];

                table.querySelectorAll('thead tr th').forEach((header, index) => {
                    if (index < 3) headers.push(header.innerText.trim());
                });

                table.querySelectorAll('tbody tr').forEach(row => {
                    const rowData = [];
                    row.querySelectorAll('td').forEach((cell, index) => {
                        if (index < 3) rowData.push(cell.innerText.trim());
                    });
                    rows.push(rowData);
                });

                doc.autoTable({
                    head: [headers],
                    body: rows,
                    startY: currentY
                });

                const listAcompDiv = document.getElementById('list_acomp');
                if (listAcompDiv) {
                    const acompBlocks = listAcompDiv.querySelectorAll('.acomp-conteudo');
                    const pageHeight = doc.internal.pageSize.getHeight();
                    const margin = 14;
                    let y = doc.lastAutoTable.finalY + 30;

                    if (acompBlocks.length > 0) {
                        doc.setFontSize(16);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont(undefined, 'bold'); // negrito para o título
                        doc.text("Histórico:", margin, y);
                        y += 8;
                        doc.setTextColor(0, 0, 0);

                        acompBlocks.forEach(block => {
                            const assuntoEl = block.querySelector('.acomp-assunto');
                            const dataEl = block.querySelector('.acomp-data');

                            const assunto = assuntoEl ? assuntoEl.innerText.trim() : '';
                            const data = dataEl ? dataEl.innerText.trim() : '';

                            const assuntoLines = doc.splitTextToSize(assunto, usableWidth);
                            const dataLines = doc.splitTextToSize(data, 260);

                            // Estimar altura total do bloco (assunto + data + espaço entre linhas)
                            const blocoAltura = (assuntoLines.length * 6) + (dataLines.length * 5) + 6; // assunto + data + espaçamento

                            // Se não couber, adiciona nova página
                            if (y + blocoAltura > pageHeight - 10) {
                                doc.addPage();
                                y = margin;
                            }

                            // Renderizar assunto
                            doc.setFontSize(11);
                            doc.setFont(undefined, 'bold');
                            assuntoLines.forEach(line => {
                                doc.text(line, margin, y);
                                y += 6;
                            });

                            // Renderizar data
                            doc.setFontSize(10);
                            doc.setFont(undefined, 'normal');
                            dataLines.forEach(line => {
                                doc.text(line, margin, y);
                                y += 5;
                            });

                            y += 6; // espaço entre blocos
                        });
                    } else {
                        console.warn("Nenhum .acomp-conteudo encontrado dentro de #list_acomp.");
                    }
                } else {
                    console.warn("A div#list_acomp não foi encontrada no DOM.");
                }

                const hoje = new Date();
                const dia = String(hoje.getDate()).padStart(2, '0');
                const mes = String(hoje.getMonth() + 1).padStart(2, '0'); // Janeiro é 0
                const ano = hoje.getFullYear();

                const dataFormatada = `${dia}/${mes}/${ano}`;

                doc.save(`${nomenclatura}-${dataFormatada}.pdf`);
            }
            reader.readAsDataURL(blob);
        })
        .catch(error => console.error('Erro ao carregar a imagem:', error));
}


const dropArea = document.getElementById('drop-area');
const fileInput = document.getElementById('fileElem');
const fileList = document.getElementById('fileList');
let imagensSelecionadas = [];
let arquivosFinais = [];
let dataIdFuncoes = [];

function abrirModal(botao) {
    imagensSelecionadas = [];
    arquivosFinais = [];

    const dataIdFuncao = botao.getAttribute('data-id-funcao');
    dataIdFuncoes = dataIdFuncao?.split(',').map(f => f.trim()) || [];

    let containerFuncao = botao.closest('.funcao') || botao.closest('.funcao_comp');
    let nomeFuncao = containerFuncao?.querySelector('.titulo p')?.textContent.trim() || '';

    document.getElementById('funcao_id_revisao').value = dataIdFuncoes.join(',');
    document.getElementById('nome_funcao_upload').value = nomeFuncao;

    // Exibir o modal
    document.getElementById('modalUpload').style.display = 'block';

    // Verificação do nome da função
    const nomeNormalizado = nomeFuncao.toLowerCase();
    if (nomeNormalizado === 'caderno' || nomeNormalizado === 'filtro de assets') {
        // Pula direto para a etapa final
        document.getElementById('etapaPrevia').style.display = 'none';
        document.getElementById('etapaFinal').style.display = 'block';
        document.getElementById('etapaTitulo').textContent = "1. Envio de arquivos";
    } else {
        // Etapa padrão
        document.getElementById('etapaPrevia').style.display = 'block';
        document.getElementById('etapaFinal').style.display = 'none';
        document.getElementById('etapaTitulo').textContent = "1. Envio de Prévia";
    }

    configurarDropzone("drop-area-previa", "fileElemPrevia", "fileListPrevia", imagensSelecionadas);
    configurarDropzone("drop-area-final", "fileElemFinal", "fileListFinal", arquivosFinais);

    document.getElementById('modalUpload').style.display = 'block';
    document.getElementById('form-edicao').style.display = 'none';
}

function fecharModal() {
    imagensSelecionadas = [];
    arquivosFinais = [];
    renderizarLista(imagensSelecionadas, 'fileListPrevia');
    renderizarLista(arquivosFinais, 'fileListFinal');
    document.getElementById('modalUpload').style.display = 'none';
}

function configurarDropzone(areaId, inputId, listaId, arquivosArray) {
    const dropArea = document.getElementById(areaId);
    const fileInput = document.getElementById(inputId);

    // Funções nomeadas para poder remover depois
    function handleDrop(e) {
        e.preventDefault();
        dropArea.classList.remove('highlight');
        for (let file of e.dataTransfer.files) arquivosArray.push(file);
        renderizarLista(arquivosArray, listaId);
    }
    function handleChange() {
        for (let file of fileInput.files) arquivosArray.push(file);
        renderizarLista(arquivosArray, listaId);
    }
    function handleClick() {
        fileInput.click();
    }
    function handleDragOver(e) {
        e.preventDefault();
        dropArea.classList.add('highlight');
    }
    function handleDragLeave() {
        dropArea.classList.remove('highlight');
    }

    // Remove listeners antigos
    dropArea.removeEventListener('click', dropArea._handleClick);
    dropArea.removeEventListener('dragover', dropArea._handleDragOver);
    dropArea.removeEventListener('dragleave', dropArea._handleDragLeave);
    dropArea.removeEventListener('drop', dropArea._handleDrop);
    fileInput.removeEventListener('change', fileInput._handleChange);

    // Adiciona listeners e guarda referência para remover depois
    dropArea.addEventListener('click', handleClick);
    dropArea.addEventListener('dragover', handleDragOver);
    dropArea.addEventListener('dragleave', handleDragLeave);
    dropArea.addEventListener('drop', handleDrop);
    fileInput.addEventListener('change', handleChange);

    // Guarda referência
    dropArea._handleClick = handleClick;
    dropArea._handleDragOver = handleDragOver;
    dropArea._handleDragLeave = handleDragLeave;
    dropArea._handleDrop = handleDrop;
    fileInput._handleChange = handleChange;
}

function renderizarLista(array, listaId) {
    const lista = document.getElementById(listaId);
    lista.innerHTML = '';
    array.forEach((file, i) => {
        // Calcula o tamanho em B, KB, MB ou GB
        let tamanho = file.size;
        let tamanhoStr = '';
        if (tamanho < 1024) {
            tamanhoStr = `${tamanho} B`;
        } else if (tamanho < 1024 * 1024) {
            tamanhoStr = `${(tamanho / 1024).toFixed(1)} KB`;
        } else if (tamanho < 1024 * 1024 * 1024) {
            tamanhoStr = `${(tamanho / (1024 * 1024)).toFixed(2)} MB`;
        } else {
            tamanhoStr = `${(tamanho / (1024 * 1024 * 1024)).toFixed(2)} GB`;
        }

        const li = document.createElement('li');
        li.innerHTML = `<div class="file-info">
            <span>${file.name} <small style="color:#888;">(${tamanhoStr})</small></span>
            <span onclick="removerArquivo(${i}, '${listaId}')" style="cursor:pointer;color: #c00;font-weight: bold;font-size: 1.2em;">×</span>
        </div>`;
        lista.appendChild(li);
    });
}

function removerArquivo(index, listaId) {
    if (listaId === 'fileListPrevia') {
        imagensSelecionadas.splice(index, 1);
        renderizarLista(imagensSelecionadas, listaId);
    } else {
        arquivosFinais.splice(index, 1);
        renderizarLista(arquivosFinais, listaId);
    }
}

// ENVIO DA PRÉVIA
function enviarImagens() {
    if (imagensSelecionadas.length === 0) {
        Toastify({
            text: "Selecione pelo menos uma imagem para enviar a prévia.",
            duration: 3000,
            gravity: "top",
            backgroundColor: "#f44336"
        }).showToast();
        return;
    }
    const formData = new FormData();
    imagensSelecionadas.forEach(file => formData.append('imagens[]', file));
    formData.append('dataIdFuncoes', JSON.stringify(dataIdFuncoes));
    formData.append('nome_funcao', document.getElementById('nome_funcao_upload').value);
    const campoNomeImagem = document.getElementById('campoNomeImagem')?.textContent || '';

    // Extrai o número inicial antes do ponto
    const numeroImagem = campoNomeImagem.match(/^\d+/)?.[0] || '';
    formData.append('numeroImagem', numeroImagem);

    // Extrai a nomenclatura (primeira palavra com "_", depois do número e ponto)
    const nomenclatura = document.getElementById('nomenclatura')?.textContent || '';
    formData.append('nomenclatura', nomenclatura);

    // Extrai a primeira palavra da descrição (depois da nomenclatura)
    const descricaoMatch = campoNomeImagem.match(/^\d+\.\s*[A-Z_]+\s+([^\s]+)/);
    const primeiraPalavra = descricaoMatch ? descricaoMatch[1] : '';
    formData.append('primeiraPalavra', primeiraPalavra);

    const statusSelect = document.getElementById('opcao_status');
    const statusNome = statusSelect.options[statusSelect.selectedIndex].text.trim();

    formData.append('status_nome', statusNome);

    fetch('https://improov/ImproovWeb/uploadArquivos.php', {
        method: 'POST',
        body: formData
    })
        .then(resp => resp.json())
        .then(res => {
            Toastify({
                text: "Prévia enviada com sucesso!",
                duration: 3000,
                gravity: "top",
                backgroundColor: "#4caf50"
            }).showToast();

            // Avança para próxima etapa
            document.getElementById('etapaPrevia').style.display = 'none';
            document.getElementById('etapaFinal').style.display = 'block';
            document.getElementById('etapaTitulo').textContent = "2. Envio do Arquivo Final";

            Swal.fire({
                position: "center",
                icon: "success",
                title: "Agora adicione o arquivo final",
                showConfirmButton: false,
                timer: 1500,
                didOpen: () => {
                    const title = Swal.getTitle();
                    if (title) title.style.fontSize = "18px";
                }
            });


        })
        .catch(err => {
            Toastify({
                text: "Erro ao enviar prévia",
                duration: 3000,
                gravity: "top",
                backgroundColor: "#f44336"
            }).showToast();
        });
}

// ENVIO DO ARQUIVO FINAL
function enviarArquivo() {
    if (arquivosFinais.length === 0) {
        Toastify({
            text: "Selecione pelo menos um arquivo para enviar a prévia.",
            duration: 3000,
            gravity: "top",
            backgroundColor: "#f44336"
        }).showToast();
        return;
    }

    const formData = new FormData();
    arquivosFinais.forEach(file => formData.append('arquivo_final[]', file));
    formData.append('dataIdFuncoes', JSON.stringify(dataIdFuncoes));
    formData.append('nome_funcao', document.getElementById('nome_funcao_upload').value);

    const campoNomeImagem = document.getElementById('campoNomeImagem')?.textContent || '';
    formData.append('nome_imagem', campoNomeImagem);

    const numeroImagem = campoNomeImagem.match(/^\d+/)?.[0] || '';
    formData.append('numeroImagem', numeroImagem);

    const nomenclatura = document.getElementById('nomenclatura')?.textContent || '';
    formData.append('nomenclatura', nomenclatura);

    const descricaoMatch = campoNomeImagem.match(/^\d+\.\s*[A-Z_]+\s+([^\s]+)/);
    const primeiraPalavra = descricaoMatch ? descricaoMatch[1] : '';
    formData.append('primeiraPalavra', primeiraPalavra);

    const statusSelect = document.getElementById('opcao_status');
    const statusNome = statusSelect.options[statusSelect.selectedIndex].text.trim();

    formData.append('status_nome', statusNome);

    // Criar container de progresso
    const progressContainer = document.createElement('div');
    progressContainer.innerHTML = `
        <progress id="uploadProgress" value="0" max="100" style="width: 100%; height: 20px;"></progress>
        <div id="uploadStatus">Enviando... 0%</div>
        <div id="uploadTempo">Tempo: 0s</div>
        <div id="uploadVelocidade">Velocidade: 0 MB/s</div>
        <div id="uploadEstimativa">Tempo restante: ...</div>
        <button id="cancelarUpload" style="margin-top:10px;padding:5px 10px;">Cancelar</button>
    `;

    Swal.fire({
        title: 'Enviando arquivo...',
        html: progressContainer,
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            const xhr = new XMLHttpRequest();
            const startTime = Date.now();
            let uploadCancelado = false;

            xhr.open('POST', 'https://improov/ImproovWeb/uploadFinal.php');

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const now = Date.now();
                    const elapsed = (now - startTime) / 1000; // em segundos
                    const uploadedMB = e.loaded / (1024 * 1024);
                    const totalMB = e.total / (1024 * 1024);
                    const percent = (e.loaded / e.total) * 100;
                    const speed = uploadedMB / elapsed; // MB/s
                    const remainingMB = totalMB - uploadedMB;
                    const estimatedTime = remainingMB / (speed || 1); // evita divisão por 0

                    document.getElementById('uploadProgress').value = percent;
                    document.getElementById('uploadStatus').innerText = `Enviando... ${percent.toFixed(2)}%`;
                    document.getElementById('uploadTempo').innerText = `Tempo: ${elapsed.toFixed(1)}s`;
                    document.getElementById('uploadVelocidade').innerText = `Velocidade: ${speed.toFixed(2)} MB/s`;
                    document.getElementById('uploadEstimativa').innerText = `Tempo restante: ${estimatedTime.toFixed(1)}s`;
                }
            });

            xhr.onreadystatechange = () => {
                if (xhr.readyState === 4 && xhr.status === 200 && !uploadCancelado) {
                    const res = JSON.parse(xhr.responseText);
                    const destino = res[0]?.destino || 'Caminho não encontrado';
                    Swal.fire({
                        position: "center",
                        icon: "success",
                        title: "Arquivo final enviado com sucesso!",
                        text: `Salvo em: ${destino}, como: ${res[0]?.nome_arquivo || 'Nome não encontrado'}`,
                        showConfirmButton: false,
                        timer: 2000
                    });
                    fecharModal();
                }
            };

            xhr.onerror = () => {
                if (!uploadCancelado) {
                    Swal.close();
                    Toastify({
                        text: "Erro ao enviar arquivo final",
                        duration: 3000,
                        gravity: "top",
                        backgroundColor: "#f44336"
                    }).showToast();
                }
            };

            // Cancelar envio
            document.getElementById('cancelarUpload').addEventListener('click', () => {
                uploadCancelado = true;
                xhr.abort();
                Swal.fire({
                    icon: 'warning',
                    title: 'Upload cancelado',
                    showConfirmButton: false,
                    timer: 1500
                });
            });

            xhr.send(formData);
        }
    });
}


const btnVerPdf = document.getElementById('ver-pdf');
btnVerPdf.addEventListener('click', function (event) {
    event.preventDefault();

    const nomePdf = this.getAttribute('data-nome-pdf');
    if (nomePdf) {
        carregarPdf(nomePdf);
    } else {
        console.error('Nenhum PDF disponível para visualização.');
        Toastify({
            text: "Nenhum PDF disponível para visualização.",
            duration: 3000,
            gravity: "top",
            backgroundColor: "#f44336"
        }).showToast();
    }
});

function carregarPdf(nomeArquivo) {
    const nomenclatura = document.getElementById('nomenclatura').textContent.trim();
    const url = 'ver-pdf.php?arquivo=' + encodeURIComponent(nomeArquivo) +
        '&nomenclatura=' + encodeURIComponent(nomenclatura);
    pdfjsLib.getDocument(url).promise.then(function (pdfDoc_) {
        pdfDoc = pdfDoc_;
        pageNum = 1;
        document.getElementById('page-count').textContent = pdfDoc.numPages;
        renderPage(pageNum);
        document.getElementById('modal_pdf').style.display = 'flex';
    }).catch(function (error) {
        alert('Erro ao carregar PDF: ' + error.message);
    });
}

let pdfDoc = null,
    pageNum = 1,
    pageRendering = false,
    pageNumPending = null,
    scale = 1.2,
    canvas = document.getElementById('pdf-canvas'),
    ctx = canvas.getContext('2d');

function renderPage(num) {
    pageRendering = true;
    pdfDoc.getPage(num).then(function (page) {
        const viewport = page.getViewport({ scale: scale });
        canvas.height = viewport.height;
        canvas.width = viewport.width;

        const renderContext = {
            canvasContext: ctx,
            viewport: viewport
        };
        const renderTask = page.render(renderContext);

        renderTask.promise.then(function () {
            pageRendering = false;
            if (pageNumPending !== null) {
                renderPage(pageNumPending);
                pageNumPending = null;
            }
        });
    });

    document.getElementById('page-num').textContent = num;
}

function queueRenderPage(num) {
    if (pageRendering) {
        pageNumPending = num;
    } else {
        renderPage(num);
    }
}

function prevPage() {
    if (pageNum <= 1) return;
    pageNum--;
    queueRenderPage(pageNum);
}

function nextPage() {
    if (pageNum >= pdfDoc.numPages) return;
    pageNum++;
    queueRenderPage(pageNum);
}
