# Flow Connect

## Definição

**Flow Connect** é o serviço central de comunicação do Flow. Sua responsabilidade é receber todos os eventos gerados pelo sistema, decidir quando uma comunicação deve acontecer, identificar os destinatários corretos e realizar o envio pelos canais disponíveis, mantendo histórico, regras de cobrança e escalonamento.

Nenhum módulo do Flow envia mensagens diretamente ao Slack. Todos os eventos passam obrigatoriamente pelo Flow Connect, tornando a comunicação consistente, rastreável e expansível.

---

## Objetivos

* Centralizar todas as notificações do Flow.
* Padronizar regras de envio e cobrança.
* Eliminar lógica de comunicação espalhada pelo sistema.
* Registrar histórico completo de eventos e entregas.
* Permitir novos canais de comunicação sem alterar os módulos do Flow.
* Atuar como um serviço independente, executando tanto eventos imediatos quanto verificações periódicas.

---

## Fluxo recomendado

```text
Módulo do Flow
(Fotográfico, Kanban, Review, Render, etc.)
                │
                ▼
        Registro do Evento
                │
                ▼
          Flow Connect
    ├─ Valida regras
    ├─ Resolve destinatários
    ├─ Define prioridade
    ├─ Escolhe template
    ├─ Agenda ou envia
    └─ Registra histórico
                │
                ▼
     Slack • E-mail • Push • Outros
```

Os módulos apenas informam que **um evento aconteceu**. Toda a lógica de comunicação pertence exclusivamente ao Flow Connect.

---

## Tipos de eventos

### Eventos imediatos

São disparados no momento em que uma ação ocorre no sistema.

Exemplos:

* Tarefa iniciada.
* Tarefa concluída.
* Arquivo enviado.
* Aprovação solicitada.
* Comentário recebido.
* Render finalizado.
* Pendência resolvida.
* Impedimento registrado.
* Plano fotográfico publicado.

---

### Eventos temporais

São gerados pela passagem do tempo e executados por um scheduler/cron.

Exemplos:

* SLA próximo do vencimento.
* SLA vencido.
* Pendência sem movimentação.
* Aprovação aguardando há muitas horas.
* Arquivo ainda não enviado.
* Cobrança periódica.
* Escalonamento para gestor.
* Resumo diário de pendências.
* Resumo semanal de produtividade.

---

## Responsabilidades do Flow Connect

* Receber eventos do Flow.
* Resolver automaticamente os destinatários.
* Definir prioridade da comunicação.
* Escolher o template adequado.
* Evitar notificações duplicadas.
* Agrupar notificações quando necessário.
* Controlar reenvios e cobranças.
* Escalonar problemas para gestores.
* Registrar todas as entregas e falhas.
* Permitir múltiplos canais de comunicação.

---

## Princípios

* Todo evento passa pelo Flow Connect.
* Nenhum módulo conhece Slack, e-mail ou qualquer outro canal.
* O Flow apenas informa que um evento ocorreu; o Flow Connect decide **se**, **quando**, **para quem** e **como** comunicar.
* A comunicação deve ser orientada por regras, e não por código espalhado pelo sistema.
* O Slack é o primeiro canal suportado, mas a arquitetura deve permitir a inclusão futura de e-mail, notificações internas, Microsoft Teams, WhatsApp ou qualquer outro meio sem alterações nos módulos do Flow.

---

## Visão

O **Flow Connect** não é apenas um integrador com o Slack. Ele é a camada de comunicação do ecossistema Flow, responsável por conectar pessoas, processos e módulos através de notificações inteligentes, cobranças automáticas, alertas operacionais e acompanhamento contínuo dos fluxos de trabalho. Seu objetivo é garantir que nenhuma informação importante dependa de acompanhamento manual e que cada colaborador receba a comunicação certa, no momento certo.
