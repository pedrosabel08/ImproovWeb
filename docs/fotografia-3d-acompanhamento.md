# Fotografia arquitetônica no Prédio 3D

## Entrega 1 · HUD

Implementada sobre o visualizador do Mapa de Pavimento. O cabeçalho reúne obra, modelo, versões, configuração espacial e mapa. O envio de GLB está dentro de Versões, inclusive para uma obra sem modelo. Orbital, Caminhada e Voo compartilham a viewport. Os painéis são sobrepostos e se recolhem ao iniciar a exploração.

**Arquivos.** Em `MapaPavimento/predio-3d`, a alteração abrange visualizador-glb-navegacao (HTML e JS), exploration-mode (JS e CSS), flow-spatial-shell.css, seagull-mode.js, model-measurement.js, model-upload.js e empreendimento-map-bridge.js.

**Validação.** Login pelo navegador interno em RKS_DCF/P00. Verificados seleção do térreo, versões, abertura e cancelamento de envio e configuração espacial, medição, desligar/ligar sol, voo com cota, posicionamento de Josh, movimento, troca de perspectiva e Esc. No notebook, abrir Sol preservou o canvas em 1302 × 702. Medidas de layout verificadas em 1920×1080, 1366×768, 1180×820, 820×1180, 390×844 e 2560×1080. As suítes seagull_flight, exploration_collision e model_measurement passaram com Three r180. A auditoria PHP de metadata passou em 850 verificações. O teste inicial do mapa do térreo verificou a tentativa de abertura, sem confirmar a renderização da planta.

**Andamento.** Próxima entrega: motor fotográfico e estudo solar. A homologação final deve incluir planta com PDF disponível, temas, evidências visuais, persistência, autorizações e comparação de desempenho. Os refinamentos aprovados exigem autoria/data da preferência, imutabilidade da composição aprovada, referência solar histórica e capturas somente por solicitação.

## Entrega 2 · Motor fotográfico e sol

Implementada a ferramenta de fotografia sobre caminhada e voo, com suspensão do controlador e uso do ciclo de renderização existente. O sensor de referência é 36 × 24 mm. A focal inicial deriva da projeção efetiva de entrada. A máscara recorta a vista simetricamente. A câmera fotográfica ajusta posição, orientação, lente, horizonte e perspectiva vertical. O estado solar guarda localização, norte, fuso, data e hora separadamente da configuração corrente da obra. Fotografar não gera arquivo.

**Arquivos.** photo-studio.js/css, photo-optics.mjs, visualizador-glb-navegacao.js/html, exploration-mode.js, seagull-mode.js, solar-lighting.js e tests/photo_optics_test.mjs.

**Validação.** Vinte combinações de janela e proporção passaram na verificação de continuidade da perspectiva e igualdade entre projeção visível e projeção de saída. A suíte solar passou incluindo norte, azimute, noite e fuso. No navegador interno autenticado, a fotografia abriu a partir do voo; recorte 4:5, correção vertical, horário 08:00 e confirmação de descarte funcionaram. A saída retornou ao Voo na cota de origem.

**Próxima entrega.** Integração com imagens, versões reais do GLB e persistência. A homologação integrada deverá exercitar a restauração e a captura de arquivos.

## Entrega 3 · Imagens, versões e persistência

A biblioteca consulta imagens_cliente_obra e mantém a imagem selecionada na mesma obra. A gravação usa o ID da versão carregada e transações com bloqueio da obra, revisão otimista e chave de repetição. Preferência guarda colaborador/data. A data da primeira aprovação torna a composição imutável inclusive depois que outra passa a ser a aprovada. A cópia conserva a origem. A leitura histórica usa o arquivo da versão solicitada sem ativá-la para outros usuários.

**Arquivos.** Migration SQL e script CLI de 24/11, helpers/enquadramento_3d_helper.php, MapaPavimento/api.php, photo-library.js e sua integração com o bridge, estúdio e controladores. Teste tests/enquadramentos_3d_test.php.

**Validação.** A suíte executou o DDL duas vezes em um MariaDB isolado em loopback, gravou e releu composições, testou vínculos cruzados, autoria, troca de preferido/aprovado, imutabilidade histórica, revisão desatualizada, vínculo posterior e cópia entre versões. Passou. O banco configurado pelo Apache é MySQL remoto; a migration não foi aplicada nele. A verificação somente de leitura encontrou um modelo legado sem versão registrada.

**Próxima entrega.** Marcadores, geração solicitada de captura e miniatura, comparação, aprovação pela interface e exportação estruturada. A homologação pelo navegador com o backend de persistência requer um banco migrado.

## Entrega 4 · Capturas, marcadores e revisão

Concluídas as entregas 1 a 4. A foto e a miniatura são geradas na ação **Gerar captura**, após salvar a composição. A API verifica sessão, obra, autoria, revisão e proporção. Os JPEG ficam em armazenamento privado e são entregues por uma rota autenticada. O filtro orbital começa em “Ocultar câmeras”. A revisão permite comparação, preferência, aprovação e exportação da câmera em JSON.

**Arquivos.** photo-review.js, photo-library.js, photo-studio.js/css, visualizador-glb-navegacao.js, api.php, helpers/enquadramento*3d*{captura,helper}.php e os testes photo\_{capture_test.cjs,qa_database.php}.

**Testes aprovados.** O PHP-CGI recebeu um upload multipart, devolveu a imagem 1638 × 2048 e a miniatura 256 × 320, rejeitou sessão ausente, CSRF inválido, obra incorreta, revisão obsoleta e substituição de captura aprovada. A repetição conservou um registro. No Chrome autenticado, o teste abriu o modelo RKS_DCF/P00, selecionou uma imagem 4:5, salvou e duplicou a composição, gerou duas capturas, comparou manhã e tarde, preferiu, aprovou, exportou e restaurou a câmera após recarregar. Conferiu também a criação do marcador no filtro e a contagem de chamadas ao GLB. Os dados de fotografia usaram a API PHP e uma sessão e um banco descartáveis. As demais leituras vieram do ambiente oficial.

**Em andamento.** Entrega 5, homologação e documentação. Consolidar as evidências visuais, as amostras antes/depois e as limitações operacionais. A migration do banco compartilhado permanece pendente de implantação.
