<?php
session_start();
// $nome_usuario = $_SESSION['nome_usuario'];

include '../conexaoMain.php';
include '../conexao.php';

// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
//     // Se não estiver logado, redirecionar para a página de login
//     header("Location: ../index.html");
//     exit();
// }

// Buscar a quantidade de funções do colaborador com status "Em andamento"
// $colaboradorId = $_SESSION['idcolaborador'];
// $funcoesCountSql = "SELECT COUNT(*) AS total_funcoes_em_andamento
//                     FROM funcao_imagem
//                     WHERE colaborador_id = ? AND status = 'Em andamento'";
// $funcoesCountStmt = $conn->prepare($funcoesCountSql);
// $funcoesCountStmt->bind_param("i", $colaboradorId);
// $funcoesCountStmt->execute();
// $funcoesCountResult = $funcoesCountStmt->get_result();

// Armazenar a quantidade na sessão
// $funcoesCount = $funcoesCountResult->fetch_assoc();

// $funcoesCountStmt->close();

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$imagens = obterImagens($conn);
$status_etapa = obterStatus($conn);

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kanban de Entregas</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header>📦 Kanban de Entregas
        <button id="adicionar_entrega">Adicionar Entrega</button>
    </header>

    <main id="kanban">
        <div class="column" data-status="pendente">
            <h2>A entregar</h2>
        </div>
        <div class="column" data-status="concluida">
            <h2>Enviado / Aguardando feedback</h2>
        </div>
        <div class="column" data-status="atrasadas">
            <h2>Atrasadas</h2>
        </div>
        <div class="column" data-status="aprovadas">
            <h2>Aprovadas</h2>
        </div>
    </main>

    <!-- Modal Adicionar Entrega -->
    <div id="modalAdicionarEntrega" class="modal">
        <div class="modal-content">
            <h2>Adicionar Entrega</h2>
            <form id="formAdicionarEntrega">
                <div>
                    <label>Obra:</label>
                    <select name="obra_id" id="obra_id" required>
                        <option value="">Selecione a obra</option>
                        <?php foreach ($obras as $obra): ?>
                            <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Status:</label>
                    <select name="status_id" id="status_id" required>
                        <option value="">Selecione o status</option>
                        <?php foreach ($status_imagens as $status): ?>
                            <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                                <?= htmlspecialchars($status['nome_status']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="imagens_container" class="imagens-container">
                    <p>Selecione uma obra e status para listar as imagens.</p>
                </div>

                <div>
                    <input type="date" name="prazo" id="prazo">
                </div>
                <div>
                    <textarea name="observacoes" id="observacoes"></textarea>
                </div>

                <button type="submit" class="btn-salvar">Salvar Entrega</button>
            </form>
        </div>
    </div>


    <!-- Modal -->
    <div class="modal" id="entregaModal">
        <div class="modal-content">
            <h3 id="modalTitulo"></h3>
            <p><strong>Etapa:</strong> <span id="modalEtapa"></span></p>
            <p><strong>Prazo:</strong> <span id="modalPrazo"></span></p>
            <p><strong>Conclusão geral:</strong> <span id="modalProgresso"></span></p>
            <h4 style="margin-top:1rem; color: var(--accent-color);">📸 Imagens</h4>
            <div id="modalImagens"></div>
            <button id="fecharModal">Fechar</button>
        </div>
    </div>

    <script src="script.js"></script>
</body>

</html>