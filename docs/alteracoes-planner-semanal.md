# Alterações: agenda operacional semanal

Implementação e validação em 29/10/2026.

## Experiência

O Kanban principal foi substituído por uma agenda de segunda a sexta, com semana atual por padrão. O wireframe orienta densidade, thumbnails, badges e hierarquia. As datas reais do calendário prevalecem: 29/10/2026 é quinta-feira; a semana atual é 26–30/10.

- Filtros por obra, colaborador (inclusive sem responsável), etapa, nível, status e busca sem distinção de acentos. Busca combina termos de imagem, obra, responsável, etapa, nível e status.
- Navegação anterior/atual/próxima e escolha de uma data da semana. Semana, filtros, aba, painel e dia mobile ficam na URL.
- Cinco colunas no desktop; largura mínima com scroll horizontal interno no notebook/tablet. A abertura traz hoje à área visível quando há scroll.
- Um dia por vez no mobile, com controles anterior/próximo e rolagem vertical. Sem comprimir cinco colunas.
- Cards mostram imagem, thumbnail/fallback, etapa, nível ou “Sem nível”, responsável ou “Sem responsável”, obra e status discreto. Não há pontos, capacidade, sugestão ou distribuição de carga.
- Dia atual destacado. Tarefas abertas de dias anteriores ficam no dia confirmado com indicação “Atrasada”. Finalizado/Aprovado recebem “Concluída” e permanecem na semana; há opção de ocultá-las. “Aprovado com ajustes” continua operacional.
- Clique abre a conferência existente, conservando suas ações. Sem drag/drop de dias ou de status. O atalho por card abre o planejamento da entrega exata. O botão geral abre essa entrega quando o recorte identifica uma única entrega; caso contrário, abre Entregas.
- Preferência light/dark compartilhada com Planejamento (`theme`), com fallback ao sistema. Sidebar original preservada.
- Um Thinking Orbs global, canvas transparente, estado `composing` e label “Carregando alterações”; licença e implementação global não modificadas.

## Origem dos dados

| Campo | Fonte |
| --- | --- |
| Identidade operacional | `funcao_imagem.idfuncao_imagem`, função 6, ligada por `alteracoes.funcao_id` |
| Nome e obra | `imagens_cliente_obra.imagem_nome`, `obra_id`; `obra.nomenclatura`/`nome_obra` |
| Rodada/entrega atual | Última `pre_alt_liberacao_itens.id` por imagem + etapa atual, via `pre_alt_itens` e `entregas`. Mesmo critério do contexto operacional R01+; não reaproveita plano de uma R05 anterior |
| Item da entrega | `entregas_itens.id`, da entrega resolvida e da mesma imagem |
| Versão confirmada | `entrega_planejamento_producao.versao_atual_id`, modelo `IMAGENS_ALTERACAO` |
| Dia operacional | `entrega_planejamento_item.fim_planejado` dessa versão, item e tarefa. `inicio_planejado` não é usado como dia diário. Nenhum recálculo |
| Responsável planejado | `responsavel_planejado_id` confirmado; nome em `colaborador`. No legado, `funcao_imagem.colaborador_id` |
| Etapa | `alteracoes.status_id`/`status_imagem.nome_status` na rodada atual; `entregas.status_id` na rodada histórica concluída |
| Nível | `nivel_complexidade_snapshot` confirmado. Sem confirmação, `alteracoes.nivel_complexidade`. Ausência é explícita, sem inventar N1 |
| Status atual | `funcao_imagem.status`, com fallback “Não iniciado” |
| Conclusão de rodada anterior | `entregas_itens.data_entregue` ou último ciclo vinculado ao item com situação `ENCERRADO` e motivo `CONCLUSAO`. O status atual da tarefa reutilizada não é copiado para a rodada antiga |
| Thumbnail | Disponibilidade agregada em `historico_aprovacoes_imagens`; bytes pelo endpoint existente `PlanejamentoProducao/thumbnail.php`, com autenticação e autorização da obra. Imagens lazy e fallback em erro |
| Calendário | `Entregas/prazo_entrega_helper.php`, o mesmo utilizado pelo R01+. Mantém cinco posições segunda–sexta; feriados são identificados e não deslocam datas confirmadas |

Rodadas anteriores concluídas continuam representadas pela identidade da entrega. Seu clique abre o planejamento correspondente para consulta, evitando abrir ações da rodada atual de uma tarefa reutilizada.

## Sem planejamento e Render em Alta

O painel “Sem planejamento” é acessível pelo contador e independe da semana. Inclui fila legada e entregas R01+ ainda sem versão confirmada. Os itens seguem o padrão dos cards. Novas entregas oferecem “Planejar”; legados sem plano R01+ oferecem “Ver entregas”. Não há conversão automática de entregas antigas.

O prazo da imagem é apenas contexto legado no tooltip: não há evidência de que represente um dia de execução confirmado. Portanto, ele nunca posiciona um card no calendário. Conclusões legadas sem data planejada mantêm a compatibilidade da fila anterior quando o prazo é hoje.

Render em Alta foi identificado como a fila da função Alteração com etapa EF (status de imagem 6). Agora tem aba própria e não aparece nas colunas de dias nem no painel sem planejamento. Mantém a conferência/status existentes. Não se criam tarefas diárias artificiais de Render.

## Leitura, segurança e performance

`getAlteracao.php` responde JSON agregado por semana; filtros simples são locais. No caminho normal, são três SELECTs: fila/contexto vigente, rodadas anteriores concluídas da semana e disponibilidade das thumbnails. Há uma inspeção de coluna, sem DDL. O teste mede o número fixo de SELECTs. As requisições individuais restantes são bytes das imagens, carregados de forma lazy.

GET não cria cabeçalhos, versões, baseline, alocação, ciclo ou schema. Rascunho não substitui a versão confirmada. Falta da migration preserva a fila sem planejamento com aviso, sem aplicar migration automaticamente.

A API exige sessão e colaborador, retorna 401 JSON sem autenticação e aceita apenas GET. O recorte de obras utiliza o contexto central: gestão ou pacotes ativos permitidos. Nenhum writer operacional ou mecanismo R01+ foi alterado.

Metadata institucional central atualizada, helper/head e retorno pós-login preservados. Nenhum nome/ID/dado privado entra na metadata pública.

## Arquivos desta implementação

- `Alteracao/index.php`: estrutura, filtros, agenda, painéis, assets e tema; mantém a carga de obras necessária à sidebar e remove consultas não utilizadas pela página.
- `Alteracao/planner.js`: interação, renderização, busca/filtros, estado na URL, navegação de semana/dia e consulta agregada.
- `Alteracao/planner.css`: visual responsivo e temas, sem alterar o CSS do detalhe existente.
- `Alteracao/script.js`: remove renderização/seleção/drag/drop do antigo Kanban; preserva a conferência e suas ações, com recarga pelo planner.
- `Alteracao/getAlteracao.php`: entrada JSON autenticada e de leitura.
- `helpers/alteracao_planner_helper.php`: montagem da semana e consultas/contextos agregados.
- `config/page_metadata.php`: descrição institucional da agenda.
- `tests/alteracao/PlannerTest.php`: regras e integração com tabelas temporárias.
- Este documento; evidências locais em `output/alteracao-planner/`.

As alterações previamente existentes em `PlanejamentoProducao/alteracao.php`, `confirm.php`, `history.php`, `script.js`, `style.css` e `thumbnail.php` foram preservadas e não fazem parte desta implementação.

## Validação executada

### Banco e regras

`php tests/alteracao/PlannerTest.php --db`: **30 verificações passaram**, usando exclusivamente tabelas TEMPORARY da conexão. Nenhum registro real foi confirmado, replanejado ou alterado pelos testes.

Cobertura: semana real; hoje; feriado; anterior/próxima; datas inválidas; legado; dia correto; atraso sem movimentação; responsável e nível confirmados; thumbnail; nova liberação de R05 sem herança; EF separado; aprovado preservado; rodadas anteriores concluídas por entrega/ciclo; consulta sem mutações; seis status operacionais; três SELECTs sem N+1; nova versão vigente muda dia/pessoa; rascunho não prevalece; baseline preservada; semana seguinte; permissão de obra e ausência de migration.

`php tests/planejamento_producao/PlanejamentoAlteracaoOperacionalTest.php --db`: **OK**, incluindo políticas existentes de início, responsável, HOLD/retomada e ciclos em tabelas temporárias.

`php tools/test_page_metadata.php`: **830 verificações, sem falhas**.

`php tools/test_auth_navigation.php`: **188 verificações, sem falhas**.

Sintaxe PHP/JS dos arquivos alterados e `git diff --check`: sem erros.

### Navegador interno, com dados reais

Acesso por `http://localhost:8066/ImproovWeb/`, login de desenvolvimento e navegação Produção → Lista Alteração. **36 verificações passaram.** Conferidos: semana atual/anterior/próxima; filtros individuais e combinados; busca; limpar; hoje; atraso; painel legado; sem responsável; aba Render; detalhe existente; ações de status disponíveis; link para a entrega correta; preferência de tema compartilhada; estado na URL e navegação mobile. O relatório das verificações está em `output/alteracao-planner/browser-checks.json`.

No recorte observado, a semana 26–30/10 tinha 25 alterações confirmadas (10 na quarta, 9 na quinta, 6 na sexta). A seguinte tinha 7. A obra WER_RIO tinha um item sem planejamento; todas as obras tinham 47. Estes números são evidência do momento do teste, não fixtures ou valores fixos no código.

Resoluções: desktop 1672×941, notebook 1366×768, iPad landscape 1024×768, iPad portrait 768×1024 e mobile 390×844, em light/dark. Sem overflow horizontal da página; o planner tem scroll interno quando necessário.

Capturas principais:

- `output/alteracao-planner/desktop-dark.jpg`
- `output/alteracao-planner/desktop-light.jpg`
- `output/alteracao-planner/mobile-dark.jpg`
- `output/alteracao-planner/mobile-light.jpg`

Capturas adicionais de notebook/iPad nos dois temas ficam no mesmo diretório.

HTTP sem cookies: API 401 JSON; página protegida chega ao login contextual sem corpo privado; um title/OG; imagem OG e favicon 200 `image/png`.

### Limites e ocorrências do ambiente

- A tentativa inicial em `https://improov/ImproovWeb/` foi recusada pela revisão automática do navegador, que avaliou a origem sem o caminho apesar da chamada completa. O segundo endereço oficial, localhost, permitiu a validação prática, sem contornar a restrição HTTPS.
- Uma recarga apresentou a falha transitória de configuração `DB_HOST` ausente. A conexão foi conferida sem expor credenciais, e a recarga seguinte recuperou a página. Não houve alteração de configuração para contornar o erro.
- Não havia Render EF ativo, “Aprovado com ajustes” ou conclusões novas reais suficientes para executar toda essa matriz na interface com dados reais. Esses casos foram cobertos pelos testes isolados. Não foram alteradas tarefas/entregas reais para fabricar cenários de teste.
- Replanejamento e conclusão com avanço de etapa foram validados na leitura das versões e fatos em tabelas temporárias; a navegação real até o Planejamento foi conferida sem confirmar mudanças de negócio.
- Legados sem modelo R01+ permanecem sem dia confirmado até uma decisão explícita pelo fluxo compatível. Isso é tratamento de compatibilidade, não backfill pendente.

Não restou bloqueio de implementação ou de acesso pelo endereço localhost na validação final.
