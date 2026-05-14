# Roteiro Manual de Testes: SLA, Replanejamento e Atrasos

Data base: 2026-05-14

Escopo coberto:
- Fluxo de primeiro acesso em PaginaPrincipal/scriptIndex.js
- Endpoint atualizarFuncoesEmAndamento.php
- Historico de prazo em funcao_imagem_prazo_historico
- Registro de notificacao em sla_notificacoes_enviadas
- Integracao de historico no writer principal insereFuncao.php
- Integracao de historico no writer Arquitetura/update_funcao_caderno.php
- Script diario scripts/slack_overdue_daily.php

## 1. Pre-requisitos

1. Aplicar a migration:

```sql
SOURCE sql/2026-05-14_funcao_imagem_sla.sql;
```

2. Confirmar criacao das tabelas:

```sql
SHOW TABLES LIKE 'funcao_imagem_prazo_historico';
SHOW TABLES LIKE 'sla_notificacoes_enviadas';
```

3. Ter pelo menos estas massas de teste em funcao_imagem:
- 1 tarefa em andamento com prazo futuro
- 1 tarefa em andamento atrasada
- 1 tarefa em andamento sem prazo
- 1 tarefa em andamento que sera colocada em HOLD

4. Fazer login com um colaborador que possua essas tarefas.

5. Para testar o job do Slack, confirmar que scripts/.env contem:
- SLACK_TOKEN
- SLACK_CHANNEL
- opcionalmente SLACK_API_URL

## 2. Queries auxiliares

Use estas queries para inspecionar o resultado de cada teste.

### 2.1 Ver tarefa e prazo atual

```sql
SELECT
    fi.idfuncao_imagem,
    fi.status,
    fi.prazo,
    fi.observacao,
    fi.colaborador_id,
    f.nome_funcao,
    i.imagem_nome
FROM funcao_imagem fi
LEFT JOIN funcao f ON f.idfuncao = fi.funcao_id
LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
WHERE fi.idfuncao_imagem = ?;
```

### 2.2 Ver historico de prazo da tarefa

```sql
SELECT
    id,
    funcao_imagem_id,
    prazo_anterior,
    prazo_novo,
    alterado_em,
    alterado_por_colaborador_id,
    alterado_por_usuario_id,
    origem,
    motivo
FROM funcao_imagem_prazo_historico
WHERE funcao_imagem_id = ?
ORDER BY id DESC;
```

### 2.3 Ver notificacoes ja registradas

```sql
SELECT
    id,
    tipo_alerta,
    data_referencia,
    funcao_imagem_id,
    canal,
    enviado_em,
    payload_hash
FROM sla_notificacoes_enviadas
ORDER BY id DESC;
```

## 3. Testes do fluxo de primeiro acesso

Tela alvo: pagina principal onde PaginaPrincipal/scriptIndex.js e carregado.

### Caso 3.1: tarefa em andamento com prazo futuro e Continuar

Objetivo: confirmar que a tarefa pode seguir sem segunda etapa quando ja possui prazo valido.

Passos:
1. Garanta uma tarefa com status Em andamento e prazo maior ou igual a hoje.
2. Entre na tela principal para disparar o alerta de tarefas em andamento.
3. Marque Continuar para essa tarefa.
4. Clique em Confirmar.

Esperado:
- Nao deve abrir a segunda etapa de novo prazo para essa tarefa.
- O fluxo deve encerrar sem erro.
- O prazo da tarefa deve permanecer inalterado.
- Nao deve ser criada linha em funcao_imagem_prazo_historico para essa tarefa.

### Caso 3.2: tarefa atrasada e Continuar

Objetivo: confirmar que tarefas atrasadas exigem reestimativa.

Passos:
1. Garanta uma tarefa com status Em andamento e prazo menor que hoje.
2. Entre na tela principal para abrir o alerta.
3. Marque Continuar para a tarefa atrasada.
4. Clique em Confirmar.
5. Na segunda etapa, informe um novo prazo futuro.
6. Opcionalmente informe o motivo da reestimativa.
7. Clique em Salvar e continuar.

Esperado:
- A segunda etapa deve abrir.
- O prazo em funcao_imagem deve ser atualizado para a nova data.
- O status deve continuar Em andamento.
- Deve existir uma nova linha em funcao_imagem_prazo_historico com:
  - prazo_anterior = prazo antigo
  - prazo_novo = novo prazo informado
  - origem = primeiro_acesso
  - motivo = valor informado ou NULL
  - alterado_por_colaborador_id = usuario da sessao

### Caso 3.3: tarefa sem prazo e Continuar

Objetivo: confirmar que tarefas sem prazo tambem exigem reestimativa.

Passos:
1. Garanta uma tarefa com status Em andamento e prazo NULL.
2. Entre na tela principal.
3. Marque Continuar.
4. Clique em Confirmar.
5. Informe novo prazo futuro.
6. Salve.

Esperado:
- A segunda etapa deve abrir.
- O novo prazo deve ser gravado em funcao_imagem.
- Deve existir historico com prazo_anterior = NULL e origem = primeiro_acesso.

### Caso 3.4: HOLD com observacao

Objetivo: confirmar que HOLD continua exigindo justificativa.

Passos:
1. Garanta uma tarefa Em andamento qualquer.
2. Abra o alerta.
3. Marque HOLD.
4. Preencha a observacao.
5. Confirme.

Esperado:
- A tarefa deve mudar para status HOLD.
- A observacao deve ser gravada em funcao_imagem.observacao.
- Nao deve ser criada linha de historico de prazo se nenhum prazo foi alterado.

### Caso 3.5: HOLD sem observacao

Objetivo: validar bloqueio de preenchimento obrigatorio.

Passos:
1. Abra o alerta.
2. Marque HOLD em uma tarefa.
3. Deixe a observacao vazia.
4. Clique em Confirmar.

Esperado:
- O modal deve bloquear o envio.
- A mensagem deve exigir observacao para todas as tarefas em HOLD.
- Nada deve ser salvo.

### Caso 3.6: Continuar atrasada sem informar novo prazo

Objetivo: validar bloqueio da segunda etapa.

Passos:
1. Abra o alerta com uma tarefa atrasada.
2. Marque Continuar.
3. Clique em Confirmar.
4. Na segunda etapa, apague o valor da data.
5. Tente salvar.

Esperado:
- O modal deve bloquear o envio.
- A mensagem deve pedir o novo prazo da tarefa.
- Nada deve ser salvo.

### Caso 3.7: Continuar atrasada com prazo anterior a hoje

Objetivo: validar restricao de data minima.

Passos:
1. Abra o alerta com uma tarefa atrasada.
2. Marque Continuar.
3. Clique em Confirmar.
4. Na segunda etapa, tente informar uma data anterior a hoje.
5. Salve.

Esperado:
- O input date deve limitar pelo min de hoje.
- Se a data invalida chegar ao backend por algum teste direto, o endpoint deve rejeitar com mensagem informando que o prazo deve ser hoje ou futuro.

### Caso 3.8: Lote misto

Objetivo: confirmar processamento conjunto.

Passos:
1. Deixe uma tarefa atrasada para Continuar.
2. Deixe uma segunda tarefa para HOLD.
3. Confirme a primeira etapa.
4. Na segunda etapa, informe o prazo da tarefa atrasada.
5. Salve.

Esperado:
- A tarefa atrasada deve ficar Em andamento com novo prazo.
- A tarefa em HOLD deve ficar com status HOLD e observacao.
- O historico deve ser criado apenas para a tarefa com alteracao de prazo.

## 4. Testes diretos do endpoint atualizarFuncoesEmAndamento.php

Use uma sessao autenticada no navegador, ou execute estes testes a partir do proprio ambiente com cookies/sessao validos.

### Exemplo de payload valido

```json
{
  "items": [
    {
      "idfuncao_imagem": 123,
      "status": "continuar",
      "prazo_novo": "2026-05-20",
      "motivo": "Replanejamento de teste"
    },
    {
      "idfuncao_imagem": 124,
      "status": "hold",
      "obs": "Dependencia externa"
    }
  ]
}
```

### Exemplo via PowerShell

```powershell
$body = @{
  items = @(
    @{
      idfuncao_imagem = 123
      status = 'continuar'
      prazo_novo = '2026-05-20'
      motivo = 'Replanejamento de teste'
    },
    @{
      idfuncao_imagem = 124
      status = 'hold'
      obs = 'Dependencia externa'
    }
  )
} | ConvertTo-Json -Depth 5

Invoke-RestMethod \
  -Uri 'http://localhost/ImproovWeb/atualizarFuncoesEmAndamento.php' \
  -Method Post \
  -ContentType 'application/json' \
  -Body $body \
  -WebSession $session
```

### Caso 4.1: payload invalido

Passos:
1. Envie body nao JSON ou sem items.

Esperado:
- HTTP 400 ou 422.
- Mensagem explicando payload invalido ou ausencia de tarefas.

### Caso 4.2: HOLD sem observacao

Passos:
1. Envie item com status hold e sem obs.

Esperado:
- HTTP 422.
- Nenhuma alteracao persistida.

### Caso 4.3: Continuar atrasada sem prazo_novo

Passos:
1. Envie item atrasado com status continuar e sem prazo_novo.

Esperado:
- HTTP 422.
- Nenhuma alteracao persistida.

## 5. Testes do writer principal do Kanban

Alvo: insereFuncao.php.

### Caso 5.1: alterar prazo pelo modal normal do card

Passos:
1. Abra um card normal do Kanban.
2. Altere o prazo para uma nova data.
3. Salve.

Esperado:
- funcao_imagem.prazo deve refletir a nova data.
- Deve existir nova linha em funcao_imagem_prazo_historico.
- A origem deve ser insereFuncao, salvo se o frontend enviar outra origem futuramente.

### Caso 5.2: salvar sem mudar o prazo

Passos:
1. Abra o mesmo card.
2. Mantenha o mesmo prazo.
3. Salve.

Esperado:
- Nao deve ser gerada nova linha de historico apenas por salvar o mesmo valor.

## 6. Testes do writer manual de Arquitetura

Alvo: Arquitetura/update_funcao_caderno.php.

### Caso 6.1: alterar prazo pela tela de Arquitetura

Passos:
1. Acesse o fluxo que usa update_funcao_caderno.php.
2. Altere status e prazo de uma funcao.
3. Salve.

Esperado:
- O prazo deve ser atualizado em funcao_imagem.
- Deve existir uma linha em funcao_imagem_prazo_historico com origem = arquitetura_caderno.

### Caso 6.2: salvar o mesmo prazo

Passos:
1. Salve novamente sem mudar a data.

Esperado:
- Nao deve gerar novo historico apenas pela repeticao do mesmo prazo.

## 7. Testes do job diario do Slack

Alvo: scripts/slack_overdue_daily.php.

### Preparacao

Garanta pelo menos:
- 1 tarefa Em andamento com prazo vencido e historico de prazo
- 1 tarefa Em andamento com prazo vencido sem historico de prazo
- obra com status_obra = 0

### Caso 7.1: primeira execucao do dia

Comando:

```powershell
php scripts/slack_overdue_daily.php
```

Esperado:
- O script deve enviar mensagem ao canal configurado.
- A mensagem deve conter:
  - nome da imagem
  - nome da funcao
  - responsavel
  - obra quando houver
  - prazo original
  - prazo atual
  - dias em atraso
- Devem ser criadas linhas em sla_notificacoes_enviadas para cada funcao notificada.

### Caso 7.2: segunda execucao no mesmo dia

Comando:

```powershell
php scripts/slack_overdue_daily.php
```

Esperado:
- Nao deve reenviar as mesmas tarefas no mesmo dia.
- O script deve informar que nao encontrou novas tarefas atrasadas para notificar hoje.
- Nao devem surgir novas linhas duplicadas em sla_notificacoes_enviadas.

### Caso 7.3: tarefa atrasada sem historico de prazo

Objetivo: confirmar fallback para prazo atual.

Esperado:
- A mensagem deve usar o proprio funcao_imagem.prazo como prazo original quando nao houver historico.

## 8. Testes de regressao minima

### Caso 8.1: abrir a pagina principal sem tarefas em andamento

Esperado:
- O fluxo deve seguir normalmente.
- Nao deve haver modal de primeiro acesso de tarefas em andamento.

### Caso 8.2: tarefas em andamento apenas com prazo futuro

Esperado:
- O modal deve abrir.
- Nenhuma tarefa deve exigir a segunda etapa de reestimativa.

### Caso 8.3: execucao do job do Slack sem configuracao

Passos:
1. Remova temporariamente SLACK_TOKEN ou SLACK_CHANNEL do scripts/.env em ambiente de teste.
2. Execute o script.

Esperado:
- O script deve falhar com mensagem clara sobre configuracao ausente.
- Nenhum registro deve ser persistido em sla_notificacoes_enviadas.

## 9. Checklist de aprovacao final

Marque como aprovado apenas se todos estes pontos passarem:

- Migration aplicada com sucesso
- Tabelas novas criadas
- Primeiro acesso exige novo prazo apenas para atrasadas ou sem prazo
- HOLD exige observacao
- Novo prazo atualiza funcao_imagem.prazo
- Historico grava prazo_anterior e prazo_novo corretamente
- Historico grava ator e origem corretamente
- Kanban normal tambem gera historico ao mudar prazo
- Arquitetura tambem gera historico ao mudar prazo
- Script do Slack envia mensagem formatada corretamente
- Script do Slack nao duplica notificacoes no mesmo dia

## 10. Observacoes

1. O endpoint novo trabalha com permissao por sessao. Se quiser validar tentativa de atualizacao indevida, faca o teste com um usuario comum tentando atualizar tarefa de outro colaborador via chamada direta.
2. Os fluxos operacionais que ainda podem sobrescrever funcao_imagem.prazo fora desse pacote devem ser testados separadamente na segunda rodada, porque eles podem impactar as metricas de SLA.
3. Para repetir testes do Slack no mesmo dia, voce pode usar novas tarefas atrasadas ou limpar apenas os registros de sla_notificacoes_enviadas do ambiente de homologacao.