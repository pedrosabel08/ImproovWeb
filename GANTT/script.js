fetch('tabela.php')
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
        const endDate = new Date(ultimaData);
        while (startDate <= endDate) {
            datas.push(new Date(startDate));
            startDate.setDate(startDate.getDate() + 1);
        }

        const table = document.getElementById('gantt');
        table.innerHTML = ''; // Limpar conteúdo anterior, se houver

        // Cabeçalho
        const headerRow = document.createElement('tr');
        const headerCell = document.createElement('th');
        const headerCell2 = document.createElement('th');
        headerCell.textContent = 'Tipo de Imagem';
        headerCell2.textContent = 'Nome da Imagem';
        headerRow.appendChild(headerCell);
        headerRow.appendChild(headerCell2);

        datas.forEach(data => {
            const dateCell = document.createElement('th');
            const formattedDate = data.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            dateCell.textContent = formattedDate;

            const dayOfWeek = data.getDay();
            if (dayOfWeek === 0 || dayOfWeek === 6) {
                dateCell.style.backgroundColor = '#FFCCCB'; // Final de semana
            }

            const formattedHoliday = formattedDate.slice(0, 5);
            if (feriados.includes(formattedHoliday)) {
                dateCell.style.backgroundColor = '#D4EDDA'; // Feriado
            }

            headerRow.appendChild(dateCell);
        });

        table.appendChild(headerRow);

        const tbody = document.createElement('tbody');

        // Corpo da tabela
        Object.keys(imagens).forEach(tipoImagem => {
            const nomesImagens = imagens[tipoImagem];
            const rowSpan = nomesImagens.length;

            let firstRow = true;
            nomesImagens.forEach(imagemNome => {
                const row = document.createElement('tr');

                // Adicionar a célula do tipo de imagem apenas na primeira linha
                if (firstRow) {
                    const tipoCell = document.createElement('td');
                    tipoCell.textContent = tipoImagem;
                    tipoCell.setAttribute('rowspan', rowSpan);
                    row.appendChild(tipoCell);
                    tipoCell.style.writingMode = 'sideways-lr'; // Rotacionar o texto
                }

                // Adicionar a célula do nome da imagem
                const nameCell = document.createElement('td');
                nameCell.textContent = imagemNome;
                row.appendChild(nameCell);

                // Adicionar as etapas, se existirem
                if (etapas[tipoImagem] && firstRow) {
                    etapas[tipoImagem].forEach(etapa => {
                        const dataInicio = new Date(etapa.data_inicio);
                        const dataFim = new Date(etapa.data_fim);

                        // Calcular o índice da data de início e fim em relação ao array de datas
                        const indexInicio = datas.findIndex(d => d.getTime() === dataInicio.getTime());
                        const indexFim = datas.findIndex(d => d.getTime() === dataFim.getTime());

                        const colspan = indexFim - indexInicio + 1;

                        const etapaCell = document.createElement('td');
                        etapaCell.setAttribute('colspan', colspan);
                        etapaCell.setAttribute('rowspan', rowSpan); // Adicionar rowspan para etapas
                        etapaCell.className = etapa.etapa
                            .toLowerCase()
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .replace(/\s/g, '')
                            .replace(/[^a-z0-9]/g, '');
                        etapaCell.textContent = etapa.etapa;
                        row.appendChild(etapaCell);
                    });
                }

                // Adicionar células vazias para os dias restantes
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
                firstRow = false; // Apenas a primeira linha terá o tipo_imagem e etapas
            });
        });

        table.appendChild(tbody);
    })
    .catch(error => console.error('Erro ao carregar os dados:', error));


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
