# TXT Importer for images

This small script reads a TXT file and inserts image records into the `imagens_cliente_obra` table using the `nomenclatura` from the `obra` table.

Supported input formats:
- Header format: first non-empty line contains `cliente_id,obra_id` (comma, tab or semicolon separated). Subsequent lines are image names (e.g. `1. Fachada`).
- Per-line format: each non-empty line contains `cliente_id,obra_id,imagem_nome`.

Example:

5,123
1. Fachada
2. Planta baixa

Run:

```bash
python process_txt.py images_input.txt
```

Configuration: set the database variables in the project root `.env`; do not put credentials in Python files.

Environment / .env
-------------------
The script loads the project root `.env` regardless of the current working directory:
- DB_HOST
- DB_PORT (defaults to 3306)
- DB_USERNAME
- DB_PASSWORD
- DB_DATABASE

You can copy `.env.example` to `.env` and load it into your shell (or set the variables manually). The script also supports a `--dry-run` flag to preview SQL and params without writing to the database.
