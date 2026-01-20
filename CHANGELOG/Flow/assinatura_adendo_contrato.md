# Automatização: Contratos e Adendos (ZapSign)

> Resumo das decisões, fluxo e próximos passos para automatizar criação e envio
> de contratos e adendos via ZapSign, conforme conversa.

## Objetivo
- Automatizar geração e envio de contratos e adendos por e-mail via ZapSign.
- Acompanhar status (enviado, aguardando assinatura, assinado, recusado).
- Bloquear acesso ao sistema enquanto contrato do colaborador não estiver assinado.
- Tornar o processo de adendo mensal rastreável e reduzir trabalho manual do financeiro.

## Decisões tomadas (essenciais)
- Canal de envio: **somente e-mail**.
- Documento a enviar: **PDF gerado pelo sistema** (manter gerador atual, upload para ZapSign).
- Assinantes: **empresa + colaborador** (ordem recomendada: empresa assina primeiro).
- No momento não há valores de tarefas automatizados — o MVP terá preenchimento manual.

## Entidades principais
- Template de Documento: tipo (`CONTRATO`, `ADENDO`), versão, mapeamento de campos.
- Documento (instância): `document_id`, `colaborador_id`, `competencia` (adendo), `status`, snapshot dos campos, `zap_sign_id`, timestamps.
- Aprovação: `aprovador_id`, `data`, `obs`.
- Logs/Eventos: webhook events e histórico de ações.

## Fluxo — Contrato (recomendado, simples)
1. Cadastro/atualização do colaborador com dados mínimos (nome, CPF, e-mail).
2. Gerar contrato (PDF) automaticamente ao criar/atualizar colaborador ou via ação.
3. Enviar para assinatura via ZapSign (empresa primeiro, depois colaborador).
4. Receber evento do webhook e marcar `assinado` quando ambos assinarem.
5. Bloquear acesso ao sistema para usuários sem contrato assinado (liberações: logout, suporte, atualização de cadastro, tela de assinatura).

## Fluxo — Adendo (mensal, com conferência)
1. Rotina no dia 1º: criar lote `Adendo - competência` e gerar rascunhos `ADENDO` para cada colaborador ativo.
2. Tela de conferência (usuário/gestor): preencher/ajustar valor total do mês (MVP) e observar.
3. Ao aprovar, sistema gera PDF do adendo e envia via ZapSign para assinatura (empresa + colaborador).
4. Webhook atualiza status (`aguardando_assinatura` → `assinado`).
5. (Opcional) Recolher nota fiscal anexada; quando `assinado + nota anexada` ⇒ sinalizar `liberado para pagamento`.

## Integração com ZapSign (módulos mínimos)
- Client/Service PHP:
  - `createDocument($pdfPath, $signatarios, $order)` → retorna `zap_document_id`.
  - `getDocumentStatus($zap_document_id)` → status para reconciliação.
  - `downloadSignedPdf($zap_document_id)` → armazenar prova no sistema.
- Webhook endpoint (público): receber eventos de assinatura/recusa, validar e atualizar documento.
- Polling/fallback: job que reconcilia documentos `aguardando_assinatura` caso webhook falhe.

## Pontos críticos / Riscos
- Webhook exige URL pública (produção) — teste local com ngrok/forwarding apenas para homolog.
- Segurança: tokens da API fora do repositório, acesso restrito a PDFs assinados, logs de auditoria.
- Versão do contrato: alterações futuras devem manter histórico (quem assinou qual versão).
- Dados pessoais (CPF) — atenção à LGPD para armazenamento e acesso.

## MVP sugerido (prioridades)
1. Contrato end-to-end: gerar PDF → enviar ZapSign → webhook atualiza → bloquear acesso enquanto não assinado.
2. Adendo (MVP manual): criar rascunhos no dia 1, tela de conferência para preenchimento rápido, aprovação e envio automático para assinatura, painel de acompanhamento.
3. Evolução: automatizar origem dos valores (tarefas/imagens), anexar notas fiscais automaticamente.

## Tela / UX mínima necessária
- Painel `Adendos`: lista por competência, status (rascunho, pendente aprovação, enviado, assinado).
- Formulário de conferência por colaborador (campo `valor_total`, observações).
- Página `Contrato pendente` exibida ao usuário não-assinado com botão `Assinar agora`.
- Painel `Assinaturas` para financeiro/gestor: filtros por competência, quem assinou, baixar PDF assinado.

## Perguntas ainda abertas
- Confirmar se o valor do adendo por colaborador é um único total por mês (A) ou soma de várias linhas (B).
  - Se for (A) → MVP muito rápido.
  - Se for (B) → tela de conferência precisa linhas + total.

## Próximos passos técnicos (curto prazo)
- Implementar endpoint de webhook e handlers de evento.
- Implementar serviço PHP para criar documento no ZapSign (usar o PDF atual).
- Implementar rotina diária para criar rascunhos de adendo (job/cron).
- Implementar checagem no `verifica_acesso.php` para bloquear funcionalidades sem contrato assinado.

---
Gerado a partir da conversa em 2026-01-20.
