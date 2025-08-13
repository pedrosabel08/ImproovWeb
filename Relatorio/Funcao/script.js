// Busca dados reais do PHP
fetch('dados.php')
    .then(response => response.json())
    .then(dados => {
        // Preenche cards principais
        document.getElementById("tempoAprovacao").innerText = dados.tempoAprovacao + " dias";
        // document.getElementById("taxaAprovacao").innerText = dados.taxaAprovacao + "%";

        // Se no PHP tempoFuncao vier como array, ajusta a exibição
        if (dados.tempoFuncao && dados.tempoFuncao.length > 0) {
            // Aqui você pode decidir qual função mostrar ou calcular média
            const mediaTempoFuncao = dados.tempoFuncao
                .map(f => parseFloat(f.duracao_dias))
                .reduce((a, b) => a + b, 0) / dados.tempoFuncao.length;
            document.getElementById("tempoFuncao").innerText = mediaTempoFuncao.toFixed(2) + " dias";
        } else {
            document.getElementById("tempoFuncao").innerText = "-";
        }

        document.getElementById("produtividade").innerText = `${dados.produtividade.nome_colaborador}: ${dados.produtividade.total_finalizadas} entregas`;

        // Colaboradores
        document.getElementById("maisProdutivo").innerText = "Mais produtivo: " + (dados.colaboradores.produtivo ?? "-");

        // Preenche tabela funções
        const tabelaFuncoes = document.getElementById("tabelaFuncoes");
        tabelaFuncoes.innerHTML = ""; // limpa antes
        if (dados.tempoFuncao && dados.tempoFuncao.length > 0) {
            dados.tempoFuncao.forEach(f => {
                const tr = document.createElement("tr");
                tr.innerHTML = `
                    <td class="p-2">Colaborador ${f.nome_colaborador}</td>
                    <td class="p-2">-</td>
                    <td class="p-2">${parseFloat(f.duracao_dias).toFixed(2)} dias</td>
                    <td class="p-2">-</td>
                    <td class="p-2">-</td>
                `;
                tabelaFuncoes.appendChild(tr);
            });
        }

        // Gráfico - exemplo usando valores fixos porque PHP ainda não envia status
        const ctx = document.getElementById("statusChart");
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Em andamento', 'Em aprovação', 'Finalizados'],
                datasets: [{
                    data: [12, 5, 18], // aqui você coloca dados reais quando o PHP retornar
                    backgroundColor: ['#face52', '#5bbcfc', '#47fc44']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

    })
    .catch(err => {
        console.error("Erro ao buscar dados:", err);
    });
