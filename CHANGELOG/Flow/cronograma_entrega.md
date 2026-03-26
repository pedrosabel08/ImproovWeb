# 📅 Cronograma de Conclusão por Entrega

> Especificação do módulo de replanejamento por entrega, integrado ao módulo **Entregas** existente.

---

## Contexto

O módulo de Entregas agrupa imagens por projeto e etapa de produção (P00, R00, EF, etc.), com um prazo previsto por entrega. Com o tempo, algumas entregas ficam atrasadas ou com prazo próximo, e o gestor precisa responder rapidamente: **"O que falta para essa entrega ser concluída, e quando isso será possível?"**

O raciocínio é simples — exemplo real:

Uma entrega atrasada tem 5 imagens ainda pendentes:

| Imagem | Funções pendentes | Responsável |
|---|---|---|
| Imagem 1 | Finalização | Pedro |
| Imagem 2 | Finalização | Pedro |
| Imagem 3 | Finalização | João |
| Imagem 4 | Finalização | Pedro |
| Imagem 5 | Composição → Finalização | Maria → André |

- Pedro tem 3 imagens → termina em **3 dias**
- João tem 1 imagem → termina em **1 dia**
- Maria faz composição em **1 dia** → André começa depois → termina em **2 dias**

**A entrega conclui em 3 dias** (Pedro é o gargalo).

---

## Objetivo

Permitir que o gestor gere um cronograma de conclusão para uma ou mais entregas, com um fluxo de seleção e priorização antes da geração, e com a possibilidade de editar o cronograma gerado.

---

## Escopo

- **Apenas leitura** — nenhum prazo é salvo no banco
- **Seleção múltipla** — o gestor pode selecionar uma ou várias entregas para planejamento conjunto
- **Quando múltiplas entregas**, a fila de cada colaborador é compartilhada entre todas: a ordem de execução respeita a prioridade definida pelo gestor
- **Quando entrega única**, a fila considera somente as imagens daquela entrega
- **Dias corridos** — sem desconto de fins de semana
- **Carga histórica**: últimos 30 dias de `log_alteracoes`, contando transições para `Finalizado` ou `Aprovado` por colaborador por função → `dias_por_imagem = 30 / count`; fallback = 1 dia quando não há histórico
- **Cronograma editável** — após geração automática pelo sistema, o gestor pode ajustar datas e colaboradores manualmente

---

## Algoritmo de Simulação de Fila

```
colaborador_livre[colab_id] = hoje

para cada imagem pendente da entrega:
    image_prev_end = hoje
    para cada função pendente da imagem (ordem crescente de funcao_id):
        start = max(colaborador_livre[colab_id], image_prev_end)
        end   = start + dias_historico[colab_id][funcao_id]
        colaborador_livre[colab_id] = end
        image_prev_end = end

data_conclusao_entrega = max(end de todas as imagens)
imagem_critica = imagem com maior end
gargalo = colaborador com maior colaborador_livre
```

As dependências entre funções da mesma imagem são respeitadas: a Finalização de uma imagem só começa após sua Composição terminar.

**Com múltiplas entregas**, as imagens são enfileiradas na ordem de prioridade das entregas: todas as imagens da entrega de maior prioridade entram na fila antes das da próxima, e assim por diante. A fila de cada colaborador é global às entregas selecionadas.

---

## Arquitetura

### Arquivo criado

| Arquivo | Descrição |
|---|---|
| `Entregas/get_cronograma.php` | Endpoint POST `{ entrega_ids: [], prioridades: [] }` — executa a simulação e retorna JSON |

### Arquivos modificados

| Arquivo | Mudança |
|---|---|
| `Entregas/index.php` | Toggle modo planejamento + `#modalPrioridade` + `#cronogramaModal` |
| `Entregas/script.js` | Toggle, seleção múltipla, modal de prioridade, `renderCronograma()`, edição inline |
| `Entregas/style.css` | `.modo-planejamento`, `.card-selecionado`, `.btn-cronograma`, `.is-critical`, `.badge-atraso`, `.fonte-padrao`, `.campo-editavel` |

---

## Endpoint `get_cronograma.php`

**Parâmetro:** `POST` com body JSON:

**Resposta JSON:**

```json
// Request
{
  "entrega_ids": [12, 7, 3],
  "prioridades": [1, 2, 3]   // ordem de execução; índice 0 = maior prioridade
}

// Response
{
  "entregas": [
    {
      "entrega_id": 12,
      "nome": "PROJETO_A — EF",
      "data_prevista": "2026-03-20",
      "data_estimada_conclusao": "2026-03-27",
      "dias_restantes": 3,
      "atrasada": true,
      "imagens": [
        {
          "nome": "Sala",
          "conclusao": "2026-03-25",
          "is_critica": false,
          "funcoes": [
            {
              "funcao_imagem_id": 1042,
              "nome": "Finalização",
              "colaborador_id": 21,
              "colaborador": "Pedro",
              "start": "2026-03-25",
              "end": "2026-03-25",
              "dias": 1,
              "fonte": "historico"
            }
          ]
        }
      ],
      "critico": "Pedro — 3 imagens na fila"
    }
  ]
}
```

`fonte` pode ser `"historico"` (calculado) ou `"padrao"` (fallback 1 dia, sem dados históricos suficientes).

**Fontes de dados SQL utilizadas:**

- `entregas_itens` + `imagens_cliente_obra` → imagens pendentes da entrega
- `funcao_imagem` + `funcao` + `colaborador` → funções pendentes por imagem
- `log_alteracoes` + `funcao_imagem` → produtividade histórica (30 dias)

---

## Interface

### Modo Planejamento

Um toggle **"Planejamento"** aparece na barra de filtros do kanban de Entregas. Quando ativado:

- Os cards passam a exibir um **checkbox** no canto superior direito
- O card ganha borda destacada ao ser selecionado (`.card-selecionado`)
- Uma barra flutuante aparece no rodapé da tela com a contagem de selecionados e o botão **"Gerar Cronograma"**
- Clicar no card em modo planejamento seleciona/deseleciona (não abre o modal de itens)

Quando o modo está desativado, o comportamento padrão dos cards é restaurado.

### Botão no card de entrega (modo individual)

Fora do modo planejamento, cada card mantém um pequeno botão `📅 Cronograma` para geração rápida de uma entrega isolada. Não abre o modal de itens — abre diretamente o modal de cronograma.

### Modal de Prioridade (somente com múltiplas entregas)

Antes de gerar o cronograma, quando há 2 ou mais entregas selecionadas, um modal intermediário é exibido:

```
┌─────────────────────────────────────────────────────────┐
│  Defina a prioridade de execução                        │
│  Arraste para reordenar                                 │
├─────────────────────────────────────────────────────────┤
│  ① PROJETO_A — EF   │ 5 imagens faltando │ Prazo: 20/03 ⚠️ atrasada  │
│  ② PROJETO_B — R00  │ 3 imagens faltando │ Prazo: 01/04            │
│  ③ PROJETO_C — R00  │ 8 imagens faltando │ Prazo: 10/04            │
├─────────────────────────────────────────────────────────┤
│              [ Cancelar ]  [ Gerar Cronograma ]         │
└─────────────────────────────────────────────────────────┘
```

- As entregas são pré-ordenadas: atrasadas primeiro, depois por `data_prevista` crescente
- O gestor pode reordenar via drag-and-drop antes de confirmar
- Para cada entrega é exibido: nome do projeto, etapa, quantidade de imagens pendentes e prazo original (com badge de atraso quando aplicável)

### Modal de Cronograma

Após geração, um único modal exibe uma aba por entrega (quando múltiplas). Cada aba mostra:

```
┌─────────────────────────────────────────────────────────┐
│  [ PROJETO_A — EF ] [ PROJETO_B — R00 ] [ PROJETO_C ]  │  ← abas
├─────────────────────────────────────────────────────────┤
│  Prazo original: 20/03/2026  │  📅 Estimativa: 27/03/2026 ⚠️  │
├─────────────────────────────────────────────────────────┤
│  Imagem   │ Função     │ Responsável ✏️  │ Início ✏️│ Fim ✏️  │
│  Sala     │ Finalização│ Pedro           │ 24/03   │ 24/03  │
│  Quarto   │ Finalização│ Pedro           │ 25/03   │ 25/03  │
│  Fachada  │ Finalização│ João            │ 24/03   │ 24/03  │
│  Varanda  │ Finalização│ Pedro           │ 26/03   │ 26/03  │◄ gargalo
│  Jardim   │ Composição │ Maria           │ 24/03   │ 24/03  │
│           │ Finalização│ André           │ 25/03   │ 25/03  │
├─────────────────────────────────────────────────────────┤
│  ⚠️ Gargalo: Pedro — 3 imagens na fila                  │
└─────────────────────────────────────────────────────────┘
```

- Células com ✏️ são **editáveis inline**: clique na data ou responsável para alterar
- Ao editar, as datas subsequentes dependentes **não** são recalculadas automaticamente — o gestor é livre para ajustar como quiser
- Linhas da imagem crítica destacadas com fundo avermelhado (`.is-critical`)
- Badge vermelho na data estimada quando posterior ao prazo original (`.badge-atraso`)
- Texto em cinza nos dias calculados por fallback (`.fonte-padrao`)
- Imagens sem responsável (`NULL`) aparecem como "Sem responsável" e usam 1 dia de fallback

---

## Casos de borda

| Situação | Comportamento |
|---|---|
| Imagem sem colaborador atribuído | Aparece como "Sem responsável", `dias = 1`, não quebra a simulação |
| Todas as imagens já entregues | Modal exibe mensagem "Todas as imagens já foram entregues" |
| Colaborador sem histórico no período | Usa fallback de 1 dia; fonte marcada como `"padrao"` |
| Entrega não encontrada | Retorna `{ "error": "Entrega não encontrada" }` com HTTP 404 |
| Entrega única selecionada | Pula o modal de prioridade e gera o cronograma diretamente |
| Gestor altera data/responsável no cronograma | As demais linhas **não** são recalculadas — ajuste manual livre |