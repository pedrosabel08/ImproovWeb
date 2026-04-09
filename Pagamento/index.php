<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
	if ($__p && is_file($__p)) {
		require_once $__p;
		break;
	}
}
unset($__root, $__p);


// session_start();

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
	<link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>" />
	<link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
	<link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
		type="image/x-icon">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

	<title>Tela de pagamento</title>
	<link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
</head>

<body>

	<?php include '../sidebar.php'; ?>

	<div class="container">

		<!-- Page Header -->
		<div class="page-header">
			<div class="page-header-left">
				<img src="../gif/assinatura_preto.gif" id="gif" style="height:36px;opacity:0.85" alt="ImproovWeb" />
				<h1 class="page-title">Pagamento</h1>
			</div>
			<span class="results-badge">
				<i class="fa-solid fa-coins"></i>
				<span id="total-imagens">0</span> itens
			</span>
		</div>

		<!-- Filters -->
		<div class="filters">
			<div class="filter-group">
				<label class="filter-label" for="colaborador">Colaborador</label>
				<select class="filter-select" name="colaborador" id="colaborador">
					<option value="">Escolha um colaborador</option>
					<?php foreach ($colaboradores as $colab): ?>
						<option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
							<?= htmlspecialchars($colab['nome_colaborador']); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="filter-group">
				<label class="filter-label" for="mes">Mês</label>
				<select class="filter-select" name="mes" id="mes">
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
			<div class="filter-group">
				<label class="filter-label" for="ano">Ano</label>
				<select class="filter-select" name="ano" id="ano">
					<option value="2026">2026</option>
					<option value="2025">2025</option>
					<option value="2024">2024</option>
					<option value="2023">2023</option>
					<option value="2022">2022</option>
				</select>
			</div>
		</div>

		<!-- Scrollable content -->
		<div class="table-scroll-area">

			<!-- Checkbox filter group -->
			<div class="checkbox-filter-group tipo-imagem">
				<label class="checkbox-label"><input type="checkbox" name="Caderno" id="Caderno" onclick="filtrarTabela()"><span>Caderno</span></label>
				<label class="checkbox-label"><input type="checkbox" name="Filtro de assets" id="Filtro de_assets" onclick="filtrarTabela()"><span>Filtro de assets</span></label>
				<label class="checkbox-label"><input type="checkbox" name="Modelagem" id="Modelagem" onclick="filtrarTabela()"><span>Modelagem</span></label>
				<label class="checkbox-label"><input type="checkbox" name="Composição" id="Composicao" onclick="filtrarTabela()"><span>Composição</span></label>
				<label class="checkbox-label"><input type="checkbox" name="Pré-Finalização" id="Pre-Finalizacao" onclick="filtrarTabela()"><span>Pré-Finalização</span></label>
				<label class="checkbox-label"><input type="checkbox" name="Finalização Completa" id="Finalizacao_Completa" onclick="filtrarTabela()"><span>Finalização Completa</span></label>
				<label class="checkbox-label"><input type="checkbox" name="Finalização Parcial" id="Finalizacao_Parcial" onclick="filtrarTabela()"><span>Finalização Parcial</span></label>
				<label class="checkbox-label"><input type="checkbox" name="Alteração" id="Alteracao" onclick="filtrarTabela()"><span>Alteração</span></label>
				<label class="checkbox-label"><input type="checkbox" name="Pós-produção" id="Pos_producao" onclick="filtrarTabela()"><span>Pós-produção</span></label>
				<label class="checkbox-label"><input type="checkbox" name="Planta Humanizada" id="Planta_Humanizada" onclick="filtrarTabela()"><span>Planta Humanizada</span></label>
				<label class="checkbox-label"><input type="checkbox" name="Acompanhamento" id="Acompanhamento" onclick="filtrarTabela()"><span>Acompanhamento</span></label>
				<label class="checkbox-label"><input type="checkbox" name="Animação" id="Animacao" onclick="filtrarTabela()"><span>Animação</span></label>
			</div>

			<!-- Action toolbar -->
			<div class="action-toolbar">
				<button class="btn btn-secondary" id="marcar-todos">
					<i class="fa-solid fa-check-double"></i> Marcar/Desmarcar Todos
				</button>
				<button class="btn btn-secondary" id="adicionar-valor">
					<i class="fa-solid fa-plus"></i> Adicionar Valor
				</button>
				<input type="text" class="btn-input" id="valor" placeholder="R$ 0,00">
			</div>

			<!-- Totals + export -->
			<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
				<div class="totals-bar">
					<div class="total-card">
						<div class="total-card-label">Total (R$)</div>
						<div class="total-card-value" id="totalValor">0,00</div>
					</div>
					<div class="total-card">
						<div class="total-card-label">Pagas</div>
						<div class="total-card-value is-paid" id="total-imagens-pagas">0</div>
						<div class="total-card-sublabel">Pago (R$)</div>
						<div class="total-card-subvalue is-paid" id="totalValorPago">0,00</div>
					</div>
					<div class="total-card">
						<div class="total-card-label">Não Pagas</div>
						<div class="total-card-value is-unpaid" id="total-imagens-nao-pagas">0</div>
						<div class="total-card-sublabel">Não Pago (R$)</div>
						<div class="total-card-subvalue is-unpaid" id="totalValorNaoPago">0,00</div>
					</div>
				</div>
				<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
					<button class="btn btn-secondary" id="generate-adendo">
						<i class="fa-solid fa-file-contract"></i> Gerar Adendo
					</button>
					<button class="btn btn-secondary" id="generate-lista">
						<i class="fa-solid fa-file-pdf"></i> Gerar Lista
					</button>
					<button class="btn btn-secondary" id="generate-excel" onclick="exportToExcel()">
						<i class="fa-solid fa-file-excel"></i> Excel
					</button>
				</div>
			</div>

			<!-- Info colaborador (shown by JS) -->
			<div id="info-colaborador" style="display: none;"></div>

			<!-- A Pagar -->
			<div class="table-section">
				<div class="table-section-header">
					<span class="table-section-title">
						<i class="fa-solid fa-clock" style="color:var(--status-andamento)"></i>
						A Pagar
					</span>
				</div>
				<div class="table-wrap">
					<table id="tabela-a-pagar" class="data-table">
						<thead>
							<tr>
								<th>Nome da Imagem</th>
								<th class="col-center">Status</th>
								<th class="col-center">Função</th>
								<th class="col-center">Valor (R$)</th>
								<th class="col-checkbox"></th>
								<th class="col-center">Data Pgt</th>
								<th class="col-center">Ações</th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</div>

			<!-- Confirm payment -->
			<div class="confirm-payment-row">
				<button class="btn btn-success" id="confirmar-pagamento">
					<i class="fa-solid fa-check"></i> Confirmar Pagamento
				</button>
			</div>

			<!-- Já Pago -->
			<div class="table-section">
				<div class="table-section-header">
					<span class="table-section-title">
						<i class="fa-solid fa-circle-check" style="color:var(--status-finalizado)"></i>
						Já Pago
					</span>
				</div>
				<div class="table-wrap">
					<table id="tabela-pago" class="data-table">
						<thead>
							<tr>
								<th>Nome da Imagem</th>
								<th class="col-center">Status</th>
								<th class="col-center">Função</th>
								<th class="col-center">Valor (R$)</th>
								<th class="col-checkbox"></th>
								<th class="col-center">Data Pgt</th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</div>

			<!-- Resumo por colaborador (hidden by default) -->
			<section id="resumo-pagamentos" style="display: none;">
				<h2>Resumo por colaborador</h2>
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
						<option value="2026">2026</option>
						<option value="2025">2025</option>
						<option value="2024">2024</option>
						<option value="2023">2023</option>
						<option value="2022">2022</option>
					</select>
					<button id="btn-carregar-resumo">Carregar Resumo</button>
				</div>
				<div class="table-section">
					<div class="table-wrap">
						<table id="tabela-resumo" class="data-table">
							<thead>
								<tr>
									<th>Colaborador</th>
									<th class="col-center">Mês</th>
									<th class="col-right">Fixo (R$)</th>
									<th class="col-right">Valor pendente (R$)</th>
									<th class="col-center">Status</th>
									<th class="col-center">Última atualização</th>
									<th class="col-center">Ações</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			</section>

		</div><!-- /.table-scroll-area -->

	</div><!-- /.container -->

	<!-- Modal: Aprovação do Adendo -->
	<div id="modalAdendo" class="modal">
		<div class="modal-content" style="width:min(900px,95vw);max-height:96vh;">
			<div class="modal-header">
				<h2 class="modal-title"><i class="fa-solid fa-file-contract"></i> Verificar Adendo</h2>
				<button class="modal-close" onclick="fecharModalAdendo()"><i class="fa-solid fa-xmark"></i></button>
			</div>
			<div class="modal-body" style="padding:0;flex:1;min-height:0;">
				<iframe id="adendo-preview-frame" src="" title="Preview do Adendo"
					style="width:100%;height:72vh;border:none;display:block;"></iframe>
			</div>
			<div class="modal-footer">
				<button class="btn btn-secondary" onclick="fecharModalAdendo()">
					<i class="fa-solid fa-xmark"></i> Cancelar
				</button>
				<button class="btn btn-success" id="btn-confirmar-adendo">
					<i class="fa-solid fa-check"></i> Confirmar e Salvar
				</button>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<script src="<?php echo asset_url('script.js'); ?>"></script>
	<script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>

	<script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
</body>

</html>