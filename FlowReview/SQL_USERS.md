# Tabela `usuario_externo`

Execute este SQL para criar a tabela de usuários externos:

```sql
CREATE TABLE IF NOT EXISTS `usuario_externo` (
  `idusuario` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome_usuario` VARCHAR(150) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `senha` VARCHAR(255) NOT NULL,
  `cargo` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idusuario`)
);

-- Já recomendado: tabela de tokens (ver `AUTH_README.md`)
```
