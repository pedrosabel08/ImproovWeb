<?php

include '../conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$usuarios = oberUsuarios($conn);

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css' rel='stylesheet' />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">

    <link href="style.css" rel="stylesheet">
    <title>Calendário de Entregas</title>
</head>

<body>

    <button onclick="window.location.href='../inicio.php'" id="button-sair"><i class="fas fa-arrow-left"></i></button>
    <div id="calendar"></div>

    <!-- Botões para adicionar prazos e ver log por obra -->
    <div class="buttons">
        <button id="addEventBtn" class="btn btn-primary mt-3">Adicionar Prazo de Entrega</button>
        <button id="logObra" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#logObraModal">Ver log por obra</button>
    </div>

    <!-- Modal para adicionar prazo de entrega -->
    <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addEventModalLabel">Adicionar Prazo de Entrega</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addEventForm">
                        <div class="mb-3">
                            <label for="obraId" class="form-label">ID da Obra</label>
                            <select name="opcao" id="opcao_obra">
                                <?php foreach ($obras as $obra): ?>
                                    <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="prazoDate" class="form-label">Prazo</label>
                            <input type="date" class="form-control" id="prazoDate" required>
                        </div>
                        <div class="mb-3">
                            <label for="assuntoEntrega" class="form-label">Assunto da Entrega</label>
                            <input type="text" class="form-control" id="assuntoEntrega" required>
                        </div>
                        <div class="mb-3">
                            <label for="tipoEntrega" class="form-label">Tipo da Entrega</label>
                            <select name="tipoEntrega" id="tipoEntrega">
                                <option value="Primeira Entrega">Primeira Entrega</option>
                                <option value="Entrega Final">Entrega Final</option>
                                <option value="Alteração">Alteração</option>
                                <option value="Entrega tarefa">Entrega tarefa</option>
                                <option value="Entrega antecipada">Entrega antecipada</option>
                                <option value="Entrega parcial">Entrega parcial</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="">Colaboradores:</label>
                            <select name="usuario_id[]" id="usuario_id" multiple class="form-control">
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?= htmlspecialchars($usuario['idusuario']); ?>">
                                        <?= htmlspecialchars($usuario['nome_usuario']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success">Salvar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para ver o log por obra -->
    <div class="modal fade" id="logObraModal" tabindex="-1" aria-labelledby="logObraModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logObraModalLabel">Log de Prazos da Obra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="logObraForm">
                        <div class="mb-3">
                            <label for="logObraSelect" class="form-label">Selecione a Obra</label>
                            <select id="logObraSelect" class="form-select">
                                <option value="">Selecione uma obra</option>
                                <?php foreach ($obras as $obra): ?>
                                    <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nome_obra']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>

                    <!-- Tabela para exibir os prazos da obra selecionada -->
                    <table id="logObraTable" class="table table-bordered mt-3" style="display: none;">
                        <thead>
                            <tr>
                                <th>Prazo</th>
                                <th>Tipo da Entrega</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dados dinâmicos serão inseridos aqui -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js'></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>

</html>