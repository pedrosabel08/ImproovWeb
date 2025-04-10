<!DOCTYPE html>
<html lang="pt-BR">


<?php
include '../conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();
?>

<head>
    <meta charset="UTF-8">
    <title>Resumo por Colaborador e Mês</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
        }

        th {
            background-color: #f4f4f4;
        }
    </style>
</head>

<body>

    <h2>Resumo por Colaborador</h2>

    <label for="colaborador">Selecione um colaborador:</label>
    <select id="colaborador">
        <?php foreach ($colaboradores as $colab): ?>
            <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                <?= htmlspecialchars($colab['nome_colaborador']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <table id="tabelaResumo" style="display:none;">
        <thead>
            <tr>
                <th>Função</th>
                <th>Mês/Ano</th>
                <th>Total Valor</th>
                <th>Total Quantidade</th>
                <th>Mês anterior</th>
                <th>Recorde Produção</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <h2>Resumo por Mês</h2>

    <label for="mes">Selecione um mês:</label>
    <select id="mes">
        <option value="">Selecione</option>
        <?php for ($i = 1; $i <= 12; $i++): ?>
            <option value="<?= $i; ?>"><?= DateTime::createFromFormat('!m', $i)->format('F'); ?></option>
        <?php endfor; ?>
    </select>

    <table id="tabelaResumoMes" style="display:none;">
        <thead>
            <tr>
                <th>Função</th>
                <th>Total Quantidade</th>
                <th>Total Valor</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <script>
        const selectColaborador = document.getElementById('colaborador');
        const tabelaColaborador = document.getElementById('tabelaResumo');
        const tbodyColaborador = tabelaColaborador.querySelector('tbody');

        const selectMes = document.getElementById('mes');
        const tabelaMes = document.getElementById('tabelaResumoMes');
        const tbodyMes = tabelaMes.querySelector('tbody');

        selectColaborador.addEventListener('change', () => {
            const nome = selectColaborador.value;
            if (!nome) {
                tabelaColaborador.style.display = 'none';
                return;
            }

            fetch(`backend.php?colaborador=${encodeURIComponent(nome)}`)
                .then(res => res.json())
                .then(data => {
                    tbodyColaborador.innerHTML = '';
                    data.forEach(item => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${item.nome_funcao}</td>
                            <td>${item.data_pagamento}</td>
                            <td>R$ ${Number(item.total_valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                            <td>${item.quantidade}</td>
                            <td>${item.quantidade_mes_anterior}</td>
                            <td>${item.recorde}</td>
                        `;
                        tbodyColaborador.appendChild(tr);
                    });
                    tabelaColaborador.style.display = '';
                });
        });

        selectMes.addEventListener('change', () => {
            const mes = selectMes.value;
            if (!mes) {
                tabelaMes.style.display = 'none';
                return;
            }

            fetch(`backend.php?mes=${encodeURIComponent(mes)}`)
                .then(res => res.json())
                .then(data => {
                    tbodyMes.innerHTML = '';
                    data.forEach(item => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${item.nome_funcao}</td>
                            <td>${item.quantidade}</td>
                            <td>R$ ${Number(item.total_valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                        `;
                        tbodyMes.appendChild(tr);
                    });
                    tabelaMes.style.display = '';
                });
        });
    </script>

</body>

</html>