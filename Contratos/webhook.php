<?php
header('Content-Type: application/json; charset=utf-8');

// Logar erros fatais do webhook para diagnóstico.
register_shutdown_function(function () {
	$error = error_get_last();
	if (!$error) return;
	$logDir = __DIR__ . '/../logs';
	$logPath = $logDir . '/zapsign_webhook_fatal.log';
	if (!is_dir($logDir) || !is_writable($logDir)) {
		$logPath = __DIR__ . '/zapsign_webhook_fatal.log';
	}
	$line = json_encode([
		'ts' => gmdate('c'),
		'type' => $error['type'] ?? null,
		'message' => $error['message'] ?? null,
		'file' => $error['file'] ?? null,
		'line' => $error['line'] ?? null,
	], JSON_UNESCAPED_UNICODE);
	@file_put_contents($logPath, $line . "\n", FILE_APPEND);
});

// Quick healthcheck to confirm routing/public access.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['ping'])) {
	echo json_encode(['ok' => true, 'message' => 'pong']);
	exit;
}

$payload = file_get_contents('php://input');
if (!is_string($payload)) {
	$payload = '';
}

// Some Windows editors write UTF-8 with BOM; json_decode fails if BOM is present.
$payload = preg_replace('/^\xEF\xBB\xBF/', '', $payload);
$payloadTrim = trim($payload);

$data = json_decode($payloadTrim, true);
if (!is_array($data)) {
	$err = json_last_error_msg();
	$debug = (isset($_GET['debug']) && $_GET['debug'] === '1');
	http_response_code(400);
	echo json_encode([
		'ok' => false,
		'message' => 'Body deve vir em JSON',
		'json_error' => $debug ? $err : null,
		'len' => $debug ? strlen($payloadTrim) : null,
	]);
	exit;
}

require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/services/ContratoStatusService.php';
require_once __DIR__ . '/services/AdendoStatusService.php';

function inferDocTypeFromName(string $docName): ?string
{
	$docName = trim($docName);
	if ($docName === '') return null;
	if (function_exists('mb_strtoupper')) {
		$upper = mb_strtoupper($docName, 'UTF-8');
	} else {
		$upper = strtoupper($docName);
	}
	if ($upper === '') return null;
	if (strpos($upper, 'CONTRATO_') === 0) return 'contrato';
	if (strpos($upper, 'ADENDO_CONTRATUAL_') === 0) return 'adendo';
	return null;
}

function normalize_name_simple(string $s): string
{
	$s = trim($s);
	if ($s === '') return '';
	if (function_exists('mb_strtolower')) {
		$s = mb_strtolower($s, 'UTF-8');
	} else {
		$s = strtolower($s);
	}
	$s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
	$s = preg_replace('/[^a-z0-9\s]/', '', $s) ?? $s;
	$s = preg_replace('/\s+/', ' ', $s) ?? $s;
	return trim($s);
}

function parseDocNameParts(string $docName): ?array
{
	$base = trim(preg_replace('/\.pdf$/i', '', $docName) ?? $docName);
	if ($base === '') return null;

	$prefix = null;
	$rest = null;
	if (stripos($base, 'ADENDO_CONTRATUAL_') === 0) {
		$prefix = 'ADENDO_CONTRATUAL';
		$rest = substr($base, strlen('ADENDO_CONTRATUAL_'));
	} elseif (stripos($base, 'CONTRATO_') === 0) {
		$prefix = 'CONTRATO';
		$rest = substr($base, strlen('CONTRATO_'));
	}
	if (!$prefix || $rest === null) return null;

	$parts = explode('_', $rest);
	if (count($parts) < 3) return null;
	$year = array_pop($parts);
	$monthName = array_pop($parts);
	$rawName = trim(str_replace('_', ' ', implode('_', $parts)));
	$monthMap = [
		'JANEIRO' => '01',
		'FEVEREIRO' => '02',
		'MARCO' => '03',
		'MARÇO' => '03',
		'ABRIL' => '04',
		'MAIO' => '05',
		'JUNHO' => '06',
		'JULHO' => '07',
		'AGOSTO' => '08',
		'SETEMBRO' => '09',
		'OUTUBRO' => '10',
		'NOVEMBRO' => '11',
		'DEZEMBRO' => '12'
	];
	$monthKey = strtoupper($monthName);
	$monthNum = $monthMap[$monthKey] ?? null;
	if (!$monthNum || !preg_match('/^\d{4}$/', $year)) return null;
	return [
		'prefix' => $prefix,
		'name_raw' => $rawName,
		'competencia' => $year . '-' . $monthNum,
	];
}

function findColaboradorIdByName(mysqli $conn, string $rawName): ?int
{
	$target = normalize_name_simple($rawName);
	if ($target === '') return null;
	$sql = "SELECT c.idcolaborador, u.nome_usuario FROM colaborador c JOIN usuario u ON u.idcolaborador = c.idcolaborador WHERE c.ativo = 1";
	$res = $conn->query($sql);
	if (!$res) return null;
	while ($row = $res->fetch_assoc()) {
		$nome = normalize_name_simple((string)($row['nome_usuario'] ?? ''));
		if ($nome !== '' && $nome === $target) {
			return (int)$row['idcolaborador'];
		}
	}
	return null;
}

function logContratoAction(mysqli $conn, array $data): void
{
	$sql = "INSERT INTO log_contratos (contrato_id, colaborador_id, zapsign_doc_token, status, acao, origem, ip, detalhe) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
	$stmt = $conn->prepare($sql);
	if (!$stmt) {
		return;
	}
	$contratoId = $data['contrato_id'] ?? null;
	$colaboradorId = $data['colaborador_id'] ?? null;
	$token = $data['zapsign_doc_token'] ?? null;
	$status = $data['status'] ?? null;
	$acao = $data['acao'] ?? 'webhook_event';
	$origem = $data['origem'] ?? 'webhook';
	$ip = $data['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
	$detalhe = $data['detalhe'] ?? null;

	$stmt->bind_param(
		'iissssss',
		$contratoId,
		$colaboradorId,
		$token,
		$status,
		$acao,
		$origem,
		$ip,
		$detalhe
	);
	$stmt->execute();
	$stmt->close();
}

function logAdendoAction(mysqli $conn, array $data): void
{
	$sql = "INSERT INTO log_adendos (adendo_id, colaborador_id, zapsign_doc_token, status, acao, origem, ip, detalhe) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
	$stmt = $conn->prepare($sql);
	if (!$stmt) {
		return;
	}
	$adendoId = $data['adendo_id'] ?? null;
	$colaboradorId = $data['colaborador_id'] ?? null;
	$token = $data['zapsign_doc_token'] ?? null;
	$status = $data['status'] ?? null;
	$acao = $data['acao'] ?? 'webhook_event';
	$origem = $data['origem'] ?? 'webhook';
	$ip = $data['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
	$detalhe = $data['detalhe'] ?? null;

	$stmt->bind_param(
		'iissssss',
		$adendoId,
		$colaboradorId,
		$token,
		$status,
		$acao,
		$origem,
		$ip,
		$detalhe
	);
	$stmt->execute();
	$stmt->close();
}

function resolveContratoId(mysqli $conn, string $docToken, string $docName): array
{
	$contratoId = null;
	$colaboradorId = null;
	if ($docToken !== '') {
		$stmt = $conn->prepare('SELECT id, colaborador_id FROM contratos WHERE zapsign_doc_token = ? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('s', $docToken);
			$stmt->execute();
			$res = $stmt->get_result();
			if ($res && ($row = $res->fetch_assoc())) {
				$contratoId = (int)$row['id'];
				$colaboradorId = isset($row['colaborador_id']) ? (int)$row['colaborador_id'] : null;
			}
			$stmt->close();
		}
	}
	if ($contratoId === null && $docName !== '') {
		$stmt = $conn->prepare('SELECT id, colaborador_id FROM contratos WHERE arquivo_nome = ? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('s', $docName);
			$stmt->execute();
			$res = $stmt->get_result();
			if ($res && ($row = $res->fetch_assoc())) {
				$contratoId = (int)$row['id'];
				$colaboradorId = isset($row['colaborador_id']) ? (int)$row['colaborador_id'] : null;
			}
			$stmt->close();
		}
	}

	return [$contratoId, $colaboradorId];
}

function resolveAdendoId(mysqli $conn, string $docToken, string $docName): array
{
	$adendoId = null;
	$colaboradorId = null;
	if ($docToken !== '') {
		$stmt = $conn->prepare('SELECT id, colaborador_id FROM adendos WHERE zapsign_doc_token = ? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('s', $docToken);
			$stmt->execute();
			$res = $stmt->get_result();
			if ($res && ($row = $res->fetch_assoc())) {
				$adendoId = (int)$row['id'];
				$colaboradorId = isset($row['colaborador_id']) ? (int)$row['colaborador_id'] : null;
			}
			$stmt->close();
		}
	}
	if ($adendoId === null && $docName !== '') {
		$stmt = $conn->prepare('SELECT id, colaborador_id FROM adendos WHERE arquivo_nome = ? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('s', $docName);
			$stmt->execute();
			$res = $stmt->get_result();
			if ($res && ($row = $res->fetch_assoc())) {
				$adendoId = (int)$row['id'];
				$colaboradorId = isset($row['colaborador_id']) ? (int)$row['colaborador_id'] : null;
			}
			$stmt->close();
		}
	}

	return [$adendoId, $colaboradorId];
}

// --- Small dotenv loader (no external dependency) ---
function load_dotenv_simple(string $path): void
{
	if (!file_exists($path)) return;
	$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || $line[0] === '#') continue;
		if (strpos($line, '=') === false) continue;
		list($k, $v) = array_map('trim', explode('=', $line, 2));
		$v = trim($v, "\"'");
		putenv("{$k}={$v}");
		$_ENV[$k] = $v;
		$_SERVER[$k] = $v;
	}
}

function slack_send_webhook(?string $webhookUrl, string $text): bool
{
	if (!$webhookUrl) return false;
	$payload = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);
	$ch = curl_init($webhookUrl);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$res = curl_exec($ch);
	$err = curl_error($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return !($res === false || $code < 200 || $code >= 300 || $err);
}

function extractSignerName(array $data): string
{
	$signer = $data['signer'] ?? null;
	if (is_array($signer)) {
		$name = trim((string)($signer['name'] ?? $signer['full_name'] ?? $signer['email'] ?? ''));
		if ($name !== '') return $name;
	}

	$signers = $data['signers'] ?? ($data['document']['signers'] ?? null);
	if (is_array($signers)) {
		// prefer signed signer
		foreach ($signers as $s) {
			if (!is_array($s)) continue;
			$status = strtolower((string)($s['status'] ?? $s['state'] ?? ''));
			$hasSigned = !empty($s['signed_at']) || $status === 'signed';
			if ($hasSigned) {
				$name = trim((string)($s['name'] ?? $s['full_name'] ?? $s['email'] ?? ''));
				if ($name !== '') return $name;
			}
		}
		// fallback: first signer with name/email
		foreach ($signers as $s) {
			if (!is_array($s)) continue;
			$name = trim((string)($s['name'] ?? $s['full_name'] ?? $s['email'] ?? ''));
			if ($name !== '') return $name;
		}
	}

	return 'Alguém';
}

$event = $data['event'] ?? $data['type'] ?? $data['event_type'] ?? $data['event_name'] ?? $data['action'] ?? '';
$docToken = $data['document']['token'] ?? $data['doc_token'] ?? $data['document_token'] ?? $data['token'] ?? '';
$docName = $data['document']['name'] ?? $data['document']['filename'] ?? $data['name'] ?? $data['document_name'] ?? '';
$signedAt = $data['document']['signed_at'] ?? $data['signed_at'] ?? null;

$signUrl = '';
$signers = $data['signers'] ?? ($data['document']['signers'] ?? []);
if (is_array($signers)) {
	foreach ($signers as $signer) {
		if (!is_array($signer)) continue;
		$url = $signer['sign_url'] ?? $signer['url'] ?? $signer['signer_url'] ?? '';
		if (is_string($url) && trim($url) !== '') {
			$signUrl = trim($url);
			break;
		}
	}
}

if (!$event || !$docToken) {
	http_response_code(422);
	echo json_encode(['ok' => false, 'message' => 'Evento ou token ausente']);
	exit;
}

function inferStatusFromEvent(string $event): ?string
{
	$e = strtolower(trim($event));
	if ($e === '') return null;
	if (strpos($e, 'created') !== false || strpos($e, 'criado') !== false || strpos($e, 'doc_created') !== false || strpos($e, 'document.created') !== false || strpos($e, 'document_created') !== false) return 'enviado';
	if (strpos($e, 'signed') !== false || strpos($e, 'assinado') !== false) return 'assinado';
	if (strpos($e, 'refus') !== false || strpos($e, 'recus') !== false) return 'recusado';
	if (strpos($e, 'expire') !== false || strpos($e, 'expir') !== false) return 'expirado';
	if (strpos($e, 'sent') !== false || strpos($e, 'enviado') !== false) return 'enviado';
	if (strpos($e, 'visual') !== false || strpos($e, 'view') !== false || strpos($e, 'read') !== false) return 'visualizado';
	return null;
}

$status = inferStatusFromEvent($event);

// Load env from project root and get Slack webhook for contratos
load_dotenv_simple(__DIR__ . '/../.env');
$SLACK_WEBHOOK_CONTRATOS_URL = getenv('SLACK_WEBHOOK_CONTRATOS_URL') ?: ($_ENV['SLACK_WEBHOOK_CONTRATOS_URL'] ?? null);

if (!$status && !$signUrl) {
	echo json_encode(['ok' => true, 'ignored' => true, 'reason' => 'unknown_event', 'event' => $event]);
	exit;
}

// Optional: connectivity test without touching DB.
if (isset($_GET['dry_run']) && $_GET['dry_run'] === '1') {
	echo json_encode(['ok' => true, 'dry_run' => true, 'event' => $event, 'status' => $status, 'doc_token' => $docToken]);
	exit;
}

// Minimal logging to help debugging without exposing full payload by default.
$logDir = __DIR__ . '/../logs';
$logPath = $logDir . '/zapsign_webhook.log';
if (!is_dir($logDir) || !is_writable($logDir)) {
	$logPath = __DIR__ . '/zapsign_webhook.log';
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$line = json_encode([
	'ts' => gmdate('c'),
	'ip' => $ip,
	'event' => $event,
	'status' => $status,
	'doc_token' => $docToken,
	'doc_name' => $docName,
], JSON_UNESCAPED_UNICODE);
$wrote = @file_put_contents($logPath, $line . "\n", FILE_APPEND);
if ($wrote === false && isset($_GET['debug']) && $_GET['debug'] === '1') {
	@error_log('ZapSign webhook: failed to write log to ' . $logPath);
}

try {
	$conn = conectarBanco();
	$docType = inferDocTypeFromName($docName);
	if (!$docType) {
		[$contratoIdProbe, $colaboradorIdProbe] = resolveContratoId($conn, $docToken, $docName);
		if ($contratoIdProbe !== null || $colaboradorIdProbe !== null) {
			$docType = 'contrato';
		} else {
			[$adendoIdProbe, $colaboradorIdProbe2] = resolveAdendoId($conn, $docToken, $docName);
			if ($adendoIdProbe !== null || $colaboradorIdProbe2 !== null) {
				$docType = 'adendo';
			}
		}
	}

	if (!$docType) {
		$conn->close();
		echo json_encode(['ok' => true, 'ignored' => true, 'reason' => 'doc_not_allowed', 'doc_name' => $docName]);
		exit;
	}

	if ($docType === 'contrato') {
		[$docIdResolved, $colaboradorIdResolved] = resolveContratoId($conn, $docToken, $docName);
	} else {
		[$docIdResolved, $colaboradorIdResolved] = resolveAdendoId($conn, $docToken, $docName);
	}
	$detalheBase = json_encode([
		'event' => $event,
		'status_inferido' => $status,
		'doc_name' => $docName,
		'sign_url' => $signUrl ?: null
	], JSON_UNESCAPED_UNICODE);

	function updateSignUrl(mysqli $conn, string $table, string $docToken, string $docName, bool $matchByName, string $signUrl): void
	{
		if (!in_array($table, ['contratos', 'adendos'], true)) return;
		if ($signUrl === '') return;
		if ($matchByName && $docName !== '') {
			if ($docToken !== '') {
				$sql = "UPDATE {$table} SET sign_url = ?, zapsign_doc_token = ? WHERE arquivo_nome = ?";
				$stmt = $conn->prepare($sql);
				if (!$stmt) {
					throw new RuntimeException('Falha ao preparar sign_url: ' . $conn->error);
				}
				$stmt->bind_param('sss', $signUrl, $docToken, $docName);
			} else {
				$sql = "UPDATE {$table} SET sign_url = ? WHERE arquivo_nome = ?";
				$stmt = $conn->prepare($sql);
				if (!$stmt) {
					throw new RuntimeException('Falha ao preparar sign_url: ' . $conn->error);
				}
				$stmt->bind_param('ss', $signUrl, $docName);
			}
			$stmt->execute();
			$stmt->close();
			return;
		}

		if ($docToken !== '') {
			$sql = "UPDATE {$table} SET sign_url = ? WHERE zapsign_doc_token = ?";
			$stmt = $conn->prepare($sql);
			if (!$stmt) {
				throw new RuntimeException('Falha ao preparar sign_url: ' . $conn->error);
			}
			$stmt->bind_param('ss', $signUrl, $docToken);
			$stmt->execute();
			$stmt->close();
		}
	}

	// Prevent downgrading statuses (e.g., a "visualized" event after signed).
	$precedence = [
		'enviado' => 1,
		'visualizado' => 2,
		'recusado' => 3,
		'expirado' => 3,
		'assinado' => 4,
	];

	$currentStatus = null;
	$matchByName = false;
	$table = $docType === 'contrato' ? 'contratos' : 'adendos';

	if ($docToken !== '') {
		$stmt = $conn->prepare("SELECT status FROM {$table} WHERE zapsign_doc_token = ? LIMIT 1");
		if ($stmt) {
			$stmt->bind_param('s', $docToken);
			$stmt->execute();
			$res = $stmt->get_result();
			if ($res && ($row = $res->fetch_assoc())) {
				$currentStatus = $row['status'] ?? null;
			}
			$stmt->close();
		}
	}

	if ($currentStatus === null && $docName !== '') {
		$stmt = $conn->prepare("SELECT status FROM {$table} WHERE arquivo_nome = ? LIMIT 1");
		if ($stmt) {
			$stmt->bind_param('s', $docName);
			$stmt->execute();
			$res = $stmt->get_result();
			if ($res && ($row = $res->fetch_assoc())) {
				$currentStatus = $row['status'] ?? null;
				$matchByName = true;
			}
			$stmt->close();
		}
	}

	if ($currentStatus === null && $docType === 'adendo') {
		$parsed = parseDocNameParts($docName);
		if ($parsed && $parsed['prefix'] === 'ADENDO_CONTRATUAL') {
			$colabId = findColaboradorIdByName($conn, $parsed['name_raw'] ?? '');
			if ($colabId) {
				$competencia = $parsed['competencia'];
				$now = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
				$st = $status ?: 'gerado';
				$sql = "INSERT INTO adendos (colaborador_id, competencia, status, zapsign_doc_token, sign_url, data_envio, arquivo_nome, arquivo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
				$stmt = $conn->prepare($sql);
				if ($stmt) {
					$emptyPath = '';
					$stmt->bind_param('isssssss', $colabId, $competencia, $st, $docToken, $signUrl, $now, $docName, $emptyPath);
					$stmt->execute();
					$stmt->close();
					$matchByName = true;
					$currentStatus = $st;
					$docIdResolved = $conn->insert_id ?: $docIdResolved;
				}
			}
		}
	}

	$notifyStatuses = ['enviado', 'visualizado', 'assinado'];
	$shouldNotify = $status && in_array($status, $notifyStatuses, true);
	if ($shouldNotify) {
		$person = extractSignerName($data);
		if ($docType === 'adendo') {
			if ($status === 'enviado') {
				$text = sprintf('O financeiro criou o adendo contratual de %s para esse mês.', $person);
			} elseif ($status === 'visualizado') {
				$text = sprintf('%s visualizou o adendo contratual desse mês.', $person);
			} else {
				$text = sprintf('%s assinou o adendo contratual desse mês.', $person);
			}
		} else {
			if ($status === 'enviado') {
				$text = sprintf('O financeiro criou o contrato de %s para esse mês.', $person);
			} elseif ($status === 'visualizado') {
				$text = sprintf('%s visualizou o contrato desse mês.', $person);
			} else {
				$text = sprintf('%s assinou o contrato desse mês.', $person);
			}
		}
		slack_send_webhook($SLACK_WEBHOOK_CONTRATOS_URL, $text);
	}

	if ($currentStatus === null) {
		if ($docType === 'contrato') {
			logContratoAction($conn, [
				'contrato_id' => $docIdResolved,
				'colaborador_id' => $colaboradorIdResolved,
				'zapsign_doc_token' => $docToken,
				'status' => $status,
				'acao' => $event ?: 'webhook_event',
				'origem' => 'webhook',
				'detalhe' => $detalheBase
			]);
		} else {
			logAdendoAction($conn, [
				'adendo_id' => $docIdResolved,
				'colaborador_id' => $colaboradorIdResolved,
				'zapsign_doc_token' => $docToken,
				'status' => $status,
				'acao' => $event ?: 'webhook_event',
				'origem' => 'webhook',
				'detalhe' => $detalheBase
			]);
		}
		$conn->close();
		echo json_encode(['ok' => true, 'ignored' => true, 'reason' => 'token_not_found', 'status' => $status]);
		exit;
	}

	if (!$status) {
		if ($docType === 'contrato') {
			logContratoAction($conn, [
				'contrato_id' => $docIdResolved,
				'colaborador_id' => $colaboradorIdResolved,
				'zapsign_doc_token' => $docToken,
				'status' => null,
				'acao' => $event ?: 'webhook_event',
				'origem' => 'webhook',
				'detalhe' => $detalheBase
			]);
		} else {
			logAdendoAction($conn, [
				'adendo_id' => $docIdResolved,
				'colaborador_id' => $colaboradorIdResolved,
				'zapsign_doc_token' => $docToken,
				'status' => null,
				'acao' => $event ?: 'webhook_event',
				'origem' => 'webhook',
				'detalhe' => $detalheBase
			]);
		}
		updateSignUrl($conn, $table, $docToken, $docName, $matchByName, $signUrl);
		$conn->close();
		echo json_encode(['ok' => true, 'updated' => 'sign_url']);
		exit;
	}

	$curP = $precedence[$currentStatus] ?? 0;
	$newP = $precedence[$status] ?? 0;
	if ($newP <= $curP) {
		if ($docType === 'contrato') {
			logContratoAction($conn, [
				'contrato_id' => $docIdResolved,
				'colaborador_id' => $colaboradorIdResolved,
				'zapsign_doc_token' => $docToken,
				'status' => $status,
				'acao' => $event ?: 'webhook_event',
				'origem' => 'webhook',
				'detalhe' => $detalheBase
			]);
		} else {
			logAdendoAction($conn, [
				'adendo_id' => $docIdResolved,
				'colaborador_id' => $colaboradorIdResolved,
				'zapsign_doc_token' => $docToken,
				'status' => $status,
				'acao' => $event ?: 'webhook_event',
				'origem' => 'webhook',
				'detalhe' => $detalheBase
			]);
		}
		updateSignUrl($conn, $table, $docToken, $docName, $matchByName, $signUrl);
		$conn->close();
		echo json_encode(['ok' => true, 'ignored' => true, 'reason' => 'status_precedence', 'current' => $currentStatus, 'incoming' => $status]);
		exit;
	}

	$service = $docType === 'contrato' ? new ContratoStatusService($conn) : new AdendoStatusService($conn);
	updateSignUrl($conn, $table, $docToken, $docName, $matchByName, $signUrl);
	if ($matchByName && $docName !== '') {
		$service->atualizarStatusPorArquivoNome($docName, $status, $signedAt, $docToken ?: null);
	} else {
		$service->atualizarStatusPorToken($docToken, $status, $signedAt);
	}
	if ($docType === 'contrato') {
		logContratoAction($conn, [
			'contrato_id' => $docIdResolved,
			'colaborador_id' => $colaboradorIdResolved,
			'zapsign_doc_token' => $docToken,
			'status' => $status,
			'acao' => $event ?: 'webhook_event',
			'origem' => 'webhook',
			'detalhe' => $detalheBase
		]);
	} else {
		logAdendoAction($conn, [
			'adendo_id' => $docIdResolved,
			'colaborador_id' => $colaboradorIdResolved,
			'zapsign_doc_token' => $docToken,
			'status' => $status,
			'acao' => $event ?: 'webhook_event',
			'origem' => 'webhook',
			'detalhe' => $detalheBase
		]);
	}
	$conn->close();

	echo json_encode(['ok' => true, 'status' => $status]);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
