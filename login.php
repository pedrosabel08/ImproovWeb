<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); // Allows all domains
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Prevent caching (important on hosts that use reverse proxies/CDN caching)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Tue, 01 Jan 2000 00:00:00 GMT');
header('Vary: Cookie');

$tempoSessao = 3600; // 1h
// Harden session behavior
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
// If your site is served over HTTPS, enable this as well:
// ini_set('session.cookie_secure', 1);

session_set_cookie_params($tempoSessao);
ini_set('session.gc_maxlifetime', $tempoSessao);
session_start();

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}
$conn->set_charset('utf8mb4');

// Verificar se a requisição é POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = htmlspecialchars(trim($_POST['login']));
    $senha = htmlspecialchars(trim($_POST['senha']));

    $sql = "SELECT idusuario, nome_usuario, nivel_acesso, idcolaborador FROM usuario WHERE login = ? AND senha = ? AND ativo = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $login, $senha);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Iniciar sessão e armazenar dados do usuário
        $_SESSION['idusuario'] = $row['idusuario'];
        $_SESSION['nome_usuario'] = $row['nome_usuario'];
        $_SESSION['nivel_acesso'] = $row['nivel_acesso'];
        $_SESSION['idcolaborador'] = $row['idcolaborador'];
        $_SESSION['logado'] = true;

        // Regenerar o ID de sessão após login bem-sucedido para garantir unicidade
        // e prevenir session fixation. O `true` faz com que o id antigo seja removido.
        session_regenerate_id(true);

        // Atualizar último acesso
        $updateSql = "UPDATE usuario SET ultimo_acesso = NOW() WHERE idusuario = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $row['idusuario']);
        $updateStmt->execute();

        // Inserir log na tabela logs_usuarios
        $ip = $_SERVER['REMOTE_ADDR'];
        $tela_atual = 'login.php';
        $agora = date('Y-m-d H:i:s');

        $insertLog = "INSERT INTO logs_usuarios (usuario_id, ip, tela_atual, login_time, ultima_atividade)
                      VALUES (?, ?, ?, ?, ?)";
        $logStmt = $conn->prepare($insertLog);
        $logStmt->bind_param("issss", $row['idusuario'], $ip, $tela_atual, $agora, $agora);
        $logStmt->execute();
        $logStmt->close();

        if ($updateStmt->affected_rows > 0) {
            echo json_encode([
                "status" => "success",
                "message" => "Login bem-sucedido!",
                "user" => [
                    "id" => $row['idusuario'],
                    "name" => $row['nome_usuario']
                ]
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Erro ao atualizar último acesso."
            ]);
        }

        $updateStmt->close();
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Login ou senha incorretos."
        ]);
    }

    $stmt->close();
    $conn->close();
}
