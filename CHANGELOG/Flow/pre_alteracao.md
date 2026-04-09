# Processo de Pré-Alteração de Imagens

## 1. Objetivo

Estruturar o fluxo entre a finalização dos comentários do cliente e o início da execução das alterações, garantindo:

- Eliminação de ambiguidades
- Redução de retrabalho
- Aumento de velocidade na execução
- Clareza total para o executor
- Previsibilidade na definição de prazos

---

## 2. Escopo

Este processo se aplica a todas as imagens após:

- Entrega inicial (R00)
- Status: RVW (em revisão pelo cliente)

---

## 3. Visão Geral do Fluxo

1. Entrega inicial → R00 + RVW
2. Cliente finaliza comentários → RVW_DONE
3. Pré-análise → PRE_ALT
4. Consolidação e esclarecimento → cliente
5. Análise finalizada → READY_FOR_PLANNING
6. Planejamento → definição de status final (EF ou R01)

---

## 4. Status do Processo

### 4.1 RVW (Review)

- Imagem entregue ao cliente
- Aguardando comentários

---

### 4.2 RVW_DONE (Review Concluído)

- Cliente finalizou todos os comentários
- Pronto para iniciar pré-análise

---

### 4.3 PRE_ALT (Pré-Alteração)

**Responsável: Nicolle**

Atividades:

- Leitura completa dos comentários
- Classificação das alterações
- Identificação de dúvidas
- Estruturação das ações

---

### 4.4 READY_FOR_PLANNING

- Todas as imagens foram analisadas
- Dúvidas resolvidas com cliente
- Escopo definido e fechado

**Trigger:**

- Notificação para o gestor de projetos

---

### 4.5 Execução

Após planejamento:

- **EF (Execução Final)** → alterações simples
- **R01 (Nova rodada)** → alterações médias e complexas

---

## 5. Papel dos Responsáveis

### 5.1 Nicolle (Arquiteta / Pré-Análise)

Responsável por:

- Interpretar comentários do cliente
- Classificar complexidade
- Traduzir comentários em ações objetivas
- Consolidar dúvidas
- Interagir com cliente (quando necessário)
- Definir escopo final antes da execução

---

### 5.2 Gestor de Projetos

Responsável por:

- Receber análise estruturada
- Definir prazos
- Definir prioridade
- Direcionar imagens para EF ou R01
- Atualizar status

---

### 5.3 Executor

Responsável por:

- Executar alterações sem necessidade de interpretação
- Seguir exatamente o que foi definido na pré-análise

---

## 6. Classificação das Alterações

### 6.1 Por Complexidade

- **S (Simples)**
  - Ajustes diretos
  - Não exigem decisão
  - Ex: cor, intensidade, pequenos elementos

- **M (Médio)**
  - Exigem interpretação leve
  - Impacto moderado na imagem

- **C (Complexo)**
  - Envolvem arquitetura ou layout
  - Podem impactar múltiplas imagens

---

### 6.2 Por Tipo

- Arquitetura
- Layout / Composição
- Materiais / Texturas
- Iluminação
- Elementos decorativos
- Correções técnicas

---

## 7. Estrutura da Pré-Análise

Para cada imagem:

### 7.1 Classificação Geral

- Complexidade: S / M / C
- Tipo predominante

---

### 7.2 Lista de Alterações

Formato:

| ID  | Tipo | Complexidade | Ação | Observação |
| --- | ---- | ------------ | ---- | ---------- |

---

### 7.3 Avaliação Executiva

- Esforço: Baixo / Médio / Alto
- Risco: Baixo / Médio / Alto
- Dependência externa: Sim / Não

---

## 8. Consolidação de Dúvidas

### Regra obrigatória:

- Todas as dúvidas devem ser consolidadas antes de enviar ao cliente
- Não enviar dúvidas isoladas

---

### Abordagem recomendada:

Sempre propor solução:

**Errado:**

- “Cliente quis dizer o que?”

**Correto:**

- “Sugestão: aplicar solução X baseada em Y. Confirmar?”

---

## 9. Definição Final de Escopo

Após retorno do cliente:

- Todas as alterações devem estar:
  - Claras
  - Objetivas
  - Sem necessidade de interpretação

---

## 10. Complexidade (Regra de Mudança)

### Fase 1 — Pré-esclarecimento

- Complexidade é provisória
- Pode mudar

### Fase 2 — Pós-esclarecimento

- Complexidade é final
- Não deve mudar

**Se mudar após isso → falha no processo**

---

## 11. Planejamento (Gestor de Projetos)

Entrada:

- Pré-análise finalizada
- Escopo fechado

Decisões:

- Direcionamento:
  - S → EF
  - M/C → R01

- Definição de prazo
- Definição de prioridade

---

## 12. Notificações

O gestor deve ser notificado quando:

- Todas as imagens estiverem em **READY_FOR_PLANNING**

---

## 13. SLA (Sugestão)

- Pré-análise: até X horas após RVW_DONE
- Consolidação com cliente: variável
- Planejamento: imediato após READY

---

## 14. Regras Críticas do Processo

- Executor não interpreta comentários
- Gestor não lê comentário bruto
- Nicolle não envia dúvidas fragmentadas
- Escopo deve estar fechado antes da execução
- Complexidade não muda após definição final

---

## 15. Riscos e Pontos de Atenção

### 15.1 Gargalo na pré-análise

- Mitigação: padronização e possível backup

---

### 15.2 Atraso invisível

- Mitigação: SLA claro para PRE_ALT

---

### 15.3 Overprocess

- Mitigação: fast track para alterações simples

---

## 16. Otimização Futura

- Nicolle sugerir prazo base por imagem
- Automatização de status
- Filtros por complexidade
- Métricas de performance:
  - Tempo de execução
  - Retrabalho
  - Tempo de ciclo total

---
