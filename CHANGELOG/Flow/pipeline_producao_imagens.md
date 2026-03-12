# 📋 Pipeline de Produção de Imagens — Improov

> Documento de análise completa do fluxo de produção de imagens no sistema Improov, baseado no mapeamento do repositório.

---

## 1. Visão Geral do Pipeline de Produção

O sistema Improov gerencia o ciclo de vida completo de imagens de visualização arquitetônica (renders), desde a **concepção inicial** até a **entrega final ao cliente**. O pipeline é composto por etapas sequenciais associadas a funções específicas de produção, com mecanismos automatizados de upload de arquivos, aprovação interna e revisão externa pelo cliente.

### Fluxo macroscópico

```
[Fotográfico / Referências]
         │
         ▼
    [P00 — Caderno & Ângulos]
         │
         ▼ (aprovação interna FlowReview)
    [R00 — Prévia]
         │
         ▼ (ciclo de revisões)
    [R01, R02, R03... — Alterações]
         │
         ▼ (aprovação final cliente FlowReviewExt)
    [EF — Entrega Final]
```

Cada etapa envolve uma ou mais **funções de produção** (Caderno, Modelagem, Composição, Finalização, Pós-Produção, Alteração). Todas as funções seguem o mesmo mecanismo:

1. Colaborador executa a função
2. Gera um arquivo (`.max`, `.psb`, `.pdf`, `.jpg`, `.exr`)
3. Envia o arquivo via **Worker de Upload** (SFTP automático para NAS)
4. Envia prévia para aprovação via **Flow Review**
5. Após aprovação, o status da imagem avança para a próxima etapa

---

## 2. Estrutura das Etapas da Imagem

As etapas são representadas na tabela `status_imagem` e associadas a cada imagem em `imagens_cliente_obra.status_id`.

### P00 — Projeto Inicial (Escolha de Ângulos)

**Objetivo:** Definir os ângulos da imagem que serão produzidos.

**Funções envolvidas:**
- **Caderno** — briefing e documentação do projeto (PDF)
- **Filtro de assets** — seleção dos elementos 3D a usar
- **Modelagem / Composição** — construção da cena 3D
- **Finalização Parcial** — renderização prévia dos ângulos candidatos

**Fluxo específico (P00):**
1. Função Finalização aprovada com status P00 → sistema detecta ângulos disponíveis
2. Ângulos são registrados na tabela `angulos_imagens`
3. Entrega é marcada como "Entrega Pendente" em `entregas_itens`
4. Gestão libera para cliente via **FlowReviewExt**
5. Cliente escolhe um ângulo (ou solicita refazer)
6. Ângulo aprovado → produção segue para render final
7. Imagem avança para R00

**Tabelas envolvidas:** `angulos_imagens`, `entregas_itens`, `imagem_decisao_angulos`, `historico_aprovacoes_imagens`

---

### R00 — Primeira Prévia

**Objetivo:** Enviar a primeira prévia completa da imagem ao cliente.

**Funções envolvidas:**
- **Finalização Completa** — render final da cena
- **Pós-Produção** — tratamento final da imagem para o cliente

**Fluxo:**
1. Finalização gera o render (`.exr` ou `.jpg`)
2. Render é registrado automaticamente em `Renders` e em `Pós-Produção`
3. Finalizador recebe notificação de aprovação de render
4. Pós-Produção processa a imagem e envia como prévia para FlowReview
5. Após aprovação interna, imagem é enviada ao cliente via FlowReviewExt
6. Se aprovada → avança para EF; se com ajustes → avança para R01

---

### R01, R02, R03... — Ciclo de Alterações

**Objetivo:** Incorporar feedback do cliente em ciclos iterativos.

**Funções envolvidas:**
- **Alteração** — executa as modificações solicitadas pelo cliente
- **Pós-Produção** — reprocessa a imagem com as alterações

**Fluxo:**
1. Gestor registra as alterações solicitadas
2. Colaborador executa a Alteração e gera novo render
3. Pós-Produção reprocessa a imagem
4. Nova prévia enviada ao cliente via FlowReviewExt
5. Ciclo se repete até aprovação (R01 → R02 → R03...)
6. Status da imagem avança a cada rodada (R01, R02, R03, R04, R05)

**Regras de nomenclatura NAS:**
- Arquivos de Alteração salvos em: `03.Models/{nome_imagem}/Final/{revisao}/`

---

### EF — Entrega Final

**Objetivo:** Concluir a entrega com a imagem aprovada pelo cliente.

**Funções envolvidas:**
- **Alteração** (se houver último ajuste)
- **Pós-Produção** — versão final para entrega

**Fluxo:**
1. Cliente aprova a prévia no FlowReviewExt
2. Arquivo de Pós-Produção aprovado é automaticamente movido para `04.Finalizacao/{revisao}/`
3. Status da imagem atualizado para EF (Em Finalização) ou APR (Aprovada)
4. Imagem pode ainda transitar para REN (Renderizada) e ENTREGUE

---

## 3. Funções do Pipeline

Todas as funções são registradas na tabela `funcao` e instanciadas por imagem em `funcao_imagem`.

### 3.1 Caderno (`funcao_id = 1`)

| | |
|---|---|
| **O que faz** | Documenta o briefing do projeto, referências e instruções técnicas em PDF |
| **Input** | Briefing do cliente, especificações do projeto |
| **Output** | Arquivo PDF com o caderno de produção |
| **Pasta NAS** | `02.Projetos/` |
| **Nome do arquivo** | `{nº}.{NOMENCLATURA}-{nome}-PDF-{processo}-{revisao}.pdf` |
| **Etapas** | P00 |
| **Status funcao_imagem** | Pendente → Em andamento → Em aprovação → Aprovado → Finalizado |
| **Aprovação** | Ao colocar em aprovação, status muda para "Em aprovação"; arquivo enviado ao worker |

---

### 3.2 Filtro de Assets (`funcao_id = 8`)

| | |
|---|---|
| **O que faz** | Filtra e seleciona assets 3D (mobiliário, materiais, vegetação) para uso na cena |
| **Input** | Referências do projeto, biblioteca de assets |
| **Output** | PDF ou arquivo de documentação dos assets selecionados |
| **Pasta NAS** | `02.Projetos/` |
| **Nome do arquivo** | `{nº}.{NOMENCLATURA}-{nome}-PDF-{processo}-{revisao}.pdf` |
| **Etapas** | P00 |
| **Status funcao_imagem** | Igual ao Caderno |

---

### 3.3 Modelagem (`funcao_id = 2`)

| | |
|---|---|
| **O que faz** | Constrói a geometria 3D da cena (arquitetura, mobiliário) |
| **Input** | Plantas arquitetônicas, briefing, caderno |
| **Output** | Arquivo `.max` (3ds Max) com a modelagem |
| **Pasta NAS** | `03.Models/{nome_imagem}/MT/` |
| **Nome do arquivo** | `{nº}.{NOMENCLATURA}-{nome}-IMG-{processo}-{revisao}.{ext}` |
| **Etapas** | P00, R00 |
| **Subfuncao NAS** | `MT` (Modelagem Técnica) |

---

### 3.4 Composição (`funcao_id = 3`)

| | |
|---|---|
| **O que faz** | Posiciona câmera, luz, materiais e assets na cena 3D |
| **Input** | Modelagem concluída, referências visuais |
| **Output** | Arquivo `.max` ou `.psb` com a cena composta |
| **Pasta NAS** | `03.Models/{nome_imagem}/Comp/` |
| **Nome do arquivo** | `{nº}.{NOMENCLATURA}-{nome}-IMG-{processo}-{revisao}.{ext}` |
| **Etapas** | P00, R00 |
| **Subfuncao NAS** | `Comp` |

---

### 3.5 Finalização (`funcao_id = 4`)

| | |
|---|---|
| **O que faz** | Executa o render final e ajustes técnicos da imagem |
| **Input** | Cena composta (`.max`/`.psb`), aprovação de ângulo (P00) |
| **Output** | Render em formato `.exr` ou `.jpg` (enviado para Pós-Produção) |
| **Pasta NAS** | `03.Models/{nome_imagem}/Final/` |
| **Nome do arquivo** | `{nº}.{NOMENCLATURA}-{nome}-IMG-{processo}-{revisao}.{ext}` |
| **Etapas** | P00 (parcial), R00 (completo) |
| **Subfuncao NAS** | `Final` |
| **Trigger render** | Ao enviar o render, sistema cria linha automática em Renders e Pós-Produção |
| **Notificação** | Finalizador recebe notificação para aprovar o render |
| **Ação especial** | Se aprovada com status P00 → registra ângulos em `angulos_imagens` |

---

### 3.6 Pré-Finalização (`funcao_id = 9`)

| | |
|---|---|
| **O que faz** | Etapa intermediária antes da finalização completa |
| **Input** | Composição aprovada |
| **Output** | Render parcial para avaliação interna |
| **Pasta NAS** | `03.Models/{nome_imagem}/Final/` |
| **Etapas** | P00 |

---

### 3.7 Pós-Produção (`funcao_id = 5`)

| | |
|---|---|
| **O que faz** | Tratamento final da imagem: color grading, retoques, composição 2D |
| **Input** | Render gerado pela Finalização ou Alteração (`.exr`/`.jpg`) |
| **Output** | Imagem final para o cliente (`.jpg`/`.png`) |
| **Pasta NAS** | `04.Finalizacao/{revisao}/` |
| **Nome do arquivo** | `{nome_imagem_clean}_{revisao}.{ext}` |
| **Etapas** | R00, R01, R02..., EF |
| **Aprovação** | Imagem enviada como prévia ao FlowReview; após aprovação, arquivo movido para destino final via SFTP |
| **Planta Humanizada** | Sub-pasta especial: `04.Finalizacao/{revisao}/PH/` |

---

### 3.8 Alteração (`funcao_id = 6`)

| | |
|---|---|
| **O que faz** | Executa modificações solicitadas pelo cliente após a prévia |
| **Input** | Render anterior, comentários do cliente do FlowReviewExt |
| **Output** | Novo render com alterações aplicadas |
| **Pasta NAS** | `03.Models/{nome_imagem}/Final/{revisao}/` |
| **Nome do arquivo** | `{nº}.{NOMENCLATURA}-{nome}-IMG-{processo}-{revisao}.{ext}` |
| **Etapas** | R01, R02, R03..., EF |
| **Trigger SFTP** | Quando aprovada, arquivo enviado automaticamente para NAS via worker |

---

### 3.9 Planta Humanizada (`funcao_id = 7`)

| | |
|---|---|
| **O que faz** | Produz plantas humanizadas (vista 2D com elementos decorativos) |
| **Input** | Planta arquitetônica, assets decorativos |
| **Output** | Imagem da planta humanizada |
| **Pasta NAS** | `04.Finalizacao/{revisao}/PH/` |
| **Etapas** | Conforme necessidade do projeto |
| **Aprovação** | Segue fluxo de Finalização quando tipo de imagem = "humanizada" |

---

## 4. Fluxo de Renderização

O render é o arquivo intermediário central do pipeline. É gerado pelas funções de **Finalização** e **Alteração** e consumido pela **Pós-Produção**.

### 4.1 Onde o render é gerado

- **Finalização** (funcao_id = 4): gera o render da cena 3D em `.exr` ou alta resolução `.jpg`
- **Alteração** (funcao_id = 6): gera novo render após modificações

### 4.2 Registro automático do render

Quando o arquivo de render é enviado via worker, o sistema cria automaticamente:
1. Uma linha na tabela de **Renders** (módulo `Render/`)
2. Uma linha na tabela de **Pós-Produção** vinculada ao render

```
Finalizador envia render
       │
       ▼
  upload_enqueue.php → staging
       │
       ▼
  upload_worker.php → NAS (03.Models/Final/)
       │
       ▼
  Notificação ao Finalizador para aprovar o render
       │
       ▼
  Finalizador aprova → Pós-Produção ativada
```

### 4.3 Nomenclatura do render

```
{número}.{NOMENCLATURA}_{nome_imagem}.exr
Exemplo: 1.JON_LIN_Sala.exr
```

### 4.4 Quem consome o render

A **Pós-Produção** recebe o render como input e produz a imagem final. A linha em Pós-Produção é criada automaticamente quando o render é enviado — **não é necessária criação manual**.

### 4.5 Circulação no sistema

```
[Finalização / Alteração]
       │ gera render (.exr)
       ▼
[Render] — aprovação pelo finalizador
       │ aprovado
       ▼
[Pós-Produção] — recebe render como input
       │ gera imagem final (.jpg)
       ▼
[FlowReview] — aprovação interna
       │ aprovado
       ▼
[FlowReviewExt] — aprovação cliente
       │ aprovado
       ▼
[NAS: 04.Finalizacao/{revisao}/]
```

---

## 5. Sistema de Uploads

### 5.1 Arquitetura Geral

O sistema de upload é dividido em duas camadas:

1. **`upload_enqueue.php`** — recebe o arquivo do browser, armazena em staging e enfileira o job
2. **`scripts/upload_worker.php`** — daemon que processa jobs da fila e envia via SFTP para o NAS

### 5.2 Como o Worker de Upload Funciona

#### Modo de execução
O worker é executado como daemon via CLI:
```bash
php scripts/upload_worker.php --daemon --sleep=3
```

#### Ciclo de processamento
```
1. Scan do diretório uploads/staging/ por arquivos .json
2. Claim atômico: rename(id.json → id.json.processing.{pid})
3. Leitura dos metadados (origem, função, nomenclatura, etc.)
4. Conexão SFTP reutilizada para NAS
5. Descoberta do ano correto (2024, 2025, 2026)
6. Construção do caminho de destino conforme função
7. Upload com progresso e retry
8. Atualização do banco (arquivo_log, funcao_imagem)
9. Notificação Slack ao colaborador
10. Deleção do arquivo staged
```

#### Mapeamento função → pasta NAS

| Função | Pasta NAS |
|--------|-----------|
| Caderno, Filtro de assets | `02.Projetos/` |
| Modelagem, Composição, Finalização, Pré-Finalização, Alteração | `03.Models/` |
| Pós-Produção, Planta Humanizada | `04.Finalizacao/` |

#### Nomenclatura de arquivos

**Funções 03.Models:**
```
{nº}.{NOMENCLATURA}-{primeiraPalavra}-IMG-{processo}-{revisao}.{ext}
Exemplo: 01.JON_LIN-Sala-IMG-P00-R00.max
```

**Funções 04.Finalizacao:**
```
{nome_imagem_clean}_{revisao}.{ext}
Exemplo: Sala_de_Estar_R00.jpg
```

**Funções 02.Projetos:**
```
{nº}.{NOMENCLATURA}-{primeiraPalavra}-PDF-{processo}-{revisao}.pdf
Exemplo: 01.JON_LIN-Sala-PDF-P00-R00.pdf
```

#### Resiliência e retries

| Tipo de erro | Estratégia |
|---|---|
| Erro de rede (ENETUNREACHABLE, ECONNREFUSED) | Retry com backoff: 20s, 40s, 60s... até 50 ciclos |
| Erro SFTP (protocolo) | Retry com backoff: 5s, 10s, 15s... até 5 tentativas |
| Erro permanente (auth, permissão) | Falha imediata, move para `failed/` |
| Job órfão (> 1 hora em processing) | Reclamado por novo worker |

#### Estados do arquivo

```
enfileirado → processando → concluido
                          → falha
                          → aguardando_rede (retry)
```

### 5.3 Quais Arquivos São Enviados

| Extensão | Tipo | Quando |
|---|---|---|
| `.pdf` | PDF | Caderno, Filtro de assets |
| `.max` | 3ds Max | Modelagem, Composição, Finalização, Alteração |
| `.psb` | Photoshop | Finalização, Pós-Produção, Alteração |
| `.exr` | Render | Finalização, Alteração (render output) |
| `.jpg` / `.png` | Imagem | Prévia para FlowReview; entrega final |

### 5.4 Quando Acontece o Upload

O upload é disparado quando o colaborador **coloca a função em aprovação** no sistema. O processo é:

1. Colaborador seleciona os arquivos na interface
2. Sistema envia para `upload_enqueue.php`
3. Arquivo vai para staging (`uploads/staging/`)
4. Worker processa em background e envia para NAS
5. Colaborador recebe notificação Slack com o caminho do arquivo

### 5.5 Rastreamento via arquivo_log

```sql
tabela: arquivo_log
- funcao_imagem_id (qual função originou)
- caminho (caminho Windows Z:\ para acesso na rede)
- nome_arquivo (nome final no NAS)
- tamanho
- tipo (PDF / IMG)
- colaborador_id
- status (enfileirado / processando / concluido / falha)
```

---

## 6. Sistema de Aprovação (Flow Review)

### 6.1 Flow Review Interno

O **Flow Review** (`FlowReview/`) é o sistema de aprovação interna utilizado pela equipe Improov.

#### Quando é acionado
- Quando um colaborador coloca uma função em aprovação (`status = 'Em aprovação'`)
- O sistema registra a entrada em `historico_aprovacoes_imagens`

#### Processo de revisão

```
Colaborador coloca função em aprovação
       │
       ▼
Analista acessa FlowReview → visualiza prévia e PDFs
       │
       ├─ Faz comentários (comentarios_imagem)
       ├─ Marca pontos na imagem
       └─ Define status: Aprovado / Aprovado com ajustes / Em ajuste
              │
              ▼
       Colaborador recebe notificação
              │
              ├─ Se "Em ajuste" → colaborador corrige e reenvia
              └─ Se "Aprovado" → função concluída
                     │
                     ▼
              Worker envia arquivo final para NAS
              Status funcao_imagem → 'Finalizado'
```

#### Tabelas de aprovação interna

| Tabela | Propósito |
|---|---|
| `historico_aprovacoes_imagens` | Registro de cada envio de prévia (índice de envio) |
| `historico_aprovacoes` | Registro de mudanças de status da função |
| `comentarios_imagem` | Comentários por imagem/página de PDF |
| `angulos_imagens` | Controle de ângulos no processo P00 |
| `funcao_imagem` | Status atual da função |

#### Estados do FlowReview interno

```
Em aprovação → [analista revisa] → Em ajuste → [colaborador corrige] → Em aprovação
                                 → Aprovado com ajustes
                                 → Aprovado → [worker SFTP] → Finalizado
```

#### Regras especiais de envio SFTP pós-aprovação

A aprovação aciona o envio SFTP nos seguintes casos:
- Função = **Pós-Produção** + status = **Aprovado**
- Função = **Alteração** + status = **Aprovado**
- Função = **Finalização** + tipo_imagem = **Humanizada** + status = **Aprovado**

---

### 6.2 Flow Review Externo (FlowReviewExt)

O **FlowReviewExt** (`FlowReviewExt/`) é o módulo voltado para **aprovação pelo cliente**.

#### Quando é acionado
- Após aprovação interna da Pós-Produção
- Gestão libera a imagem para revisão do cliente

#### Processo de revisão externa

```
Gestão gera link único de revisão
       │
       ▼
Cliente acessa link → informa nome e e-mail
       │
       ▼
Cliente visualiza a imagem/arquivo
       │
       ├─ Aprovar
       ├─ Aprovar com ajustes + comentário
       ├─ Solicitar ajustes + comentário
       └─ Reprovar + comentário
              │
              ▼
       Sistema notifica gestão (Slack + notificação interna)
              │
              ├─ Se aprovado → imagem avança para próxima etapa
              └─ Se ajustes → nova rodada de alterações (R01, R02...)
```

#### Controle de acesso externo
- Token único por revisão (gerado em `generate_obra_tokens.php`)
- Acesso via `/review/{codigo-da-obra}`
- Sessão PHP com tempo de expiração
- Autenticação por nome + e-mail do cliente

#### Etapas que precisam de aprovação externa

| Etapa | Tipo de aprovação |
|---|---|
| P00 | Cliente escolhe ângulo (via FlowReviewExt) |
| R00 | Cliente aprova ou solicita ajustes |
| R01, R02... | Cliente aprova iteração de alterações |
| EF | Aprovação final antes da entrega |

---

## 7. Estrutura Técnica Encontrada no Repositório

### 7.1 Módulos e Serviços

| Módulo | Caminho | Propósito |
|---|---|---|
| Produção | `Producao/` | Dashboard de KPIs e acompanhamento da produção mensal |
| Pós-Produção | `Pos-Producao/` | Gestão de tarefas de pós-produção |
| Render | `Render/` | Gerenciamento de arquivos de render |
| Alteração | `Alteracao/` | Controle de iterações de revisão |
| FlowReview | `FlowReview/` | Aprovação interna |
| FlowReviewExt | `FlowReviewExt/` | Aprovação pelo cliente |
| FlowDrive | `FlowDrive/` | Integração com NAS/armazenamento |
| FlowReferências | `FlowReferencias/` | Gestão de referências do projeto |
| FlowTrack | `FlowTrack/` | Rastreamento de progresso |
| Painel Produção | `PainelProducao/` | Painel de alocação de colaboradores |
| Dashboard | `Dashboard/` | Visão gerencial por obra |
| Imagens | `Imagens/` | Catálogo e gestão de imagens |
| Upload | `Upload/` | Interface de upload legada |

### 7.2 Workers e Automações

| Worker / Automação | Caminho | Propósito |
|---|---|---|
| Upload Worker (daemon) | `scripts/upload_worker.php` | Processa fila de uploads SFTP para NAS |
| Retry Worker | `scripts/retry_failed_uploads.php` | Reprocessa uploads com falha |
| NAS Test | `scripts/test_nas_path.php` | Testa conectividade com NAS |
| Upload Enqueue | `upload_enqueue.php` | Recebe arquivos e cria jobs na fila |
| Upload Final | `uploadFinal.php` | Upload complementar para entregas finais |
| Verificar Render | `verifica_render.php` | Verifica status de renders |
| Add Render | `addRender.php` | Adiciona render ao pipeline |
| Script Diários | `scriptsDiarios.php` | Rotinas automáticas diárias |
| Webhook | `webhook.php` | Integração via webhooks externos |

### 7.3 Tabelas Relevantes do Pipeline

| Tabela | Propósito |
|---|---|
| `imagens_cliente_obra` | Entidade principal: imagem por cliente/obra |
| `funcao` | Tipos de funções (Caderno, Modelagem, etc.) |
| `funcao_imagem` | Instância de função por imagem |
| `funcao_imagem_pdf` | Nome do PDF calculado por função |
| `status_imagem` | Códigos de etapa (P00, R00, R01..., EF, APR) |
| `arquivo_log` | Rastreamento de uploads de arquivos |
| `historico_aprovacoes` | Histórico de aprovações por função |
| `historico_aprovacoes_imagens` | Histórico de envios de prévias |
| `comentarios_imagem` | Comentários de revisão |
| `angulos_imagens` | Ângulos registrados no P00 |
| `entregas_itens` | Itens de entrega vinculados a imagens |
| `fotografico_info` | Endereço para fotografia da obra |
| `fotografico_registro` | Registros de visitas fotográficas |
| `flow_ref_axis` | Eixos de referência |
| `flow_ref_category` | Categorias de referências |
| `flow_ref_subcategory` | Subcategorias de referências |
| `flow_ref_upload` | Arquivos de referência enviados |
| `arquivo_fisico` | Deduplicação de arquivos por hash SHA256 |
| `acompanhamento_email` | Acompanhamento de comunicações por obra |

### 7.4 Estrutura NAS (Storage)

```
/mnt/clientes/
├── 2024/
├── 2025/
└── 2026/
    └── {NOMENCLATURA}/               (ex.: JON_LIN)
        ├── 02.Projetos/               (Caderno, Filtros)
        ├── 03.Models/
        │   └── {nome_imagem}/         (ex.: Sala_de_Estar)
        │       ├── MT/                (Modelagem)
        │       ├── Comp/              (Composição)
        │       └── Final/             (Finalização, Alteração)
        │           └── {revisao}/     (ex.: R01/)
        └── 04.Finalizacao/
            └── {revisao}/             (ex.: R00/)
                ├── imagem_final.jpg
                └── PH/                (Planta Humanizada)
```

### 7.5 Tecnologias Utilizadas

| Tecnologia | Uso |
|---|---|
| PHP | Backend principal |
| MySQL | Banco de dados |
| SFTP / phpseclib | Envio de arquivos para NAS |
| Redis | Progresso de upload em tempo real |
| WebSocket | Notificações de progresso no browser |
| Slack API | Notificações para colaboradores |
| 3ds Max | Software de modelagem 3D (externo) |
| Photoshop/Affinity | Software de pós-produção (externo) |

---

## 8. Lacunas no Processo Atual

### 8.1 Fotográfico — Estrutura Incompleta

A etapa de **Fotográfico** existe parcialmente no sistema:
- Tabelas `fotografico_info` e `fotografico_registro` existem
- Dashboard registra data e observações de visita fotográfica
- Notificação Slack é enviada aos finalizadores quando fotográfico é registrado
- **Porém não há:**
  - Função específica "Fotográfico" na tabela `funcao`
  - Fluxo de upload de fotos de referência da obra
  - Aprovação formal das fotos tiradas
  - Integração com o FlowReview para as fotos

### 8.2 Referências — Módulo Separado sem Integração

O **FlowReferências** (`FlowReferencias/`) existe como módulo independente com:
- Upload de referências categorizadas por eixo/categoria/subcategoria
- Tabelas: `flow_ref_axis`, `flow_ref_category`, `flow_ref_subcategory`, `flow_ref_upload`
- **Porém não há:**
  - Integração com `funcao_imagem` para vincular referências a funções específicas
  - Notificação para colaboradores quando novas referências são adicionadas à sua imagem
  - Aprovação ou revisão das referências
  - Vínculo com etapas do pipeline (P00, R00...)

### 8.3 Ausência de Validação no Caderno

A função Caderno vai direto para aprovação sem validação estruturada de conteúdo (campos obrigatórios, checklists).

### 8.4 Fluxo Manual de Aprovação de Render

O processo de aprovação de renders ainda envolve passos manuais:
- Finalizador precisa acessar o sistema para aprovar o render
- Não há aprovação automática com critérios técnicos (resolução, formato)
- Render rejeitado requer reenvio manual sem histórico vinculado ao FlowReview

### 8.5 Sem Status de Bloqueio Entre Funções

O sistema não garante que funções sejam executadas em ordem. Por exemplo:
- Pós-Produção pode ser iniciada antes da Finalização ser aprovada
- Alteração pode ser aberta sem o feedback formal do cliente no FlowReviewExt

### 8.6 Dependências Manuais na Configuração de Obras

Para cada nova obra, é necessário:
- Criar imagens manualmente em `imagens_cliente_obra`
- Criar funções manualmente em `funcao_imagem`
- Associar colaboradores manualmente
- Não há template ou automação de onboarding de obra

### 8.7 Ausência de Versionamento de Arquivos

O sistema salva arquivos com nome único no NAS, mas:
- Não há versionamento histórico de arquivos substituídos
- Arquivo mais recente sobrescreve o anterior (sem backup automático no NAS)
- Apenas `historico_aprovacoes_imagens` rastreia índices de envio

---

## 9. Sugestões de Melhoria

### 9.1 Padronização de Etapas

Criar uma tabela `etapa_pipeline` que mapeie formalmente:
- Etapa (P00, R00, R01...)
- Funções permitidas por etapa
- Ordem obrigatória de funções
- Pré-requisitos para avançar à próxima etapa

Isso permitiria bloquear automaticamente funções fora de ordem e dar visibilidade clara do progresso.

### 9.2 Separação de Responsabilidades no Worker

O `upload_worker.php` atualmente faz:
- Gerenciamento de fila
- Upload SFTP
- Atualização de banco
- Envio de notificações Slack
- Gerenciamento de erros e retries

**Sugestão:** Separar em classes/serviços:
- `QueueManager` — gerenciamento de staging/claiming
- `SFTPService` — upload e retry de SFTP
- `NotificationService` — Slack e in-app
- `DatabaseUpdater` — atualizações de status

### 9.3 Aprovação Automática de Render

Implementar validação automática do render antes de enviar para aprovação humana:
- Verificar resolução mínima
- Verificar formato correto (`.exr`, `.jpg`)
- Verificar se nomenclatura está correta
- Aprovação automática para renders que passam na validação técnica

### 9.4 Integração FlowReferências → funcao_imagem

Vincular referências diretamente a imagens/funções:
- Ao adicionar referência, selecionar a imagem/função alvo
- Notificação automática ao colaborador responsável pela função
- Visualização de referências dentro do FlowReview ao revisar a função

### 9.5 Templates de Obra

Criar templates de criação de obra que automaticamente:
- Geram as imagens padrão do projeto
- Atribuem funções conforme o tipo de imagem
- Definem prazos com base no cronograma
- Criam estrutura de pastas no NAS

### 9.6 Dashboard de Status do Pipeline

Criar uma visão unificada que mostre, por imagem:
- Etapa atual (P00, R00, R01...)
- Função em andamento
- Status de cada função (com indicador visual)
- Próxima ação necessária
- Arquivo pendente de upload

### 9.7 Histórico Completo de Arquivos

Implementar versionamento de arquivos com:
- Retenção de versões anteriores no NAS (pasta `.history/`)
- Registro de substituições em `arquivo_log`
- Possibilidade de rollback para versão anterior

---

## 10. Sugestões para Estruturar Fotográfico e Referências

### 10.1 Estrutura para o Fotográfico

O **Fotográfico** é uma etapa inicial do pipeline que coleta imagens reais da obra para uso como referência. Proposta de estruturação:

#### Adicionar função "Fotográfico" à tabela `funcao`

```sql
INSERT INTO funcao (idfuncao, nome_funcao) VALUES (10, 'Fotográfico');
```

#### Fluxo proposto para Fotográfico

```
Gestor registra visita fotográfica (fotografico_registro)
       │
       ▼
Fotógrafo/Colaborador envia fotos pelo sistema
       │
       ▼
Fotos armazenadas no NAS: 01.Fotografico/{nome_obra}/
       │
       ▼
FlowReview — aprovação interna das fotos
       │ aprovado
       ▼
Fotos disponíveis para consulta em todas as funções subsequentes
       │
       ▼
Notificação para modeladores e finalizadores
```

#### Estrutura NAS para Fotográfico

Adicionar nova pasta base:
```
/mnt/clientes/{ano}/{NOMENCLATURA}/
└── 01.Fotografico/            ← NOVA PASTA
    ├── fachada/
    ├── interior/
    └── detalhes/
```

#### Mapeamento no Worker

Adicionar ao `mapFuncaoParaPasta()`:
```php
'fotográfico' => '01.Fotografico',
'fotografico' => '01.Fotografico',
```

#### Tabelas necessárias

- `fotografico_info` — já existe (endereço da obra)
- `fotografico_registro` — já existe (registro de visitas)
- **Novo:** `fotografico_arquivo` — arquivos individuais enviados
  ```sql
  CREATE TABLE fotografico_arquivo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fotografico_registro_id INT NOT NULL,
    obra_id INT NOT NULL,
    caminho VARCHAR(500),
    nome_arquivo VARCHAR(255),
    tipo ENUM('foto', 'video', 'outro'),
    descricao TEXT,
    aprovado TINYINT(1) DEFAULT 0,
    criado_por INT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (obra_id) REFERENCES obra(idobra)
  );
  ```

---

### 10.2 Estrutura para as Referências

As **Referências** já existem como módulo (`FlowReferencias/`), mas precisam de melhor integração com o pipeline de produção.

#### Fluxo proposto para Referências

```
Gestor/Cliente envia referências ao sistema (FlowReferencias)
       │ categorizadas por eixo/categoria/subcategoria
       ▼
Sistema vincula referência à obra e às imagens relevantes
       │
       ▼
Colaboradores das funções recebem notificação
       │
       ▼
Referências disponíveis dentro do FlowReview ao revisar
       │
       ▼
Gestor aprova ou descarta referências (curadoria)
```

#### Integração FlowReferências → funcao_imagem

Adicionar coluna de vínculo à tabela `flow_ref_upload`:
```sql
ALTER TABLE flow_ref_upload 
  ADD COLUMN imagem_id INT NULL,
  ADD COLUMN funcao_id INT NULL,
  ADD FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra(idimagens_cliente_obra);
```

#### Exibição no FlowReview

Dentro da interface de revisão de cada função, exibir:
- Painel lateral com referências vinculadas à imagem
- Filtro por categoria (estilo, materiais, mobiliário, iluminação, etc.)
- Botão para adicionar nova referência diretamente da tela de revisão

#### Notificações automáticas

Quando uma referência é adicionada a uma obra, notificar automaticamente:
- Todos os colaboradores com funções ativas nessa obra
- Ou apenas os colaboradores das funções selecionadas no vínculo

#### Pasta NAS para Referências

```
/mnt/clientes/{ano}/{NOMENCLATURA}/
└── 00.Referencias/            ← NOVA PASTA
    ├── cliente/               (referências enviadas pelo cliente)
    ├── equipe/                (referências coletadas internamente)
    └── fotografico/           (fotos da obra)
```

---

## Resumo do Status das Etapas

| Etapa | Implementação | Observação |
|---|---|---|
| **Fotográfico** | ⚠️ Parcial | Registro existe, sem fluxo completo de upload/aprovação |
| **Referências** | ⚠️ Parcial | Módulo existe, sem integração com pipeline de produção |
| **P00 (Caderno + Ângulos)** | ✅ Implementado | Fluxo completo incluindo FlowReviewExt para escolha de ângulo |
| **R00 (Prévia)** | ✅ Implementado | Render + Pós-Produção + aprovação |
| **R01...Rnn (Alterações)** | ✅ Implementado | Ciclo iterativo funcional |
| **EF (Entrega Final)** | ✅ Implementado | Aprovação e SFTP automático |
| **Worker de Upload** | ✅ Implementado | Daemon com retry, Slack, Redis |
| **FlowReview Interno** | ✅ Implementado | Comentários, ângulos, PDFs |
| **FlowReview Externo** | ✅ Implementado | Link único, auth cliente, multi-round |

---

*Documento gerado com base na análise do repositório ImproovWeb — Março 2026.*
