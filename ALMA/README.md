# ALMA operacional no Flow

## Diagnóstico de integração

- A imagem canônica é `imagens_cliente_obra.idimagens_cliente_obra`.
- `funcao_imagem.imagem_id` liga todas as tarefas/funções à mesma imagem; não há cópia de ALMA na tarefa.
- `imagens_cliente_obra.tipo_imagem` é a identificação estrutural disponível para o tipo de imagem. O módulo exclui exatamente `Planta Humanizada` em um único helper compartilhado.
- A modal da tarefa, em `PaginaPrincipal/scriptIndex.js`, consulta um resumo efetivo e somente leitura.
- O SIRE usa `sire_referencia`, `sire_pilar`, `sire_pilar_valor` e `sire_referencia_valor`. A chave primária de `sire_referencia_valor` torna a classificação aditiva e idempotente.

## Modelo efetivo

O ALMA possui sete decisões visuais efetivas:

- Projeto/obra: Arquitetura, Materialidade e Lifestyle.
- Imagem: Atmosfera, Momento da Luz, Linguagem da Luz, Direção Fotográfica e Composição.

As três decisões de projeto ficam em `alma_projeto_direcao` e tabelas filhas. Elas são herdadas por todas as imagens elegíveis da obra durante a leitura e não são duplicadas em revisões de imagem.

Cada decisão contém somente um item da Biblioteca ALMA e zero ou mais referências SIRE selecionadas. A Intenção Geral é opcional e pertence à revisão da imagem. Os campos legados de narrativa, contexto, justificativa e interpretação continuam preservados no banco e no histórico, mas não fazem parte do fluxo operacional atual.

`fotografia_teste_angulos`, `fotografia_enquadramento` e `fotografia_referencias_sire` foram desativadas na Biblioteca operacional. Dados históricos não são apagados. Direção Fotográfica usa os itens explícitos Editorial e Imersiva.

## Persistência, SIRE e histórico

Salvar uma imagem gera uma nova revisão vigente e substitui a anterior, mantendo o histórico. Copiar uma base e aplicar uma dimensão em lote também geram revisões e eventos auditáveis próprios.

Ao persistir uma referência, o backend:

1. valida imagem, obra, versão, dimensão, item e referência;
2. resolve o pilar/valor SIRE a partir do item ALMA validado;
3. adiciona a classificação com `INSERT IGNORE` dentro da mesma transação;
4. nunca remove classificações SIRE preexistentes quando um vínculo ALMA é removido.

Trocas de item com referências exigem a escolha explícita entre manter ou limpar os vínculos ALMA. Cópias e aplicações em lote substituem o bloco inteiro (`item + referências`), sem merge silencioso, e exigem confirmação em conflitos.

O status da imagem considera somente as cinco decisões específicas: Não iniciado (0), Parcial (1–4) ou Completo (5). Intenção e referências são opcionais e não alteram o status.

## Tabelas

Biblioteca e imagem:

- `alma_biblioteca_versao`, `alma_biblioteca_dimensao`, `alma_biblioteca_item`
- `alma_biblioteca_item_secao`, `alma_biblioteca_secao_entrada`
- `alma_direcao`, `alma_direcao_revisao`
- `alma_revisao_selecao`, `alma_revisao_referencia`, `alma_evento`

Projeto/obra:

- `alma_projeto_direcao`
- `alma_projeto_selecao`
- `alma_projeto_referencia`
- `alma_projeto_evento`

## Migrations

1. `sql/2026-09-03_alma_v1.sql`
2. `sql/2026-09-03_alma_biblioteca_v1_seed.sql`
3. `sql/2026-09-03_alma_biblioteca_v1_import_correction.sql`
4. `sql/2026-09-04_alma_operacional_v1.sql`
5. `sql/2026-09-04_alma_operacional_fotografia.sql`

`ALMA/scripts/apply_sql.php` aceita somente os scripts ALMA da allowlist.

## API operacional

GET:

- `resumo`, `direcao`, `biblioteca`, `historico`, `permissions`
- `obra_contexto`: decisões globais, imagens elegíveis, status e decisões específicas
- `sire_seletor`: relacionadas por correspondência exata pilar/valor e demais referências, com busca, Golden Samples e paginação

POST:

- `salvar_projeto`
- `criar_revisao`, `salvar_revisao`
- `usar_imagem_base`
- `aplicar_dimensao`

Os endpoints administrativos e legados permanecem disponíveis por compatibilidade. A tela operacional não expõe fluxo de aprovação ou ativação manual.

## Telas

- A tela da obra incorpora o ALMA com as decisões globais, navegação rápida entre imagens e editor das cinco decisões específicas.
- `ALMA/index.php` também funciona como tela dedicada e pode abrir uma imagem específica.
- O seletor SIRE mostra apenas referências relacionadas e outras referências; selecionadas permanecem identificadas mesmo fora da página corrente.
- A modal da tarefa mostra os sete pilares efetivos, referências, status e intenção opcional, sem controles de edição.

## Validação

Executar:

```powershell
C:\xampp\php\php.exe ALMA\tests\smoke.php
```

O smoke cria dados descartáveis, valida as cinco decisões específicas, status, intenção vazia, classificação SIRE idempotente, cópia parcial, aplicação por dimensão, herança global, resumo de sete pilares, preservação da classificação ao remover vínculo ALMA e exclusão de Planta Humanizada. Ao final, remove somente os dados artificiais criados pelo próprio teste.

Alterações de interface devem ainda ser verificadas com login real nas duas URLs oficiais e nas resoluções desktop, notebook, iPad landscape, iPad portrait e mobile.
