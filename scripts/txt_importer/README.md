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

Configuration: edit the DB connection in `process_txt.py` (DB_CONFIG) if needed.

Environment / .env
-------------------
The script reads database connection defaults from environment variables:
- IMPORT_DB_HOST
- IMPORT_DB_USER
- IMPORT_DB_PASSWORD
- IMPORT_DB_NAME

You can copy `.env.example` to `.env` and load it into your shell (or set the variables manually). The script also supports a `--dry-run` flag to preview SQL and params without writing to the database.
