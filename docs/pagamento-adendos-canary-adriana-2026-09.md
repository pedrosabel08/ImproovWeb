# Canary histórico — Adriana, setembro/2026

Registro da etapa inicial somente leitura. A continuação, com referência financeira validada pelo responsável e decisão explícita SEM_BONUS, foi concluída em [Canary Adriana até preview](pagamento-adendos-canary-adriana-preview.md). As conclusões abaixo preservam o estado anterior a essa autorização.

**CANARY PARCIAL · PENDÊNCIA: BONUS_PENDENTE. A referência histórica requer investigação. FLAG OFF.** Nenhum write financeiro/documental ou ativação foi realizado.

Pré-flight: deployment 1C-A/1C-B OK, 8 tabelas / 14 triggers, storage OK. Backup de 97.143.354 bytes preservado, SHA-256 `c58d9613518e57ecf22158fb5db904112a2f9dca14912e57db0464906209e667`. Configuração Apache privada continua em `PAGAMENTO_FECHAMENTO_V2_ENABLED=0`. Legado autenticado de Adriana/setembro continua carregando: 64 itens A pagar, 38 Pagos, zero Divergências; sem erro visível e sem link da nova tela.

Adriana identificada inequivocamente: `colaborador.idcolaborador=14`, único cadastro com esse nome. Histórico correspondente: **adendo #150, competência 2026-09, estado gerado, total R$ 0,00, fixo R$ 0,00**. [PDF histórico existente](../Contratos/gerados/adendos/temp/ADENDO_CONTRATUAL_Adriana_Tavares_Halmenschlager_SETEMBRO_2026.pdf) disponível (uma página, 201.201 bytes). Texto extraído e página renderizada efetivamente conferidos: tabela com uma linha vazia, sem serviços identificáveis; cláusula de total R$ 0,00. Não há discriminação recuperável de bônus/extras nesse payload. Esse conteúdo não prova uma decisão SEM_BONUS nem determina regras do motor.

SHA-256 do PDF: `c75e5a41289ae21d01846466224dd0a0f5307b36df3160290113094e69be7dba`. PDF e payload foram conferidos novamente no encerramento e permaneceram iguais. Intermediários de leitura/render estão em diretório privado; o histórico original não foi alterado. A coincidência com o período de erro do endpoint legado não foi tomada como prova da causa do documento vazio.

Foi executado somente o leitor paralelo 1A/1B para verificar a adequação da referência antes de habilitar o fluxo. Transação `START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY`, encerrada com `ROLLBACK`; nenhuma revisão persistida. Snapshot: **05/10/2026 21:49:06 -03:00**. `revision_id=null`, versão inexistente.

| COMPONENTE | ADENDO ANTIGO | NOVO MOTOR (somente leitura) | DIFERENÇA | EXPLICAÇÃO |
|---|---|---|---|---|
| Quantidade de serviços devidos | Nenhum serviço identificável | 64 serviços | 64 sem correspondência histórica | **DIFERENÇA A INVESTIGAR:** documento vazio impede comparação por serviço/ID. |
| Subtotal de serviços | R$ 0,00 no documento | R$ 3.840,00; subtotal completo | R$ 3.840,00 | **DIFERENÇA A INVESTIGAR:** faltam as linhas históricas; o legado atual também exibe R$ 3.840,00 A pagar. |
| Valores por serviço | Não discriminados | Valores calculados pelo motor; legado atual mostra 64 × R$ 60,00 | Não comparável individualmente | **DIFERENÇA A INVESTIGAR:** não atribuir a ausência de linhas a uma regra financeira. |
| Valor fixo | R$ 0,00 | Configurado/utilizado/pago/saldo: R$ 0,00 | R$ 0,00 | **IGUAL:** zero conhecido; liquidação NAO_APLICAVEL_VALOR_ZERO. |
| Bônus/extras | Não discriminados; decisão ausente | PENDENTE; subtotal null | Não determinada | **DIFERENÇA ESPERADA:** ausência de registro histórico não equivale a SEM_BONUS. Requer decisão explícita. |
| Especial / demais rubricas | Nenhuma rubrica identificável | Especial não aplicável a Adriana, R$ 0,00 | Sem diferença monetária demonstrada | **DIFERENÇA ESPERADA:** o motor explicita a não aplicação; não reconstrói rubricas ausentes do histórico. |
| Total | R$ 0,00 | null | Não calculável | **DIFERENÇA ESPERADA:** BONUS_PENDENTE conserva total indeterminado; a divergência dos serviços segue sob investigação. |

Resultado do leitor: **PENDENTE_BONUS**, 64 DEVIDO, 38 QUITADO, 59 NAO_ELEGIVEL; componentes conhecidos **R$ 3.840,00**; única pendência `BONUS_PENDENTE`. Fixo e especial não têm pendências. Essa leitura não é uma revisão/preview preparado pelo endpoint novo.

Parada antes de ativação/preparação: a referência não permite comprovar a equivalência solicitada, e já há pendência explícita no motor. Não se inferiu bônus, não se registrou decisão para obter igualdade e não se selecionou outra competência. Competências recentes com histórico de Adriana: agosto/2026 (#131, gerado), julho/2026 (#114, assinado), junho/2026 (#98, assinado); nenhuma foi testada como alternativa.

Necessário informar: **referência válida de setembro/2026**, ou confirmar que #150 vazio é intencionalmente o gabarito para investigar a diferença; e **decisão de bônus de setembro**, explicitamente SEM_BONUS ou rubricas com categoria/valor/evidência. A decisão só poderá ser registrada depois de esclarecer o gabarito.

As oito tabelas novas continuam vazias. Não há revision_id, decisão, preview, documento definitivo, envio, assinatura, alteração de pagamento ou FASE 1E. [Manifesto sanitizado](evidence/pagamento-canary-adriana-2026-09.json).
