<?php

session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
	// Se não estiver logado, redirecionar para a página de login
	header("Location: ../index.html");
	exit();
}


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

include '../conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

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
	<link rel="stylesheet" href="../css/styleSidebar.css" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
	<link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
		type="image/x-icon">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

	<title>Tela de pagamento</title>
</head>

<body>

	<?php

	include '../sidebar.php';

	?>

	<main>
		<section id="resumo-pagamentos" style="margin-bottom: 20px;">
			<h2 style="margin: 10px 0;">Resumo por colaborador (MVP)</h2>
			<div class="resumo-filtro">
				<label>Referência:</label>
				<select id="mes-resumo">
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
				<select id="ano-resumo">
					<option value="2025">2025</option>
					<option value="2024">2024</option>
					<option value="2023">2023</option>
					<option value="2022">2022</option>
				</select>
				<button id="btn-carregar-resumo">Carregar Resumo</button>
			</div>
			<div class="tabela" style="margin-top:10px; max-height: max-content;">
				<table id="tabela-resumo">
					<thead>
						<tr>
							<th>Colaborador</th>
							<th>Mês</th>
							<th>Fixo (R$)</th>
							<th>Valor pendente (R$)</th>
							<th>Status</th>
							<th>Última atualização</th>
							<th>Ações</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</section>
		<div class="filtro">
			<div class="colaborador">
				<h2>Colaborador:</h2>
				<select name="colaborador" id="colaborador">
					<option value="">Escolha um colaborador</option>
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
			<div class="ano">
				<h2>Ano:</h2>
				<select name="ano" id="ano">
					<option value="2025">2025</option>
					<option value="2024">2024</option>
					<option value="2023">2023</option>
					<option value="2022">2022</option>
				</select>
			</div>

		</div>
		<div class="tipo-imagem" style="display: grid; grid-template-columns: repeat(3, 1fr); gap:10px;">
			<label class="checkbox-label"><input type="checkbox" name="Caderno" id="Caderno"
					onclick="filtrarTabela()"><span>Caderno</span></label>
			<label class="checkbox-label"><input type="checkbox" name="Filtro de assets" id="Filtro de_assets"
					onclick="filtrarTabela()"><span>Filtro de assets</span></label>
			<label class="checkbox-label"><input type="checkbox" name="Modelagem" id="Modelagem"
					onclick="filtrarTabela()"><span>Modelagem</span></label>
			<label class="checkbox-label"><input type="checkbox" name="Composição" id="Composicao"
					onclick="filtrarTabela()"><span>Composição</span></label>
			<label class="checkbox-label"><input type="checkbox" name="Pré-Finalização" id="Pre-Finalizacao"
					onclick="filtrarTabela()"><span>Pré-Finalização</span></label>
			<label class="checkbox-label"><input type="checkbox" name="Finalização Completa" id="Finalizacao_Completa"
					onclick="filtrarTabela()"><span>Finalização Completa</span></label>
			<label class="checkbox-label"><input type="checkbox" name="Finalização Parcial" id="Finalizacao_Parcial"
					onclick="filtrarTabela()"><span>Finalização Parcial</span></label>
			<label class="checkbox-label"><input type="checkbox" name="Alteração" id="Alteracao"
					onclick="filtrarTabela()"><span>Alteração</span></label>
			<label class="checkbox-label"><input type="checkbox" name="Pós-produção" id="Pos_producao"
					onclick="filtrarTabela()"><span>Pós-produção</span></label>
			<label class="checkbox-label"><input type="checkbox" name="Planta Humanizada" id="Planta_Humanizada"
					onclick="filtrarTabela()"><span>Planta Humanizada</span></label>
			<label class="checkbox-label"><input type="checkbox" name="Acompanhamento" id="Acompanhamento"
					onclick="filtrarTabela()"><span>Acompanhamento</span></label>
			<label class="checkbox-label"><input type="checkbox" name="Animação" id="Animacao"
					onclick="filtrarTabela()"><span>Animação</span></label>
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
						<span id="totalValor">0,00</span>
					</div>
					<div class="total-tarefas">
						<p>Total:</p>
						<span id="total-imagens">0</span>
					</div>
					<button id="generate-adendo">Gerar Adendo</button>
					<button id="generate-lista">Gerar Lista</button>
					<button id="generate-excel" onclick="exportToExcel()">Exportar para Excel</button>
				</div>
			</div>
			<div id="info-colaborador" style="display: none;"></div>
			<div class="tabela">
				<table id="tabela-faturamento">
					<thead>
						<tr>
							<th>Nome da Imagem</th>
							<th>Status</th>
							<th>Função</th>
							<th>Valor (R$)</th>
							<th>Status</th>
							<th>Data Pgt</th>
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
	<script src="../script/sidebar.js"></script>

</body>

</html>