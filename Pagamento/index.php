<?php

include '../conexao.php';

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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
    integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css">

  <title>Document</title>
</head>

<body>
  <header>
    <div class="navbar-left">
      <h1>Improov + Flow</h1>

    </div>
    <div class="navbar-right">
      <i class="fas fa-bars" id="menu-toggle" style="cursor: pointer;"></i>

      <div id="menu" class="hidden">
        <a href="../ControleComercial/index.html" target="_blank">Controle Comercial</a>
      </div>
      <i class="fa-solid fa-user"></i>
    </div>
  </header>

  <main>
    <div class="filtros">
      <div class="colab">
        <label for="">Colaborador:</label>
        <select name="colaborador" id="colaborador">
          <option value="0">Selecione:</option>
          <?php foreach ($colaboradores as $colab): ?>
            <option value="<?= $colab['idcolaborador']; ?>">
              <?= htmlspecialchars($colab['nome_colaborador']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="obra">
        <label for="">Obra:</label>
        <select name="obra" id="obra">
          <option value="0">Selecione:</option>
          <?php foreach ($obras as $obra): ?>
            <option value="<?= $obra['idobra']; ?>">
              <?= htmlspecialchars($obra['nome_obra']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

    </div>

    <div class="menu-buttons">

      <div class="buttons">
        <button id="marcar-todos">Marcar/Desmarcar Todos</button>
        <button id="confirmar-pagamento">Confirmar Pagamento</button>
        <button id="adicionar-valor">Adicionar valor</button>
        <input type="text" id="valor" placeholder="R$ 0,00">
      </div>

      <div class="valor">
        <label id="totalValor">Total: R$ 0,00</label>
        <label id="contagemLinhasLabel">Total de imagens: 0</label>
      </div>
    </div>

    <div class="tabela">

      <table id="tabela-faturamento">
        <thead>
          <tr>
            <td>Nome da Imagem</td>
            <td>Status</td>
            <td>Função</td>
            <td>Valor</td>
            <td>Status Pgt</td>
          </tr>
        </thead>
        <tbody>

        </tbody>
      </table>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
  <script src="script.js"></script>

</body>

</html>