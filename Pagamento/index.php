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

			<select name="colaborador" id="colaborador">
				<?php foreach ($colaboradores as $colab): ?>
					<option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
						<?= htmlspecialchars($colab['nome_colaborador']); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<select name="mes" id="mes">
				<option value="Janeiro">Janeiro</option>
				<option value="Fevereiro">Fevereiro</option>
				<option value="Março">Março</option>
				<option value="Abril">Abril</option>
				<option value="Maio">Maio</option>
				<option value="Junho">Junho</option>
				<option value="Julho">Julho</option>
				<option value="Agosto">Agosto</option>
				<option value="Setembro">Setembro</option>
				<option value="Outubro">Outubro</option>
				<option value="Novembro">Novembro</option>
				<option value="Dezembro">Dezembro</option>
			</select>

		</div>

		<img src="../gif/assinatura_branco.gif" alt="">

		<p id="data"></p>
	</header>

	<main>
		<section id="table-list">
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
							<th>Nome da Imagem</th>
							<th>Status</th>
							<th>Função</th>
							<th>Valor</th>
							<th>Status Pgt</th>
						</tr>
					</thead>
					<tbody>

					</tbody>
				</table>
			</div>
		</section>

		<section id="graficos-tarefas">
			<div>
				<canvas id="tarefasPorMes"></canvas>
				p#
			</div>
			<div>
				<canvas id="statusTarefas"></canvas>
			</div>
		</section>
	</main>

	<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script src="script.js"></script>

</body>

</html>