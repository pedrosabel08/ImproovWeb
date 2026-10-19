<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$login = getenv('IMPROOV_QA_LOGIN'); $password = getenv('IMPROOV_QA_PASSWORD');
if (!$login || !$password) throw new RuntimeException('Informe as credenciais de QA nas variáveis de ambiente.');
$base = 'http://localhost:8066/ImproovWeb/';
$curl = curl_init();
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60]);
function op_http_assert(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
$request = function (string $path, ?array $post = null) use ($curl, $base): array {
    curl_setopt_array($curl, [CURLOPT_URL => $base . $path, CURLOPT_HTTPHEADER => ['Accept: application/json'], CURLOPT_POSTFIELDS => $post]);
    if ($post === null) curl_setopt($curl, CURLOPT_HTTPGET, true);
    $raw = curl_exec($curl);
    if ($raw === false) throw new RuntimeException(curl_error($curl));
    return [(int) curl_getinfo($curl, CURLINFO_HTTP_CODE), json_decode($raw, true, 128, JSON_THROW_ON_ERROR)];
};
try {
    $route = 'MapaPavimento/api.php?action=imagens_contexto_operacional&obra_id=102&imagem_ids=';
    [$code] = $request($route . '3246');
    op_http_assert($code === 401, 'Consulta sem sessão não foi negada.');
    [$code,$result] = $request('login.php', ['login' => $login, 'senha' => $password]);
    op_http_assert($code === 200 && ($result['status'] ?? '') === 'success', 'Login de QA falhou.');
    [$code,$result] = $request($route . '3246,3250');
    op_http_assert($code === 200 && count($result['contextos'] ?? []) === 2, 'Lote da mesma obra não retornou duas imagens.');
    foreach ($result['contextos'] as $context) {
        op_http_assert($context['imagem']['obra_id'] === 102, 'Contexto de obra indevida retornado.');
        op_http_assert(!empty($context['imagem']['status_nome']), 'Status oficial da imagem ausente.');
        op_http_assert(!isset($context['planejamento']['timeline']), 'Timeline de planejamento exposta.');
    }
    foreach (['3133', '3246,3133', '999999999'] as $ids) {
        [$code,$result] = $request($route . $ids);
        op_http_assert($code === 403 && !isset($result['contextos']), 'Consulta externa/inexistente não foi recusada integralmente.');
    }
    [$code] = $request('MapaPavimento/api.php?action=imagens_contexto_operacional&obra_id=0&imagem_ids=3246');
    op_http_assert($code === 403, 'Obra não autorizada não foi recusada.');
    foreach (['', 'abc', '3246%20OR%201=1', implode(',',range(1,101))] as $ids) {
        [$code] = $request($route . $ids);
        op_http_assert($code === 422, 'Entrada inválida não foi recusada.');
    }
    [$code,$result] = $request($route . '3246,3246');
    op_http_assert($code === 200 && count($result['contextos']) === 1, 'IDs duplicados não foram normalizados.');
    [$code] = $request($route . '3246', ['imagem_ids' => '3246']);
    op_http_assert($code === 405, 'Método incorreto aceito.');
    echo "OK: sessão, autorização de obra, lote, status oficial, isolamento entre obras, entrada inválida, limites, duplicados e método HTTP.\n";
} finally { curl_close($curl); }
