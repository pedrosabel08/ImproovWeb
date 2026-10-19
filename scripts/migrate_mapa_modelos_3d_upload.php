<?php

// Executar também no VPS após publicar o código. Não altera modelos ou vínculos existentes.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../conexaoMain.php';
$conn = conectarBanco();
$conn->query(file_get_contents(__DIR__ . '/../sql/2026-10-14_mapa_modelos_3d_upload.sql'));
$conn->close();
echo "Armazenamento e histórico de modelos 3D preparados.\n";
