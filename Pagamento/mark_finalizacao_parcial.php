<?php
require_once __DIR__ . '/pagamento_auth.php';
pagamento_require_gestor(true);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') pagamento_json(['success' => false, 'error' => 'Use POST.'], 405);
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/financeiro_v2.php';
require_once __DIR__ . '/registrar_parcela.php';
financeiro_registrar_parcela($conn, 'parcial');
