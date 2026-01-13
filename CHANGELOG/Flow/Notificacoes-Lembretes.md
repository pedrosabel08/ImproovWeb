# Módulo de Notificações e Lembretes (Proposta)

Data: 13/01/2026

## Objetivo
Criar um módulo único, dinâmico e reaproveitável para comunicações no sistema, cobrindo desde lembretes leves até avisos críticos (ex.: atualizações, mudanças de processo, novidades por projeto).

O módulo deve permitir:
- Criar mensagens com agendamento (início/fim) e prioridade
- Segmentar público (ex.: finalizadores) e contexto (global, por módulo/página, por projeto)
- Controlar experiência do usuário (visto/dispensado/confirmado/soneca)
- Padronizar UI (banner, toast, modal) com regras consistentes

## Casos de uso (exemplos)
1) Aviso aos finalizadores (segmentado por perfil)
2) Aviso geral de um projeto (segmentado por projeto/obra)
3) Aviso de versionamento (global; aparece 1 vez ou exige confirmação)
4) Novo arquivo de referências (por projeto; com CTA “Abrir arquivo”)

## Conceitos
### 1) Mensagem
Representa o conteúdo e as regras básicas de exibição.

Campos recomendados:
- `titulo`, `mensagem`
- `tipo`: `info | warning | danger | success`
- `canal`: `banner | toast | modal | card`
- `prioridade`: número (maior = mais importante)
- `ativa`: bool
- `inicio_em`, `fim_em`: janela de exibição
- `fixa`: mantém sempre visível durante a janela
- `fechavel`: permite dispensar
- `exige_confirmacao`: requer “Li e entendi”
- CTA (call-to-action): `cta_label`, `cta_url`
- `payload_json`: dados opcionais para casos especiais (ex.: versão, arquivo_id)

### 2) Segmentação e contexto
Define para quem e onde a mensagem aparece.

Dimensões típicas:
- Público: por `perfil/função` e/ou usuário específico
- Escopo: global, por `projeto/obra`, por cliente
- Local: global (todas telas), por módulo/página específica

### 3) Estado por usuário
Evita spam e permite fluxos “até confirmar”.

Estados recomendados:
- `visto_em`: quando apareceu pela primeira vez
- `dispensado_em`: quando o usuário fechou
- `confirmado_em`: quando o usuário confirmou leitura
- `snooze_ate`: adiar por X tempo (opcional, muito útil para lembretes)

## Regras de exibição (sugestão)
- `modal`: usar apenas para avisos críticos (prioridade alta) e, preferencialmente, `exige_confirmacao = true`
- `banner`: bom para avisos gerais e lembretes persistentes
- `toast`: bom para novidades/eventos (“novo arquivo”) e mensagens rápidas
- Respeitar sempre:
  - janela (`inicio_em`/`fim_em`)
  - segmentação (perfil/usuário/projeto/página)
  - estado do usuário (dispensado/confirmado/soneca)

## Modelo de dados (proposta)
> Observação: nomes/tipos podem ser adaptados às tabelas/padrões atuais do projeto.

### Tabela: `notificacoes`
Armazena o conteúdo e regras básicas.

Campos (sugestão):
- `id` (PK)
- `titulo` (varchar)
- `mensagem` (text)
- `tipo` (varchar)
- `canal` (varchar)
- `prioridade` (int)
- `ativa` (tinyint)
- `inicio_em` (datetime, null)
- `fim_em` (datetime, null)
- `fixa` (tinyint)
- `fechavel` (tinyint)
- `exige_confirmacao` (tinyint)
- `cta_label` (varchar, null)
- `cta_url` (varchar, null)
- `payload_json` (json/text, null)
- auditoria: `criado_por`, `criado_em`, `atualizado_em`

### Tabela: `notificacao_alvos`
Relaciona a notificação com seus alvos/segmentos.

Exemplos de alvos:
- `global`
- `perfil:finalizador`
- `usuario:123`
- `pagina:FlowReferencias/index.php`
- `projeto:456`

Campos (sugestão):
- `id` (PK)
- `notificacao_id` (FK)
- `alvo_tipo` (varchar) — ex.: `global|perfil|usuario|pagina|projeto`
- `alvo_valor` (varchar) — ex.: `finalizador`, `123`, `FlowReferencias`, `456`

### Tabela: `notificacao_usuario_estado`
Persistência por usuário para “dispensar”, “confirmar”, etc.

Campos (sugestão):
- `id` (PK)
- `notificacao_id` (FK)
- `usuario_id` (FK)
- `visto_em` (datetime, null)
- `dispensado_em` (datetime, null)
- `confirmado_em` (datetime, null)
- `snooze_ate` (datetime, null)

Índices úteis (sugestão):
- `notificacoes(ativa, inicio_em, fim_em, prioridade)`
- `notificacao_alvos(notificacao_id, alvo_tipo, alvo_valor)`
- `notificacao_usuario_estado(usuario_id, notificacao_id)` único

## Integração no sistema (proposta)
### Backend (PHP)
- Função/serviço central para buscar notificações aplicáveis ao usuário/contexto.
- Estratégia: carregar no layout global (header/sidebar) para mostrar em qualquer página.

### Endpoints (opcional)
- `notificacoes_listar.php` (retorna JSON com lista filtrada)
- `notificacao_dispensar.php` (marca `dispensado_em`)
- `notificacao_confirmar.php` (marca `confirmado_em`)
- `notificacao_snooze.php` (define `snooze_ate`)

## UI/UX (proposta)
- Componente de renderização único:
  - recebe lista de notificações
  - organiza por prioridade
  - aplica layout por `canal`

Padrões:
- Banner topo: lista compacta (máximo 1–3 simultâneos; restante em “ver mais”)
- Modal: 1 por vez (prioridade mais alta), com confirmação quando necessário
- Toast: aparece e some; pode manter histórico por poucos minutos

## Roadmap sugerido
### MVP (primeira entrega)
- CRUD básico de notificações (criar/editar/ativar/expirar)
- Segmentação por perfil e global
- Banner global no layout
- “Dispensar” por usuário (persistente no banco)

### V2
- Segmentação por projeto/página
- Modal com confirmação (“Li e entendi”)
- Snooze

### V3
- Condições dinâmicas (gatilhos baseados em dados: atrasos, status, etc.)
- Auditoria/relatórios (“quem viu”, “quem confirmou”)
