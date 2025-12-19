
# SOE – Sistema Operacional Essencial | Improov
## Resumo Técnico para Implementação no Flow

---

## 1. Objetivo do SOE

Criar um sistema de gestão operacional que permita:

- Planejar produção semanal com previsibilidade  
- Antecipar riscos de atraso  
- Balancear carga entre etapas e colaboradores  
- Priorizar projetos de forma objetiva  
- Tomar decisões baseadas em dados, não feeling  

---

## 2. Estrutura Base da Produção

### 2.1 Hierarquia de Trabalho

Projeto

└── Imagens (≈30 por projeto)

└── Etapas (7)


### 2.2 Etapas do Processo

1. Caderno  
2. Modelagem Técnica  
3. Composição  
4. Finalização  
5. Pós-produção  
6. Alterações  
7. Entrega Final  

Cada imagem passa sequencialmente por todas as etapas.

---

## 3. Modelo de Capacidade (sem horas)

### 3.1 Unidade de Medida
- Capacidade é medida em **tarefas/imagens por dia**
- Não utilizamos horas (modelo PJ)

### 3.2 Capacidade Base
- Calculada a partir de **histórico de 3 meses**
- Média real de imagens entregues por dia
- Calculada por:
  - colaborador
  - etapa específica

### 3.3 Capacidade Alvo (+20%)
- Capacidade Alvo = Capacidade Base × 1,2
- Usada para planejamento semanal
- Objetivo: acelerar o sistema sem forçar artificialmente

Exemplo:
Capacidade Base: 2 imagens/dia
Capacidade Alvo: 2,4 imagens/dia


---

## 4. Janela de Planejamento

### 4.1 Janela Padrão
- Planejamento em **janela móvel de 7 dias**
- Exemplo:
  - Hoje é quarta
  - Planejamento olha até a próxima quarta

### 4.2 Vantagem
- Visão contínua
- Antecipação de risco
- Não depende de fechamento de semana fixa

---

## 5. Start Real do Projeto

### 5.1 Conceito
O prazo de produção **só começa a contar quando**:

- Todos os arquivos necessários foram recebidos
- O projeto está apto para produção

Essa data é chamada de:

Start Real


### 5.2 Regra
- Antes do Start Real:
  - Projeto fica em `Hold – aguardando arquivos`
  - Não consome capacidade
- Após o Start Real:
  - Prazos são calculados automaticamente
  - Projeto entra na fila real de produção

---

## 6. Deadline Propagation (Propagação Automática de Prazos)

### 6.1 Princípio
Os prazos das etapas **não são definidos manualmente**.

Eles são:
- Calculados automaticamente
- Baseados em:
  - data final da imagem
  - capacidade das etapas
  - fila atual

### 6.2 Tempo por Etapa

Tempo por imagem = `1 ÷ capacidade da etapa`

Exemplo:

| Etapa       | Capacidade | Tempo    |
| ----------- | ---------- | -------- |
| Caderno     | 2/dia      | 0,5 dia  |
| Modelagem   | 1/dia      | 1 dia    |
| Composição  | 2/dia      | 0,5 dia  |
| Finalização | 3/dia      | 0,33 dia |
| Pós         | 3/dia      | 0,33 dia |

---

### 6.3 Cálculo Simplificado

1. Definir data final da imagem  
2. Calcular tempo total das etapas restantes  
3. Ajustar pelo volume da fila de cada etapa  
4. Propagar prazos para trás (backwards planning)

### 6.4 Exemplo

Entrega final da imagem: **10/01**

Resultado automático:

Pós → até 10/01
Finalização → até 09/01
Composição → até 08/01
Modelagem → até 07/01
Caderno → até 06/01



---

## 7. Automático vs Manual

### 7.1 Regra Geral
- **80–90% dos prazos são automáticos**
- **10–20% são exceções manuais**

### 7.2 Exceções Permitidas
- Imagem hero / capa
- Projeto marcado como alta prioridade
- Mudança drástica de escopo
- Dependência externa específica

### 7.3 Regra de Ouro
> Toda exceção deve ter flag + motivo registrado.

---

## 8. Gestão de Múltiplos Projetos com Mesmo Prazo Final

### 8.1 Problema
Dois ou mais projetos:
- Mesma data final
- Start Real tardio
- Capacidade insuficiente

### 8.2 Critério de Prioridade (ordem)

1. Projeto que entrou primeiro em produção (FIFO)
2. Projeto com maior impacto sistêmico (mais imagens / mais gargalo)
3. Prioridade comercial explícita (flag)

---

## 9. Indicadores Essenciais para Tela Operacional

### 9.1 Capacidade vs Demanda
- Por etapa
- Por colaborador
- Próximos 7 dias

### 9.2 Status Visual
- 🟢 Dentro da capacidade
- 🟡 Atenção
- 🔴 Risco real de atraso

### 9.3 Alertas
- Imagens que deveriam ter avançado de etapa
- Gargalos futuros
- Conflitos de prazo

---

## 10. Regra Final do Sistema

> O Flow calcula.  
> O gestor valida.  
> A exceção é consciente.  

Esse modelo garante:
- escala
- previsibilidade
- justiça operacional
- redução de urgências artificiais
