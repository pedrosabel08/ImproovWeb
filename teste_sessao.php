<?php
require_once __DIR__ . '/config/session_bootstrap.php';
session_start();

echo "<h2>Teste de Sessão</h2>";

if (isset($_SESSION['ultimo_renovado'])) {
    echo "Sessão renovada por último às: <strong>" . $_SESSION['ultimo_renovado'] . "</strong><br>";
} else {
    echo "Sessão ainda não renovada.<br>";
}

echo "Hora atual do servidor: <strong>" . date('H:i:s') . "</strong><br>";

if (!isset($_SESSION['idusuario'])) {
    echo "<p style='color:red;'>Usuário NÃO logado (" . (isset($_SESSION['idusuario']) ? 'não definido' : 'valor inválido') . ")</p>";
} else {
    echo "<p style='color:green;'>Usuário logado ✅</p>";
}
