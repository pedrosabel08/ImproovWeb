---
applyTo: "Dashboard/**/*, TelaGerencial/**/*, **/*dashboard*.php, **/*dashboard*.css, **/*dashboard*.js"
description: "Padroes completos para dashboards gerenciais do ImproovWeb, baseados em TelaGerencial. Use when: criando dashboards, KPIs, tabelas gerenciais, endpoints JSON, filtros de periodo, relatorios, calculos de producao, custos, metas ou recordes."
---

# Dashboard Gerencial - ImproovWeb

Use estas instrucoes junto com `.github/instructions/ui-styles.instructions.md`.
O modulo de referencia e `TelaGerencial/`.

Dashboards no ImproovWeb sao telas operacionais de leitura rapida. Eles devem priorizar confiabilidade dos numeros, densidade visual, comparacao entre periodos e clareza de origem dos dados. Nao criar tela com linguagem de landing page, hero grande, cards decorativos ou explicacoes visuais longas.

---

## Arquitetura do modulo

Um dashboard deve ser dividido em arquivos pequenos, seguindo o padrao de `TelaGerencial/`:

```text
Dashboard/
  index.php                  # shell da pagina, sessao, includes, HTML base
  style.css                  # layout escuro/denso do dashboard
  script.js                  # orquestracao do front, fetch, renderizacao, relatorio
  dashboard-utils.js         # helpers puros e reutilizaveis do dashboard
  buscar_*.php               # endpoints JSON especificos
  prova_real_queries.sql     # opcional: queries de conferencia manual
```

Regras:

- `index.php` monta a pagina, valida sessao, atualiza `logs_usuarios`, carrega dados estaticos iniciais quando necessario e inclui assets.
- Endpoints `buscar_*.php` retornam JSON e nao renderizam HTML.
- `script.js` faz `fetch`, renderiza tabelas/cards/modais e coordena filtros.
- `dashboard-utils.js` deve conter apenas funcoes puras ou helpers sem dependencia de DOM complexa, como formatacao e animacao de numeros.
- Se uma query for usada para validar regra financeira ou produtiva, manter uma prova real em `.sql` ou comentar claramente a referencia de regra.

---

## Sessao e bootstrap

Em paginas principais de dashboard, usar o mesmo fluxo de `TelaGerencial/index.php`:

```php
require_once __DIR__ . '/../config/session_bootstrap.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../index.html");
    exit();
}

$idusuario = $_SESSION['idusuario'];
$tela_atual = basename($_SERVER['PHP_SELF']);
```

Regras:

- Validar login antes de qualquer HTML.
- Atualizar `logs_usuarios` com `tela_atual` e `ultima_atividade = NOW()`.
- Usar horario do MySQL (`NOW()`) para evitar divergencia de timezone entre PHP e banco.
- Fechar a sessao com `session_write_close()` depois de capturar dados necessarios, antes de consultas pesadas.
- Incluir `../sidebar.php` no body e `../css/modalSessao.php` no fim do body.
- Preferir `asset_url()` quando o modulo ja usa versionamento por `config/version.php`.

Exemplo de log:

```php
$sql = "UPDATE logs_usuarios
        SET tela_atual = ?, ultima_atividade = NOW()
        WHERE usuario_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $tela_atual, $idusuario);
$stmt->execute();
```

---

## Includes e dependencias

No `head`, manter:

```html
<link
  href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
  rel="stylesheet"
/>
<link
  rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
/>
<link
  rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css"
/>
<link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>" />
<link
  rel="stylesheet"
  href="<?php echo asset_url('../css/styleSidebar.css'); ?>"
/>
<link
  rel="stylesheet"
  href="<?php echo asset_url('../css/modalSessao.css'); ?>"
/>
```

No fim do body, manter a ordem:

```html
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script src="<?php echo asset_url('dashboard-utils.js'); ?>"></script>
<script src="<?php echo asset_url('script.js'); ?>"></script>
<script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
<script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
<?php include '../css/modalSessao.php'; ?>
```

Regras:

- Font Awesome para icones.
- Inter como fonte principal.
- Nao usar Bootstrap.
- Para dialogos de confirmacao, seguir `ui-styles.instructions.md`: SweetAlert2, nunca `alert()`, `confirm()` ou `prompt()`. Observacao: se encontrar `alert()` legado em dashboards, substituir ao refatorar a feature.

---

## Layout visual

Dashboards gerenciais usam uma variacao escura e densa do design system.

Base:

```css
:root {
  --bg-body: #070b14;
  --bg-card: #111827;
  --bg-card-soft: #121827;
  --bg-table-head: #182033;
  --bg-table-row: #111827;
  --bg-table-row-alt: #101725;
  --bg-table-hover: rgba(59, 130, 246, 0.1);
  --border: rgba(148, 163, 184, 0.12);
  --border-strong: rgba(148, 163, 184, 0.18);
  --text-primary: #f8fafc;
  --text-secondary: #cbd5e1;
  --text-muted: #94a3b8;
  --blue: #3b82f6;
  --green: #10b981;
  --orange: #f59e0b;
  --red: #ef4444;
  --purple: #8b5cf6;
  --radius-card: 14px;
  --radius-inner: 8px;
  --shadow-card: 0 16px 40px rgba(0, 0, 0, 0.2);
}
```

Body:

```css
body {
  display: grid;
  grid-template-columns: 60px 1fr;
  height: 100vh;
  overflow: hidden;
  background: var(--bg-body);
  color: var(--text-primary);
}
```

Container:

```css
.container {
  grid-column: 2;
  display: flex;
  flex-direction: column;
  height: 100vh;
  min-width: 0;
  gap: 14px;
  overflow: hidden;
  padding: 18px 24px;
}
```

Regras visuais:

- Tela compacta, feita para escanear tabelas e numeros.
- Fundo escuro, cards com borda sutil e sombra controlada.
- Evitar gradientes decorativos, hero sections, imagens de fundo ou textos explicativos longos.
- Usar `font-variant-numeric: tabular-nums` em numeros, custos, metas e comparativos.
- Logo `assinatura_branco.gif` no dashboard escuro.
- Titulos curtos, com icone funcional e subtitulo opcional.

---

## Header e filtros

O header deve ter:

- logo + titulo + subtitulo curto;
- filtros principais de periodo;
- acoes globais, como gerar relatorio.

Exemplo:

```html
<div class="page-header">
  <div class="page-header-left">
    <img src="../gif/assinatura_preto.gif" class="page-header-logo" id="gif" />
    <div class="page-heading">
      <h1 class="page-title">Tela Gerencial</h1>
      <p class="page-subtitle">Visao geral da producao e custos</p>
    </div>
  </div>
  <div class="filtros-linha">
    <label for="mes">Mes:</label>
    <select id="mes" onchange="refreshAll()">
      ...
    </select>
    <label for="ano">Ano:</label>
    <select id="ano" onchange="refreshAll()">
      ...
    </select>
    <button id="gerar-relatorio" type="button">
      <i class="fa-solid fa-download" aria-hidden="true"></i>
      Gerar relatorio
    </button>
  </div>
</div>
```

Regras:

- Mes deve aceitar `01` a `12` no front, mas endpoints devem converter para inteiro.
- Ano deve ser selecionavel, normalmente ano atual ate ano atual - 10.
- Mudanca de periodo chama uma funcao unica como `refreshAll()`.
- Filtros do dashboard ficam no header quando forem poucos. Se crescerem, usar o bottom-sheet descrito no guia geral de UI.
- Acoes globais ficam no canto direito do header em desktop.

---

## Grid de dashboard

Para dashboard baseado em tabelas:

```css
.tables-main-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.42fr);
  grid-template-rows: minmax(220px, 1fr) auto;
  gap: 14px;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.table-block.full-span {
  grid-column: 1 / -1;
}
```

Regras:

- Tabela principal pode ocupar largura total.
- Tabelas secundarias podem ficar em duas colunas.
- Sempre usar `min-width: 0` e `min-height: 0` nos containers de grid/flex para scroll funcionar.
- Em desktop, manter a tela em `100vh` sem scroll do body; o scroll deve ficar dentro das tabelas.
- Em telas menores, liberar scroll do body e empilhar blocos.

---

## Blocos e tabelas

Cada tabela deve ficar em `.table-block` com header e `.table-scroll`.

```html
<div class="table-block full-span">
  <div class="table-block-header">
    <h2 class="section-title">
      <i class="fa-solid fa-user-group" aria-hidden="true"></i>
      Producao por colaborador
    </h2>
    <span class="section-action-icon">
      <i class="fa-solid fa-table" aria-hidden="true"></i>
    </span>
  </div>
  <div class="table-scroll">
    <table id="tabelaProducao">
      <thead>
        ...
      </thead>
      <tbody></tbody>
      <tfoot>
        ...
      </tfoot>
    </table>
  </div>
</div>
```

CSS de tabela:

```css
.table-block {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
  padding: 12px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-card);
  box-shadow: var(--shadow-card);
}

.table-scroll {
  flex: 1;
  min-height: 0;
  overflow: auto;
  background: rgba(15, 23, 42, 0.42);
  border: 1px solid var(--border);
  border-radius: var(--radius-inner);
}

table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  table-layout: fixed;
}

th,
td {
  height: 30px;
  padding: 6px 10px;
  border-bottom: 1px solid var(--border);
  font-size: 12px;
  line-height: 1.2;
  text-align: center;
  vertical-align: middle;
  font-variant-numeric: tabular-nums;
}

th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: var(--bg-table-head);
  color: #9fb0c8;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  white-space: nowrap;
}
```

Regras:

- Cabecalho de tabela sempre sticky.
- Primeira coluna normalmente alinhada a esquerda.
- Valores monetarios alinhados a direita.
- Colunas textuais longas devem usar `overflow: hidden`, `text-overflow: ellipsis`, `white-space: nowrap`.
- Usar `tfoot` para totais e metas agregadas.
- Usar `tbody tr:nth-child(odd/even)` para alternancia sutil.
- Hover nao pode reduzir contraste.
- Nao renderizar linhas vazias sem estado visual. Quando nao houver dados, mostrar linha unica com mensagem curta.

---

## KPIs e totais

Quando houver cards de resumo:

- colocar KPIs acima das tabelas ou no topo do grid;
- usar labels curtos e numeros grandes, mas nao hero-scale;
- animar numeros inteiros com `DashboardUtils.animateNumber`;
- moeda deve ser formatada diretamente, sem animacao se isso prejudicar leitura.

Helper recomendado:

```js
window.DashboardUtils = {
  animateNumber,
  formatNumber,
};
```

Formato:

```js
function formatarMoeda(valor) {
  return `R$ ${Number(valor || 0).toLocaleString("pt-BR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}
```

Regras:

- IDs de KPIs devem ser estaveis: `totalProducao`, `totalPagas`, `totalNaoPagas`, `totalCusto`.
- Nao calcular indicador critico apenas no front se ele tambem for usado pelo negocio; prefira endpoint com regra no PHP.
- O front pode somar totais derivados da resposta quando a regra ja veio fechada do backend.

---

## Frontend: orquestracao

Usar uma funcao central para atualizar o dashboard:

```js
function refreshAll() {
  buscarDados();
  buscarDadosFuncao();
  buscarMetasColaboradores();
}
```

No carregamento:

```js
window.addEventListener("DOMContentLoaded", function () {
  const dataAtual = new Date();
  document.getElementById("mes").value = String(
    dataAtual.getMonth() + 1,
  ).padStart(2, "0");
  document.getElementById("ano").value = String(dataAtual.getFullYear());
});

window.onload = function () {
  refreshAll();
};
```

Regras:

- Inicializar mes/ano atual antes de chamar os endpoints.
- Cada funcao de busca deve renderizar apenas sua propria area.
- Ao trocar periodo, recarregar todas as tabelas que dependem de mes/ano.
- Usar `encodeURIComponent()` para valores vindos do DOM.
- Usar optional chaining apenas quando o elemento realmente pode nao existir.
- Em caso de erro, limpar a area afetada ou mostrar estado de erro curto; nao deixar dado antigo parecendo atual.
- Evitar `innerHTML` com dados vindos do banco. Preferir `createElement` e `textContent`. Se usar `innerHTML` para linhas, garantir que os valores sejam controlados ou escapados.

---

## Contratos de endpoints JSON

Endpoints devem:

- definir `Content-Type: application/json; charset=utf-8`;
- validar parametros de entrada;
- retornar arrays ou objetos previsiveis;
- usar `http_response_code(400)` para parametros invalidos;
- usar `http_response_code(500)` em falha interna com resposta JSON;
- manter nomes de campos em `snake_case`.

Exemplo:

```php
header('Content-Type: application/json; charset=utf-8');

$mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
$ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

if ($mes < 1 || $mes > 12 || $ano < 2000 || $ano > 2100) {
    http_response_code(400);
    echo json_encode(['error' => 'Parametros invalidos'], JSON_UNESCAPED_UNICODE);
    exit;
}
```

Campos comuns:

```json
{
  "nome_colaborador": "Nome",
  "nome_funcao": "Finalizacao Completa",
  "quantidade": 10,
  "pagas": 4,
  "nao_pagas": 6,
  "mes_anterior": 8,
  "recorde_producao": 12,
  "recorde_data": "2026-03",
  "bate_recorde": false,
  "custo": 2100.0,
  "custo_medio": 350.0
}
```

Para metas:

```json
{
  "colaboradores": [
    {
      "colaborador_id": 1,
      "nome_colaborador": "Nome",
      "quantidade_feita": 10,
      "meta_individual": 12,
      "saldo": -2
    }
  ],
  "total_produzido": 10,
  "meta_funcao": 100
}
```

---

## Backend: conexao e prepared statements

Regras:

- Incluir conexao com `include __DIR__ . '/../conexao.php';` ou `include_once`, conforme o padrao local do endpoint.
- Usar `prepare()` e `bind_param()` para parametros vindos da requisicao.
- Evitar interpolar `$_GET`, datas ou IDs diretamente no SQL.
- Quando o SQL tiver apenas constantes internas controladas, interpolacao ainda deve ser evitada se houver parametro dinamico equivalente.
- Para queries complexas com agregacao legada, pode remover `ONLY_FULL_GROUP_BY` apenas na sessao atual:

```php
$conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");
```

- Para `GROUP_CONCAT` de imagens/tarefas, aumentar limite na sessao:

```php
$conn->query("SET SESSION group_concat_max_len = 1048576");
```

- Fechar statements quando houver varios blocos sequenciais.
- Em endpoints criticos, envolver em `try/catch (Throwable $e)` e responder JSON.

---

## Periodo, mes anterior e fim do mes

Padrao para mes selecionado:

```php
$mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
$anoSelecionado = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

$mesAnterior = ($mes === 1) ? 12 : ($mes - 1);
$anoMesAnterior = ($mes === 1) ? ($anoSelecionado - 1) : $anoSelecionado;

$fimMesDia = cal_days_in_month(CAL_GREGORIAN, $mes, $anoSelecionado);
$fimMesData = sprintf('%04d-%02d-%02d', $anoSelecionado, $mes, $fimMesDia);
$fimMesDataTime = $fimMesData . ' 23:59:59';
```

Regras:

- Usar fim do mes selecionado como data limite para pagamento, historico e snapshot.
- Janeiro deve comparar com dezembro do ano anterior.
- Para snapshot de status, usar `MAX(data_movimento)` ate `$fimMesDataTime`.
- Ao comparar pagamento por `pagamento_itens.criado_em`, usar `DATE(pi.criado_em) <= ?` com `$fimMesData`.
- Ao comparar campos datetime/historico, usar `$fimMesDataTime`.

---

## Regras de producao

Dashboards de producao devem manter a logica de `TelaGerencial`:

- Uma tarefa entra no mes quando existe movimento em `log_alteracoes` no mes/ano selecionado com status valido ou quando `fi.prazo` cai no mes/ano com status valido.
- Status validos: `finalizado`, `em aprovacao`, `ajuste`, `aprovado com ajustes`, `aprovado`.
- Tambem confirmar que a tarefa estava finalizada/aprovavel ate o fim do periodo via status atual ou historico.
- Excluir colaboradores administrativos/fora da regra quando a query de referencia excluir: `21`, `15`; para finalizacao tambem `7`, `34` quando aplicavel.
- Usar `COUNT(DISTINCT fi.idfuncao_imagem)` quando a query pode duplicar por historico, joins ou pagamentos.
- Para recordes, considerar janela historica de 36 meses quando seguir TelaGerencial.
- Excluir o mes atual e o proprio mes selecionado do calculo de recorde quando a comparacao for "bateu recorde".
- Excluir `2024-10` do recorde quando estiver seguindo a regra gerencial existente.

Normalizacao de funcao:

```sql
CASE
  WHEN fi.funcao_id = 4 AND LOWER(TRIM(i.tipo_imagem)) = 'planta humanizada'
    THEN 'Finalizacao de Planta Humanizada'
  WHEN fi.funcao_id = 4
    THEN 'Finalizacao Completa'
  ELSE f.nome_funcao
END AS nome_funcao
```

Ordem canonica:

```sql
FIELD(nome_funcao,
  'Caderno',
  'Filtro de assets',
  'Modelagem',
  'Composicao',
  'Pre-finalizacao',
  'Finalizacao Parcial',
  'Finalizacao Completa',
  'Finalizacao de Planta Humanizada',
  'Pos-producao',
  'Alteracao'
)
```

Observacao: no banco podem existir nomes com acentos. Ao escrever SQL real, usar exatamente os textos armazenados nas tabelas ou normalizar com `LOWER(TRIM(...))`.

---

## Regras de pagamento e nao pagas

Em `TelaGerencial`, "quantidade feita" para metas e varias tabelas significa quantidade nao paga dentro da regra gerencial.

Regras:

- `pagas` conta tarefas ja pagas ate a data limite do periodo.
- `nao_pagas` conta tarefas ainda nao pagas ate a data limite do periodo.
- Quando existir `pagamento_itens`, ele prevalece sobre `funcao_imagem.data_pagamento`.
- Quando nao existir `pagamento_itens`, usar `fi.data_pagamento` desde que nao seja `NULL` nem `'0000-00-00'`.
- Para finalizacao (`funcao_id = 4`), tratar observacoes:
  - `Finalizacao Parcial` indica pagamento parcial.
  - `Pago Completa` ou observacao vazia/nula indica pagamento completo.
  - Se parcial estiver pendente, tarefa pode continuar como nao paga para custo restante.
- Planta humanizada e finalizacao de planta humanizada devem compartilhar regra de recorde quando a tela exigir comparacao conjunta.

---

## Custos

Para custos, usar helper central:

```php
require_once __DIR__ . '/../helpers/custo_tarefa.php';
```

Fluxo recomendado:

```php
$statusFinalizacaoMap = custo_tarefa_carregar_status_finalizacao($conn, $tarefasFinalizacao, $fimMesData);
custo_tarefa_carregar_contexto($conn, $colaboradoresParaCusto);
$custo = calcularCustoTarefa($colaboradorId, $funcaoId, $imagemNome);
```

Regras:

- Nao duplicar tabela fixa de custos no front se houver helper backend.
- Custo total deve ser calculado no PHP quando depender de colaborador, funcao, imagem ou pagamento parcial.
- Para finalizacao parcial pendente, aplicar fator `0.5` quando a regra do helper/status indicar metade restante.
- Para funcao que nao gera custo no dashboard atual, manter custo `0.0` explicitamente.
- Arredondar custos no backend com `round($valor, 2)`.
- Custo medio = `custo_total / quantidade` ou `0.0` se quantidade for zero.
- No front, formatar com `R$` e locale `pt-BR`.

---

## Metas

Metas de finalizacao seguem:

- colaboradores ativos com `funcao_id = 4`;
- excluir colaboradores fora da regra (`21`, `15`, `30`, `7`, `34`) quando o endpoint de referencia fizer isso;
- excluir colaboradores que tambem tenham `funcao_id = 7`, quando a regra for Finalizacao Completa;
- `quantidade_feita` deve seguir a mesma regra de nao pagas da producao;
- meta individual vem de `meta_colaborador`;
- meta mensal da funcao vem de `metas`;
- saldo = `quantidade_feita - meta_individual`.

Resposta:

```php
echo json_encode([
    'colaboradores' => $colaboradores,
    'total_produzido' => $totalProduzido,
    'meta_funcao' => $metaFuncao,
], JSON_UNESCAPED_UNICODE);
```

No front:

- saldo positivo usa verde;
- saldo negativo usa vermelho;
- saldo zero usa texto muted;
- total produzido e meta da funcao ficam no `tfoot`.

---

## Recordes e comparativos

Regras:

- `mes_anterior` compara com o mes imediatamente anterior ao selecionado.
- `recorde_producao` representa maior quantidade historica dentro da regra do indicador.
- `bate_recorde` deve ser booleano.
- Quando bater recorde, destacar a linha inteira.
- A barra visual de recorde deve mostrar quantidade atual e marcador do recorde anterior.

Exemplo de UI:

```js
function _buildRecordBar(qtd, recorde) {
  const pct = recorde > 0 ? Math.round((recorde / qtd) * 100) : 0;
  const lineHtml =
    recorde > 0
      ? `<div class="record-bar-line" style="left:${pct}%"></div>`
      : "";
  return `<div class="record-bar-wrap"><div class="record-bar-fill" style="width:100%"><span class="record-bar-qty">${qtd}</span></div>${lineHtml}</div>`;
}
```

Regras de seguranca para esse HTML:

- `qtd` e `recorde` devem ser numericos.
- Nao inserir texto livre do banco dentro do HTML da barra.

---

## Modais e paineis laterais

Para drill-down de itens, usar painel lateral quando o conteudo for lista de imagens/tarefas:

```html
<div id="modalImagensOverlay" class="imagens-overlay" aria-hidden="true">
  <div
    class="imagens-panel"
    role="dialog"
    aria-modal="true"
    aria-labelledby="imagensTitulo"
  >
    <div class="imagens-header">
      <h3 id="imagensTitulo"></h3>
      <button id="fecharModalImagens" type="button" class="imagens-fechar">
        Fechar
      </button>
    </div>
    <div id="imagensBody" class="imagens-body"></div>
  </div>
</div>
```

Regras:

- Abrir ao clicar em um link/botao textual dentro da tabela.
- Fechar por botao, click no backdrop e tecla `Escape`.
- Atualizar `aria-hidden`.
- Usar `textContent` para nomes de imagens e colaboradores.
- Status curtos como `Pago` e `Nao pago` podem usar badges.

---

## Filtros internos de tabela

Quando precisar filtrar uma tabela sem recarregar endpoint:

- criar menu flutuante posicionado pelo `getBoundingClientRect()` do `th`;
- fechar ao clicar fora, pressionar `Escape`, rolar ou redimensionar;
- manter estado local simples;
- reinicializar filtro apos recarregar dados.

Padrao:

```js
let _prodFiltroColab = "";
let _prodFiltroMenuEl = null;
```

Regras:

- Usar radio para escolha exclusiva.
- Incluir opcao `Todos`.
- Aplicar filtro via `tr.style.display`.
- Marcar visualmente o `th` ativo.
- Nao duplicar fetch apenas para filtro local quando os dados ja estao na tabela.

---

## Relatorios

Relatorios gerenciais podem ser gerados no front a partir das tabelas atuais, quando o objetivo for exportar exatamente a visao filtrada.

Regras:

- Botao global: `#gerar-relatorio`.
- Coletar HTML das tabelas atuais com funcao dedicada.
- Permitir selecionar colunas por indice e/ou regex de cabecalho.
- Preservar `tfoot` quando houver totais.
- Renomear cabecalhos apenas no HTML do relatorio quando necessario, sem alterar a tela.
- Abrir nova janela apenas para o documento de exportacao.
- Usar `html2canvas` + `jsPDF` apenas dentro da janela de relatorio, nao carregar essas libs na tela principal se nao forem necessarias.
- Evitar `alert()` em caso de popup bloqueado; usar SweetAlert2 ao refatorar.

Exemplo de nome:

```js
const fileName = `Relatorio_Tela_Gerencial_${safeFileMonth}_${new Date().getFullYear()}.pdf`;
```

---

## Responsividade

Desktop:

- body em grid `60px 1fr`;
- altura fixa `100vh`;
- scroll interno nas tabelas;
- header em uma linha;
- tabelas densas.

Abaixo de `1200px`:

```css
body {
  height: auto;
  min-height: 100vh;
  overflow: auto;
}

.container {
  height: auto;
  min-height: 100vh;
  overflow: visible;
}

.tables-main-grid {
  grid-template-columns: 1fr;
  grid-template-rows: auto;
  overflow: visible;
}

.table-scroll {
  overflow: auto;
}

table {
  min-width: 820px;
}
```

Abaixo de `760px`:

- body vira uma coluna;
- container ocupa `grid-column: 1`;
- header empilha;
- filtros ocupam largura total;
- botao de relatorio pode ocupar 100%.

Regras:

- Nao transformar tabela gerencial densa em cards se isso prejudicar comparacao numerica.
- Preferir scroll horizontal para tabelas com muitas colunas.
- Garantir que nenhuma tabela force overflow invisivel.
- Manter header sticky apenas dentro do scroll da tabela.

---

## Estados de carregamento, erro e vazio

Regras:

- Antes de fetch, opcionalmente mostrar linha de carregamento curta.
- Em erro, limpar a tabela afetada e mostrar mensagem no console; se o usuario precisa agir, usar Toastify ou SweetAlert2.
- Em resposta vazia, renderizar uma linha:

```html
<tr>
  <td colspan="8" class="empty-cell">Sem dados para o periodo selecionado.</td>
</tr>
```

- Nao deixar totais antigos depois de erro ou resposta vazia.
- Totais devem voltar para `0` ou `R$ 0,00`.

---

## Acessibilidade e semantica

Regras:

- Botoes clicaveis devem ser `<button type="button">`, nao `<a>` sem href.
- Icones decorativos usam `aria-hidden="true"`.
- Modais usam `role="dialog"`, `aria-modal="true"` e `aria-labelledby`.
- Controles de filtro devem ter `<label for="...">`.
- Textos de tabela nao devem depender apenas de cor; quando possivel, usar texto como `Pago`, `Nao pago`, `+3`, `-2`.
- Foco visivel em selects, botoes e menus.

---

## Seguranca

Regras obrigatorias:

- Nunca confiar em `$_GET`; converter e validar.
- Nunca interpolar entrada do usuario em SQL.
- Retornar JSON com `JSON_UNESCAPED_UNICODE` quando houver texto em portugues.
- No front, preferir `textContent` para dados do banco.
- Se precisar usar `innerHTML`, escapar valores dinamicos.
- Nao expor mensagens internas detalhadas para usuarios finais em producao. Durante desenvolvimento, endpoint pode retornar erro JSON curto.
- Evitar abrir novas janelas com conteudo vindo diretamente do banco sem escape.

---

## Checklist para criar ou alterar dashboard

- A pagina valida sessao antes do HTML.
- `logs_usuarios` e atualizado com `NOW()`.
- A sessao e fechada antes de consultas pesadas.
- Assets usam `asset_url()` quando disponivel.
- Filtros de mes/ano atualizam todas as areas dependentes.
- Endpoints retornam JSON e validam parametros.
- Queries usam prepared statements para entrada dinamica.
- Regras de periodo usam fim do mes e mes anterior corretamente.
- Custos usam `helpers/custo_tarefa.php` quando dependerem de tarefa/colaborador.
- Pagas/nao pagas seguem regra de `pagamento_itens` antes de `data_pagamento`.
- Metas e recordes usam a mesma definicao de quantidade do indicador exibido.
- Tabelas tem scroll interno, header sticky e `tfoot` para totais.
- Numeros usam locale `pt-BR`; moeda usa `R$`.
- Estados vazio/erro nao deixam dados antigos.
- Responsividade foi testada em desktop, tablet e mobile.
- Dialogos nao usam `alert()`, `confirm()` ou `prompt()` nativos em codigo novo.
