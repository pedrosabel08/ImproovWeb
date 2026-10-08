# Canary Adriana / 2026-09 até preview

**CANARY ADRIANA APROVADO ATÉ PREVIEW.** Referência financeira: decisão explícita do responsável fornecida nesta continuação. O adendo histórico #150 é documentalmente incorreto e foi excluído do gabarito financeiro; seu payload e PDF foram preservados.

Execução em 05/10/2026, encerrada às 22:16 -03:00. Navegação pela URL oficial `https://improov/ImproovWeb/`, login autorizado, Financeiro → Pagamento → Adriana / Setembro 2026 → Abrir novo fechamento individual. Atos financeiros/documentais executados exclusivamente pela nova UI e seus endpoints autenticados, com CSRF normal. O helper de evidência executa somente SELECT em transação read-only.

Antes do primeiro write: readiness exit 0; 8 tabelas e 14 triggers; componentes financeiro/documental e storage privado OK; legado de Adriana acessível; flag OFF; backup completo privado preservado. Backup `flowdb_pre_fechamento_2026-10-05_20-15-50_2c42b1.sql`, 97.143.354 bytes, SHA-256 `c58d9613518e57ecf22158fb5db904112a2f9dca14912e57db0464906209e667`.

Flag ativada somente no include Apache privado já implantado: `PAGAMENTO_FECHAMENTO_V2_ENABLED=1`. `httpd -t`: exit 0, Syntax OK. Graceful reload pelo evento nativo da instância console homologada; parent PID 2960 mantido, child 23240 substituído por 1704. Configuração principal e ACL privada preservadas; cópia anterior da configuração mantida em storage privado. Após reload, link/tela novos apareceram e o legado continuou carregando Adriana e R$ 3.840,00 a pagar.

Fechamento **1**, colaborador **14**, competência **2026-09**, ator autenticado **usuario_id=1**:

| Etapa | ID / versão | Estado | Componentes e pendências |
|---|---|---|---|
| Preparação | revision_id **1**, V1 | PENDENTE / PENDENTE_BONUS | 64 serviços de R$ 60,00; serviços R$ 3.840,00; fixo/especial zero; extras null; conhecidos R$ 3.840,00; total null; apenas BONUS_PENDENTE |
| Decisão explícita | decisão **1**, tipo BONUS | SEM_BONUS | Motivo registra a autoridade do responsável, inexistência de bônus/extras e limite até preview; nenhum extra criado; expected_version **1** |
| Revisão decorrente | revision_id **2**, V2 | **PRONTO** | Serviços R$ 3.840,00; fixo/especial/extras zero; total R$ 3.840,00; sem pendências |
| Documento | document_id **1**, revisão **2** | **PREVIEW** | PDF de 3 páginas, 209.821 bytes; geração e visualização concluídas; nenhuma confirmação |

| Componente | Esperado | Obtido no motor / PDF | Diferença | Classificação |
|---|---|---|---|---|
| Serviços devidos | 64 | 64; PDF com sequência de linhas 1 a 64 | 0 | IGUAL |
| Valor por serviço | R$ 60,00 | R$ 60,00 em todas as 64 linhas | R$ 0,00 | IGUAL |
| Subtotal serviços | R$ 3.840,00 | R$ 3.840,00 | R$ 0,00 | IGUAL |
| Fixo | R$ 0,00 | R$ 0,00; rubrica Valor fixo no PDF | R$ 0,00 | IGUAL |
| Acompanhamento especial | Não aplicável / R$ 0,00 | Não aplicável / R$ 0,00; sem rubrica indevida no PDF | R$ 0,00 | IGUAL |
| Bônus/extras | SEM_BONUS / R$ 0,00 | SEM_BONUS / R$ 0,00; zero extras | R$ 0,00 | IGUAL |
| Total | **R$ 3.840,00** | **R$ 3.840,00** | **R$ 0,00** | **IGUAL** |

PDF realmente aberto pelo botão **Visualizar PDF** na nova UI: primeira página renderizada; páginas 2 e 3 também visitadas. Nome de Adriana, título SETEMBRO 2026, serviços, rubrica de fixo zero e cláusula de total conferidos. Valor por extenso: **três mil oitocentos e quarenta reais**. As três páginas foram também renderizadas com Poppler e inspecionadas; texto extraído validou exatamente 64 linhas numeradas com valor R$ 60,00. Intermediários com conteúdo jurídico permanecem privados.

`pdf_hash` persistido e SHA-256 dos bytes privados são iguais:

`fdb781f4209d8ee6200b9b36b2f90d0e5d43fa5d4be3ba7eca2189ab132b1b6b`

Documento e modelo vinculados à revision_id **2**; `financial_snapshot_hash` corresponde ao snapshot imutável V2:

`788f6b4cb8c9fceb9e6256d14c204ecb30ec3dc870b52422d9872f0f5d0fd19e`

Auditoria e idempotência: operações PREPARAR, BONUS, GERAR e VISUALIZAR registradas normalmente, cada qual com sua chave UUID nova. Nenhuma resposta incerta/timeout de operação financeira ou documental, nenhum retry e nenhum preview duplicado. Há exatamente duas revisões e um preview neste fechamento. Chaves e expected_version constam no manifesto; não são credenciais.

**Não houve confirmação definitiva.** `estado=PREVIEW`, `confirmado_por=null`, `confirmado_em=null`; arquivo definitivo ausente; nenhuma operação CONFIRMAR. O botão Confirmar este documento não foi acionado. Nenhum envio, assinatura ou pagamento foi executado. Hashes dos 590 itens de ledger e dos 11 pagamentos de Adriana permanecem iguais ao baseline; PDF, payload e estado do adendo #150 permanecem iguais. Nenhum outro colaborador foi testado e FASE 1E não foi iniciada.

Encerramento: readiness novamente exit 0; storage OK; backup completo com mesmo SHA; sem erro de console ou erro estrutural/operacional do fluxo. **Flag mantida ON** conforme autorização, sem risco identificado nesta execução. A tela de resultado foi preservada aberta, e o preview permanece registrado como evidência.

Evidências: [gates iniciais](evidence/pagamento-canary-adriana-preview-gates.json), [baseline](evidence/pagamento-canary-adriana-preview-before.json), [V1](evidence/pagamento-canary-adriana-preview-initial.json), [SEM_BONUS e V2](evidence/pagamento-canary-adriana-preview-bonus.json), [preview e auditoria](evidence/pagamento-canary-adriana-preview-preview.json), [conferência PDF](evidence/pagamento-canary-adriana-preview-pdf.json), [gates finais](evidence/pagamento-canary-adriana-preview-final-gates.json).

![Resultado do canary: V2 Pronto, total R$ 3.840,00 e Preview #1](evidence/pagamento-canary-adriana-preview.png)
