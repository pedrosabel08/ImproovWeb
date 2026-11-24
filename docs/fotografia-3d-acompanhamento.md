# Fotografia arquitetônica no Prédio 3D

## Entrega 1 · HUD

Implementada sobre o visualizador do Mapa de Pavimento. O cabeçalho reúne obra, modelo, versões, configuração espacial e mapa. O envio de GLB está dentro de Versões, inclusive para uma obra sem modelo. Orbital, Caminhada e Voo compartilham a viewport. Os painéis são sobrepostos e se recolhem ao iniciar a exploração.

**Arquivos.** Em `MapaPavimento/predio-3d`, a alteração abrange visualizador-glb-navegacao (HTML e JS), exploration-mode (JS e CSS), flow-spatial-shell.css, seagull-mode.js, model-measurement.js, model-upload.js e empreendimento-map-bridge.js.

**Validação.** Login pelo navegador interno em RKS_DCF/P00. Verificados seleção do térreo, versões, abertura e cancelamento de envio e configuração espacial, medição, desligar/ligar sol, voo com cota, posicionamento de Josh, movimento, troca de perspectiva e Esc. No notebook, abrir Sol preservou o canvas em 1302 × 702. Medidas de layout verificadas em 1920×1080, 1366×768, 1180×820, 820×1180, 390×844 e 2560×1080. As suítes seagull_flight, exploration_collision e model_measurement passaram com Three r180. A auditoria PHP de metadata passou em 850 verificações. O teste inicial do mapa do térreo verificou a tentativa de abertura, sem confirmar a renderização da planta.

**Andamento.** Próxima entrega: motor fotográfico e estudo solar. A homologação final deve incluir planta com PDF disponível, temas, evidências visuais, persistência, autorizações e comparação de desempenho. Os refinamentos aprovados exigem autoria/data da preferência, imutabilidade da composição aprovada, referência solar histórica e capturas somente por solicitação.
