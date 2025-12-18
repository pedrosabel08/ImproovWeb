README — atualizar_prazo.php

Objetivo
- Atualiza prazos e datas de recebimento para imagens de uma obra e gera/atualiza o planejamento (Gantt) e alocações de colaboradores com base nas datas/flags informadas.

Visão por função (regras gerais e por tipo de imagem)

1) adicionarDiasUteis($dataInicial, $diasUteis)
  - Regras gerais:
    - Conta apenas dias úteis (segunda a sexta).
    - Pula feriados fixos (lista codificada: e.g., 01-01, 04-21, 05-01, 09-07, 10-12, 11-02, 11-15, 12-25).
    - Pula feriados móveis calculados por `feriadosMoveis($ano)` (usa `easter_date()` para derivar Páscoa, Carnaval, Sexta Santa, Corpus, etc.).
    - Itera dia-a-dia até cumprir `diasUteis` e retorna a última data útil.
  - Regras por tipo: nenhuma — função é neutra quanto a tipos de imagem.

2) feriadosMoveis($ano)
  - Regras gerais:
    - Calcula data da Páscoa e deriva feriados móveis relevantes (Sexta Santa, Carnaval, Corpus, Ascensão etc.).
  - Regras por tipo: nenhuma — função fornece dados de feriados para `adicionarDiasUteis`.

3) gerarGantt($conn, $obra_id, $grupos)
  - Regras gerais:
    - Lê tipos de imagem que têm `recebimento_arquivos` registrados em `imagens_cliente_obra` para a obra.
    - Filtra o mapa `$grupos` (definição de etapas e durações) para incluir apenas tipos com recebimento.
    - Para cada tipo (grupo) e para cada imagem do tipo:
      - Obtém `data_inicio` = `recebimento_arquivos` da imagem.
      - Para cada etapa do grupo:
        - Calcula `diasCalculados` (padrão: valor definido no `$grupos`).
        - Chama `verificarDisponibilidadeColaborador` para obter `data_inicio_disponivel` e flag `freelancer`.
        - Calcula `data_fim = adicionarDiasUteis(data_inicio_disponivel['data_inicio'], diasCalculados)`.
        - Insere/atualiza linha em `gantt_prazos` (ON DUPLICATE KEY UPDATE).
        - Se houver `colaborador_id` e não for freelancer, insere em `etapa_colaborador` (evita duplicidade).
        - Atualiza `data_inicio` para próxima etapa (dia útil após `data_fim`).
    - Após processar grupos principais, processa `Planta Humanizada` iniciando após o maior `data_fim` encontrado para a etapa Caderno (se aplicável).
  - Regras por tipo (observações extraídas do código):
    - Os grupos e durações são definidos no array `$grupos` (exemplo abaixo).
    - Para `Planta Humanizada` o cálculo é feito somente após calcular `maiorDataCaderno` das demais etapas.
    - Há uma regra pontual no código que refere `Modelagem` com comportamento condicional em alguns grupos (mantém `dias` específicos para `Modelagem` dependendo do grupo).

  - `$grupos` atual no código (valores de dias usados para gerar o Gantt):
    - Fachada: Modelagem=7, Finalização=2, Pós-Produção=0.2
    - Imagem Externa: Caderno=0.5, Filtro de assets=0.5, Modelagem=7, Composição=1, Finalização=1, Pós-Produção=0.2
    - Imagem Interna: Caderno=0.5, Filtro de assets=0.5, Modelagem=0.5, Composição=0.5, Finalização=1, Pós-Produção=0.2
    - Unidade: Caderno=0.5, Filtro de assets=0.5, Modelagem=0.5, Composição=0.5, Finalização=1, Pós-Produção=0.2
    - Planta Humanizada: Planta Humanizada=1

4) verificarDisponibilidadeColaborador($conn, $etapa, $data_inicio, $dias)
  - Regras gerais:
    - Tenta localizar um colaborador disponível para a etapa começando em `data_inicio`.
    - Realiza até 5 tentativas, incrementando `data_inicio` em 1 dia útil a cada tentativa.
    - Em cada tentativa chama `colaboradorDisponivel()` que verifica limite de carga por colaborador.
    - Se encontrar colaborador com capacidade, retorna `['data_inicio' => data, 'freelancer' => false, 'colaborador_id' => id]`.
    - Se não encontrar após 5 tentativas, retorna `['data_inicio' => data_final, 'freelancer' => true, 'colaborador_id' => null]` (alocação por freelancer).
  - Regras por tipo: nenhuma explícita — tentativa é aplicada a todas as etapas/tipos igualmente.

5) colaboradorDisponivel($conn, $etapa, $data_inicio, $dias)
  - Regras gerais:
    - Calcula `data_fim = adicionarDiasUteis(data_inicio, dias)`.
    - Busca colaboradores associados à função/etapa (tabela `funcao_colaborador` e `funcao` para limite).
    - Para cada candidato conta tarefas existentes no intervalo (verifica conflitos em `gantt_prazos` + `etapa_colaborador`).
    - Se carga atual < limite do colaborador → retorna `['data_inicio'=>..., 'freelancer'=>false, 'colaborador_id'=>id]`.
    - Caso contrário, verifica o próximo candidato.
  - Regras por tipo: nenhuma explícita — a checagem é por etapa (nome da função), independente do tipo de imagem.

6) enviarAlertaFreelancer($etapa, $imagem_id)
  - Regras gerais:
    - Placeholder (stub) no código atual — intenção: notificar quando não houver colaborador disponível e for necessária alocação de freelancer.
  - Regras por tipo: nenhuma implementada.

Consulta de seleção de "última data por tipo" (regras SQL aplicadas)
- A query `queryTipoData` só considera registros de `arquivos` que atendam combinações de flags por tipo:
  - Fachada: `dwg = 1 AND pdf = 1 AND trid = 1 AND paisagismo = 1`
  - Imagem Externa: `dwg = 1 AND pdf = 1 AND (trid = 1 OR paisagismo = 1)`
  - Imagem Interna: `dwg = 1 AND pdf = 1 AND (trid = 1 OR luminotecnico = 1)`
  - Unidade: `dwg = 1 AND pdf = 1 AND (trid = 1 OR unidades_definidas = 1) AND luminotecnico = 1`

Efeitos no banco de dados (resumo)
- `arquivos`: insert/update por tipo/obra com `data_recebimento` e flags.
- `imagens_cliente_obra`: atualiza `recebimento_arquivos` e `prazo` onde houver correspondência com `arquivos`.
- `gantt_prazos`: insert/update por obra/tipo/imagem/etapa com datas calculadas.
- `etapa_colaborador`: associa colaboradores a itens do Gantt quando possível.

Exemplo de entrada
```json
{
  "obraId": 123,
  "tiposSelecionados": [
    {
      "tipo": "Fachada",
      "dataRecebimento": "2025-12-01",
      "subtipos": { "DWG": true, "PDF": true, "3D ou Referências/Mood": true, "Paisagismo": true }
    }
  ]
}
```

Observações e recomendações
- Implementar retorno JSON resumido no fim da execução (quantas linhas atualizadas/erros).
- Implementar `enviarAlertaFreelancer()` (Slack/Email) para fechar o ciclo de alocação.
- Externalizar lista de feriados para configuração (evitar hardcode).
- Revisar chave única de `gantt_prazos` para confirmar comportamento de `ON DUPLICATE KEY`.

Arquivo fonte
- `atualizar_prazo.php` (local: `Dashboard/atualizar_prazo.php`)

---
Gerado em: 2025-12-18