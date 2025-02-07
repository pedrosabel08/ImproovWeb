document.addEventListener('DOMContentLoaded', function () {
    const tabelaObrasBody = document.querySelector('#tabela-obras tbody');
    const modal = document.getElementById('modalAcompanhamento');
    const closeModal = document.querySelector('.close');
    const acompanhamentoConteudo = document.getElementById('acompanhamentoConteudo');

    // Função para carregar os dados das obras
    function carregarObras() {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'getObras.php', true);

        xhr.onload = function () {
            if (xhr.status === 200) {
                const obras = JSON.parse(xhr.responseText);
                tabelaObrasBody.innerHTML = '';

                let contagemAtivas = 0;
                let contagemNaoAtivas = 0;

                obras.forEach(obra => {
                    const row = document.createElement('tr');
                    const statusTexto = obra.status_obra === "0" ? "Ativo" : "Não Ativo";

                    if (obra.status_obra === "0") {
                        contagemAtivas++;
                    } else {
                        contagemNaoAtivas++;
                    }

                    row.innerHTML = `
                        <td>${obra.idobra}</td>
                        <td>${obra.nome_obra}</td>
                        <td>${statusTexto}</td>
                    `;


                    row.addEventListener('click', function () {
                        abrirModalAcompanhamento(obra.idobra);
                    });

                    tabelaObrasBody.appendChild(row);
                });

                document.getElementById('contagemAtivas').innerText = contagemAtivas;
                document.getElementById('contagemNaoAtivas').innerText = contagemNaoAtivas;
            } else {
                console.error('Erro ao carregar dados:', xhr.status);
            }
        };

        xhr.onerror = function () {
            console.error('Erro de rede');
        };

        xhr.send();
    }

    // Função para abrir o modal e carregar os acompanhamentos
    function abrirModalAcompanhamento(idObra) {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `getAcompanhamentoEmail.php?idobra=${idObra}`, true);

        xhr.onload = function () {
            if (xhr.status === 200) {
                const acompanhamentos = JSON.parse(xhr.responseText);
                acompanhamentoConteudo.innerHTML = ''; // Limpa o conteúdo anterior

                if (acompanhamentos.length > 0) {
                    acompanhamentos.forEach(acomp => {
                        const item = document.createElement('p');
                        item.innerHTML = `<strong>Assunto:</strong> ${acomp.assunto}<br><strong>Data:</strong> ${acomp.data}`;
                        acompanhamentoConteudo.appendChild(item);
                    });
                } else {
                    acompanhamentoConteudo.innerHTML = '<p>Nenhum acompanhamento encontrado.</p>';
                }

                modal.style.display = 'block'; // Exibe o modal
            } else {
                console.error('Erro ao carregar dados:', xhr.status);
            }
        };

        xhr.onerror = function () {
            console.error('Erro de rede');
        };

        xhr.send();
    }

    // Evento para fechar o modal
    closeModal.addEventListener('click', function () {
        modal.style.display = 'none';
    });

    // Fecha o modal se o usuário clicar fora dele
    window.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    // Carrega as obras ao carregar a página
    carregarObras();

});