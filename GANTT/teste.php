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

    <script src="script.js">

    </script>

</body>

</html>