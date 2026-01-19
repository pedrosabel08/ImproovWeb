<?php
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

require_once __DIR__ . '/auth_cookie.php';
// Se já estiver logado via cookie, redireciona para index
if (!empty($flow_user_id)) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Registrar - Flow Review</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body class="auth-page">
    <main class="auth-card">
        <h1>Cadastre-se</h1>
        <p class="muted">Crie uma conta para acessar o Flow Review</p>
        <form action="auth_register.php" method="post" autocomplete="off">
            <label>Nome
                <input type="text" name="nome_usuario" required maxlength="100">
            </label>
            <label>E-mail
                <input type="email" name="email" required maxlength="150">
            </label>
            <label>Senha
                <input type="password" name="senha" required minlength="6">
            </label>
            <label>Cargo
                <input type="text" name="cargo" required maxlength="80">
            </label>
            <div class="actions">
                <button type="submit" class="button primary">Criar conta</button>
                <a href="login.php" class="button ghost">Já tenho conta</a>
            </div>
        </form>
    </main>
</body>
</html>
