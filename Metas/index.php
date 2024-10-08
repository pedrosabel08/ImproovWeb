<?php
session_start();

include 'conexao.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

$sql = "SELECT COUNT(*) as total FROM imagens_cliente_obra";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

// Obter o total de imagens
$row = $result->fetch_assoc();
$total_imagens = $row['total'];

// Fechar a conexão
$stmt->close();
$conn->close();

$meta_imagens = 600;

// Calcular a porcentagem
$porcentagem = ($total_imagens / $meta_imagens) * 100;
$porcentagem = number_format($porcentagem, 2);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/styleMetas.css">
    <title>Metas e progresso</title>
</head>

<body>

    <header>
        <button id="voltar" onclick="window.location.href='../inicio.php'">Voltar</button>
        <h2>Metas e progresso</h2>
    </header>
    <nav>
        <div id="nav-left">
            <p id="dataAtual"></p>
        </div>
        <select name="" id="">
            <option value="1">Geral</option>
            <option value="2">Por função</option>
        </select>
    </nav>
    <main>
        <div id="descricao">

            <div class="total_anual">
                <h2>Anual</h2>
                <div>
                    <label>Total de imagens: </label>
                    <p id="totalImagens" data-value="<?php echo $total_imagens; ?>"><?php echo $total_imagens; ?></p>
                </div>
                <div>
                    <label>Meta de imagens: </label>
                    <p id="metaImagens" data-value="<?php echo $meta_imagens; ?>"><?php echo $meta_imagens; ?></p>
                </div>
                <div>
                    <label>Porcentagem: </label>
                    <p id="porcentagem" data-value="<?php echo $porcentagem; ?>"><?php echo $porcentagem; ?>%</p>
                </div>
            </div>
            <div class="total_mes">
                <h2>Mensal</h2>
                <div>
                    <label>Total de imagens: </label>
                    <p>50</p>
                </div>
                <div>
                    <label for="">Meta de imagens: </label>
                    <p>100</p>
                </div>
                <div>
                    <label>Porcentagem: </label>
                    <p>50%</p>
                </div>
            </div>
        </div>

        <div class="graficos">
            <div id="grafico-mensal">
                <select id="anoSelect">
                    <option value="2024">2024</option>
                    <option value="2023">2023</option>
                    <option value="2022">2022</option>
                </select>

                <div id="chartContainer">
                    <canvas id="imagensChart" width="700" height="400"></canvas>
                </div>
            </div>
        </div>
    </main>

    <div class="container">
        <div class="metas">
            <h3>Caderno:</h3>
            <div>
                <label for="">Tarefas atuais: </label>
                <p id="caderno" data-value="0">0</p>
            </div>
            <div>
                <label for="">Meta: </label>
                <p id="meta-caderno" data-value="0">Meta: 0</p>
            </div>
            <div>
                <label for="">Porcentagem concluída: </label>
                <p id="porcentagem-caderno" data-value="0">Porcentagem: 0%</p>
            </div>
        </div>

        <div class="metas">
            <h3>Modelagem:</h3>
            <div>
                <label for="">Tarefas atuais: </label>
                <p id="model" data-value="0">0</p>
            </div>
            <div>
                <label for="">Meta: </label>
                <p id="meta-model" data-value="0">Meta: 0</p>
            </div>
            <div>
                <label for="">Porcentagem concluída: </label>
                <p id="porcentagem-model" data-value="0">Porcentagem: 0%</p>
            </div>
        </div>

        <div class="metas">
            <h3>Composição:</h3>
            <div>
                <label for="">Tarefas atuais: </label>
                <p id="comp" data-value="0">0</p>
            </div>
            <div>
                <label for="">Meta: </label>
                <p id="meta-comp" data-value="0">Meta: 0</p>
            </div>
            <div>
                <label for="">Porcentagem concluída: </label>
                <p id="porcentagem-comp" data-value="0">Porcentagem: 0%</p>
            </div>
        </div>

        <div class="metas">
            <h3>Finalização:</h3>
            <div>
                <label for="">Tarefas atuais: </label>
                <p id="final" data-value="0">0</p>
            </div>
            <div>
                <label for="">Meta: </label>
                <p id="meta-final" data-value="0">Meta: 0</p>
            </div>
            <div>
                <label for="">Porcentagem concluída: </label>
                <p id="porcentagem-final" data-value="0">Porcentagem: 0%</p>
            </div>
        </div>

        <div class="metas">
            <h3>Pós-produção:</h3>
            <div>
                <label for="">Tarefas atuais: </label>
                <p id="pos" data-value="0">0</p>
            </div>
            <div>
                <label for="">Meta: </label>
                <p id="meta-pos" data-value="0">Meta: 0</p>
            </div>
            <div>
                <label for="">Porcentagem concluída: </label>
                <p id="porcentagem-pos" data-value="0">Porcentagem: 0%</p>
            </div>
        </div>

        <div class="metas">
            <h3>Planta Humanizada:</h3>
            <div>
                <label for="">Tarefas atuais: </label>
                <p id="planta" data-value="0">0</p>
            </div>
            <div>
                <label for="">Meta: </label>
                <p id="meta-planta" data-value="0">Meta: 0</p>
            </div>
            <div>
                <label for="">Porcentagem concluída: </label>
                <p id="porcentagem-planta" data-value="0">Porcentagem: 0%</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>

</body>

</html>