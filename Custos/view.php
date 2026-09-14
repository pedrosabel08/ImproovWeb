<main class="cost-main" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <div class="cost-topline">
        <nav aria-label="Breadcrumb"><a href="../Obras/">Obras</a><span>›</span><span id="breadcrumb-obra">Projeto</span></nav><button type="button" id="theme-toggle" class="icon-button" aria-label="Alternar tema">◐</button>
    </div>
    <header class="cost-header">
        <div>
            <p class="eyebrow">VISÃO FINANCEIRA</p>
            <h1>Custos do Projeto</h1>
            <p class="muted">Acompanhe a receita, os custos de produção e a margem do projeto.</p>
        </div>
        <div class="header-actions"><label class="sr-only" for="obra">Obra</label><select id="obra">
                <option value="">Selecione uma obra</option><?php foreach ($todas as $o): ?><option value="<?= (int)$o['idobra'] ?>"><?= htmlspecialchars($o['nomenclatura'], ENT_QUOTES, 'UTF-8') ?><?= (int)$o['status_obra'] === 1 ? ' · Inativa' : '' ?></option><?php endforeach; ?>
            </select><button id="open-commercial" type="button">Composição comercial <span>↗</span></button></div>
    </header>
    <div id="page-message" class="notice" role="status">Selecione uma obra para consultar os custos.</div>
    <div id="dashboard" hidden>
        <section class="kpi-grid" aria-label="Indicadores financeiros">
            <article class="kpi">
                <div class="kpi-label"><span class="kpi-icon gold">$</span>Valor vendido</div><strong id="k-vendido">—</strong>
                <p>Receita líquida <b id="k-liquido">—</b></p>
            </article>
            <article class="kpi">
                <div class="kpi-label"><span class="kpi-icon blue">✓</span>Produção realizada</div><strong id="k-realizado">—</strong>
                <p id="k-realizado-note">Pagamentos registrados</p>
            </article>
            <article class="kpi">
                <div class="kpi-label"><span class="kpi-icon amber">◷</span>Produção a pagar</div><strong id="k-a-pagar">—</strong>
                <p>Produção projetada <b id="k-projetado">—</b></p>
            </article>
            <article class="kpi margin-kpi">
                <div class="kpi-label"><span class="kpi-icon green">↗</span>Margem projetada</div><strong id="k-margem">—</strong>
                <p><b id="k-margem-pct">—</b> da receita líquida</p>
            </article>
        </section>
        <div class="analysis-grid">
            <section class="panel health-panel">
                <div class="panel-heading">
                    <div>
                        <h2>Saúde financeira do projeto</h2>
                        <p class="muted">Como a receita líquida está comprometida</p>
                    </div><span id="project-health"></span>
                </div>
                <div id="health-bar" class="health-bar" aria-label="Composição financeira"></div>
                <div id="health-legend" class="health-legend"></div>
                <p class="panel-foot" id="health-note"></p>
            </section>
            <section class="panel distribution-panel">
                <div class="panel-heading">
                    <h2>Distribuição da produção</h2><label class="sr-only" for="distribution-mode">Tipo de custo</label><select id="distribution-mode">
                        <option value="projetado">Projetada</option>
                        <option value="realizado">Realizada</option>
                    </select>
                </div>
                <div class="distribution-body">
                    <div id="donut"></div>
                    <div id="distribution-list"></div>
                </div>
                <details id="other-functions" hidden>
                    <summary>Ver funções em Outros</summary>
                    <div id="other-list"></div>
                </details>
            </section>
        </div>
        <section class="panel images-panel">
            <div class="panel-heading">
                <div>
                    <h2>Lista de imagens <span class="count" id="image-count"></span></h2>
                    <p class="muted">Identifique onde a produção consome mais receita.</p>
                </div>
                <div class="table-tools"><label class="search"><span aria-hidden="true">⌕</span><input id="search" type="search" placeholder="Buscar imagem…" aria-label="Buscar imagem"></label><select id="health-filter" aria-label="Filtrar saúde">
                        <option value="">Todas as imagens</option>
                        <option value="critico">Crítico</option>
                        <option value="atencao">Atenção</option>
                        <option value="neutro">Margem positiva</option>
                        <option value="saudavel">Saudável</option>
                        <option value="sem_comercial">Sem comercial</option>
                    </select></div>
            </div>
            <div class="table-scroll">
                <table id="images-table">
                    <thead>
                        <tr>
                            <th><button data-sort="nome">Imagem ↕</button></th>
                            <th><button data-sort="vendido">Vendido ↕</button></th>
                            <th><button data-sort="liquido">Receita líquida ↕</button></th>
                            <th><button data-sort="realizado">Realizado ↕</button></th>
                            <th><button data-sort="a_pagar">A pagar ↕</button></th>
                            <th><button data-sort="projetado">Projetado ↕</button></th>
                            <th><button data-sort="margem">Margem projetada ↕</button></th>
                            <th>Saúde</th>
                        </tr>
                    </thead>
                    <tbody id="images-body"></tbody>
                </table>
            </div>
            <div class="table-footer"><span id="table-note">Clique em uma imagem para abrir o detalhe financeiro.</span>
                <div><button id="prev-page" aria-label="Página anterior">←</button><span id="page-number"></span><button id="next-page" aria-label="Próxima página">→</button></div>
            </div>
        </section>
        <div class="bottom-grid">
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>Custos gerais da obra</h2>
                        <p class="muted">Itens sem vínculo direto com uma imagem</p>
                    </div><strong id="general-total"></strong>
                </div>
                <div id="general-content"></div>
            </section>
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>Pagamentos recentes</h2>
                        <p class="muted">Últimos lançamentos registrados</p>
                    </div><span class="subtle-icon">↙</span>
                </div>
                <div id="recent-payments"></div>
            </section>
        </div>
        <details class="panel audit-panel" id="audit-panel">
            <summary id="audit-summary">Qualidade dos dados</summary>
            <div id="audit-content"></div>
        </details>
        <p class="disclaimer">Margem de produção do projeto. Custos administrativos e demais despesas da empresa não estão incluídos.</p>
    </div>
</main>
<dialog id="image-detail" class="drawer">
    <div class="dialog-top">
        <p class="eyebrow">DETALHE FINANCEIRO</p><button class="close-dialog icon-button" aria-label="Fechar detalhe">×</button>
    </div>
    <div id="detail-content"></div>
</dialog>
<dialog id="commercial-dialog" class="commercial-dialog">
    <div class="dialog-top">
        <div>
            <p class="eyebrow">RECEITA DO PROJETO</p>
            <h2>Composição comercial</h2>
        </div><button class="close-dialog icon-button" aria-label="Fechar composição comercial">×</button>
    </div>
    <p class="muted" id="commercial-summary"></p>
    <div class="commercial-actions"><button id="add-commercial">+ Adicionar item</button><button id="show-import">Importar CSV</button></div>
    <div id="commercial-list"></div>
    <form id="commercial-form" hidden>
        <h3 id="commercial-form-title">Adicionar item</h3><input name="id" type="hidden">
        <div class="form-grid"><label>Tipo<select name="categoria">
                    <option value="imagem">Imagem</option>
                    <option value="foto">Serviço fotográfico</option>
                </select></label><label id="commercial-image-label">Imagem<select name="imagem_id" id="commercial-image"></select></label><label>Valor vendido (R$)<input name="valor" type="number" min="0" step="0.01" required></label><label data-image-field>Contrato<input name="numero_contrato" maxlength="255"></label><label data-image-field>Imposto (%)<input name="imposto" type="number" min="0" max="100" step="0.01" value="0"></label><label data-image-field>Imposto (R$)<input name="valor_imposto" type="number" min="0" step="0.01" value="0"></label><label data-image-field>Comissão comercial (%)<input name="comissao_comercial" type="number" min="0" max="100" step="0.01" value="0"></label><label data-image-field>Comissão comercial (R$)<input name="valor_comissao_comercial" type="number" min="0" step="0.01" value="0"></label></div>
        <p class="muted">Os percentuais sugerem as deduções em reais. Confira os valores antes de salvar. Serviço fotográfico não possui deduções cadastradas no schema atual.</p><button type="submit" class="primary">Salvar valores</button><button type="button" id="cancel-commercial">Cancelar</button>
    </form>
    <form id="import-form" hidden>
        <h3>Importar valores comerciais</h3>
        <p class="muted">CSV em UTF-8, separado por vírgulas. Valores decimais com ponto. Cada nome deve corresponder a uma única imagem desta obra. Itens existentes serão atualizados; novos serão adicionados.</p><a href="modelo-comercial.csv" download>Baixar modelo de cabeçalhos ↓</a><label class="file-field">Arquivo CSV<input name="arquivo" type="file" accept=".csv,text/csv" required></label><button type="submit" class="primary">Validar e importar</button>
    </form>
    <div id="commercial-message" role="status"></div>
</dialog>