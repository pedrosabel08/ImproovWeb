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

                                etapaCell.oncontextmenu = (event) => {
                                    event.preventDefault();
                                    etapaAtual = etapa;

                                    const rect = event.target.getBoundingClientRect();
                                    const modal = document.getElementById("colaboradorModal");
                                    select.value = '';

                                    modal.style.position = "absolute";
                                    const isRightSpace = rect.right + modal.offsetWidth < window.innerWidth;
                                    modal.style.left = isRightSpace
                                        ? `${rect.right + 10}px`
                                        : `${rect.left - modal.offsetWidth - 10}px`;

                                    modal.style.top = `${rect.top + window.scrollY}px`;
                                    modal.style.display = "block";
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

document.getElementById('opcao_obra').addEventListener('change', (e) => {
    localStorage.setItem('obraId', e.target.value); // armazena novo valor
    atualizarTabela();
});


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
