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
    <title>Login - Flow Review</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>

<body class="auth-page">
    <main class="auth-card">
        <h1>Entrar</h1>
        <?php if (!empty($_GET['registered'])): ?>
            <p class="muted success">Conta criada! Faça login.</p>
        <?php endif; ?>
        <form action="auth_login.php" method="post" autocomplete="off">
            <label>E-mail
                <input type="email" name="email" required maxlength="150">
            </label>
            <label>Senha
                <input type="password" name="senha" required minlength="6">
            </label>
            <div class="actions">
                <button type="submit" class="button primary">Entrar</button>
                <a href="register.php" class="button ghost">Criar conta</a>
            </div>
        </form>
    </main>
</body>

</html>