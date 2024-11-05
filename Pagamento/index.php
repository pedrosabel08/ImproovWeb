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
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
	<link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
		type="image/x-icon">

	<title>Tela de pagamento</title>
</head>

<body>
	<header>
		<img src="../gif/assinatura_branco.gif" alt="">

		<p id="data"></p>
	</header>

	<main>
		<div class="filtro">
			<div class="colaborador">
				<h2>Colaborador:</h2>
				<select name="colaborador" id="colaborador">
					<?php foreach ($colaboradores as $colab): ?>
						<option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
							<?= htmlspecialchars($colab['nome_colaborador']); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="mes">
				<h2>Mês:</h2>
				<select name="mes" id="mes">
					<option value="1">Janeiro</option>
					<option value="2">Fevereiro</option>
					<option value="3">Março</option>
					<option value="4">Abril</option>
					<option value="5">Maio</option>
					<option value="6">Junho</option>
					<option value="7">Julho</option>
					<option value="8">Agosto</option>
					<option value="9">Setembro</option>
					<option value="10">Outubro</option>
					<option value="11">Novembro</option>
					<option value="12">Dezembro</option>
				</select>
			</div>

			<div class="tipo-imagem">
				<h2>Função:</h2>
				<select id="tipoImagemFiltro" onchange="filtrarTabela()">
					<option value="">Todos as Funções</option>
					<option value="Caderno">Caderno</option>
					<option value="Filtro de assets">Filtro de assets</option>
					<option value="Modelagem">Modelagem</option>
					<option value="Composição">Composição</option>
					<option value="Finalização">Finalização</option>
					<option value="Alteração">Alteração</option>
					<option value="Pós-Produção">Pós-Produção</option>
					<option value="Planta Humanizada">Planta Humanizada</option>
					<option value="Acompanhamento">Acompanhamento</option>
					<option value="Animação">Animação</option>

					
				</select>
			</div>
		</div>
		<section id="table-list">
			<div class="menu-buttons">
				<div class="buttons">
					<button id="marcar-todos">Marcar/Desmarcar Todos</button>
					<button id="confirmar-pagamento">Confirmar Pagamento</button>
					<button id="adicionar-valor">Adicionar valor</button>
					<input type="text" id="valor" placeholder="R$ 0,00">
				</div>

				<div id="valores">
					<div class="total-valor">
						<p>Total (R$):</p>
						<span id="totalValor"></span>
					</div>
					<div class="total-tarefas">
						<p>Total:</p>
						<span id="total-imagens"></span>
					</div>
					<button id="generate-pdf">Gerar PDF</button>
				</div>
			</div>

			<div class="tabela">
				<table id="tabela-faturamento">
					<thead>
						<tr>
							<th>Nome da Imagem</th>
							<th>Status</th>
							<th>Função</th>
							<th>Valor (R$)</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>

					</tbody>
				</table>
			</div>
		</section>

		<!-- <section id="graficos-tarefas">
			<div>
				<canvas id="tarefasPorMes"></canvas>
				
			</div>
			<div>
				<canvas id="statusTarefas"></canvas>
			</div>
		</section> -->
	</main>

	<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script src="script.js"></script>

</body>

</html>