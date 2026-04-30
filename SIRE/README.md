# SIRE — Sistema de Importação de Referências de Imagens

Rotina de importação das imagens finais JPG existentes no VPS para o storage permanente no NAS,
com registro completo no banco de dados.

---

## O que o script faz

1. Consulta o banco para obter a última imagem final aprovada por `funcao_imagem_id`
2. Para cada registro, localiza o arquivo em `/uploads/<nome_arquivo>` no VPS via SFTP
3. Calcula o SHA-1 do arquivo (garantia de integridade e deduplicação)
4. Copia para o NAS no caminho baseado no hash:
   ```
   /mnt/exchange/_SIRE/storage/imagens/<hh>/<hh>/<sha1completo>.jpg
   ```
5. Insere os metadados na tabela `referencias_imagens`
6. Gera log detalhado em `/var/log/importador_referencias.log`

O processo é **idempotente**: pode ser executado várias vezes sem duplicar registros
(verifica `funcao_imagem_id` e `hash_sha1` antes de cada inserção).

---

## Pré-requisitos

### No servidor onde o script será executado

- PHP ≥ 8.1 CLI (`php --version`)
- Extensão GD habilitada (`php -m | grep gd`) — necessária para `getimagesize()`
- `composer install` executado na raiz do projeto
- Acesso de rede ao VPS (`72.60.137.192:22`) e ao NAS (`192.168.0.250:2222`)

### Variáveis de ambiente (`.env` na raiz do projeto)

As variáveis já existentes são suficientes. Opcionalmente adicione:

```env
# Caminho raiz dos uploads no VPS (padrão: /uploads)
IMPROOV_VPS_UPLOADS_PATH=/uploads

# Caminho destino no NAS (padrão: /mnt/exchange/_SIRE/storage/imagens)
SIRE_STORAGE_BASE=/mnt/exchange/_SIRE/storage/imagens
```

---

## Passo 1 — Criar a tabela no banco de dados

Conecte ao banco MySQL e execute:

```bash
mysql -h 72.60.137.192 -u improov -p flowdb < SIRE/sql/cria_referencias_imagens.sql
```

Ou via cliente gráfico (DBeaver, phpMyAdmin etc.), execute o conteúdo de `SIRE/sql/cria_referencias_imagens.sql`.

---

## Passo 2 — Executar a importação

### No VPS (recomendado — evita transferência dupla)

```bash
# Acesse o VPS
ssh root@72.60.137.192

# Navegue até o projeto
cd /home/improov/web/improov.com.br/public_html/flow/ImproovWeb

# Execute com simulação primeiro (nenhuma alteração é feita)
php SIRE/importar_referencias.php --dry-run --verbose

# Execute de verdade
php SIRE/importar_referencias.php --verbose

# Execute com limite de registros
php SIRE/importar_referencias.php --limit=500 --verbose
```

### Localmente (Windows / XAMPP)

```powershell
cd C:\xampp\htdocs\ImproovWeb

# Simulação
php SIRE\importar_referencias.php --dry-run --verbose

# Produção
php SIRE\importar_referencias.php --verbose
```

> **Nota:** ao rodar localmente, o script conecta via SFTP ao VPS para ler os arquivos
> e em seguida via SFTP ao NAS para gravá-los. A transferência passa pela máquina local.
> Prefira executar direto no VPS para maior velocidade.

---

## Opções de linha de comando

| Opção          | Descrição                                                         |
|----------------|-------------------------------------------------------------------|
| `--dry-run`    | Simula toda a lógica sem copiar arquivos nem gravar no banco      |
| `--limit=N`    | Processa no máximo N registros (padrão: 10000)                    |
| `--verbose`    | Exibe todas as linhas de log, inclusive os SKIP                   |

---

## Estrutura de diretórios do storage

```
/mnt/exchange/_SIRE/storage/imagens/
├── a1/
│   ├── b2/
│   │   ├── a1b2c3d4e5f6....jpg
│   │   └── a1b20000....jpg
│   └── f0/
│       └── a1f0....jpg
└── ff/
    └── 00/
        └── ff00....jpg
```

Os dois primeiros caracteres do SHA-1 formam o primeiro nível, os dois seguintes formam
o segundo nível, e o nome do arquivo é o SHA-1 completo com a extensão original.

---

## Saída de exemplo

```
[2026-04-29 14:00:00] [INFO ] SIRE — Importador de Referências de Imagens
[2026-04-29 14:00:00] [INFO ] Dry-run : NÃO
[2026-04-29 14:00:00] [INFO ] Limite  : 10000 registros
[2026-04-29 14:00:00] [INFO ] VPS SFTP: OK
[2026-04-29 14:00:00] [INFO ] NAS SFTP: OK
[2026-04-29 14:00:00] [INFO ] Registros encontrados: 842
[2026-04-29 14:00:01] [INFO ] [1/842] NOME-DA-IMAGEM.jpg  (funcao_imagem_id=1234)
[2026-04-29 14:00:03] [OK   ]   → IMPORTADO  sha1=a1b2...  1920x1080  2048000B  → /mnt/exchange/_SIRE/...
[2026-04-29 14:00:03] [INFO ] [2/842] OUTRA-IMAGEM.jpg  (funcao_imagem_id=1235)
[2026-04-29 14:00:03] [SKIP ]   → DUPLICADO (hash já existe no storage)
...
[2026-04-29 14:05:00] [INFO ] ══════════════════════════
[2026-04-29 14:05:00] [INFO ] RESUMO FINAL
[2026-04-29 14:05:00] [INFO ] Total processado  : 842
[2026-04-29 14:05:00] [INFO ] Total importado   : 820
[2026-04-29 14:05:00] [INFO ] Total ignorado    : 5
[2026-04-29 14:05:00] [INFO ] Total duplicado   : 12
[2026-04-29 14:05:00] [INFO ] Não encontrado    : 5
[2026-04-29 14:05:00] [INFO ] Total com erro    : 0
```

---

## Log persistente

O log completo é salvo em `/var/log/importador_referencias.log`.

Para acompanhar em tempo real enquanto o script roda:

```bash
tail -f /var/log/importador_referencias.log
```

---

## Tabela criada

```sql
referencias_imagens
├── id                BIGINT PK AUTO_INCREMENT
├── funcao_imagem_id  BIGINT  UNIQUE — chave da função de imagem no flowdb
├── nomenclatura      VARCHAR(255)
├── nome_arquivo      VARCHAR(255) — nome original do arquivo
├── caminho_origem    TEXT   — caminho no VPS (/uploads/...)
├── caminho_storage   TEXT   — caminho no NAS (/mnt/exchange/_SIRE/...)
├── hash_sha1         CHAR(40) UNIQUE — integridade e deduplicação
├── largura           INT
├── altura            INT
├── tamanho_bytes     BIGINT
└── importado_em      DATETIME DEFAULT CURRENT_TIMESTAMP
```

---

## Segurança

- Nenhum arquivo original é removido ou movido — apenas copiado
- Arquivos existentes no destino não são sobrescritos
- Credenciais lidas exclusivamente via `.env` (nunca hardcoded)
- Dupla verificação de duplicidade: por `funcao_imagem_id` e por `hash_sha1`
- Arquivos temporários criados com `tempnam()` e removidos após cada operação
