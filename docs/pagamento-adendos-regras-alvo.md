# Pagamento / Adendos — contrato funcional alvo

**Contrato funcional oficial — FASE 0.5, atualizado para a FASE 1A.** Formalizado em 02/10/2026 a partir das 19 regras aprovadas; D01–D07 foram resolvidas explicitamente no pedido da FASE 1A. As regras R01–R19 permanecem aprovadas. O contrato não permite inventar evidência financeira ou valores legados.

Referências históricas: [relatório da FASE 0](pagamento-adendos-fase0.md), [golden master original](../tests/characterization/pagamento_adendos_golden.php) e [diagnóstico original](../tests/characterization/pagamento_adendos.php). A golden é evidência do comportamento anterior e deve permanecer intacta. Expectativas alvo serão mantidas separadamente na FASE 1.

## 1. Escopo e princípio do contrato

O adendo representa **o conjunto dos serviços financeiros devidos ao colaborador naquela competência, somado ao valor fixo e aos extras aplicáveis**. O backend determina identidade, elegibilidade, valor, pagamento, saldo, pendências e total. A UI apresenta esses dados e solicita ações explícitas.

Busca, função, obra, divergências, aba, paginação, visibilidade, `offsetParent`, células e checkboxes não delimitam o conjunto financeiro. A mesma preparação deve produzir o mesmo resultado financeiro independentemente da tela que a solicita, para os mesmos dados de origem, ledger, configuração, decisões e versão de regras congelados.

A FASE 0.5 criou somente este documento. A FASE 1A autoriza o motor de serviços em paralelo, testes alvo separados e comparação CLI somente leitura. Não substituir consumidores atuais, modificar endpoints/JS/PDFs/templates/banco/dados/golden, preparar adendos reais, confirmar, enviar ou lançar pagamentos. Fixo, extras e acompanhamento especial pertencem à FASE 1B; preview, confirmação, envio e versões documentais à FASE 1C ou posterior. Os aceites deste contrato são implementados conforme o escopo de cada etapa.

Os identificadores **R01–R19** correspondem diretamente às decisões aprovadas. “Preservar” descreve a regra financeira indicada, não todos os efeitos de UI ou de persistência do código atual. A FASE 1 deverá aplicar apenas as alterações autorizadas por este contrato e manter explícitas as demais lacunas.

## 2. Regras canônicas aprovadas

### R01 — Elegibilidade por status de função de imagem

Preservar a existência de pelo menos uma movimentação elegível dentro da competência: um status posterior não elegível não apaga a elegibilidade já alcançada. Preservar também o ramo atual de prazo na competência combinado com status atual elegível, como referência inicial V2. O intervalo mensal permanece início inclusivo e primeiro dia do mês seguinte exclusivo.

Status financeiros atuais, após trim e comparação sem distinção de maiúsculas: **Finalizado, Em aprovação, Ajuste, Aprovado com ajustes e Aprovado**. Não acrescentar ou remover status sem nova decisão. A regra não exige o último status elegível do mês.

`CASE_LOG_001`, FI 120385: logs 37775 e 38238 de setembro tornam a tarefa elegível para setembro/2026, mesmo com prazo em outubro e log 38274 posterior de Em andamento. Deve continuar elegível; base R$ 300,00 e ledger correspondente zero na referência.

Fontes atuais: `Pagamento/financeiro_v2.php:7–29`; seção 5 da FASE 0. Não redefinir status de outras entidades usando essa lista por analogia.

### R02 — Snapshot no instante de preparação

Cada revisão representa exatamente os dados utilizados no instante de sua preparação, com instante e fuso identificados. Itens, identidades, valores, ledger, pagos, saldos, comissões, fixo, extras, divergências, regras e total são congelados juntos. A FASE 1 deverá assegurar consistência das leituras que compõem a revisão; registrar apenas uma hora sobre consultas temporalmente incompatíveis não satisfaz o contrato.

Pagamentos e alterações de valor, fixo ou extras posteriores não modificam uma revisão existente. É necessária ação explícita para preparar uma nova revisão, que pode utilizar os novos dados. Uma consulta ao banco atual não pode reinterpretar um snapshot antigo.

O exemplo de fechamento em `01/10/2026 08:14:32` é ilustrativo da decisão aprovada, não um fechamento real localizado. A captura da FASE 0 em `02/10/2026 10:54:06` é uma evidência de diagnóstico, não uma revisão de fechamento já implantada.

### R03 — Identidade persistente do direito financeiro

A identidade mínima é **beneficiário + origem + origem_id + classe financeira**. Comissão e remuneração da tarefa não compartilham saldo. A identidade do item descreve o direito financeiro; a PK do lançamento identifica cada pagamento considerado.

| Entidade real             | Origem literal     | Tabela / PK                        | Valor / beneficiário                                  | Data de competência alvo                                                                            |
| ------------------------- | ------------------ | ---------------------------------- | ----------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| Remuneração de tarefa     | funcao_imagem      | funcao_imagem / idfuncao_imagem    | fi.valor / colaborador da tarefa                      | Prazo/status ou log elegível, R01                                                                   |
| Comissão do gestor        | funcao_imagem      | Mesma PK da tarefa                 | Valor derivado R07 / gestor beneficiário 8            | Elegibilidade da tarefa, R01/R07                                                                    |
| Função de animação        | funcao_animacao    | funcao_animacao / id               | fa.valor / fa.colaborador_id                          | **fa.prazo**, R08                                                                                   |
| Animação legada           | animacao           | animacao / idanimacao              | a.valor / a.colaborador_id                            | Preservar identidade/data operacional existente; canônica FA; diagnóstico de legado conforme D05 |
| Acompanhamento individual | acompanhamento     | acompanhamento / idacompanhamento  | ac.valor / ac.colaborador_id                          | ac.data, R09                                                                                        |
| Pagamento considerado     | origem + origem_id | pagamento_itens / idpagamento_item | pi.valor / pagamentos.colaborador_id por pagamento_id | pagamentos.mes_ref, separada de criado_em                                                           |

Uma parcela e um complemento aplicáveis à mesma remuneração reduzem o mesmo direito financeiro; não se deve criar um saldo independente por causa do texto da observação. Isso preserva a soma de R$ 125,00 + R$ 150,00 do `CASE_ENTRE_MESES_001`. Tipos mais detalhados de lançamento podem ser conservados como informações, sem misturar comissão com tarefa.

`animacao:633` e `funcao_animacao:534` são entidades distintas. IDs numéricos iguais em tabelas distintas também não são equivalentes. Essa distinção não autoriza contar automaticamente as duas entidades como dois serviços nem converter pagamentos legados entre elas. D05 resolvida determina diagnóstico, sem conversão ou soma automática.

O schema real da FASE 0 não possui `pagamento_itens.tipo_lancamento` ou `chave_lancamento`; o helper usa origem/observação. Não presumir que novas colunas já existam. Fixo, extra manual e acompanhamento fixo especial também precisam de identidade/rastreabilidade conceitual, sem definir aqui PKs, tabelas ou enum físico novos.

### R04 — Uma fonte financeira no servidor

O caminho A/V2 é a referência inicial para valores financeiros, com as alterações deliberadas deste contrato. `getAdendoItens()` não continuará como motor independente nem como fallback financeiro. Preparação, resumo financeiro e consumo documental devem obter resultados consistentes da regra compartilhada para os mesmos dados e instante.

Conservar descrições e vínculos reais por suas identidades. Nome da imagem e função são apresentação, nunca chaves para calcular, conciliar ou excluir valores. A implementação não deve copiar falhas do DOM nem o cálculo bruto de comissão do caminho B.

### R05 — Valor financeiro, pagamentos aplicáveis e saldo

Definições para evitar ambiguidade de “devido”:

- **Base financeira**: valor financeiro da origem, ou comissão derivada R07, antes de descontar pagamentos.
- **Pago aplicável**: ledger compatível com beneficiário, origem, ID e classe, disponível no instante do snapshot.
- **Saldo bruto**: base financeira menos pago aplicável.
- **Saldo pendente**: saldo bruto positivo; quando bruto é zero ou negativo, não há valor positivo a pagar por esse item.
- **Excesso**: pago aplicável superior à base financeira. Gera divergência bloqueante R15, conservando base, pago e excesso na revisão.

Preservar o uso do valor persistido da origem como referência, sem substituir silenciosamente por tarifa atual. Preservar a consideração de pagamentos entre competências: o mês do lançamento não limita sozinho a soma aplicável. A revisão utiliza os lançamentos existentes no instante de preparação e congela seus IDs, valores, competências e classificação. Não introduzir por conta própria nova filtragem por status do cabeçalho de pagamento; alterações nesse predicado exigem decisão se pretendidas.

Contadores `parcial/completa`, observações, nomes e flags legadas são auxiliares. Não podem eliminar saldo positivo. A identificação de inconsistência deve abrir pendência R15, e não corrigir ou lançar pagamento automaticamente.

`CASE_ENTRE_MESES_001`, FI 111524 / Heverton (40): base 300, pagamentos aplicáveis 125 de março e 150 de abril, saldo **25**. A comissão de 80 ao beneficiário 8 não reduz esse saldo. `completa_count=1` não elimina os 25. Correção deliberada autorizada.

No `CASE_PARCIAL_001`, FI 102804 elegível, base 250 e parcial efetivamente registrado de 125: reconhecer saldo **125**, sem descartá-lo apenas por `parcial=1` ou pelo nome “Parcial”. A regra de saldo prevalece sobre esses auxiliares; eventual inconsistência independente permanece sujeita a R15. Isso não transforma automaticamente todo registro histórico chamado parcial em uma dívida sem conferir origem, elegibilidade e ledger.

### R06 — Quitação e seleção de serviços

Item com saldo zero não integra os serviços financeiros devidos do adendo e não cria linha de R$ 0,00 para representar pagamento pendente. Pode permanecer no snapshot como item analisado/quitado e na UI histórica; não aumenta subtotal ou total.

`CASE_PAGO_001`, FI 110526: base 380, ledger 380, saldo zero; remover a linha financeira zero do adendo. A exceção de routing da UI não autoriza inclusão. Igual princípio para comissão e animação quitadas. Um excesso não deve ser escondido como simples quitação: exige R15.

### R07 — Comissão do gestor

Preservar os critérios financeiros exatos atuais:

- Beneficiário gestor **8**, tarefas `funcao_imagem` de colaboradores **23 ou 40**, função **4**, dentro da elegibilidade atual.
- **R$ 100,00** quando `tipo_imagem` é exatamente `Fachada` e o nome da imagem **não contém “embasamento”**, com a busca sem distinção de maiúsculas usada atualmente.
- **R$ 80,00** nos demais casos dessa regra de comissão.
- Descontar somente ledger aplicável à comissão desse beneficiário e dessa origem/ID. Pagamento da tarefa ao executor não quita a comissão, nem vice-versa.

`CASE_COMISSAO_001`, FI 120172: a tarefa tem valor bruto 300, mas a comissão de Marcio em setembro/2026 é **80**. B=300 está incorreto e sua correção é autorizada. O caso adicional FI 117304, Fachada diurna, tem comissão 100, ledger do gestor 100 e saldo zero na golden. Não aplicar 100 a embasamento apenas porque tipo_imagem é Fachada.

### R08 — Competência da função de animação

A competência financeira de `funcao_animacao` é determinada por **funcao_animacao.prazo**. `animacao.data_anima` permanece informação operacional. Manter os critérios atuais de beneficiário e status elegível das funções de animação; alterar deliberadamente a fonte da data.

`CASE_ANIMACAO_001`, FA 534: prazo **2026-08-31**, data_anima **2026-09-04**. A expectativa alvo é elegibilidade de **agosto**, não setembro. Na mesma base congelada, o ledger aplicável 100 já cobre a base 100: saldo zero, sem serviço devido em setembro nem linha zero em agosto. Mudar a competência não autoriza cobrar novamente 100.

O cenário aprovado com prazo 10/10/2026 e data_anima 29/09/2026 tem competência outubro. É um cenário conceitual futuro, não registro inventado no banco. Não alterar a golden original nem documentos assinados para materializar essa expectativa.

Renomeações de apresentação existentes para Animação de valor 100/175 não recebem nova regra financeira nesta etapa. Valores, nomes e formatos do PDF não podem determinar competência, saldo ou classe.

### R09 — Acompanhamento individual e rubrica especial

Preservar acompanhamentos individuais como serviços independentes quando elegíveis por `acompanhamento.data` e financeiramente devidos. Aplicar ledger, saldo e pendências como nas demais origens.

Para **Nicolle, ID 1**, conservar também **R$ 4.000,00 de acompanhamento fixo especial**, como rubrica separada e rastreável. Esse valor soma aos demais serviços, acompanhamentos individuais devidos, fixo pendente aplicável e extras manuais aplicáveis. **Não substitui outros extras e essa soma não é considerada dupla contagem.** Não confundir a rubrica de 4.000 com `colaborador.valor_fixo`.

Fórmula conceitual, sem duplicar acompanhamentos entre parcelas:

`total = serviços devidos sem acompanhamentos + acompanhamentos individuais devidos + fixo pendente + acompanhamento especial aplicável + demais extras aplicáveis`.

O futuro contrato deve tornar a rubrica explícita e explicar sua origem. Sua configuração financeira será implementada na FASE 1 ou posterior, conforme a decisão aprovada; esta fase não cria configuração ou tabela. A aplicação inicial aos registros conhecidos permanece vinculada ao ID 1, com regra identificada no snapshot, sem extensão automática a outros colaboradores.

`CASE_ACOMPANHAMENTO_001`, AC 617: origem marcada paga e sem ledger compatível. **Não afirmar automaticamente que os 10 são devidos ou quitados**; abrir pendência individual R15. A soma aprovada de acompanhamento especial não resolve essa inconsistência histórica.

### R10 — Fixo padrão, congelamento e override

O padrão na preparação é **colaborador.valor_fixo atual**. Congelar o valor, sua fonte e os dados consultados na revisão. Alterações cadastrais posteriores só podem aparecer em nova revisão. O campo atual não tem vigência/histórico suficiente para reconstruir o fixo aplicável a uma preparação antiga nunca registrada.

Override manual substitui o valor padrão daquela preparação e registra **valor original, substituto, usuário, data/hora e motivo**. Não soma os dois valores e não modifica o cadastro implicitamente. Após congelamento, um novo override exige nova revisão. D04 resolvida restringe a decisão financeira ao nível administrativo apropriado, com antes/depois e evidência quando aplicável; implementação fora da 1A.

Zero explicitamente informado é um valor conhecido. D03 resolvida define ausência/NULL como fixo NÃO DEFINIDO, exigindo decisão explícita SEM_VALOR_FIXO ou valor informado antes do fechamento definitivo. Não converter ausência em zero; implementação na 1B.

### R11 — Fixo aplicável versus liquidado

Configuração não é pendência financeira. O resultado distingue **fixo aplicável, parcela já liquidada e saldo pendente**. Fixo comprovadamente quitado não volta a compor pendente ou total devido por existir no cadastro. Quitação parcial, se existente e comprovada, reduz o saldo correspondente; ausência de identificação suficiente da liquidação não autoriza inventar uma parcela paga.

`CASE_FIXO_001`: Anderson tem cadastro 4.600 e o resumo mostra pendente 4.600 apesar de pagamento com status pago. A correção dessa confusão está autorizada. **A golden não identifica um ledger específico que prove quitação do fixo**; o adendo assinado de total 4.600 prova valor documental, não pagamento financeiro. Não concluir automaticamente saldo fixo zero apenas pelo status do cabeçalho ou pela assinatura. A evidência que autoriza liquidação e sua vinculação está em D01.

### R12 — Estado e registro de bônus/extras

| Estado conceitual | Significado                                            | Tratamento do valor                                                                     |
| ----------------- | ------------------------------------------------------ | --------------------------------------------------------------------------------------- |
| PENDENTE          | Ainda não decidido/preenchido                          | Desconhecido; não equivale a zero ou SEM_BONUS                                          |
| SEM_BONUS         | Responsável confirmou ausência de bônus na competência | Ausência de extras manuais confirmada e auditável                                       |
| DEFINIDO          | Há um ou mais extras registrados                       | Conservar e somar os valores aplicáveis, sujeito às validações explicitamente definidas |

Cada extra conserva pelo menos **colaborador, competência, categoria, valor, autor e data/hora**. Estado e rubricas integram o snapshot. Novo extra após a preparação exige nova revisão. Conservar declaração de ausência; não substituí-la por dedução de lista vazia.

A rubrica especial R09 permanece separada do estado dos extras manuais: SEM_BONUS não cancela o acompanhamento especial de 4.000. DEFINIDO para extras manuais não autoriza substituí-los por essa rubrica.

Com PENDENTE podem ser calculados componentes conhecidos, com estado PENDENTE_DE_BONUS, sem declarar total final completo, confirmar documento final ou enviar. Não bloquear outros colaboradores. SEM_BONUS ou DEFINIDO permite preparar nova revisão completa (D02 resolvida). D06 resolvida exige extra manual positivo, zero representado por SEM_BONUS e negativos fora deste fluxo até regra específica; moeda válida normalizada no backend. Implementação concreta na 1B/1C.

### R13 — Independência de filtros, abas e seleção visual

Busca, função, obra, filtro de divergência, A pagar, Pagos, paginação, visibilidade e checkboxes não alteram o conjunto financeiro ou total da mesma revisão. As incompatibilidades de células deixam de participar do cálculo ao remover a dependência do DOM.

A UI pode solicitar uma ação como pagamento por interação própria, mas esse evento não é uma fonte alternativa de cálculo do adendo. Uma alteração financeira efetiva posterior exige nova revisão R02; mudar apenas a apresentação não exige revisão financeira.

### R14 — Conjunto vazio sem algoritmo alternativo

Um conjunto financeiro canônico vazio significa ausência de itens nesse conjunto. Não aciona `getAdendoItens()` nem outro motor. Isso não elimina fixo ou extras aplicáveis: subtotal de serviços pode ser zero e total depender dessas rubricas.

Em uma solicitação de preparação, o backend obtém o conjunto por colaborador/competência/dados canônicos; `itens=[]` recebido da UI não ordena “nenhum devido”, nem “usar B”. A lista do navegador não é input autoritativo. Ao consumir uma revisão já calculada com lista canônica vazia, o documento conserva esse resultado, sem nova consulta alternativa. Assim se conciliam R13 e a semântica de vazio.

### R15 — Divergência bloqueante por colaborador

Pago superior à base esperada, origem marcada paga sem ledger compatível ou outra inconsistência financeira não resolvida abre **PENDÊNCIA do colaborador**. Não preparar/liberar seu adendo como fechamento definitivo regular ignorando a pendência. Conservar diagnóstico, valores conhecidos e evidências; exigir resolução ou decisão explícita do gestor, com registro. Nenhuma decisão pode alterar silenciosamente snapshot anterior.

Os demais colaboradores continuam sua preparação normalmente. Uma pendência individual não bloqueia o mês inteiro. Não criar lançamento corretivo, zerar excesso, assumir pagamento ou escolher valor automaticamente.

`CASE_DIVERGENCIA_001`, FI 110107: base esperada 150, ledger 275, excesso **125**; bloquear o colaborador mesmo com flag legada false ou valor_aprovado=1. `CASE_ACOMPANHAMENTO_001` também exige pendência, sem tratar sua flag como quitação provada.

O diagnóstico de uma pendência pode ser registrado sem aprovação financeira. R15 não define sozinho quais atos o gestor pode autorizar para resolvê-la; permissões, motivos e alcance da decisão estão em D04.

### R16 — Preview como revisão própria

Preview possui **versão, snapshot, hash, arquivo, instante de preparação, responsável e estado**. Pode conservar seus próprios dados/artefato identificável, mas não confirmar, enviar, atualizar data_envio, pagar, sobrescrever definitivo ou substituir assinatura. “Não persistir antes da confirmação” significa não persistir efeitos de documento definitivo/envio; não proíbe conservar a revisão de preview imutável.

Reabrir um preview reproduz a revisão existente. Recalcular com dados diferentes prepara outra revisão explicitamente. O hash identifica o conteúdo/revisão confirmável; algoritmo e detalhes técnicos serão definidos na arquitetura, sem escolher aqui uma implementação física.

### R17 — Confirmação da versão visualizada

Confirmar aprova **a versão específica visualizada**, com seu snapshot, hash e arquivo. Registrar versão confirmada, hash, arquivo definitivo, usuário, data/hora, itens, fixo, extras e total daquela versão. Não recalcular consultando o banco atual durante a confirmação para trocar valores sem nova revisão.

Mover arquivo é insuficiente. Vínculos de arquivo/nome/hash e estado registrado devem corresponder ao definitivo efetivo. Confirmar não significa enviar, pagar ou substituir assinatura existente. Tratamento de tentativa repetida, acesso e conflito entre revisão visualizada e revisão selecionada pertence ao desenho futuro; deve respeitar essa identidade e nunca aprovar outra versão silenciosamente.

### R18 — Regeneração e vínculo documental

| Estado anterior     | Comportamento aprovado                                                |
| ------------------- | --------------------------------------------------------------------- |
| GERADO / PREVIEW    | Pode preparar nova revisão explícita, preservando a anterior          |
| ENVIADO             | Nova revisão exige ação explícita e tratamento do vínculo de envio    |
| VISUALIZADO         | Mesma exigência de ENVIADO                                            |
| ASSINADO            | Documento assinado imutável; nenhuma substituição dessa versão        |
| RECUSADO / EXPIRADO | Preservar histórico; nova tentativa é nova revisão/processo explícito |

Tokens, URLs e metadados de envio/assinatura identificam a versão à qual pertencem. D07 resolvida mantém ENVIADO/VISUALIZADO como versões históricas imutáveis: nova revisão exige ação explícita, não sobrescreve, não reutiliza token/URL nem transfere metadados de assinatura; seu envio é um novo processo. Implementação na 1C ou posterior. Versões assinadas antigas e seus valores não são recalculados pelas novas regras.

### R19 — Explicação completa do total

O snapshot deve permitir responder “por que este adendo vale R$ X?” sem consultar valores atuais para reconstruir a composição. Conservar colaborador, competência, instante, itens, origem/IDs, beneficiário/classe, valores originais e financeiros, ledger considerado, pagos, saldos, elegibilidade, regra aplicada, comissão, fixo e sua fonte/override/liquidação, extras e seu estado, rubrica especial, divergências, total e versão das regras.

Não definir schema físico, nomes finais de APIs/PHP, migrations ou tecnologia de armazenamento. A golden não é esse schema e o payload atual de quatro campos não cumpre esse contrato completo.

## 3. O que deve permanecer e o que muda

**Preservar:** valores positivos normais da origem, status elegíveis e existência de log elegível da FI, ramo prazo/status atual da FI, pagamentos aplicáveis entre competências, separação de beneficiários/comissão, critérios de comissão 80/100, acompanhamentos individuais, rubrica especial 4.000 do ID 1, zero explicitamente conhecido, distinção entre animação legada e FA e todos os documentos assinados/históricos como evidência anterior.

**Alterar deliberadamente:** retirar influência financeira do DOM, filtros/abas/contadores; considerar saldo positivo mesmo com flag completa/parcial; excluir serviços quitados/linhas zero; eliminar cálculo independente B; usar prazo da FA; fixo padrão cadastrado com override auditável e quitação distinguida; extras com estado/rastreabilidade e soma em vez de substituição pelo especial; divergência individual bloqueante; snapshot/revisão imutáveis; preview/confirmar/envio/pagamento distintos; nova revisão sem sobrescrita ou reuso automático de tokens.

**Não mudar por autorização implícita:** tarifas, lista de status, critérios da comissão, nomenclatura/estilo do PDF, regra de data de pagamento/quinto dia útil, critérios de extras aprovados em D06 (implementação na 1B), pagamentos históricos, arquivos assinados, estados históricos ou dados antigos. Este contrato não concede permissão para reparar registros reais.

## 4. Mapa de todos os casos da golden

Classificação avalia o comportamento completo do caso; quando o valor é preservado mas seleção muda, registrar ambos. Os testes **T01–T22** são descritos na seção 8. Novas expectativas são separadas; os asserts históricos não são regravados.

| CASE_ID                 | Comportamento atual                                                          | Comportamento alvo                                                                                                  | Classificação           | Motivo / regra                                                        | Teste futuro |
| ----------------------- | ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- | ----------------------- | --------------------------------------------------------------------- | ------------ |
| CASE_NORMAL_001         | FI 120437: 50 devido, sem ledger                                             | 50 devido e serviço de 50 na revisão, sem influência da UI                                                          | PRESERVAR               | Valor/saldo normal, R01/R04/R05                                       | T01/T14      |
| CASE_PARCIAL_001        | FI 102804: base 250, pago 125, elegível V2 mas removido por parcial=1        | Reconhecer 125 de saldo; parcial auxiliar não apaga dívida elegível; pendência independente respeitada              | ALTERAR DELIBERADAMENTE | Saldo prevalece, R05/R13/R15                                          | T02          |
| CASE_PAGO_001           | FI 110526: base/pago 380; linha financeira zero incluída                     | Saldo zero; fora dos serviços devidos, conservável no diagnóstico                                                   | ALTERAR DELIBERADAMENTE | Quitação, R06                                                         | T03          |
| CASE_COMISSAO_001       | FI 120172: A 80, B 300                                                       | Comissão 80; saldo calculado em sua classe/beneficiário                                                             | ALTERAR DELIBERADAMENTE | Corrigir B, R03/R04/R07                                               | T04          |
| CASE_COMISSAO_PAGA_001  | FI 118830: 300 pago ao executor e 80 ao gestor; Pagos projeta linha zero     | Manter dois direitos/ledgers distintos; comissão quitada não vira serviço zero                                      | ALTERAR DELIBERADAMENTE | Valores/identidades preservados, seleção corrigida, R03/R06/R07       | T05          |
| CASE_ANIMACAO_001       | FA 534 entra em setembro por data_anima; prazo agosto; pago 100/saldo 0      | Pertence a agosto pelo prazo; sem devido na base quitada e sem linha zero                                           | ALTERAR DELIBERADAMENTE | Competência/seleção corrigidas, R06/R08                               | T07/T08      |
| CASE_ACOMPANHAMENTO_001 | AC 617: flag paga, sem ledger; A inclui 10, B exclui                         | Pendência de Nicolle por incompatibilidade; não afirmar devido/quitado 10 automaticamente                           | ALTERAR DELIBERADAMENTE | Divergência antes de liberar total; soma especial preservada, R09/R15 | T09/T10      |
| CASE_FIXO_001           | Anderson: cadastro 4600; resumo pendente 4600/status pago; fixo manual       | Padrão 4600 congelado; pendência considera liquidação comprovada; valor exato liquidado no caso real depende de D01 | ALTERAR DELIBERADAMENTE | Configuração distinta de saldo, R10/R11                               | T11/T12      |
| CASE_FIXO_ZERO_001      | Mariana: cadastro/payload fixo 0; ausência vira 0 no backend                 | Zero conhecido continua 0; ausência não é convertida silenciosamente em 0                                           | ALTERAR DELIBERADAMENTE | Preserva zero, altera semântica de ausência, R10/D03                  | T13          |
| CASE_BONUS_001          | Nicolle: especial 4000 substitui extras; fixo histórico 4000 e total 8000    | Especial 4000 soma aos extras; fixo novo usa cadastro/override; bônus com estado; histórico 8000 intocado           | ALTERAR DELIBERADAMENTE | R09/R10/R12/R19; total novo não inferível do histórico                | T10/T15      |
| CASE_DIVERGENCIA_001    | FI 110107: base 150/pago 275; V2 true/legada false; não bloqueia             | Excesso 125 gera pendência individual bloqueante, sem ajuste automático                                             | ALTERAR DELIBERADAMENTE | R05/R15                                                               | T16          |
| CASE_LOG_001            | FI 120385 entra por logs elegíveis apesar de movimento posterior             | Mesma elegibilidade e saldo 300 com os mesmos dados                                                                 | PRESERVAR               | R01/R05                                                               | T06          |
| CASE_FILTROS_001        | Linhas visíveis influenciam coleta financeira                                | Mesmo conjunto/total para qualquer combinação visual                                                                | ALTERAR DELIBERADAMENTE | R04/R13                                                               | T14          |
| CASE_ABA_A_PAGAR_001    | Coletor usa Ações como data; AC 617 pode entrar com 10                       | Coleta de células não decide finanças; AC 617 segue pendência R15                                                   | ALTERAR DELIBERADAMENTE | R03/R13/R15                                                           | T09/T14      |
| CASE_ABA_PAGOS_001      | Função vira imagem, moeda vira função, valor 0; contadores perdidos          | Backend calcula por identidade; quitados não viram serviços; mesma revisão em qualquer aba                          | ALTERAR DELIBERADAMENTE | R03/R06/R13                                                           | T03/T14      |
| CASE_LISTA_VAZIA_001    | itens=[] aciona B e erro bind                                                | Vazio canônico não aciona motor alternativo; vazio da UI não é autoridade                                           | ALTERAR DELIBERADAMENTE | R04/R14                                                               | T17          |
| CASE_ENTRE_MESES_001    | FI 111524: saldo 25 eliminado por completa_count=1                           | 25 reconhecido como devido; comissão de outro beneficiário não descontada                                           | ALTERAR DELIBERADAMENTE | R03/R05                                                               | T18          |
| CASE_REGENERACAO_001    | Sobrescreve gerado/enviado/visualizado; mantém vínculos; terminais bloqueiam | Novas revisões/histórico; enviado/visualizado explícitos; assinado intocado; recusado/expirado novo processo        | ALTERAR DELIBERADAMENTE | R02/R16/R17/R18/R19                                                   | T19/T20/T21  |

Nenhum dos 18 casos ficou integralmente “AINDA NÃO AFETADO”: todos têm preservação ou mudança explicitamente relacionada às decisões. Aspectos específicos não cobertos permanecem nas seções 5 e 9; isso não autoriza mudar o restante do caso.

Os subtotais históricos A=9.120/B=11.100 do gestor e A=2.415/B=0 de Nicolle permanecem evidência anterior. Este documento não proclama novos totais globais para esses pares: seleção, pendências, fixo, extras e competência alvo devem ser considerados na preparação futura. Nenhum total definitivo é deduzido apenas da diferença entre os dois caminhos antigos.

## 5. Classificação de todos os possíveis bugs da FASE 0

**A — CORREÇÃO AUTORIZADA PELA REGRA-ALVO. B — SERÁ ELIMINADO COMO CONSEQUÊNCIA DA NOVA ARQUITETURA. C — NÃO RELACIONADO À FASE 1 / DEIXAR PARA DEPOIS. D — AINDA PRECISA DE DECISÃO.** A/B autorizam o comportamento futuro indicado, não uma correção imediata nesta fase. Cada linha corresponde a um achado da seção 7 da FASE 0.

| Achado preservado                                               | Classe | Justificativa e limite da autorização                                                                                                                                                                                 |
| --------------------------------------------------------------- | ------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Bind inválido em B                                              | B      | R04/R14 retiram B como motor/fallback. Não corrigir bind para perpetuar o segundo cálculo; testar que o novo fluxo não o chama.                                                                                       |
| Coletor posicional incompatível com Pagos                       | B      | R03/R13 eliminam posições como autoridade financeira. Mudanças visuais que não decorram desse contrato exigem escopo próprio.                                                                                         |
| Data de pagamento perdida em A pagar                            | B      | R04/R05/R13 usam ledger/identidade no servidor. Não restaurar data de célula como fonte superior de pagamento.                                                                                                        |
| Comissão B usa bruto da tarefa                                  | A      | R07 fixa 80/100 nos critérios atuais, não 300; caso principal 120172.                                                                                                                                                 |
| Excesso pago V2 não alimenta tratamento legado/bloqueio         | A      | R15 exige pendência individual independentemente da flag legada; forma visual do alerta será desenho de UI futuro.                                                                                                    |
| Contador de completa exclui saldo positivo                      | A      | R05 conserva 25 no caso 111524; não apagar por contador/nome.                                                                                                                                                         |
| Pago integral com parcial_count permanece em A pagar/linha zero | A      | R06 exclui de serviços devidos saldo 0; não altera ledger histórico.                                                                                                                                                  |
| Origem paga sem ledger                                          | A      | R15 exige pendência/decisão, não “corrigir” automaticamente flag ou pagamento.                                                                                                                                        |
| Nomes degradados de animação no merge V2                        | B      | R03/R04/R19 tornam descrição vinculada à identidade real, sem nomes genéricos como chave financeira. Não autoriza nova nomenclatura artística/template; essa apresentação permanece fora da centralização financeira. |
| Lista vazia por filtro aciona consulta interna                  | B      | R13/R14 eliminam dependência da visibilidade e fallback financeiro.                                                                                                                                                   |
| Fixo sempre adicionado ao pendente do resumo                    | A      | R10/R11 separam cadastro e quitação. A fonte de comprovação legada precisa de D01; status pago isolado não basta para inferir 4600 liquidados.                                                                        |
| Geração grava definitivo/data_envio antes de confirmar          | A      | R16 autoriza a separação de efeitos: preview próprio pode ser conservado; definitivo/envio não podem ser alterados pela preparação. Não proibir persistência do próprio snapshot de preview.                          |
| Confirmação move arquivo sem atualizar vínculo registrado       | A      | R17 exige arquivo definitivo/hash/versão corretos, não apenas rename; depende da separação de revisão/confirmação.                                                                                                    |
| Nome registrado ignora sufixo retornado pelo PDF                | B      | R16/R17 vinculam a versão ao arquivo efetivo e hash. O formato do nome/sufixo não é escolhido aqui e não autoriza editar template/renderizador nesta fase.                                                            |
| Regeneração conserva token/URL/metadados de outra versão        | A      | R18 proíbe transferência automática; política de vínculo enviado/visualizado exige D07.                                                                                                                               |
| Extras com vírgula descartados e negativos numéricos aceitos | A | D06/R12 resolvidas autorizam moeda válida normalizada no backend e somente extras positivos. Correção pertence à FASE 1B; negativos não entram como bônus. |

Após D06 resolvida, os 16 achados têm tratamento A/B autorizado para a etapa pertinente; não há achado inteiro C ou D nessa lista. Aspectos de apresentação/formatação do PDF, downloads gerais, calendário de pagamento e demais problemas externos à lista são **C** quando não afetados diretamente pelo vínculo de revisão R16–R18. Centralizar finanças não é autorização geral para corrigir interface, arquivos ou legado.

Dívidas técnicas da FASE 0: identidade ausente no payload e SQL duplicado → B por R03/R04/R13; falta de itens/extras no histórico → A por R12/R19 para novas revisões, sem fabricar histórico antigo; observações livres → exigem mapeamento explícito na arquitetura, sem reinterpretar registros ambíguos; sincronização de divergência por substring → B como autoridade, preservando o diagnóstico R15.

## 6. Contrato conceitual de entrada

Solicitação conceitual: **preparar o fechamento de um beneficiário, para uma competência, no instante de snapshot definido e registrado pelo servidor**. Não é uma assinatura PHP final nem contrato HTTP pronto.

| Input canônico                                    | Responsabilidade                                                                                                    |
| ------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Beneficiário/colaborador persistente              | Identifica quem tem o direito financeiro; validado pelo backend                                                     |
| Competência                                       | Mês/ano normalizados; elegibilidade R01/R08/R09                                                                     |
| Instante de preparação e fuso                     | Determina o estado congelado; não declara retrospectivamente que uma captura atual é o estado de um instante antigo |
| Identidade e versão das regras                    | Permite repetir/explicar os critérios realmente aplicados                                                           |
| Dados de origem e ledger aplicável                | Obtidos e classificados no backend de forma consistente; não recebidos como verdade do navegador                    |
| Fixo cadastrado e eventual override auditado      | Fonte padrão R10; usuário/motivo/instante do override são dados validados, não substituição silenciosa              |
| Estado de extras e rubricas registradas           | Decisão explícita PENDENTE/SEM_BONUS/DEFINIDO e registros R12; rubrica especial R09 separada                        |
| Responsável pela preparação e decisões explícitas | Identidade autenticada e auditável; resolução de pendência conforme política a definir                              |

Confirmação futura tem entrada conceitualmente diferente: identificação da revisão específica visualizada/aprovada, com hash/arquivo e ator. Consome snapshot existente; não calcula novamente a competência como se fosse uma nova preparação.

**Inputs sem autoridade financeira:** valor, nome de imagem/função ou data vindos do DOM; índice de célula; contador textual; flag legada isolada; aba; busca/filtro; checkbox; paginação; offsetParent; itens visíveis e totais calculados no navegador. Campos enviados pela UI podem solicitar uma ação validável, mas não substituir origem, ledger ou snapshot. A ausência de `itens` do cliente não escolhe algoritmo alternativo.

Uma mesma solicitação nominal de colaborador/competência, feita após pagamento ou override, pode produzir **nova revisão diferente** porque os inputs financeiros mudaram. “Mesmo input, mesmo resultado” compara também dados congelados e versão das regras, não apenas dois parâmetros em bancos diferentes.

## 7. Contrato conceitual de saída e invariantes

O resultado deve distinguir candidato calculado, pendências e revisão aprovada. Não usar um número de subtotal conhecido como se fosse total final resolvido.

| Bloco de saída          | Conteúdo conceitual mínimo                                                                                                                                                                                                 |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Revisão                 | Identidade, versão, competência, beneficiário, snapshot_em/fuso, responsável, versão das regras, estado e hash                                                                                                             |
| Itens analisados        | Identidade completa, descrição real, elegibilidade e motivo, valor original/financeiro, comissão, ledger considerado, pago, saldo, regra aplicada e divergências                                                           |
| Serviços devidos        | Subconjunto elegível com saldo positivo; exclusões explicadas; quitados ficam no diagnóstico, sem linha financeira zero                                                                                                    |
| Fixo                    | Valor cadastrado/original, utilizado, origem/override, evidência de liquidação e saldo pendente; ausência distinta de zero                                                                                                 |
| Extras manuais          | Estado explícito e rubricas com categoria/valor/competência/autor/data                                                                                                                                                     |
| Acompanhamento especial | Rubrica4000 separada e sua fonte/configuração/regra, quando aplicável                                                                                                                                                      |
| Pendências              | Motivo, entidade/classe, valores/evidências, estado e eventual decisão do gestor auditada                                                                                                                                  |
| Composição              | Subtotal dos serviços (inclui acompanhamentos individuais uma única vez), fixo pendente, acompanhamento especial, subtotal de extras e total final quando todos os componentes forem determinados e a revisão estiver apta |
| Documento               | Arquivo, hash, revisão vinculada; confirmação, envio e assinatura com seus próprios atos/metadados, sem transferência implícita                                                                                            |

Saldo/excesso e itens bloqueados não são omitidos do diagnóstico apenas para fazer o total fechar. Onde a evidência não determinar um componente, a saída explica essa ausência; não atribui valor por IA ou dedução de UI. O formato físico dessas informações pertence à arquitetura da FASE 1.

Invariantes obrigatórias:

1. Frontend não é fonte de verdade financeira.
2. Mesmos dados financeiros congelados e regras produzem o mesmo resultado, independentemente da tela.
3. Filtros, abas, paginação, visibilidade e checkbox não alteram total/conjunto financeiro.
4. Saldo positivo não desaparece por texto, flag ou contador auxiliar.
5. Item quitado não gera valor devido ou linha financeira zero.
6. Comissão e remuneração são direitos/classes distintos; beneficiários não compartilham saldo por mera PK igual.
7. Preview não é confirmação, envio ou pagamento.
8. Snapshot criado não muda; confirmação não consulta valores atuais para substituí-lo.
9. Nova revisão não sobrescreve a anterior.
10. Documento assinado é imutável, incluindo composição e vínculos daquela versão.
11. Divergência não é corrigida, ignorada ou resolvida automaticamente.
12. Pendência individual não bloqueia os demais colaboradores.
13. Nenhum valor financeiro é inferido por IA.
14. Ausência de extra não equivale a SEM_BONUS; ausência de fixo não equivale automaticamente a zero.
15. Tokens/URLs/metadados identificam uma versão e não migram automaticamente para outro conteúdo.
16. Fixo cadastrado não é saldo pendente; liquidação precisa de evidência compatível.
17. Os acompanhamentos individuais aparecem uma única vez no subtotal, e a rubrica especial não substitui os demais extras.
18. Golden histórica e expectativa alvo permanecem separadas e identificadas.

## 8. Testes de aceite a implementar na FASE 1

Testes abaixo descrevem comportamento, não implementação. Referências reais usam os dados congelados da FASE 0. Cenários de mutação, override, NULL, extras e ciclo documental são **cenários futuros de teste isolado**, não novos registros reais encontrados ou instruções para alterar produção.

| Teste                           | Given / contexto                                                                                                         | Expected / aceite                                                                                                                                                               |
| ------------------------------- | ------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| T01 — Tarefa normal             | CASE_NORMAL_001, FI 120437, base 50, sem ledger                                                                          | Elegível setembro; saldo/serviço 50; identidade/razão no snapshot                                                                                                               |
| T02 — Parcial devido            | CASE_PARCIAL_001, FI 102804, base 250, parcial 125, elegível                                                             | Saldo 125 reconhecido; flag/nome parcial não elimina valor; eventual inconsistência separada vira pendência                                                                     |
| T03 — Quitação                  | CASE_PAGO_001, base 380/ledger 380, parcial_count=1                                                                      | Saldo 0, sem serviço/linha financeira zero; item explicável como quitado                                                                                                        |
| T04 — Comissão 80               | Marcio8/setembro, FI 120172, origem 40/base 300                                                                          | Comissão 80 e saldo 80; nenhuma rota retorna 300 para esse direito                                                                                                              |
| T05 — Dois beneficiários        | CASE_COMISSAO_PAGA_001, FI 118830, pagamento 300 ao 40 e 80 ao 8                                                         | Ledgers separados; comissão quitada/saldo 0; remuneração não quita outro beneficiário                                                                                           |
| T06 — Qualquer log elegível     | CASE_LOG_001, FI 120385, logs elegíveis seguidos de Em andamento                                                         | Elegível setembro e saldo 300; último movimento não apaga elegibilidade                                                                                                         |
| T07 — Prazo real da FA          | CASE_ANIMACAO_001, FA 534, prazo agosto/data_anima setembro, pago 100                                                    | Elegível agosto, não setembro; saldo 0 com ledger congelado, sem nova cobrança 100                                                                                              |
| T08 — Prazo versus operação     | Cenário aprovado: prazo 10/10/2026, data_anima 29/09/2026, status elegível                                               | Competência outubro; comparação da data operacional não inclui em setembro                                                                                                      |
| T09 — Pago sem ledger           | AC 617 marcado pago sem ledger compatível                                                                                | Pendência do colaborador 1; não liberar adendo definitivo atribuindo 0 ou 10 como solução automática                                                                            |
| T10 — Soma Nicolle              | Cenário resolvido com serviços S, acompanhamentos A, fixo pendente F, extras definidos E                                 | Total=S+A+F+4000+E; A contado uma vez; E não descartado; SEM_BONUS manual mantém 4000                                                                                           |
| T11 — Fixo congelado            | Cadastro 4600 na preparação; cadastro muda depois para outro valor                                                       | Revisão antiga mantém 4600; nova revisão explícita pode usar novo cadastro                                                                                                      |
| T12 — Quitação do fixo          | Evidência válida de fixo 4600 liquidado, vinculada conforme D01                                                          | Fixo aplicável 4600, pago 4600, pendente 0; resumo não reapresenta 4600 como devido; não deduzir essa prova do caso real só pelo status pago                                    |
| T13 — Zero, ausência e override | Zero conhecido; ausência/NULL; override de original para substituto com autor/motivo                                     | Zero permanece conhecido; ausência distinta; override preserva ambos valores/ator/instante/motivo; política de ausência conforme D03                                            |
| T14 — Todas as telas            | Mesmo snapshot solicitado/exibido com busca, função, obra, divergência, Pagos/A pagar, paginação e checkboxes diferentes | Mesmos IDs financeiros, subtotais e total; nenhum índice DOM participa do cálculo                                                                                               |
| T15 — Estados de extras         | Ausência de decisão, declaração SEM_BONUS e registros DEFINIDO                                                           | Estados distintos; ausência não vira 0; registros completos congelados; posterior extra exige revisão nova; gating conforme D02/D06                                             |
| T16 — Divergência individual    | CASE_DIVERGENCIA_001, base 150/pago 275, legada false, mais 15 colaboradores aptos                                       | Excesso 125 e pendência do 33; demais 15 preparam normalmente; nenhum ajuste financeiro automático                                                                              |
| T17 — Vazio                     | Lista canônica sem serviços; UI manda itens=[]                                                                           | Nenhum fallback B; fixo/extras não somem; UI vazia não determina o conjunto canônico do backend                                                                                 |
| T18 — Entre meses               | CASE_ENTRE_MESES_001, base 300, pagos 125+150, completa_count=1; comissão 80 para outro beneficiário                     | Saldo/serviço 25 reconhecido; comissão não descontada da remuneração                                                                                                            |
| T19 — Snapshot e preview        | Preparação seguida de pagamento/valor/extra/fixo alterados no teste isolado                                              | Snapshot/hash/composição antigos imutáveis; preview não altera definitivo, data_envio, assinatura ou pagamento; nova preparação cria revisão distinta                           |
| T20 — Confirmação               | Usuário visualiza versão V1; DB atual ou versão V2 têm valores diferentes                                                | Confirmar V1 registra exatamente V1/hash/arquivo/itens/fixo/extras/total/ator/instante; não confirma V2 silenciosamente nem apenas move arquivo                                 |
| T21 — Regeneração e estados     | GERADO, ENVIADO, VISUALIZADO, ASSINADO, RECUSADO, EXPIRADO em fixtures isoladas                                          | Nova revisão preserva anterior quando permitida; enviado/visualizado exigem ação/vínculo; assinado intocado; recusado/expirado novo processo; sem reuso automático de token/URL |
| T22 — Fachada e classe          | Caso real FI 117304/comissão 100 quitada; cenários Fachada com embasamento e igual ID em animacao/FA                     | Critério 100/80 preservado; embasamento→80; quitação→sem serviço; origens/beneficiários/classes distintas não colidem                                                           |

O teste de lista de status também deve assegurar que nenhum novo status foi introduzido. A comparação futura deve verificar IDs, cardinalidade, elegibilidade e componentes do total no servidor, além de valores de amostras. Os 1.312 asserts originais não validam sozinhos queries de elegibilidade, DOM, revisão/confirmar ou versão das regras; manter seu escopo histórico e adicionar testes alvo separados.

Não “ajustar” a golden para fazer os novos testes passarem. Nos testes alvo, marcar as diferenças deliberadas com R correspondente e preservar os resultados históricos para consulta. Valores globais novos exigem inputs completos/pendências resolvidas; não fixar expectativas numéricas incompletas por dedução.

## 9. D01–D07 resolvidas — implementação por etapa

Todas as decisões abaixo foram aprovadas no pedido da FASE 1A. Não são mais bloqueios gerais ou valores a inferir.

| ID | Decisão aprovada | Implementação |
|---|---|---|
| D01 | Fixo só é pago com evidência financeira explicitamente vinculada a colaborador + competência + direito de fixo. Status pago, assinatura ou total agregado isolados não provam liquidação. Legado sem vínculo inequívoco recebe LIQUIDACAO_INDETERMINADA e futura reconciliação manual auditável; não inferir pago=0 nem pago=fixo. | Principalmente 1B; sem cálculo de fixo na 1A. |
| D02 | Extras PENDENTE permitem componentes conhecidos, com PENDENTE_DE_BONUS e total final incompleto; não permitem confirmar/enviar. Não bloqueiam outros colaboradores. Após SEM_BONUS/DEFINIDO preparar nova revisão completa. | 1B/1C. |
| D03 | NULL/ausência significa fixo NÃO DEFINIDO, distinto de zero conhecido. Antes do definitivo exigir SEM_VALOR_FIXO ou valor informado. | 1B. |
| D04 | Preparação/leitura seguem autorização atual de Pagamento. Decisões financeiras/override/resolução/reconciliação exigem nível administrativo apropriado e usuário, instante, motivo, antes/depois e evidência quando aplicável. | Ações não implementadas na 1A; futura integração deve manter autorização. |
| D05 | funcao_animacao é a origem canônica. animacao legada não é convertida/somada/equiparada automaticamente; sobreposição ou pagamento sem vínculo inequívoco exige diagnóstico/pendência, nunca cobrança dupla. | Diagnóstico de serviços na 1A; reconciliação manual posterior. |
| D06 | Extra manual exige valor > 0. Zero é SEM_BONUS; negativo não é bônus e fica fora do fluxo até regra de desconto/ajuste. Entrada monetária válida normalizada no backend, sem autoridade de parseFloat. | 1B. |
| D07 | ENVIADO/VISUALIZADO permanecem históricos imutáveis. Nova revisão explícita sem sobrescrita, reuso de token/URL ou transferência de assinatura; novo envio é novo processo. | 1C ou posterior. |

As referências a D01–D07 nas tabelas de casos/testes indicam essas decisões resolvidas, não lacunas desconhecidas. CASE_FIXO_001 não passa a provar quitação por causa dessa aprovação: a situação legada sem evidência continua LIQUIDACAO_INDETERMINADA. No mapa de bugs, o achado de formatos/negativos de extras passa de D para **A — correção autorizada por D06/R12, na FASE 1B**.

Estratégia de consistência, centavos, hash, API, storage e schema físico são escolhas técnicas das etapas pertinentes. Não alterar tarifas, status elegíveis ou predicado de pagamentos aplicáveis por conta própria. A origem financeira legada continua distinta e sua pendência não é permissão para remapear pagamentos.

## 10. Riscos e condição para iniciar a FASE 1A

**FASE 1A PODE INICIAR.** O contrato funcional está suficientemente definido. D01/D02 não são mais decisões desconhecidas. O escopo autorizado é motor backend reutilizável de serviços, puro quando possível, repositório somente leitura com visão consistente, testes alvo separados e comparação shadow. Não substituir ainda getColaborador, gerar_adendo, AdendoLocalService, financeiro_v2 ou a tela.

Riscos conhecidos: flags pagas sem ledger compatível; ausência de liquidação discriminada de fixo; histórico sem itens/extras; mudança de competência da FA; observação livre para classificação de pagamentos; animação legada sobreposta; concorrência durante leitura; consumidores antigos e tokens/arquivos vinculados ao fluxo atual. Exigir diagnóstico e evidência, sem corrigir registros para obter um total.

Fixo, bônus/extras e configuração definitiva dos R$ 4.000 ficam na 1B. Snapshot/versionamento documental, preview, confirmação, envio e assinatura ficam na 1C ou posterior. A leitura consistente e identificação da versão das regras da 1A não equivalem à implementação dessas revisões documentais. O subtotal de serviços da 1A não é total final do adendo.

A validação prática de UI/PDF pendente da FASE 0 permanece como limite de verificação da futura integração; não autoriza gerar adendos reais nesta etapa. A autorização para começar a 1A não significa automação mensal pronta ou migração de produção aprovada.
