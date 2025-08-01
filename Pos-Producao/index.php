<?php

session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

$usuario_id = $_SESSION['idusuario']; // ID do usuário logado


$sql_clientes = "SELECT idcliente, nome_cliente FROM cliente";
$result_cliente = $conn->query($sql_clientes);
$clientes = array();
if ($result_cliente->num_rows > 0) {
    while ($row = $result_cliente->fetch_assoc()) {
        $clientes[] = $row;
    }
}

$sql_obras = "SELECT idobra, nome_obra FROM obra";
$result_obra = $conn->query($sql_obras);
$obras = array();
if ($result_obra->num_rows > 0) {
    while ($row = $result_obra->fetch_assoc()) {
        $obras[] = $row;
    }
}

$sql_colaboradores = "SELECT idcolaborador, nome_colaborador FROM colaborador order by nome_colaborador";
$result_colaboradores = $conn->query($sql_colaboradores);
$colaboradores = array();
if ($result_colaboradores->num_rows > 0) {
    while ($row = $result_colaboradores->fetch_assoc()) {
        $colaboradores[] = $row;
    }
}

$sql_status = "SELECT idstatus, nome_status 
               FROM status_imagem 
               ORDER BY 
                   CASE 
                       WHEN nome_status = 'Sem status' THEN 0 
                       ELSE 1 
                   END, 
                   idstatus";

$result_status = $conn->query($sql_status);
$status_imagens = array();
if ($result_status->num_rows > 0) {
    while ($row = $result_status->fetch_assoc()) {
        $status_imagens[] = $row;
    }
}
// $sql_imagens = "SELECT idimagens_cliente_obra, imagem_nome FROM imagens_cliente_obra";
// $result_imagens = $conn->query($sql_imagens);
// $imagens = array();
// if ($result_imagens->num_rows > 0) {
//     while ($row = $result_imagens->fetch_assoc()) {
//         $imagens[] = $row;
//     }
// }

include '../conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$imagens = obterImagens($conn);

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="stylePos.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.2.0/dist/css/tabulator.min.css" rel="stylesheet">

    <title>Lista Pós-Produção</title>
</head>

<body>

    <?php

    include '../sidebar.php';

    ?>
    <div id="filtro">
        <header>
            <h1>Lista Pós-Produção</h1>
            <img src="../gif/assinatura_preto.gif" alt="" style="width: 200px;">

            <button id="openModalBtn" style="display: none;">Inserir render</button>
            <button id="openModalBtnRender">Ver Render Elements</button>
        </header>
        <div class="tabela-container">
            <p style="margin: 15px 0; text-align: right;">Total de pós: <span id="total-pos"></span></p>
            <div id="tabela-imagens"></div>
        </div>
    </div>



    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <button id="deleteButton">Excluir</button>
            <div id="form-inserir">
                <h2>Formulário de Dados</h2>
                <form id="formPosProducao">
                    <div>
                        <label for="nomeFinalizador">Nome Finalizador</label>
                        <select name="final_id" id="opcao_finalizador" required>
                            <option value="0">Selecione um colaborador:</option>
                            <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                    <?= htmlspecialchars($colab['nome_colaborador']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="nomeObra">Nome Obra</label>
                        <select name="obra_id" id="opcao_obra" onchange="buscarImagens()" required>
                            <option value="0">Selecione uma obra:</option>
                            <?php foreach ($obras as $obra): ?>
                                <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="imagem_id">Nome Imagem</label>
                        <select id="imagem_id_pos" name="imagem_id_pos" required>
                            <option value="">Selecione uma imagem:</option>
                            <?php foreach ($imagens as $imagem): ?>
                                <option value="<?= $imagem['idimagens_cliente_obra']; ?>">
                                    <?= htmlspecialchars($imagem['imagem_nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="caminhoPasta">Caminho Pasta</label>
                        <input type="text" id="caminhoPasta" name="caminho_pasta">
                    </div>

                    <div>
                        <label for="numeroBG">Número BG</label>
                        <input type="text" id="numeroBG" name="numero_bg">
                    </div>

                    <div>
                        <label for="referenciasCaminho">Referências/Caminho</label>
                        <input type="text" id="referenciasCaminho" name="refs">
                    </div>

                    <div>
                        <label for="observacao">Observação</label>
                        <textarea id="observacao" name="obs" rows="3"></textarea>
                    </div>

                    <div>
                        <label for="status">Revisão</label>
                        <select name="status_id" id="opcao_status">
                            <?php foreach ($status_imagens as $status): ?>
                                <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                                    <?= htmlspecialchars($status['nome_status']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="text" name="id-pos" id="id-pos" hidden>
                    <input type="hidden" id="alterar_imagem" name="alterar_imagem" value="false">

                    <div>
                        <label for="status_pos">Status</label>
                        <input type="checkbox" name="status_pos" id="status_pos" disabled>
                    </div>

                    <div>
                        <label for="nome_responsavel">Nome Responsável</label>
                        <select name="responsavel_id" id="responsavel_id">
                            <option value="14" id="Adriana">Adriana</option>
                            <option value="28" id="Eduardo">Eduardo</option>
                        </select>
                    </div>

                    <input type="hidden" id="render_id_pos" name="render_id_pos">


                    <div>
                        <button type="submit">Enviar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="renderModal" class="modal">
        <div class="modal-content">
            <span class="closeModalRender">&times;</span>
            <h2>Render Elements</h2>
            <p>Aqui estão os elementos de render que você deve considerar:</p>
            <ul>
                <li>Alpha</li>
                <li>Máscara RGB para vegetações</li>
                <li>Máscara RGB para vidros</li>
                <li>Máscara RGB para paredes das fachadas</li>
                <li>Máscara RGB para detalhes arquitetônicos</li>
                <li>(Essas máscaras RGB não precisam ser necessariamente cada uma em um element diferente, apenas cores diferentes)</li>
                <li>Wire Color</li>
                <li>Masking ID</li>
                <li>Direct</li>
                <li>Indirect</li>
                <li>Beauty</li>
                <li>Bloom glare</li>
                <li>Environment</li>
                <li>Light Select - Sol ou outras que sejam importantes para a cena</li>
                <li>(Não precisa separar cada luz da cena em um element diferente)</li>
                <li>Raw component</li>
                <li>Component</li>
                <li>Translucency</li>
                <li>Reflect</li>
                <li>Refract</li>
                <li>Texmap</li>
                <li>Albedo</li>
                <li>Zdeph</li>
            </ul>
            <p><strong>Observações sobre luzes:</strong></p>
            <h3>Alguns mandam cada luz da cena num element, como a academia que tinha um element para cada spot, não precisa.</h3>
            <p><strong>Observações sobre Zdeph:</strong></p>
            <h3>Sobre o Zdeph, eu não sei como são feitas as configurações, mas alguns vem todo preto e outros já num gradiente, o legal é esse gradiente, que dá pra usar pra colocar um fog e separar os planos da imagem.</h3>
        </div>
    </div>


    <script src="scriptPos.js"></script>
    <script src="../script/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://unpkg.com/tabulator-tables@6.2.0/dist/js/tabulator.min.js"></script>

</body>

</html>