<?php
// Shared fragment; this is not a standalone page.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
?>
<div class="modal" id="entregaModal" role="dialog" aria-modal="true" aria-labelledby="modalTitulo">
    <div class="modal-content modal-wide delivery-workspace">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitulo">Entrega</h2>
            <button type="button" class="modal-close fecharModal" aria-label="Fechar detalhes da entrega"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="modal-body delivery-workspace-body">
            <div class="delivery-workspace-sidebar">
                <section class="delivery-information" aria-labelledby="deliveryInformationTitle">
                    <h3 class="delivery-section-title" id="deliveryInformationTitle"><i class="fa-solid fa-file-lines" aria-hidden="true"></i> Informações da entrega</h3>
                    <div class="delivery-information-stats">
                        <div class="delivery-information-stat">
                            <span class="delivery-stat-label">Recebimento</span>
                            <div class="delivery-stat-value"><i class="fa-regular fa-calendar" aria-hidden="true"></i><span id="modalRecebimento">—</span></div>
                        </div>
                        <div class="delivery-information-stat">
                            <span class="delivery-stat-label">Prazo</span>
                            <div class="delivery-stat-value"><i class="fa-regular fa-calendar" aria-hidden="true"></i><span id="modalPrazo">—</span></div>
                        </div>
                        <div class="delivery-information-stat">
                            <span class="delivery-stat-label">Conclusão geral</span>
                            <div class="delivery-stat-value"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i><span id="modalProgresso">—</span></div>
                        </div>
                    </div>
                    <div id="modalCaminhoRow" class="modal-caminho-row">
                        <span class="caminho-label">Caminho</span>
                        <div class="caminho-field">
                            <i class="fa-solid fa-folder delivery-folder-icon" aria-hidden="true"></i>
                            <code id="modalCaminhoTexto" class="caminho-text">—</code>
                            <button type="button" id="btnCopiarCaminho" class="btn-copiar-caminho" title="Copiar caminho" aria-label="Copiar caminho" disabled><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                        </div>
                    </div>
                </section>
                <section id="modalReviewBatches" class="review-batches-panel" aria-label="Cobrança de review" style="display:none;"></section>
            </div>
            <section class="delivery-images-pane" aria-labelledby="deliveryImagesTitle">
                <div class="delivery-images-heading">
                    <h3 class="delivery-section-title" id="deliveryImagesTitle"><i class="fa-regular fa-images" aria-hidden="true"></i> Imagens</h3>
                    <button type="button" class="btn-action btn-primary" id="btnAdicionarImagem"><i class="fa-solid fa-plus" aria-hidden="true"></i> Adicionar</button>
                </div>
                <div class="delivery-images-scroll" tabindex="0" role="region" aria-label="Lista de imagens da entrega">
                    <div id="modalImagens"></div>
                </div>
            </section>
        </div>
        <div class="modal-footer">
            <a id="deliveryPlanLink" class="btn-action btn-primary" href="#" hidden><i class="fa-regular fa-calendar-check" aria-hidden="true"></i> Planejar entrega</a>
            <button type="button" class="btn-action btn-secondary fecharModal">Fechar</button>
        </div>
    </div>
</div>
