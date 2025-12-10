<?php
include '../conexao.php';

session_start();
// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

include '../conexaoMain.php';
$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">

    <title>Prioridades</title>
</head>

<body>
    <?php

    include '../sidebar.php';

    ?>
    <div id="container" class="container">
        <table id="tabela-arquivos">
            <thead>
                <tr>
                    <th>Nome do Arquivo</th>
                    <th>Projeto</th>
                    <th>Status</th>
                    <th>Data de Recebimento</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <!-- MODAL ÚNICO -->
    <div class="modal" id="modalUpload" style="display: none;">
        <div class="modal-content">
            <h3>Revisão do arquivo</h3>

            <form id="formRevisaoArquivo">
                <input type="hidden" name="arquivoId" id="arquivoId" value="">
                <input type="hidden" name="obraId" id="obraId" value="">
                <!-- <input type="checkbox" name="batch_imagens[]" value="ID"> -->

                <!-- Arquivo completo ou incompleto -->
                <label>O arquivo está completo ou incompleto?</label>
                <select name="status_arquivo" required>
                    <option value="">Selecione</option>
                    <option value="completo">Completo</option>
                    <option value="incompleto">Incompleto</option>
                </select>

                <!-- Tipo de arquivo -->
                <label>O arquivo é:</label>
                <select name="tipo_arquivo" id="tipo_arquivo" required>
                    <option value="">Selecione</option>
                    <option value="novo">Novo (primeira versão)</option>
                    <option value="revisao">Revisão de um existente</option>
                    <option value="material_complementar">Material complementar</option>
                </select>

                <label for="categoria">Categoria:</label>
                <select name="categoria" id="categoria" required>
                    <option value="Arquitetonico">Arquitetônico</option>
                    <option value="Luminotecnico">Luminotécnico</option>
                    <option value="Paisagistico">Paisagístico</option>
                    <option value="Interiores">Interiores</option>
                    <option value="Complementar">Complementar</option>
                </select>


                <!-- Campo que será mostrado apenas para revisão -->
                <div id="divSubstitui" style="display: none;">
                    <label>O arquivo substitui outro existente? Qual?</label>
                    <select name="substitui_arquivo" id="substitui_arquivo">
                        <option value="">Carregando...</option>
                    </select>
                </div>

                <!-- Relacionamento com tipo_imagem -->
                <label>A que tipo_imagem se relaciona?</label>
                <select name="tipo_imagem[]" id="tipo_imagem" multiple required>
                    <option value="todas">Todas</option>
                    <option value="Fachada">Fachada</option>
                    <option value="Imagem Interna">Imagem Interna</option>
                    <option value="Imagem Externa">Imagem Externa</option>
                    <option value="Unidade">Unidade</option>
                    <option value="Planta Humanizada">Planta Humanizada</option>
                </select>

                <!-- Área onde as imagens aparecerão -->
                <div id="imagensTipo" style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 10px;">
                    <!-- Imagens serão inseridas aqui dinamicamente -->
                </div>

                <!-- Observação / descrição -->
                <label>Observação / Descrição:</label>
                <textarea name="observacao" placeholder="Ex.: Arquitetônico, Luminotécnico"></textarea>


                <button type="submit">Salvar revisão</button>
                <button type="button" onclick="fecharModal()">Cancelar</button>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>
</body>

</html>