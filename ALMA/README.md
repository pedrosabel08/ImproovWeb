# ALMA V1 no Flow

## Diagnóstico de integração

- A imagem canônica é `imagens_cliente_obra.idimagens_cliente_obra`.
- `funcao_imagem.imagem_id` liga todas as tarefas/funções à mesma imagem. A tarefa não recebe colunas ALMA.
- A modal da tarefa é montada em `PaginaPrincipal/scriptIndex.js`; seu resumo ALMA é carregado depois do conteúdo operacional por uma chamada específica e leve.
- O SIRE existente usa `sire_referencia`, `sire_pilar`, `sire_pilar_valor` e `sire_referencia_valor`. O ALMA reutiliza apenas `sire_referencia` e os helpers de thumbnail; a taxonomia SIRE não define a direção.
- O preview da imagem continua usando `thumb.php` e a infraestrutura atual de `arquivos`.
- O Flow não possui hoje uma matriz genérica de capacidades. `alma_helpers.php` concentra o adaptador V1 entre capacidades conceituais e o padrão legado de nível/cargo, sem IDs fixos de colaboradores.
- Não havia código ou tabela ALMA preexistente. A taxonomia do SIRE não foi reaproveitada como Biblioteca ALMA porque possui finalidade e conteúdo distintos.

## Arquitetura

`imagem -> alma_direcao -> alma_direcao_revisao -> selecoes/referencias/eventos`

A relação `alma_direcao.imagem_id` é única. A tarefa chega ao ALMA exclusivamente por `funcao_imagem.imagem_id`; nenhuma decisão visual é copiada para `funcao_imagem`.

A Biblioteca Oficial é independente da direção. Cada revisão aponta para uma versão imutável publicada e cada seleção aponta para um item dessa versão. A revisão armazena apenas aplicação, justificativa, contexto operacional e vínculos interpretados — nunca uma cópia do texto oficial.

Uma revisão ativa é protegida por `UNIQUE (direcao_id, ativa_token)`. Edições da revisão vigente criam um novo rascunho ligado por `revisao_anterior_id`; ao ativá-lo, a anterior passa a `SUBSTITUIDA`.

## Tabelas

- `alma_biblioteca_versao`
- `alma_biblioteca_dimensao`
- `alma_biblioteca_item`
- `alma_biblioteca_item_secao`
- `alma_biblioteca_secao_entrada`
- `alma_direcao`
- `alma_direcao_revisao`
- `alma_revisao_selecao`
- `alma_revisao_referencia`
- `alma_evento`

Luz possui `luz_momento` e `luz_linguagem`. Fotografia possui `fotografia_direcao`, `fotografia_teste_angulos`, `fotografia_enquadramento` e `fotografia_referencias_sire`. Essa modelagem mantém estruturas diferentes por pilar sem dezenas de colunas nem um EAV irrestrito.

## Migrations e Biblioteca

1. `sql/2026-09-03_alma_v1.sql`
2. `sql/2026-09-03_alma_biblioteca_v1_seed.sql`
3. `sql/2026-09-03_alma_biblioteca_v1_import_correction.sql` (idempotente e condicionada à ausência de revisões; corrige dois campos omitidos no primeiro carregamento por delimitadores do PDF)

O seed foi gerado de forma auditável por `ALMA/scripts/build_library_v1_seed.py`, registra o SHA-256 do PDF de origem e mantém um bloco de proveniência integral em cada item. Ele importa apenas conteúdo encontrado na Biblioteca Oficial. Fotografia, que não possui catálogo detalhado de itens no documento, permanece composta por dimensões contextuais e referências SIRE, sem conceitos inventados.

`ALMA/scripts/apply_sql.php` é um executor apenas-CLI com allowlist limitada aos scripts ALMA listados acima.

## API

O controlador único `ALMA/api.php` segue o padrão JSON do projeto.

GET:

- `action=resumo&imagem_id=...`
- `action=direcao&imagem_id=...&revisao_id=...`
- `action=biblioteca&versao_id=...`
- `action=historico&imagem_id=...`
- `action=sire_busca&q=...&page=...`
- `action=permissions`
- `action=admin_versoes`

POST:

- `criar_revisao`
- `salvar_revisao`
- `ativar_revisao`
- `admin_clonar_versao`
- `admin_salvar_item`
- `admin_publicar_versao`

Salvar usa transação, valida que itens pertencem à versão da revisão, exige interpretação mínima (`representa` e `aplicar`) para referências e usa `lock_version` contra sobrescrita concorrente.

## Telas

- `ALMA/index.php`: Direção Visual por imagem; resumo, jornada, sete pilares, detalhe, referências, histórico, criação, edição e ativação.
- `ALMA/admin.php`: administração versionada e separada da Biblioteca; versões publicadas são leitura apenas, e mudanças são feitas em clone rascunho.
- A modal da tarefa ganhou apenas o card lateral resumido e um link para a tela completa.

## Capacidades V1

- `alma.visualizar`: qualquer usuário autenticado.
- `alma.editar`: administrador, Direção, Gestão de Projetos ou Arquitetura pelo cargo atual.
- `alma.ativar`: administrador, Direção ou Gestão de Projetos.
- `alma.administrar_biblioteca`: administrador (`nivel_acesso = 1`) enquanto não existir ACL granular no Flow.

O mapeamento está centralizado para troca futura por uma ACL real.

## Simplificações conscientes da V1

- Herança Projeto -> Imagem não foi ativada: o modelo atual não oferece infraestrutura de defaults/versionamento por projeto. A direção direta por imagem evita um segundo motor de resolução e mantém o ponto de extensão claro.
- Não há aprovação, cliente, automação, IA, notificações nem regras que alterem tarefas.
- A administração permite editar apenas itens e seções já existentes em um clone; não cria pilares ou categorias novas.
- O histórico é próprio da Direção Visual e não entra na timeline operacional da tarefa.

## Validação

Executar:

```powershell
C:\xampp\php\php.exe ALMA\tests\smoke.php
```

O teste confirma estrutura da jornada, dimensões especiais, biblioteca publicada, leitura de imagem, busca SIRE, resumo sem ALMA, seleção/contexto, referência interpretada, ativação, substituição de revisão e rollback integral dos dados artificiais.

## V2 / riscos conhecidos

- Substituir o adaptador legado por ACL granular persistida.
- Avaliar herança explícita Projeto -> Imagem com indicação de origem e override por dimensão.
- Adicionar testes HTTP autenticados ao pipeline do Flow. O smoke atual valida banco/helpers; a sessão real precisa ser usada no teste manual da UI.
- O conteúdo importado preserva a Biblioteca v1.0; correções editoriais devem entrar em uma nova versão, nunca alterar silenciosamente a publicada.
