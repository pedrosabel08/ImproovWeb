# Mapear uso da PreAlteracao

> Eu quero que você vasculhe o que temos construído para a tela [PreAlteracao](PreAlteracao/). Quero que me mostre de maneira fácil e prática o que hoje ela deveria ser usada.

<details><summary>16 previous messages</summary>

> Vou mapear a pasta `PreAlteracao/` e os pontos onde essa tela é referenciada, para entender não só a UI, mas também o fluxo de uso que o sistema espera hoje. Depois te devolvo um resumo prático em português, sem ficar preso em detalhe técnico desnecessário.
>
> <details><summary>Ran 3 commands</summary>
>
> - Ran `rg --files`
> - Ran `rg -n "PreAlteracao|prealteracao|pre_alteracao|Pré|Alteracao"`
> - Ran `Get-ChildItem -Force`
>
> </details>
>
> Já achei que a tela é um módulo próprio, com backend, JS, CSS e uma migration. Agora vou ler esses arquivos e o changelog relacionado para separar “o que aparece na tela” de “qual processo operacional ela apoia”.
>
> <details><summary>Ran 6 commands</summary>
>
> - Ran `Get-Content -Path PreAlteracao\index.php`
> - Ran `Get-Content -Path PreAlteracao\migration.sql`
> - Ran `Get-Content -Path PreAlteracao\script.js`
> - Ran `Get-Content -Path PreAlteracao\get_pre_alt_entregas.php`
> - Ran `Get-Content -Path PreAlteracao\save_pre_analise.php`
> - Ran `Get-Content -Path PreAlteracao\get_imagens_rvw_done.php`
>
> </details>
>
> O fluxo central já ficou claro: ela entra depois do review do cliente e antes do planejamento de alterações. Vou conferir agora se algum outro módulo coloca imagens nesse estado ou consome o `READY_FOR_PLANNING`, porque isso diz até onde a tela está integrada de fato.
>
> <details><summary>Ran 5 commands</summary>
>
> - Ran `Get-Content -Path PreAlteracao\update_complexidade.php`
> - Ran `rg -n "RVW_DONE|PRE_ALT|READY_FOR_PLANNING|substatus_id\s*=\s*10|substatus_id\s*=\s*11|substatus_id\s*=\s*12|substatus_id IN \(10, 11, 12\)|pre_alt_analise|necessita_retorno|complexidade"`
> - Ran `Get-Content -Path sidebar.php`
> - Ran `Get-Content -Path CHANGELOG\Flow\pre_alteracao.md`
> - Ran `Get-Content -Path sidebar_counts.php`
>
> </details>
>
> Um detalhe importante apareceu: o badge da sidebar muda conforme o usuário. Para o colaborador `1`, ele aponta obras com imagens aguardando pré-análise; para o `21`, obras já prontas para planejamento. Vou fazer mais uma busca focada para ver quem cria o `RVW_DONE` e quem usa o `READY_FOR_PLANNING`.
>
> <details><summary>Ran 5 commands</summary>
>
> - Ran `rg -n "RVW_DONE"`
> - Ran `rg -n "pre_alt_analise|necessita_retorno|complexidade"`
> - Ran `rg -n "PRE_ALT"`
> - Ran `rg -n "substatus_id = 10|substatus_id = 11|substatus_id = 12|substatus_id IN \(10, 11, 12\)"`
> - Ran `rg -n "READY_FOR_PLANNING"`
>
> </details>
>
> A busca global com `rg` não trouxe resultado mesmo com termos presentes nos arquivos, então vou trocar para `Select-String` em cima dos arquivos relevantes. Coisa pequena, mas prefiro confirmar com evidência do código em vez de inferir no escuro.
>
> <details><summary>Ran 5 commands</summary>
>
> - Ran `Get-ChildItem -Recurse -File -Include *.php,*.js,*.sql,*.md | Select-String -Pattern 'substatus_id = 10','substatus_id = 11','substatus_id = 12'`
> - Ran `Get-ChildItem -Recurse -File -Include *.php,*.js,*.sql,*.md | Select-String -Pattern 'pre_alt_analise'`
> - Ran `Get-ChildItem -Recurse -File -Include *.php,*.js,*.sql,*.md | Select-String -Pattern 'PRE_ALT'`
> - Ran `Get-ChildItem -Recurse -File -Include *.php,*.js,*.sql,*.md | Select-String -Pattern 'READY_FOR_PLANNING'`
> - Ran `Get-ChildItem -Recurse -File -Include *.php,*.js,*.sql,*.md | Select-String -Pattern 'RVW_DONE'`
>
> </details>
>
> Achei a integração de entrada: a tela de `Entregas` tem ação para marcar itens como `RVW_DONE`, que é justamente o que alimenta a Pré-Alteração. Vou conferir o trecho para te explicar o uso ponta a ponta, sem misturar com os backups e changelogs.
>
> <details><summary>Ran 5 commands</summary>
>
> - Ran `Get-Content -Path Alteracao\getAlteracao.php`
> - Ran `Get-Content -Path Alteracao\script.js | Select-Object -First 180`
> - Ran `Get-Content -Path PaginaPrincipal\scriptIndex.js | Select-Object -Skip 5435 -First 25`
> - Ran `Get-Content -Path Entregas\script.js | Select-Object -Skip 1380 -First 330`
> - Ran `Get-Content -Path Dashboard\scriptObra.js | Select-Object -Skip 1350 -First 40`
>
> </details>
>
> Também confirmei que a “Lista Alteração” hoje não nasce automaticamente da Pré-Alteração; ela mostra funções de alteração já existentes. Então a tela atual parece mais um funil de triagem e handoff para planejamento do que uma criadora automática de tarefas.
>
> <details><summary>Ran 3 commands</summary>
>
> - Ran `Get-Content -Path Entregas\update_substatus_imagem.php`
> - Ran `Get-Content -Path PreAlteracao\style.css | Select-Object -First 120`
> - Ran `Get-Content -Path Entregas\listar_entregas.php | Select-String -Pattern "substatus|nome_substatus|RVW_DONE|READY|PRE_ALT" -Context 2,2`
>
> </details>
>
> Tenho material suficiente para fechar um diagnóstico prático. Só vou pegar os números de linha dos pontos principais, para você conseguir abrir exatamente onde isso está implementado se quiser conferir depois.
>
> <details><summary>Ran 5 commands</summary>
>
> - Ran `Select-String -Path PreAlteracao\save_pre_analise.php -Pattern 'substatus_id = 11|verificarReadyForPlanning|substatus_id = 10|necessita_retorno = 1|substatus_id = 12|notificarPedroSlack'`
> - Ran `Select-String -Path sidebar_counts.php -Pattern 'pre_alt_analise_count|substatus_id = 10|substatus_id = 12|userId == 1|userId == 21'`
> - Ran `Select-String -Path Entregas\script.js -Pattern 'Marcar como RVW_DONE|substatus_id: 10|RVW_DONE aplicado|ready_for_planning'`
> - Ran `Select-String -Path PreAlteracao\script.js -Pattern 'get_pre_alt_entregas|count_analise|count_planning|Review Studio|Complexidade|necessita_retorno|save_pre_analise|READY_FOR_PLANNING'`
> - Ran `Select-String -Path PreAlteracao\index.php -Pattern 'Pr|columns-layout|Pré-Análise|Para Planejamento|substatus'`
>
> </details>

</details>

Vasculhei a `PreAlteracao/`. Em termos práticos, hoje essa tela deveria ser usada como **mesa de triagem das alterações depois que o cliente terminou o review e antes do gestor planejar a execução**.

O fluxo atual é este:

1. Na tela de `Entregas`, alguém marca imagens em review como `RVW_DONE`.
2. Essas imagens aparecem na Pré-Alteração, na coluna **Pré-Análise**.
3. A pessoa responsável abre a obra/entrega, entra no `Review Studio`, lê os comentários e registra:
   - complexidade: `S`, `M`, `C` ou `TA`
   - se precisa retorno do cliente
   - ação/observação objetiva para execução
4. Ao salvar, a imagem vira `PRE_ALT`.
5. Quando não sobra nenhuma imagem `RVW_DONE` e nenhuma `PRE_ALT` marcada como “necessita retorno”, o sistema promove tudo para `READY_FOR_PLANNING` e notifica o Pedro via Slack.
6. A coluna **Para Planejamento** mostra o que já está pronto para o gestor definir prazo, prioridade e se vai para `EF` ou `R01`.

O uso ideal hoje seria:

**Nicolle / pré-análise:** usar a tela para transformar comentário bruto do cliente em escopo claro de alteração.
**Gestor / Pedro:** usar a coluna “Para Planejamento” como fila do que já está fechado e pode virar planejamento de alteração.
**Executor:** não deveria usar essa tela como fonte principal; ele deveria receber depois uma tarefa já planejada, sem precisar interpretar comentário do cliente.

Pontos importantes que vi no código:

- A tela tem exatamente duas colunas: `Pré-Análise` para `RVW_DONE/PRE_ALT` e `Para Planejamento` para `READY_FOR_PLANNING`: [PreAlteracao/index.php](./PreAlteracao/index.php:78)
- O formulário salva complexidade, retorno do cliente e observações: [PreAlteracao/script.js](./PreAlteracao/script.js:295)
- Ao salvar, muda a imagem para `PRE_ALT`: [PreAlteracao/save_pre_analise.php](./PreAlteracao/save_pre_analise.php:71)
- O `READY_FOR_PLANNING` só acontece quando a obra não tem pendências de análise nem retorno do cliente: [PreAlteracao/save_pre_analise.php](./PreAlteracao/save_pre_analise.php:88)
- A entrada do fluxo vem da tela de Entregas, pelo botão “Marcar como RVW_DONE”: [Entregas/script.js](./Entregas/script.js:1534)
- A sidebar mostra contagem diferente por usuário: usuário `1` vê obras aguardando análise; usuário `21` vê obras prontas para planejamento: [sidebar_counts.php](./sidebar_counts.php:134)

Minha leitura: **ela ainda não é uma tela de execução de alteração**. Ela é um filtro operacional para evitar que alteração vá para produção com comentário ambíguo. O que falta no fluxo atual é uma ponte mais explícita entre `READY_FOR_PLANNING` e a criação/planejamento real na `Alteracao`; hoje isso parece ficar como etapa humana do gestor.

> exatamente, essa tela ou módulo serve para triagem de alteração. Então com isso conseguimos fazer uma classificação das alterações que serve para:
>
> Visualizar de maneira mais prática qual a complexidade das alterações, passar prazo para novas etapas de maneira mais concreta.
>
> Hoje para a imagem aparecer aqui como RVW_DONE eu acho muito "chato", tem que clicar em cada imagem dentro do card da Entrega, Temos o processo de lotes que podemos usar para isso, quando for marcado a entrega como Resolvida, então ela vai para esse lugar aqui. Que é onde faremos a análise da entrega.
>
> Vamos supor que temos uma entrega com 20 imagens, foi finalizado os comentários em tal dia então temos que fazer as análises para passar um prazo. Entraremos na tela de pré-alteração, clicaremos na entrega e então faremos a análise. Ao fim, temos que das 20 imagens 10 delas são de nível 1, 5 de nível 2, 3 de nível 4 e 2 de nível 5.
>
> Isso agora irá para a fase de planejamento que verá que temos tantas imagens com os status e passará um novo prazo.
>
> Para a classificação eu quero que seja assim:
> | Nível | Complexidade |
> | ----- | ------------ |
> | 1 | Muito baixa (Ajustes superficiais) |
> | 2 | Baixa (Ajustes acabamento) |
> | 3 | Média (Revisão de composição) |
> | 4 | Alta (Revisão estrutural) |
> | 5 | Muito alta (Alteração de projeto) |
>
> Isso seria apenas para alteração, se houver.
>
> Tenho alguns questionamentos e quero que você me ajude:
> Caso a imagem esteja aprovada, como podemos fazer?
> O status de cada imagem, com RVW_DONE, PRE_ALT E READY_FOR_PLANNING, não quero que seja alterado na tabela da imagem porque já temos muitos status e esses status são mais gerenciais.
> Como podemos ter uma entrega para a obra com a mesma etapa, quando hoje marcamos como RVW_DONE cada imagem em sua respectiva entrega, porém com a mesma etapa (Ex.: R01), ficam dois cards na parte de pré-análise. Portanto e se o cliente esperou as duas imagens estarem prontas para assim finalizar os comentários, temos que fazer a pré-analise das duas entregas juntas.
>
> Analise o que temos em entregas quanto ao lote de entregas por dia e sobre o que comentei acima, para vermos uma solução.

<details><summary>10 previous messages</summary>

> Perfeito, agora o desenho ficou bem mais operacional: Pré-Alteração como triagem por entrega/lote, não como status real da imagem. Vou olhar com calma o que o módulo `Entregas` já tem de lote, resolução e agrupamento por dia para propor um caminho que reaproveite o que existe.
>
> <details><summary>Ran 4 commands</summary>
>
> - Ran `rg -n "lote|batch|resolvid|Resolvida|resolver|review_batch|cobranca|data_conclusao|data_prevista|entrega" Entregas`
> - Ran `rg -n "CREATE TABLE|ALTER TABLE|review_batch|cobranca_review|entregas" Entregas\sql`
> - Ran `Get-ChildItem -Force Entregas`
> - Ran `rg -n "review_batch|cobranca_review|sp_review|trg_review|batch" sql Entregas Backup -g "*.sql"`
>
> </details>
>
> Já apareceu uma peça interessante: existe um sistema de `review_batch`/`review_batch_items` que agrupa imagens por lote de entrega no review, com cobrança e resolução. Agora vou ler os pontos exatos desse mecanismo para entender se dá para transformar esse lote no objeto de pré-análise.
>
> <details><summary>Ran 4 commands</summary>
>
> - Ran `Get-Content -Path Entregas\sql\review_cobranca_install.sql | Select-Object -First 290`
> - Ran `Get-Content -Path Entregas\review_batch_action.php | Select-Object -First 260`
> - Ran `Get-Content -Path Entregas\get_entrega_item.php | Select-Object -First 170`
> - Ran `Get-Content -Path Entregas\review_cobranca_lib.php | Select-Object -First 260`
>
> </details>
>
> O lote atual é criado quando a imagem entra em `RVW`, usando `entregas_itens.data_entregue` para formar o “lote do dia”. Isso já responde boa parte do problema dos dois cards: hoje o lote é por `entrega_id + data`, não por obra/etapa/data. Vou ver como o botão “resolver/aprovar/alteração” fecha esse lote.
>
> <details><summary>Ran 3 commands</summary>
>
> - Ran `Select-String -Path Entregas\review_cobranca_lib.php -Pattern "function entregas_review_fetch_batch|function entregas_review_fetch_batches_for_entrega|function entregas_review_allowed_actions|function entregas_review_sync" -Context 0,80`
> - Ran `Select-String -Path Entregas\script.js -Pattern "review_batch|reviewBatches|resolve|ignore|snooze|customer_response|change_origin|RESOLVED|Aprovada|Alter" -Context 2,3`
> - Ran `Get-Content -Path Entregas\review_batch_action.php | Select-Object -Skip 260 -First 360`
>
> </details>
>
> No fechamento do batch, há uma diferença: para P00 já existe “Aprovada” versus “Alteração”; para entrega normal, o botão “Resolver” só grava motivo/texto, sem classificar se houve alteração nem alimentar Pré-Alteração. É aqui que a ponte provavelmente deve entrar.
>
> <details><summary>Ran 4 commands</summary>
>
> - Ran `Get-Content -Path Entregas\script.js | Select-Object -Skip 678 -First 420`
> - Ran `Get-Content -Path Entregas\save_entrega.php`
> - Ran `Get-Content -Path Entregas\listar_entregas.php | Select-Object -First 260`
> - Ran `Get-Content -Path Entregas\review_cobranca_lib.php | Select-Object -Skip 617 -First 130`
>
> </details>
>
> Também confirmei a estrutura das entregas: uma entrega é por `obra_id + status_id/etapa`, mas os batches de review ficam presos a cada `entrega_id`. Portanto, duas entregas R01 da mesma obra, no mesmo dia, viram dois lotes separados. Para a Pré-Alteração, isso provavelmente precisa virar um agrupamento acima do `entrega_id`.
>
> <details><summary>Ran 3 commands</summary>
>
> - Ran `Get-Content -Path Entregas\registrar_entrega.php | Select-Object -First 300`
> - Ran `Select-String -Path Entregas\script.js -Pattern "registrar_entrega|update_entrega_status|Entregue no prazo|data_entregue|RVW|DRV|substatus" -Context 2,2`
> - Ran `rg -n "data_entregue|status = 'Entregue|substatus_id.*6|substatus_id.*9|nome_substatus.*RVW|DRV" -g "*.php" -g "*.js"`
>
> </details>

</details>

Sim. Olhando o que já existe, minha recomendação é: **não usar mais `RVW_DONE`, `PRE_ALT` e `READY_FOR_PLANNING` como `substatus_id` da imagem**. Eles realmente são estados de gestão da triagem, não estados produtivos da imagem.

O melhor caminho é reaproveitar o que o módulo `Entregas` já tem de mais forte hoje: **`review_batch`**.

Hoje funciona assim:

- Quando uma entrega é registrada, as imagens entregues viram `RVW` ou `DRV` em `imagens_cliente_obra.substatus_id`: [registrar_entrega.php](./Entregas/registrar_entrega.php:230)
- O sistema já cria/usa um lote de review por `entrega_id + data_entrega_lote`: [review_cobranca_install.sql](./Entregas/sql/review_cobranca_install.sql:4)
- Esse lote já tem ações de cobrança, pausa, resolver e ignorar: [script.js](./Entregas/script.js:658)
- Ao “resolver batch”, hoje entrega normal só pede um motivo; P00 já diferencia `Aprovada` e `Alteração`: [script.js](./Entregas/script.js:869)

Então a solução elegante é:

**1. Resolver o lote vira a entrada da Pré-Alteração**
Na tela de Entregas, no botão **Resolver** do lote de review, trocar o modal atual por algo como:

- Cliente aprovou, sem alteração
- Cliente pediu alteração
- Ignorar / fora de escopo

Se escolher **Cliente pediu alteração**, o sistema cria um lote de pré-alteração automaticamente. Sem clicar imagem por imagem. Isso substitui o fluxo chato de marcar `RVW_DONE`.

**2. A Pré-Alteração deve trabalhar com lote, não com substatus da imagem**
Criaria algo nessa linha:

- `pre_alt_lote`
  - `obra_id`
  - `status_id` / etapa, ex. R01
  - `data_finalizacao_cliente`
  - `status`: `EM_TRIAGEM`, `AGUARDANDO_CLIENTE`, `PRONTO_PLANEJAMENTO`, `PLANEJADO`, `SEM_ALTERACAO`
- `pre_alt_lote_batches`
  - liga um lote de pré-alteração a um ou mais `review_batch`
- `pre_alt_itens`
  - `imagem_id`
  - `entrega_item_id`
  - `review_batch_item_id`
  - `resultado`: `ALTERACAO`, `SEM_ALTERACAO`, `AGUARDANDO_CLIENTE`
  - `nivel_complexidade`: 1 a 5
  - `acao`
  - `necessita_retorno`

Assim a imagem pode continuar com seu status real, e a triagem vive num módulo próprio.

**3. Classificação nova**
Eu substituiria `S/M/C/TA` por:

| Nível | Uso                               |
| ----- | --------------------------------- |
| 1     | Muito baixa, ajustes superficiais |
| 2     | Baixa, ajustes de acabamento      |
| 3     | Média, revisão de composição      |
| 4     | Alta, revisão estrutural          |
| 5     | Muito alta, alteração de projeto  |

E deixaria “troca de ângulo” como **tipo/tag de alteração**, não como complexidade. Uma troca de ângulo pode ser nível 2, 3 ou 4 dependendo do impacto.

**Sobre imagem aprovada**
Se a imagem estiver aprovada ou não tiver alteração, ela entra no lote apenas como **Sem alteração / aprovada**. Não precisa nível. Ela conta no resumo, mas não entra no cálculo de prazo de alteração.

Exemplo da entrega de 20 imagens:

- 5 sem alteração
- 10 nível 1
- 3 nível 2
- 2 nível 5

O planejamento vê só as 15 com alteração, mas sabe que o lote tinha 20 imagens analisadas.

**Sobre duas entregas da mesma obra e mesma etapa**
Hoje dá dois cards porque `review_batch` está preso a `entrega_id`. Para Pré-Alteração, eu não usaria `entrega_id` como agrupador principal.

Eu agruparia por:

`obra_id + status_id/etapa + data_finalizacao_cliente`

Ou seja: se duas entregas R01 da mesma obra foram resolvidas pelo cliente no mesmo dia, elas aparecem como **um lote de pré-alteração**, contendo dois `review_batch` por baixo.

Isso resolve exatamente o caso: cliente esperou as duas imagens/entregas, finalizou os comentários, e a análise deve ser conjunta.

Minha visão final: **Entregas continua cuidando de entrega e cobrança de retorno do cliente. Pré-Alteração passa a cuidar da triagem do retorno quando houve alteração.** Fica mais limpo, evita poluir `substatus_imagem`, e transforma o lote em algo útil para prazo e planejamento.

> Isso ai, pode dar continuidade e fazer o plano por completo!

Fechado. O plano completo eu montaria assim:

**Direção Geral**
A Pré-Alteração deixa de depender de `substatus_id` da imagem e passa a ser um **fluxo próprio de triagem**, alimentado quando um lote de review é resolvido com “cliente pediu alteração”.

A imagem continua com status/substatus operacional normal (`RVW`, `DRV`, `FIN`, etc.). A triagem passa a morar em tabelas próprias.

**Novo Fluxo**

1. Entrega é criada normalmente.
2. Imagens são entregues e entram em review.
3. O sistema já cria `review_batch` por lote de review.
4. Cliente finaliza comentários.
5. Na tela de Entregas, no lote de review, usuário clica em **Resolver**.
6. O modal pergunta:
   - `Aprovada / sem alteração`
   - `Cliente pediu alteração`
   - `Ignorar`
7. Se for aprovada, o batch só é resolvido.
8. Se tiver alteração, o sistema cria/anexa esse batch a um **lote de Pré-Alteração**.
9. A tela `PreAlteracao` mostra esse lote para triagem.
10. Após classificar todas as imagens, o lote vira **Pronto para planejamento**.
11. O planejamento usa o resumo por nível para passar prazo.

**Estrutura De Dados**
Criaria três tabelas novas:

`pre_alt_lote`

Guarda o agrupamento principal da triagem.

Campos principais:

- `id`
- `obra_id`
- `status_id` etapa, ex. R01
- `data_finalizacao_cliente`
- `status`: `EM_TRIAGEM`, `AGUARDANDO_CLIENTE`, `PRONTO_PLANEJAMENTO`, `PLANEJADO`, `CANCELADO`
- `created_by`
- `created_at`
- `updated_at`

`pre_alt_lote_batches`

Liga um lote de pré-alteração a um ou mais `review_batch`.

Campos:

- `id`
- `pre_alt_lote_id`
- `review_batch_id`

Isso resolve o caso de duas entregas R01 da mesma obra no mesmo dia: os dois `review_batch` entram no mesmo `pre_alt_lote`.

`pre_alt_itens`

Guarda a análise por imagem.

Campos:

- `id`
- `pre_alt_lote_id`
- `review_batch_item_id`
- `entrega_id`
- `entrega_item_id`
- `imagem_id`
- `resultado`: `ALTERACAO`, `SEM_ALTERACAO`, `AGUARDANDO_CLIENTE`
- `nivel_complexidade`: 1 a 5, nulo quando sem alteração
- `tipo_alteracao`: opcional, ex. acabamento, composição, projeto, troca de ângulo
- `acao`
- `necessita_retorno`
- `responsavel_id`
- `created_at`
- `updated_at`

**Classificação**
Trocar `S/M/C/TA` por:

| Nível | Complexidade                      |
| ----- | --------------------------------- |
| 1     | Muito baixa, ajustes superficiais |
| 2     | Baixa, ajustes de acabamento      |
| 3     | Média, revisão de composição      |
| 4     | Alta, revisão estrutural          |
| 5     | Muito alta, alteração de projeto  |

“Troca de ângulo” vira tipo/tag, não nível.

**Regra De Agrupamento**
Quando resolver um `review_batch` como alteração:

Procurar lote aberto com:

`obra_id + status_id + data_finalizacao_cliente`

Se existir, anexar o batch nesse lote.
Se não existir, criar novo lote.

Eu não usaria `entrega_id` como chave principal da Pré-Alteração, porque isso mantém o problema atual dos cards separados.

**Como Tratar Imagem Aprovada**
Dentro do lote, cada imagem pode ser marcada como:

- `SEM_ALTERACAO`: aprovada, não entra no cálculo de prazo
- `ALTERACAO`: precisa nível 1 a 5
- `AGUARDANDO_CLIENTE`: trava o lote em `AGUARDANDO_CLIENTE`

Assim uma entrega com 20 imagens pode fechar com:

- 5 sem alteração
- 10 nível 1
- 5 nível 2

O planejamento enxerga só as 15 alteradas, mas com rastreio das 20 analisadas.

**Tela Pré-Alteração**
Eu remodelaria para três colunas:

`Em Triagem`
Lotes recém-criados, aguardando análise.

`Aguardando Cliente`
Lotes com alguma imagem marcada como `necessita_retorno`.

`Pronto Para Planejamento`
Todos os itens resolvidos, com alteração classificada ou sem alteração.

Dentro do modal do lote:

- cabeçalho com obra, etapa, data de finalização do cliente
- lista de imagens agrupadas por entrega original
- botão para abrir `Review Studio`
- seleção de resultado: alteração / sem alteração / aguardando cliente
- nível 1 a 5 quando for alteração
- campo de ação/observação
- resumo lateral:
  - total imagens
  - sem alteração
  - nível 1
  - nível 2
  - nível 3
  - nível 4
  - nível 5
  - aguardando cliente

**Planejamento**
Quando tudo estiver classificado e sem pendência de cliente:

- status do lote vira `PRONTO_PLANEJAMENTO`
- sidebar pode mostrar badge para gestor
- tela mostra um resumo tipo:

`R01 - Obra X`
`20 imagens analisadas`
`15 com alteração`
`N1: 10 | N2: 5 | N3: 0 | N4: 3 | N5: 2`

Daí o gestor consegue passar prazo com base no esforço real.

**Fases De Implementação**

1. Criar migrations das novas tabelas.
2. Alterar `Entregas/review_batch_action.php` para permitir resolver batch normal como:
   - aprovado
   - alteração
   - ignorado
3. Criar função backend para `ensure_pre_alt_lote_from_review_batch`.
4. Reescrever consultas da `PreAlteracao` para usar `pre_alt_lote`, não `substatus_id`.
5. Atualizar modal da Pré-Alteração com níveis 1 a 5.
6. Atualizar salvamento para `pre_alt_itens`.
7. Criar resumo de planejamento por lote.
8. Ajustar badges da sidebar para contar:
   - pré-alteração em triagem
   - pronto para planejamento
9. Remover dependência visual de `RVW_DONE`, `PRE_ALT`, `READY_FOR_PLANNING` como substatus da imagem.
10. Migrar dados antigos, se necessário, de `pre_alt_analise` para o novo formato.

**Minha Recomendação De MVP**
Eu faria primeiro o MVP assim:

- manter `review_batch` como origem
- criar `pre_alt_lote` e `pre_alt_itens`
- alimentar Pré-Alteração ao resolver batch como “Alteração”
- tela já classifica nível 1 a 5
- tela já gera resumo para planejamento

Depois, numa segunda passada, refinamos prazo automático, tipos de alteração e integração direta com criação de tarefas em `Alteracao`.

Esse desenho encaixa bem no que vocês já têm e tira o peso errado da tabela de imagem.

> Perfeito! Pode fazer o plano completo como você montou

<details><summary>109 previous messages</summary>

> Vou transformar esse desenho em implementação agora. Como é uma mudança de fluxo, vou fazer em camadas: primeiro a estrutura nova e helpers, depois a ponte em `Entregas`, depois a tela `PreAlteracao` usando lote/itens em vez de substatus da imagem.
>
> Vou começar pelos novos alicerces: tabelas e helper PHP. A ideia é que qualquer endpoint consiga garantir a estrutura nova e trabalhar com `pre_alt_lote`/`pre_alt_itens`, sem depender de mexer no `substatus_id` da imagem.
>
> <details><summary>Created 1 file</summary>
>
> - Wrote `./PreAlteracao/pre_alt_helpers.php` (+249 -0)
>
> </details>
>
> Helper criado. Agora vou deixar uma migration explícita junto do módulo, para a estrutura ficar documentada e aplicável em ambientes onde vocês preferirem rodar SQL manualmente.
>
> <details><summary>Created 1 file</summary>
>
> - Wrote `./PreAlteracao/migration_lotes.sql` (+82 -0)
>
> </details>
>
> Agora vou ligar o fechamento do lote de review com a criação do lote de Pré-Alteração. Para entrega normal, o “Resolver batch” vai diferenciar aprovado versus alteração; se for alteração, nasce o lote de triagem automaticamente.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./Entregas/review_batch_action.php` (+41 -19)
>
> </details>
>
> O patch pegou diferença de encoding no texto existente. Vou localizar o trecho exato e aplicar em pedaços menores, assim não encosto em nada além do necessário.
>
> <details><summary>Edited 1 file, ran 3 commands</summary>
>
> - Ran `Get-Content -Path Entregas\review_batch_action.php | Select-Object -Skip 292 -First 110`
> - Ran `Get-Content -Path Entregas\review_batch_action.php | Select-Object -First 12`
> - Ran `Get-Content -Path Entregas\review_batch_action.php | Select-Object -Skip 178 -First 25`
> - Wrote `./Entregas/review_batch_action.php` (+8 -6)
>
> </details>
>
> Integração base carregada no backend. Agora vou ajustar o bloco de resolução para criar o lote quando a resposta for “alteração solicitada”.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./Entregas/review_batch_action.php` (+31 -11)
>
> </details>
>
> A falha está só no encaixe do trecho textual com acentuação corrompida. Vou inserir as mudanças por âncoras mais simples.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./Entregas/review_batch_action.php` (+6 -5)
>
> </details>
>
> Agora vou inserir a ramificação para entrega normal logo antes do `UPDATE` que resolve o batch.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./Entregas/review_batch_action.php` (+18 -3)
>
> </details>
>
> Backend agora reconhece “alteração solicitada”. Falta disparar a criação efetiva do lote depois que o batch é resolvido.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./Entregas/review_batch_action.php` (+9 -5)
>
> </details>
>
> Vou validar visualmente o bloco alterado para garantir que o fluxo P00 continuou separado do fluxo normal.
>
> <details><summary>Ran 1 command</summary>
>
> - Ran `Get-Content -Path Entregas\review_batch_action.php | Select-Object -Skip 304 -First 130`
>
> </details>
>
> A leitura mostrou que a ramificação normal ainda não entrou, só o disparo final. Vou corrigir agora: o `else` precisa ficar logo depois do bloco P00.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./Entregas/review_batch_action.php` (+19 -4)
>
> </details>
>
> A frase com acento ainda não casa. Vou pegar o trecho com números de linha e aplicar por contexto estrutural menor.
>
> <details><summary>Edited 1 file, ran 2 commands</summary>
>
> - Ran `$i=1; Get-Content -Path Entregas\review_batch_action.php | ForEach-Object { if($i -ge 370 -and $i -le 390){ '{0,4}: {1}' -f $i, $_ }; $i++ }`
> - Wrote `./Entregas/review_batch_action.php` (+15 -0)
> - Ran `$i=1; Get-Content -Path Entregas\review_batch_action.php | ForEach-Object { if($i -ge 382 -and $i -le 420){ '{0,4}: {1}' -f $i, $_ }; $i++ }`
>
> </details>
>
> Hmm, a inserção caiu em outro `UPDATE cobranca_review` anterior. Vou remover o trecho deslocado se necessário e aplicar no ponto certo usando número de linha com um patch mais específico.
>
> <details><summary>Ran 2 commands</summary>
>
> - Ran `Select-String -Path Entregas\review_batch_action.php -Pattern "Cliente aprovou o lote|Pre-Alteracao|Alteracao solicitada|Resposta do cliente invalida" -Context 3,3`
> - Ran `$i=1; Get-Content -Path Entregas\review_batch_action.php | ForEach-Object { if($i -ge 250 -and $i -le 322){ '{0,4}: {1}' -f $i, $_ }; $i++ }`
>
> </details>
>
> Achei um trecho deslocado dentro do `snooze`. Vou limpar isso antes de continuar, para não deixar o backend num estado inválido.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./Entregas/review_batch_action.php` (+3 -33)
>
> </details>
>
> Com o `snooze` limpo, vou aplicar a ramificação no bloco certo usando uma âncora que só existe no fluxo P00.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./Entregas/review_batch_action.php` (+25 -10)
>
> </details>
>
> Para evitar briga com encoding, vou estruturar de forma mais simples: calculo antes se o batch é P00; se não for, trato aprovado/alteração antes do bloco P00 existente. Isso preserva o fluxo P00 sem mexer no miolo dele.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./Entregas/review_batch_action.php` (+20 -2)
>
> </details>
>
> Backend pronto para receber `customer_response`. Vou atualizar o modal de resolução da entrega normal para perguntar “aprovada” ou “alteração” em vez de só um motivo livre.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./Entregas/script.js` (+31 -27)
>
> </details>
>
> O JS também tem texto com encoding antigo. Vou pegar o bloco exato e substituir com contexto menor.
>
> <details><summary>Ran 1 command</summary>
>
> - Ran `$i=1; Get-Content -Path Entregas\script.js | ForEach-Object { if($i -ge 972 -and $i -le 1006){ '{0,4}: {1}' -f $i, $_ }; $i++ }`
>
> </details>
>
> Vou substituir esse bloco específico do modal normal.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./Entregas/script.js` (+29 -25)
>
> </details>
>
> O patch textual não está conseguindo casar por causa da codificação do arquivo grande. Vou fazer uma substituição mecânica e bem delimitada pelo trecho do `swal-review-resolve-reason`, sem mexer no restante do arquivo.
>
> <details><summary>Ran 1 command</summary>
>
> - Ran ``$path = 'Entregas\script.js'
$text = Get-Content -Path $path -Raw
$pattern = '(?s)      const result = await Swal\.fire\(\{\r?\n        title: "Resolver batch",\r?\n        html: `\r?\n\s*<input id="swal-review-resolve-reason".*?\r?\n\s*<textarea id="swal-review-resolve-note".*?\r?\n\s*`,\r?\n showCancelButton: true,\r?\n confirmButtonText: "Resolver",\r?\n cancelButtonText: "Cancelar",\r?\n focusConfirm: false,\r?\n preConfirm: \(\) => \{\r?\n\s*const reason =\r?\n\s*document\.getElementById\("swal-review-resolve-reason"\)\?\.value \|\| "";\r?\n\s*const note =\r?\n\s*document\.getElementById\("swal-review-resolve-note"\)\?\.value \|\| "";\r?\n\s*\r?\n\s*if \(!reason\.trim\(\)\) \{\r?\n\s*Swal\.showValidationMessage\(".*?"\);\r?\n\s*return false;\r?\n\s*\}\r?\n\s*\r?\n\s*return \{\r?\n\s*action,\r?\n\s*reason: reason\.trim\(\),\r?\n\s*note: note\.trim\(\),\r?\n\s*\};\r?\n\s*\},\r?\n \}\);'
>   $replacement = @'
>   const result = await Swal.fire({
>   title: "Resolver batch",
>   html: `           <select id="swal-review-response" class="swal2-input">
            <option value="">Selecione a resposta do cliente</option>
            <option value="approved">Aprovada / sem alteracao</option>
            <option value="change_requested">Cliente pediu alteracao</option>
          </select>
          <textarea id="swal-review-resolve-note" class="swal2-textarea" placeholder="Observacao opcional"></textarea>
        `,
>   showCancelButton: true,
>   confirmButtonText: "Resolver",
>   cancelButtonText: "Cancelar",
>   focusConfirm: false,
>   preConfirm: () => {
>   const customerResponse =
>   document.getElementById("swal-review-response")?.value || "";
>   const note =
>   document.getElementById("swal-review-resolve-note")?.value || "";
>
>             if (!customerResponse) {
>               Swal.showValidationMessage("Selecione a resposta do cliente.");
>               return false;
>             }
>
>             return {
>               action,
>               customer_response: customerResponse,
>               note: note.trim(),
>             };
>           },
>         });
>
>   '@
>   $new = [regex]::Replace($text, $pattern, $replacement, 1)
> if ($new -eq $text) { throw 'Trecho do modal Resolver batch nao encontrado para substituicao.' }
>   Set-Content -Path $path -Value $new -NoNewline -Encoding UTF8``
>
> </details>
>
> Ponte com Entregas montada. Agora vou trocar os endpoints da Pré-Alteração para consultarem lotes/itens próprios em vez dos substatus `10/11/12` da imagem.
>
> <details><summary>Created 1 file, deleted 1 file</summary>
>
> - Deleted `./PreAlteracao/get_pre_alt_entregas.php` (+0 -66)
> - Wrote `./PreAlteracao/get_pre_alt_entregas.php` (+96 -0)
>
> </details>
>
> Lista de lotes pronta. Agora vou criar o endpoint de detalhe do lote, com as imagens e a análise salva de cada uma.
>
> <details><summary>Created 1 file</summary>
>
> - Wrote `./PreAlteracao/get_pre_alt_lote.php` (+120 -0)
>
> </details>
>
> Detalhe pronto. Vou refatorar o salvamento para gravar `resultado`, `nível 1-5`, tipo, observação e retorno do cliente no item da triagem.
>
> <details><summary>Created 1 file, deleted 1 file</summary>
>
> - Deleted `./PreAlteracao/save_pre_analise.php` (+0 -190)
> - Wrote `./PreAlteracao/save_pre_analise.php` (+111 -0)
>
> </details>
>
> Endpoints principais migrados. Agora vou ajustar a estrutura HTML da tela para três filas operacionais: Em Triagem, Aguardando Cliente e Pronto para Planejamento.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./PreAlteracao/index.php` (+36 -28)
>
> </details>
>
> O `index.php` tem o mesmo encoding antigo nos comentários/textos. Vou trocar a seção central com uma substituição delimitada, mantendo cabeçalho, sidebar e modal intactos.
>
> <details><summary>Ran 1 command</summary>
>
> - Ran `$i=1; Get-Content -Path PreAlteracao\index.php | ForEach-Object { if($i -ge 76 -and $i -le 108){ '{0,4}: {1}' -f $i, $_ }; $i++ }`
>
> </details>
>
> Vou aplicar o bloco novo inteiro nesse intervalo.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./PreAlteracao/index.php` (+36 -28)
>
> </details>
>
> Vou usar substituição mecânica nessa seção também; o trecho é pequeno e delimitado entre `<!-- Two-column layout -->` e o fechamento do layout.
>
> <details><summary>Ran 1 command</summary>
>
> - Ran `$path = 'PreAlteracao\index.php'
> $text = Get-Content -Path $path -Raw
> $pattern = '(?s) <!-- Two-column layout -->\r?\n <div class="columns-layout" id="columnsLayout" style="display:none">.\*? </div>\r?\n </div>'
>   $replacement = @'
>   <!-- Triagem layout -->
>   <div class="columns-layout" id="columnsLayout" style="display:none">
>   <div class="col-panel col-pre-analise">
>   <div class="col-panel-header">
>   <div class="col-panel-title">
>   <i class="fa-solid fa-magnifying-glass"></i>
>   <span>Em Triagem</span>
>   </div>
>   <span class="col-panel-count" id="countTriagem">0</span>
>   </div>
>   <div class="col-panel-body" id="colTriagem"></div>
>   </div>
>
>               <div class="col-panel col-aguardando">
>                   <div class="col-panel-header">
>                       <div class="col-panel-title">
>                           <i class="fa-solid fa-clock-rotate-left"></i>
>                           <span>Aguardando Cliente</span>
>                       </div>
>                       <span class="col-panel-count" id="countAguardando">0</span>
>                   </div>
>                   <div class="col-panel-body" id="colAguardando"></div>
>               </div>
>
>               <div class="col-panel col-planejamento">
>                   <div class="col-panel-header">
>                       <div class="col-panel-title">
>                           <i class="fa-solid fa-calendar-check"></i>
>                           <span>Para Planejamento</span>
>                       </div>
>                       <span class="col-panel-count col-panel-count--green" id="countPlanejamento">0</span>
>                   </div>
>                   <div class="col-panel-body" id="colPlanejamento"></div>
>               </div>
>           </div>
>       </div>
>
>   '@
>   $new = [regex]::Replace($text, $pattern, $replacement, 1)
> if ($new -eq $text) { throw 'Secao columns-layout nao encontrada.' }
>   Set-Content -Path $path -Value $new -NoNewline -Encoding UTF8`
>
> </details>
>
> Agora vem a parte maior: refazer o JS da Pré-Alteração para trabalhar com lotes, três colunas e níveis 1-5. Vou substituir o script do módulo, mantendo o mesmo padrão de fetch/toast/modal que já existia.
>
> <details><summary>Deleted 1 file</summary>
>
> - Deleted `./PreAlteracao/script.js` (+0 -435)
>
> </details>
>
> Arquivo antigo removido; vou adicionar a versão nova.
>
> <details><summary>Created 1 file</summary>
>
> - Wrote `./PreAlteracao/script.js` (+441 -0)
>
> </details>
>
> A UI já está falando com os novos endpoints. Vou acrescentar os estilos que faltam para três colunas, resumo por nível, select/input e badges compactos.
>
> <details><summary>Edited 1 file, ran 2 commands</summary>
>
> - Wrote `./PreAlteracao/style.css` (+112 -2)
> - Ran `Select-String -Path PreAlteracao\style.css -Pattern "columns-layout|complexidade-btn.active" -Context 1,2`
> - Ran `Get-Content -Path PreAlteracao\style.css | Select-Object -Skip 304 -First 40`
>
> </details>
>
> O CSS antigo ainda forçava duas colunas mais abaixo. Vou ajustar essa regra principal para três colunas e deixar o responsivo no final prevalecer.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./PreAlteracao/style.css` (+16 -12)
>
> </details>
>
> Vou colocar o responsivo no final do CSS para ele ganhar da regra desktop.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./PreAlteracao/style.css` (+14 -8)
>
> </details>
>
> Vou olhar o final do CSS para anexar no lugar certo.
>
> <details><summary>Ran 1 command</summary>
>
> - Ran `Get-Content -Path PreAlteracao\style.css | Select-Object -Last 60`
>
> </details>
>
> Vou anexar no fim então.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./PreAlteracao/style.css` (+9 -3)
>
> </details>
>
> Agora vou ajustar o badge da sidebar para contar os novos lotes. Mantendo fallback antigo se as tabelas ainda não existirem, para não quebrar ambiente sem migration.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./sidebar_counts.php` (+46 -16)
>
> </details>
>
> Mesmo caso de encoding nos comentários da sidebar. Vou substituir o bloco por regex delimitado entre `$pre_alt_analise_count` e `$modules`.
>
> <details><summary>Ran 1 command</summary>
>
> - Ran `$path = 'sidebar_counts.php'
> $text = Get-Content -Path $path -Raw
> $pattern = '(?s)\$pre_alt_analise_count = 0;._?\r?\n\$modules = \['
>   $replacement = @'
> $pre_alt_analise_count = 0;
>   $preAltTablesReady = false;
> $resPreAltTables = $conn->query(
>   "SELECT COUNT(_) AS cnt
>   FROM information_schema.tables
>   WHERE table_schema = DATABASE()
>   AND table_name IN ('pre_alt_lote', 'pre_alt_itens')"
>   );
>   if ($resPreAltTables) {
>     $preAltTablesReady = intval($resPreAltTables->fetch_assoc()['cnt'] ?? 0) === 2;
>   }
>
> if ($preAltTablesReady) {
>     if ($userId == 1) {
> $res_pa = $conn->query(
>             "SELECT COUNT(*) AS cnt
>              FROM pre_alt_lote
>              WHERE status IN ('EM_TRIAGEM', 'AGUARDANDO_CLIENTE')"
>         );
>         $pre_alt_analise_count = ($res_pa) ? intval($res_pa->fetch_assoc()['cnt']) : 0;
> }
>
>     if ($userId == 21) {
>         $res_pa = $conn->query(
>             "SELECT COUNT(*) AS cnt
>              FROM pre_alt_lote
>              WHERE status = 'PRONTO_PLANEJAMENTO'"
>         );
>         $pre_alt_analise_count = ($res_pa) ? intval($res_pa->fetch_assoc()['cnt']) : 0;
>     }
>
> } else {
> if ($userId == 1) {
>         $res_pa = $conn->query(
>             "SELECT COUNT(DISTINCT obra_id) AS cnt FROM imagens_cliente_obra WHERE substatus_id = 10"
>         );
>         $pre_alt_analise_count = ($res_pa) ? intval($res_pa->fetch_assoc()['cnt']) : 0;
> }
>
>     if ($userId == 21) {
>         $res_pa = $conn->query(
>             "SELECT COUNT(DISTINCT obra_id) AS cnt FROM imagens_cliente_obra WHERE substatus_id = 12"
>         );
>         $pre_alt_analise_count = ($res_pa) ? intval($res_pa->fetch_assoc()['cnt']) : 0;
>     }
>
> }
>
> $modules = [
> '@
> $new = [regex]::Replace($text, $pattern, $replacement, 1)
> if ($new -eq $text) { throw 'Bloco pre_alt_analise_count nao encontrado.' }
> Set-Content -Path $path -Value $new -NoNewline -Encoding UTF8`
>
> </details>
>
> A primeira versão da tela nova está montada. Vou rodar checagens de sintaxe nos PHP alterados/criados e uma checagem básica no JavaScript para pegar erro de parsing antes de seguir.
>
> <details><summary>Ran 6 commands</summary>
>
> - Ran `php -l PreAlteracao\pre_alt_helpers.php`
> - Ran `php -l PreAlteracao\get_pre_alt_entregas.php`
> - Ran `php -l PreAlteracao\save_pre_analise.php`
> - Ran `php -l PreAlteracao\get_pre_alt_lote.php`
> - Ran `php -l Entregas\review_batch_action.php`
> - Ran `node --check PreAlteracao\script.js`
>
> </details>
>
> Sintaxe dos principais passou. Vou checar os arquivos que foram alterados por substituição mecânica também, especialmente `sidebar_counts.php`, `index.php` e o JS grande de Entregas.
>
> <details><summary>Ran 5 commands</summary>
>
> - Ran `php -l PreAlteracao\index.php`
> - Ran `Select-String -Path Entregas\script.js -Pattern "swal-review-response|customer_response|Cliente pediu" -Context 2,2`
> - Ran `php -l sidebar_counts.php`
> - Ran `Select-String -Path Entregas\review_batch_action.php -Pattern "shouldCreatePreAlt|isP00Batch|pre_alt_criar|Cliente aprovou|Alteracao solicitada" -Context 2,2`
> - Ran `node --check Entregas\script.js`
>
> </details>
>
> Uma regra de status merece ajuste: se qualquer item estiver aguardando cliente, o lote deve cair na coluna “Aguardando Cliente”, mesmo que ainda existam outros itens incompletos. Vou corrigir essa precedência.
>
> <details><summary>Edited 1 file</summary>
>
> - Wrote `./PreAlteracao/pre_alt_helpers.php` (+3 -1)
>
> </details>
>
> Vou fazer uma rodada de lint depois desse ajuste e verificar também o `git diff` em alto nível para garantir que os arquivos esperados foram alterados.
>
> <details><summary>Ran 6 commands</summary>
>
> - Ran `php -l PreAlteracao\pre_alt_helpers.php`
> - Ran `git diff --stat`
> - Ran `php -l Entregas\review_batch_action.php`
> - Ran `node --check Entregas\script.js`
> - Ran `node --check PreAlteracao\script.js`
> - Ran `git status --short`
>
> </details>
>
> As checagens passaram. O diff mostra bastante alteração em `Entregas/script.js`, provavelmente por normalização de encoding/linhas na substituição mecânica; vou inspecionar para garantir que a mudança funcional é só o modal de resolver batch.
>
> <details><summary>Ran 1 command</summary>
>
> - Ran `git diff -- Entregas/script.js | Select-Object -First 220`
>
> </details>
>
> Boa captura: a substituição mecânica regravou `Entregas/script.js` com mojibake nos acentos. Vou reparar a codificação convertendo o texto “CP1252 interpretado errado” de volta para UTF-8, preservando a mudança funcional do modal.
>
> <details><summary>Ran 1 command</summary>
>
> - Ran `$path = 'Entregas\script.js'
$text = Get-Content -Path $path -Raw -Encoding UTF8
$cp1252 = [System.Text.Encoding]::GetEncoding(1252)
$utf8 = [System.Text.Encoding]::UTF8
$bytes = $cp1252.GetBytes($text)
$fixed = $utf8.GetString($bytes)
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText((Resolve-Path $path), $fixed, $utf8NoBom)`
>
> </details>
>
> Vou conferir o diff de novo para garantir que a reparação voltou os textos e deixou só a alteração esperada no modal.
>
> <details><summary>Ran 1 command</summary>
>
> - Ran `git diff -- Entregas/script.js | Select-Object -First 140`
>
> </details>
>
> `Entregas/script.js` está limpo agora. Vou conferir os outros arquivos que passaram por substituição mecânica para evitar o mesmo problema.
>
> <details><summary>Ran 4 commands</summary>
>
> - Ran `git diff -- PreAlteracao\index.php | Select-Object -First 160`
> - Ran `php -l sidebar_counts.php`
> - Ran `git diff -- sidebar_counts.php | Select-Object -First 180`
> - Ran `node --check Entregas\script.js`
>
> </details>
>
> `index.php` e `sidebar_counts.php` tiveram o mesmo mojibake. Vou aplicar a mesma reparação de codificação neles; depois o diff deve voltar a mostrar só as mudanças reais.
>
> <details><summary>Ran 1 command</summary>
>
> - Ran `foreach ($path in @('PreAlteracao\index.php', 'sidebar_counts.php')) {
  $text = Get-Content -Path $path -Raw -Encoding UTF8
  $cp1252 = [System.Text.Encoding]::GetEncoding(1252)
  $utf8 = [System.Text.Encoding]::UTF8
  $bytes = $cp1252.GetBytes($text)
  $fixed = $utf8.GetString($bytes)
  $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
  [System.IO.File]::WriteAllText((Resolve-Path $path), $fixed, $utf8NoBom)
}`
>
> </details>
>
> Vou conferir novamente para garantir que os SQLs com “Em aprovação” não ficaram alterados por encoding, porque isso seria um bug chato.
>
> <details><summary>Ran 4 commands</summary>
>
> - Ran `php -l sidebar_counts.php`
> - Ran `php -l PreAlteracao\index.php`
> - Ran `git diff -- PreAlteracao\index.php | Select-Object -First 140`
> - Ran `git diff -- sidebar_counts.php | Select-Object -First 160`
>
> </details>
>
> Encoding está limpo agora. Vou fazer uma checagem final de sintaxe em todos os PHP tocados e revisar o diff resumido, depois fecho com o que foi entregue e pontos de atenção.
>
> <details><summary>Ran 6 commands</summary>
>
> - Ran `php -l PreAlteracao\save_pre_analise.php`
> - Ran `php -l Entregas\review_batch_action.php`
> - Ran `php -l PreAlteracao\get_pre_alt_entregas.php`
> - Ran `node --check PreAlteracao\script.js`
> - Ran `php -l PreAlteracao\get_pre_alt_lote.php`
> - Ran `php -l PreAlteracao\pre_alt_helpers.php`
>
> </details>
>
> Context compaction
>
> Source: automatic
> Status: completed
>
> Vou fazer uma última passada de sanidade no diff e nos arquivos tocados para garantir que o plano ficou implementado de ponta a ponta, sem respingar em coisa fora do módulo. Depois te devolvo um resumo bem prático do que entrou e como usar.
>
> <details><summary>Ran 4 commands</summary>
>
> - Ran `git status --short`
> - Ran `git diff -- Entregas/review_batch_action.php`
> - Ran `git diff -- PreAlteracao/script.js`
> - Ran `git diff --stat`
>
> </details>
>
> A implementação já está montada; estou só conferindo duas coisas agora: se os novos pontos PHP/JS continuam válidos e se algum arquivo não relacionado apareceu no meio do caminho. Vi um `Revisao/` não versionado que já estava fora do escopo, então vou deixá-lo quietinho.
>
> <details><summary>Ran 5 commands</summary>
>
> - Ran `php -l PreAlteracao\get_pre_alt_entregas.php`
> - Ran `rg -n "PreAlteracao|Entregas|review_batch|lote" C:\Users\pedro\.codex\memories\MEMORY.md`
> - Ran `php -l PreAlteracao\pre_alt_helpers.php`
> - Ran `php -l PreAlteracao\get_pre_alt_lote.php`
> - Ran `php -l PreAlteracao\save_pre_analise.php`
>
> </details>
>
> A checagem de memória não achou contexto específico antigo para `PreAlteracao`, então estou seguindo pelo estado atual do repo. Os novos endpoints PHP passaram no `php -l`; vou fechar com os arquivos de integração e JS.
>
> <details><summary>Ran 5 commands</summary>
>
> - Ran `php -l Entregas\review_batch_action.php`
> - Ran `php -l sidebar_counts.php`
> - Ran `node --check Entregas\script.js`
> - Ran `node --check PreAlteracao\script.js`
> - Ran `php -l PreAlteracao\index.php`
>
> </details>
>
> Tudo validou sintaticamente. Vou rodar uma checagem final de whitespace/diff e então te passo o fechamento com o fluxo operacional que ficou implementado.
>
> <details><summary>Ran 3 commands</summary>
>
> - Ran `git diff --check`
> - Ran `git status --short`
> - Ran `Get-ChildItem PreAlteracao | Select-Object Name,Length,LastWriteTime`
>
> </details>
>
> Como mexemos bastante na experiência da tela, vou fazer uma verificação visual rápida no navegador interno em `PreAlteracao` também. Se o Apache/MySQL local não estiver de pé, eu te aviso claramente e fico só com as validações estáticas que já passaram.
>
> <details><summary>Ran 1 command</summary>
>
> - Ran `Get-Content C:\Users\pedro\.codex\plugins\cache\openai-bundled\browser\26.608.12217\skills\control-in-app-browser\SKILL.md`
>
> </details>
>
> Vou usar o navegador interno só para checar se a tela carrega e se a estrutura principal aparece. É uma validação visual de interface, sem salvar nada nem mexer em dados.
>
> MCP tool call
>
> node_repl.js
>
> ```json
> {
>   "code": "const { setupBrowserRuntime } = await import('file:///C:/Users/pedro/.codex/plugins/cache/openai-bundled/browser/26.608.12217/scripts/browser-client.mjs');\nawait setupBrowserRuntime({ globals: globalThis });\nglobalThis.browser = await agent.browsers.get('iab');\nnodeRepl.write(await browser.documentation());",
>   "timeout_ms": 30000,
>   "title": "Conectar navegador interno"
> }
> ```
>
> ```text
> node_repl kernel exited unexpectedly
>
> node_repl diagnostics: {"kernel_pid":2856,"kernel_status":"running","kernel_stderr_tail":"windows sandbox failed: runner error: CreateProcessAsUserW failed: 5","reason":"stdout_eof","stream_error":null}
> ```
>
> A tentativa de abrir o navegador interno bateu na limitação do sandbox do Windows (`CreateProcessAsUserW failed: 5`), aquela bem típica deste ambiente. Então a validação ficou em `php -l`, `node --check` e `git diff --check`, todos ok; não vou forçar outro caminho que altere estado local sem necessidade.
>
> <details><summary>Ran 4 commands</summary>
>
> - Ran `rg -n "pre_alt_criar_de_review_batch|customer_response|shouldCreatePreAlt" Entregas\review_batch_action.php Entregas\script.js`
> - Ran `rg -n "colTriagem|get_pre_alt_lote|resultado-select|nivel_complexidade" PreAlteracao\index.php PreAlteracao\script.js PreAlteracao\save_pre_analise.php PreAlteracao\get_pre_alt_entregas.php PreAlteracao\get_pre_alt_lote.php`
> - Ran `rg -n "function pre_alt_ensure_schema|function pre_alt_criar_de_review_batch|function pre_alt_recalcular_status_lote" PreAlteracao\pre_alt_helpers.php`
> - Ran `rg -n "pre_alt_lote|PRE_ALT" sidebar_counts.php`
>
> </details>
>
> <details><summary>Plan</summary>
>
> - [x] Criar schema/helper de pré-alteração por lote
> - [x] Integrar resolução de review batch com criação de lote de pré-alteração
> - [x] Refatorar endpoints da PreAlteracao para lote/itens
> - [ ] Atualizar UI da PreAlteracao para níveis 1-5 e resumo
> - [x] Ajustar badges/contagens da sidebar
> - [ ] Verificar sintaxe e fluxos principais
>
> </details>

</details>

Feito. Transformei a `PreAlteracao` em um fluxo de triagem por lote, separado dos substatus da imagem.

O fluxo agora fica assim: em `Entregas`, ao resolver um lote, o usuário escolhe se o cliente aprovou ou pediu alteração. Se aprovou, não entra na pré-alteração. Se pediu alteração, o sistema cria/agrupa um lote gerencial em `PreAlteracao` para a mesma obra, etapa e dia de finalização do cliente. Isso resolve o caso de duas entregas da mesma etapa, tipo `R01`, serem analisadas juntas quando fechadas no mesmo ciclo.

Principais mudanças:

- Criei as tabelas gerenciais `pre_alt_lote`, `pre_alt_lote_batches` e `pre_alt_itens`, sem mexer em `imagens_cliente_obra.substatus_id`.
- A tela agora tem três colunas: `Em Triagem`, `Aguardando Cliente` e `Para Planejamento`.
- Cada imagem do lote pode ser marcada como `Alteração`, `Sem alteração / aprovada` ou `Aguardando cliente`.
- Para alteração, a classificação agora usa níveis `1` a `5` conforme sua regra de complexidade.
- Quando todas as imagens estão classificadas, o lote muda para `PRONTO_PLANEJAMENTO`.
- O contador lateral também passou a contar pelos novos lotes gerenciais.

Arquivos centrais:

- [pre_alt_helpers.php](./PreAlteracao/pre_alt_helpers.php:3)
- [migration_lotes.sql](./PreAlteracao/migration_lotes.sql:1)
- [review_batch_action.php](./Entregas/review_batch_action.php:193)
- [script.js Entregas](./Entregas/script.js:963)
- [index.php PreAlteracao](./PreAlteracao/index.php:87)
- [script.js PreAlteracao](./PreAlteracao/script.js:46)
- [save_pre_analise.php](./PreAlteracao/save_pre_analise.php:27)
- [get_pre_alt_lote.php](./PreAlteracao/get_pre_alt_lote.php:2)

Validações feitas:

- `php -l` nos PHPs alterados: ok
- `node --check` nos JS alterados: ok
- `git diff --check`: ok

Não consegui validar visualmente no navegador interno porque o sandbox do Windows bloqueou a abertura (`CreateProcessAsUserW failed: 5`). Também deixei o diretório não versionado `Revisao/` intacto, porque ele está fora do escopo desta alteração.
