# FECHAMENTO MENSAL V1

**Mantido:** motor financeiro 1A, revisões/snapshots imutáveis, auditoria e decisões de extras 1C-A, concorrência/idempotência, storage privado e preview/confirmação 1C-B. As migrations e tabelas anteriores permanecem.

**Simplificado:** extra manual ausente vale zero; fixo usa `colaborador.valor_fixo` integralmente, sem exigir liquidação. FIXO soma fixo + extras manuais; VARIAVEL soma serviços + bônus R0 + extras manuais; FIXO_VARIAVEL soma os quatro componentes. O bônus automático R0 só se aplica a VARIAVEL/FIXO_VARIAVEL. Extras manuais mantêm validação, auditoria e rubrica no PDF para todos os tipos. Pendências reais aparecem como Atenção.

**Removido da experiência normal:** abertura individual como fluxo principal, botões de SEM_BONUS/liquidação, hashes, IDs, versões e diagnóstico. Os módulos técnicos e testes antigos continuam disponíveis no repositório.

**Novo:** participação explícita, forma de remuneração e valor fixo no CRUD; seção Fechamento; preparação manual apenas de ativos com `participa_fechamento_mensal=1`; lista/progresso; revisão sequencial com PDF integrado, extras opcionais, pular, voltar e Confirmar e próximo. Nenhum scheduler, envio, assinatura ou confirmação automática.

## Participação no fechamento

- `participa_fechamento_mensal`: 1 = Sim, 0 = Não, NULL = ainda não revisado. Nenhum backfill, classificação por função/setor/fixo ou quantidade predeterminada de participantes.
- Ativos com NULL aparecem na pendência compacta de configuração, com links para o CRUD, fora da fila e dos contadores financeiros. Ativos com Não e inativos ficam fora da fila. A pendência também aparece quando a fila está vazia.
- “Atualizar cadastros” consulta a classificação atual. “Iniciar fechamento” prepara somente os participantes explícitos; preparar/decidir e geração/confirmação mensal também validam participação no servidor.
- Novo cadastro exige Sim/Não. Edição de cadastro existente pode preservar NULL até a revisão do responsável. Sim exige remuneração; FIXO/FIXO_VARIAVEL exigem fixo válido, inclusive zero. Não permite remuneração/fixo ausentes. Valores informados continuam sujeitos à validação monetária.

## Correções da revisão mensal

- No PDF mensal, o fixo participa do total e não gera categoria “Valor fixo”. Nicolle (cadastro 1) usa o valor fixo contratado como uma única categoria “Acompanhamento”; a parcela histórica de R$ 4.000 não é somada novamente. O legado 1B preserva sua regra original.
- Tarefas elegíveis de valor individual zero também aparecem no adendo. Tarefas de FIXO são apresentadas com “-” na coluna de valor, sem adicionar produção ao total. Pagas e fora da competência continuam excluídas.
- Finalização mantém “Finalização completa”, “Finalização completa com pagamento final” (parcela anterior registrada) e “Finalização parcial” (Pré-Finalização ou etapa P0 no corte da competência). Os nomes não alteram tarifas.
- No cálculo mensal, o lançamento compatível “Pago Completa”/FINALIZACAO_COMPLEMENTO encerra a tarefa de Finalização. Base, valor efetivamente lançado, diferença bruta e IDs dos lançamentos permanecem no snapshot; não se fabrica a parcela paga a outro executor. Beneficiário/origem/classe e alertas de excesso continuam validados.
- PDF contínuo: todas as páginas são renderizadas; anterior/próxima navegam pelo documento já exibido. Zoom e mudança de largura redesenham todas as páginas. A confirmação continua vinculada aos bytes/revisão/hash visualizados.

Consulta real somente leitura de setembro/2026: Anderson tem 20 tarefas elegíveis com valor zero; Pedro Henrique, 48. Nicolle tem fixo de R$ 4.000. Bruna: serviço 109178, imagem “9.WER_RIO Brinquedoteca”, possui complemento final quitado de R$ 190 para ela e parcela antiga de R$ 125 para outro executor; não volta a cobrar R$ 190.

## Regra R0 e tarifa

- Tarefa: `funcao_imagem.funcao_id=4` (Finalização). Evento de conclusão: `log_alteracoes.status_novo=Finalizado`.
- Ciclo: último `historico_imagens` na data do evento, ordenado por `data_movimento,idhistorico`; `status_imagem.idstatus=2` é **R00**, o R0 deste pedido. P00, R01/R02/ajustes e demais funções não contam.
- Competência: data da primeira conclusão R00 da imagem; início inclusivo/fim exclusivo, seguindo o calendário do motor existente. A busca considera o histórico anterior, para não recontar a imagem em outro mês.
- Duplicidade: uma imagem por colaborador, usando a primeira conclusão R00 em ordem `data,idlog`. Histórico ausente exige conferência, sem inferir um ciclo.
- Faixas: até 20 → zero; 21–31 → uma unidade; 32+ → duas. Tarifa: `funcao_imagem.valor`, a mesma base persistida usada na 1A. Tarifa ausente ou divergente entre imagens elegíveis bloqueia apenas quando impede determinar um bônus devido. A meta 20 não reduz a remuneração normal.

## Banco e execução

Migrations aditivas: [tipo_remuneracao](../sql/2026-10-06_fechamento_mensal_v1.sql) e [participação](../sql/2026-10-06_fechamento_mensal_participacao.sql), nullable e sem backfill. O deploy verifica cada coluna separadamente. O progresso reutiliza `pagamento_fechamento` e a confirmação documental da revisão atual. Snapshots antigos preservam suas regras; revisões mensais se identificam internamente por `monthly_rule_version`. O bônus automático fica separado nos componentes/snapshot e no PDF, sem nova tabela ou duplicação de tarifa.

O ambiente compartilhado já foi observado com ambas as colunas disponíveis e cadastros classificados pelo responsável. A [lista atual](fechamento-mensal-v1-colaboradores.md), atualizada por consulta somente leitura, encontrou 24 registros ativos: 17 participantes e 3 ainda sem definição. O agente não classificou colaboradores nem confirmou adendos. O número observado não determina quem participa; a revisão humana define a fila.

## Validação

- Regras mensais e cadastro: `php tests/fechamento_mensal_v1_rules_test.php` — 53 verificações: tipos, extra para os três tipos, extra inválido no FIXO, exigências condicionais de participação/remuneração/fixo, fixo null/zero, divergência, faixas, tarifa diferente, Bruna e mistura de eventos/ciclos.
- Integração em MySQL 8 isolado 3320: `php tests/fechamento_mensal_v1_integration_test.php` — 48 verificações: participação e idempotência, extras, confirmação, tarefas zero/cobertas pelo fixo, categoria única de acompanhamento, ausência de categoria fixa, nomes de Finalização e exclusão do serviço quitado da Bruna no PDF.
- Pendências mensais: `php tests/fechamento_mensal_pendencias_test.php` — 26 verificações: quitação por observação/tipo estruturado, ledger real preservado, escopo por beneficiário/origem/classe, parcela parcial, excesso, nomenclatura e acompanhamento único da Nicolle.
- Regressões existentes: 230 verificações 1A, 169 composição, 117 persistência e 194 documentais passaram.
- Validação da revisão atual pelo navegador autenticado em `https://improov/ImproovWeb/`. Não é necessária a rota alternativa: o ambiente normal já disponibiliza o Fechamento. As revisões antigas permanecem imutáveis; use “Atualizar valores” para recalcular e gerar nova prévia quando houver correção de regra/origem.

As correções financeiras/documentais são verificadas também nos casos reais indicados pelo usuário, por novas revisões/prévias. A confirmação real permanece a cargo do responsável.

O CRUD foi validado no navegador autenticado: Não torna remuneração/fixo opcionais; Sim + FIXO exige ambos. [Evidência desktop](../output/ui/mensal-v1/participacao-cadastro-desktop.png). Nenhum cadastro foi salvo.

As novas prévias reais de setembro/2026 foram conferidas pelo navegador e por consulta somente leitura: Nicolle, Acompanhamento único de R$ 4.000; Anderson, 20 tarefas e total de R$ 4.600; Pedro Henrique, 48 tarefas e total de R$ 3.500; Bruna, 30 tarefas e total de R$ 7.090, sem a Finalização quitada da Brinquedoteca. Os quatro documentos permanecem em PREVIEW. Revisões anteriores não foram alteradas.

Responsividade validada nas dimensões efetivas: desktop 1920×1080, notebook 1366×768, iPad landscape 1024×768, iPad portrait 768×1024 e mobile 390×844. PDF com duas páginas mantém ambas exibidas, ajusta a largura ao redimensionar, navega até a segunda página e preserva as páginas ao ampliar o zoom; sem transbordamento horizontal da tela. Evidências: [Nicolle](../output/ui/mensal-v1/correcoes-nicolle-notebook.png), [Anderson](../output/ui/mensal-v1/correcoes-anderson-notebook.png), [Pedro Henrique](../output/ui/mensal-v1/correcoes-pedro-desktop.png), [Bruna](../output/ui/mensal-v1/correcoes-bruna-notebook.png), [iPad landscape](../output/ui/mensal-v1/correcoes-ipad-landscape.png), [iPad portrait](../output/ui/mensal-v1/correcoes-ipad-portrait.png) e [mobile](../output/ui/mensal-v1/correcoes-mobile.png).
