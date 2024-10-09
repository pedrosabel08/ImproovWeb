function atualizarTabela() {
    fetch('buscar_acomp.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const tabela = document.getElementById('tabela-acomp');
            tabela.querySelector('tbody').innerHTML = '';

            data.forEach(acomp => {
                const tr = document.createElement('tr');
                tr.classList.add('linha-tabela');
                tr.setAttribute('data-obra-id', acomp.obra_id);

                // Cria as células da tabela
                tr.innerHTML = `
                    <td>${acomp.nome_obra}</td>       
                    <td>${acomp.nome_colaborador}</td>   
                    <td>${(acomp.data)}</td> <!-- Formata a data -->
                `;

                tabela.querySelector('tbody').appendChild(tr);
            });
        })
        .catch(error => console.error('Erro ao atualizar a tabela:', error));
}

// Chama a função quando a página carregar
document.addEventListener('DOMContentLoaded', atualizarTabela);


function atualizarTabelaEmail() {
    fetch('buscar_acomp_email.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const tabela = document.getElementById('tabela-acomp-email');
            tabela.querySelector('tbody').innerHTML = '';

            data.forEach(acomp => {
                const tr = document.createElement('tr');
                tr.classList.add('linha-tabela');
                tr.setAttribute('data-obra-id', acomp.obra_id);

                // Cria as células da tabela
                tr.innerHTML = `
                    <td>${acomp.nome_obra}</td>       
                    <td>${acomp.nome_colaborador}</td>   
                    <td>${acomp.assunto}</td>    <!-- ID do colaborador -->
                    <td>${(acomp.data)}</td> <!-- Formata a data -->
                `;

                tabela.querySelector('tbody').appendChild(tr);
            });
        })
        .catch(error => console.error('Erro ao atualizar a tabela:', error));
}

// Chama a função quando a página carregar
document.addEventListener('DOMContentLoaded', atualizarTabelaEmail);