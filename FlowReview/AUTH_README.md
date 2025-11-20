# Autenticação por Cookie (`flow_auth`)

Este arquivo descreve como criar a tabela de tokens e exemplos de uso em PHP para emitir e validar o cookie `flow_auth`.

## SQL: criar tabela `login_tokens`

```sql
CREATE TABLE IF NOT EXISTS `login_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`token_hash`)
);
```

## Emissão do token (ex.: após login bem-sucedido)

Exemplo de uso em PHP — gere um token seguro, grave o hash no banco e envie o token ao usuário via cookie persistente (2 dias):

```php
// gera token aleatório
$token = bin2hex(random_bytes(32));

// calcula expiry (2 dias)
$expiresAt = date('Y-m-d H:i:s', time() + 86400 * 2);

// gravar no banco (usa SHA2 para guardar apenas o hash)
$stmt = $conn->prepare("INSERT INTO login_tokens (user_id, token_hash, expires_at) VALUES (?, SHA2(?, 256), ?)");
$stmt->bind_param('iss', $userId, $token, $expiresAt);
$stmt->execute();
$stmt->close();

// definir cookie no navegador (HttpOnly)
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie('flow_auth', $token, time() + 86400 * 2, '/', '', $secure, true);
```

Observações:
- Armazene apenas o hash do token (`SHA2(?,256)`) no banco para reduzir riscos se o DB vazar.
- Use `HttpOnly` e `Secure` quando possível. Em ambiente de desenvolvimento sem HTTPS, `Secure` pode impedir que o cookie seja enviado; o snippet acima detecta `HTTPS` dinamicamente.
- Para logout ou revogação, remova a linha correspondente em `login_tokens` e expire o cookie do cliente.
- Considere ter uma rotina de limpeza (`cron`) que apague tokens expirados.

## Validação (já implementada em `index.php`)

No início de `FlowReview/index.php` há a lógica que, caso não exista sessão, verifica o cookie `flow_auth`, busca o hash no banco e, se válido e não-expirado, cria a sessão automaticamente. A função também renova o cookie por mais 2 dias.

## Próximos passos
- Integrar emissão de token na página de login (onde ocorre a autenticação de `usuario_externo`).
- Implementar endpoint para revogar tokens (logout).
- Opcional: usar tokens com identificador (selector) + validator para facilitar revogação e reduzir custo de hashing no DB.
