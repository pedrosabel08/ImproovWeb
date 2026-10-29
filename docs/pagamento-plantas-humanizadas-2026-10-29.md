# Tarifas de planta humanizada no fechamento mensal

## Regras implementadas

| Tipo | Tarifa integral |
| --- | ---: |
| Apartamento individual | R$ 130,00 |
| Apartamento variação / repetição | R$ 80,00 |
| Pavimento de apartamentos / garagem | R$ 150,00 |
| Pavimento de garagem repetido | R$ 80,00 |
| Lazer / implantação grande | R$ 200,00 |

O catálogo fica centralizado em `Pagamento/services/FechamentoPlantaRules.php`. A conferência é por categoria, não por uma lista genérica de valores permitidos: R$ 150,00 em um apartamento individual também diverge.

Aplica-se à remuneração de Finalização (`funcao_id=4`) em imagens do tipo Planta Humanizada. Mantém também o reconhecimento dos colaboradores 12 e 24 utilizado pela tela antiga. Não aplica a tarifa de planta à comissão do gestor nem a outras funções. Mantém os motores legados e o bônus R0 existentes.

O nome da imagem fornece somente uma sugestão de tipo. Repetições de garagem são reconhecidas mesmo sem a palavra “pavimento”. Nomes sem indicação suficiente exigem escolher o tipo. A categoria pode ser conferida no seletor; quando confirmada, fica registrada no journal do fechamento.

## Fluxo

O modal de serviços mostra tipo sugerido/confirmado, tarifa padrão, valor atual, pagamentos e saldo. Diferenças ou tipo indefinido deixam o item em atenção e impedem gerar o adendo variável até resolver ou retirar legitimamente a tarefa.

- **Aplicar tarifa padrão:** seleciona o tipo e aplica o valor do catálogo no servidor. Não aceita um valor arbitrário do cliente nem pede justificativa livre. Gera motivo institucional automaticamente, com tipo, e preserva autor, instante, antes/depois e versão da tabela.
- **Usar valor excepcional:** exige tipo, valor e motivo escrito. Identifica o item como **Exceção justificada** e usa o valor aprovado na revisão e no PDF.
- **Restaurar valor:** remove a decisão vigente em uma nova revisão. Se o original voltar a divergir, o item retorna à atenção.

Reutiliza a decisão `SERVICOS` e os JSONs auditados existentes; sem nova tabela ou migration. A tarifa da origem, os lançamentos e as revisões/documentos anteriores não são regravados. Parcelas já pagas reduzem o saldo; nenhum ajuste pode reduzir o total abaixo do já pago. Tarefas quitadas são somente consultadas e eventuais diferenças aparecem como históricas, sem recalcular pagamentos. Alteração posterior na base ou no ledger invalida um ajuste antigo e exige nova conferência.

## Validação

- `php tests/fechamento_plantas_rules_test.php`: 39 verificações das cinco categorias, sugestões, nomes ambíguos, valores divergentes, motivo automático, exceções, escopo, comissões, parcelas, quitados e payloads indevidos.
- `php tests/fechamento_plantas_integration_test.php`: 23 verificações com MySQL 8 isolado, incluindo journals, snapshot anterior, PDF nas tarifas padrão/excepcional, retry, restauração e origem alterada.
- `php tests/fechamento_plantas_http_test.php`: aplicação sem motivo livre, cálculo server-side, idempotência, rejeição de valor arbitrário, exceção sem motivo, CSRF e restauração.
- Regressões: 22 cenários de edição por tarefa, 111 do ciclo financeiro, 30 de pendências mensais e 53 de regras mensais passaram.
- Metadata: 830 verificações sem falhas; PHP lint, JavaScript e diff sem erros.

Escritas somente no MySQL 8.0.42 de homologação em loopback 3320, com banco e documentos sintéticos. A navegação oficial HTTPS autenticada por Financeiro → Pagamento → Fechamento foi conferida em leitura. No navegador interno, a fixture local confirmou a correção de R$ 250,00 para R$ 130,00 sem motivo livre, a regeneração do PDF e a exigência de motivo para voltar a R$ 250,00 como exceção.

Capturas em `output/ui/plantas-20261029/`: desktop 1920×1080, notebook 1366×768, iPad 1024×768 e 768×1024, mobile 390×844 e tema claro. Modal e formulário sem transbordamento horizontal nas resoluções verificadas.

Ao finalizar, os bancos sintéticos foram removidos somente após conferir o datadir exato da instância temporária, e o MySQL de homologação foi desligado. Os manifestos versionados de fixtures foram restaurados. Os dez arquivos do pacote foram conferidos por SHA-256 contra as fontes locais.

## Publicação

O pacote `output/pagamento-plantas-20261029.zip` contém o conjunto completo desta melhoria e das mudanças anteriores de serviços/PDF:

```text
Pagamento/api/FechamentoHttp.php
Pagamento/fechamento.php
Pagamento/mensal.css
Pagamento/mensal.js
Pagamento/services/FechamentoFinanceiroRules.php
Pagamento/services/FechamentoPlantaRules.php
Pagamento/services/FechamentoRetiradaRules.php
Pagamento/services/FechamentoRevisaoService.php
config/page_metadata.php
Contratos/services/ContratoPdfService.php
```

Publicar os arquivos em conjunto, preservando backup e observando OPcache quando aplicável. Não foi realizado deploy nem alteração de pagamentos/valores reais. Depois da publicação, usar **Atualizar valores** nos colaboradores com revisões antigas para incorporar a conferência de tarifa e abrir **Ver detalhes dos serviços** para resolver os itens indicados. Documentos já confirmados permanecem históricos.
