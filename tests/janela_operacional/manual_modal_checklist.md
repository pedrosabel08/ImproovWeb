# Checklist manual — Janela Operacional V1

Executar após aplicar a migration em ambiente de desenvolvimento.

- Primeiro início NORMAL: exibe os três conceitos, não mostra motivo e confirma em uma operação.
- EXCECAO_OPERACIONAL: mostra janela e previsão, exige motivo e permite prosseguir.
- CONFLITO_PLANEJAMENTO: usa faixa crítica, exige motivo e aparece em Atenção necessária.
- Alteração e função desconhecida: não exibem alerta/janela e não exigem justificativa operacional.
- Modelagem fachada: mostra 10 dias úteis; modelagem comum mostra 2.
- Modelagem + Composição conjunta: um card, uma previsão, uma justificativa e ciclo de 4 dias úteis.
- Caderno + Filtro legado: um card, um ciclo de 2 dias úteis e nenhum POST secundário no início.
- Caderno/Filtro separados: cada card cria o próprio ciclo de 2 dias úteis.
- HOLD: limite original não muda; retomada amplia apenas limite atual pelos dias úteis suspensos.
- Reabertura após estado terminal: cria novo ciclo ligado ao anterior; Ajuste do ciclo ativo não cria ciclo.
- Transferência ativa: valida WIP de B, encerra ciclo de A e cria ciclo completo de B.
- Duas abas: somente uma cria o ciclo; a outra recebe conflito e recarrega.
- Forçar falha depois de salvar previsão em teste transacional: status, previsão e ciclo devem sofrer rollback.
- Validar desktop, notebook, iPad landscape, iPad portrait e mobile nas duas URLs oficiais.

