<?php
header('Content-Type: application/json; charset=utf-8');

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

	function updateSignUrl(mysqli $conn, string $docToken, string $docName, bool $matchByName, string $signUrl): void
	{
		if ($signUrl === '') return;
		if ($matchByName && $docName !== '') {
			if ($docToken !== '') {
				$sql = "UPDATE contratos SET sign_url = ?, zapsign_doc_token = ? WHERE arquivo_nome = ?";
				$stmt = $conn->prepare($sql);
				if (!$stmt) {
					throw new RuntimeException('Falha ao preparar sign_url: ' . $conn->error);
				}
				$stmt->bind_param('sss', $signUrl, $docToken, $docName);
			} else {
				$sql = "UPDATE contratos SET sign_url = ? WHERE arquivo_nome = ?";
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
			$sql = "UPDATE contratos SET sign_url = ? WHERE zapsign_doc_token = ?";
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

	if ($docToken !== '') {
		$stmt = $conn->prepare('SELECT status FROM contratos WHERE zapsign_doc_token = ? LIMIT 1');
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
		$stmt = $conn->prepare('SELECT status FROM contratos WHERE arquivo_nome = ? LIMIT 1');
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

	if ($currentStatus === null) {
		$conn->close();
		echo json_encode(['ok' => true, 'ignored' => true, 'reason' => 'token_not_found', 'status' => $status]);
		exit;
	}

	if (!$status) {
		updateSignUrl($conn, $docToken, $docName, $matchByName, $signUrl);
		$conn->close();
		echo json_encode(['ok' => true, 'updated' => 'sign_url']);
		exit;
	}

	$curP = $precedence[$currentStatus] ?? 0;
	$newP = $precedence[$status] ?? 0;
	if ($newP <= $curP) {
		updateSignUrl($conn, $docToken, $docName, $matchByName, $signUrl);
		$conn->close();
		echo json_encode(['ok' => true, 'ignored' => true, 'reason' => 'status_precedence', 'current' => $currentStatus, 'incoming' => $status]);
		exit;
	}

	$service = new ContratoStatusService($conn);
	updateSignUrl($conn, $docToken, $docName, $matchByName, $signUrl);
	if ($matchByName && $docName !== '') {
		$service->atualizarStatusPorArquivoNome($docName, $status, $signedAt, $docToken ?: null);
	} else {
		$service->atualizarStatusPorToken($docToken, $status, $signedAt);
	}
	$conn->close();

	echo json_encode(['ok' => true, 'status' => $status]);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
