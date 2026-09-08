# Conteúdo estruturado da Biblioteca ALMA

Cada `alma_biblioteca_item` pode ter `conteudo_estruturado` em paralelo aos campos legados. O documento válido é `{"version":1,"blocks":[...]}`. Quando o JSON é válido, consumidores usam seus blocos; caso contrário, continuam usando os campos legados sem inferência no frontend.

Tipos v1: `text` e `principle` usam `title` e `content`; `positive_list` e `negative_list` usam `title` e `items`; `material_guideline` usa `title`, `positive` e `negative`.

O editor administrativo só serializa texto puro, remove blocos/itens vazios e preserva a ordem do DOM. O backend normaliza e valida novamente em `alma_normalize_structured_content()`.

Execute primeiro `sql/2026-09-08_alma_conteudo_estruturado.sql`. Em seguida, use `php ALMA/scripts/migrate_structured_content.php --dry-run`; apenas registros de alta confiança são escritos com `--apply`. O script não sobrescreve JSON existente nem remove LONGTEXT. Registros `REVISAR` permanecem no fallback legado até revisão humana.

Para criar um novo tipo, adicione-o à constante `ALMA_CONTENT_BLOCK_TYPES`, à validação backend, ao template/serializador do editor e ao renderer semântico do ALMA.
