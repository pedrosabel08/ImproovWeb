# Fotografia arquitetônica no Flow

## Entrega funcional

- A HUD concentra obra, modelo, versões, configurações espaciais e mapa no cabeçalho. Orbital, Caminhada e Voo compartilham a viewport e mantêm os controles correspondentes ao modo ativo.
- A partir de Caminhada ou Voo, **Fotografar** preserva a pose da câmera e aplica a máscara central. O painel ajusta lente, recorte, posição, direção, inclinação, horizonte, perspectiva vertical e sol.
- **Imagens e enquadramentos** consulta as imagens da própria obra. A composição salva fica vinculada ao ID da versão do GLB. A biblioteca permite localizar, restaurar, renomear, duplicar, editar, comparar e vincular composições.
- A preferência registra a pessoa e a data da seleção. Cada par imagem–versão admite um preferido e um aprovado. Uma composição que já foi aprovada permanece imutável. Para refiná-la, o usuário salva uma cópia rastreável. O fluxo de aprovação da composição é independente das etapas de produção da imagem.
- Marcadores orbitais indicam posição, direção e status. O filtro começa desligado. **Gerar captura** escreve um JPEG de até 2048 px no lado maior e uma miniatura de até 320 px. A comparação usa as capturas solicitadas para aquela imagem.

## Navegação e fotografia

`exploration-mode.js` controla Orbital, Caminhada e Voo. `PhotoStudio` mantém a origem enquanto a fotografia está ativa. Ele clona a câmera de perspectiva, pausa o controlador e esconde o avatar. A fotografia usa a cena, o renderer, a geometria e o estudo solar existentes. A saída recupera a iluminação de navegação e retoma o controlador de origem. Um deslocamento fotográfico é transferido ao controlador quando passa pela verificação de suporte ou colisão.

O render da captura usa a câmera calculada para o recorte, sem grade, eixos, guias, marcadores, auxiliares de sol ou avatares. A captura mantém as atribuições exibidas para os dados geográficos. O canvas que recebe o JPEG é reduzido após a codificação.

| Fotografia | Ação |
|---|---|
| F | Abrir a ferramenta durante Caminhada ou Voo |
| W / A / S / D | Deslocar no plano horizontal |
| Q / E | Baixar / elevar |
| Setas | Girar e inclinar |
| Arrastar | Ajustar direção e inclinação |
| H | Alternar a prévia limpa |
| Esc | Sair e confirmar descarte quando houver alterações pendentes |

## Referencial e contrato de câmera

- Metros, eixo Y para cima, câmera voltada para −Z no seu próprio espaço. As matrizes têm 16 componentes em ordem de colunas, como em Three.js.
- `local = inverse(modelToScene) × cameraToScene`. Ao restaurar, a cena aplica a transformação atual do modelo à pose local. `targetLocal` indica a direção no GLB a uma unidade de distância da câmera.
- `opticalLocal` e `projectionMatrix` representam a câmera efetiva da captura, inclusive a correção vertical. O sensor de referência é 36 × 24 mm. O campo `gate` registra o recorte do sensor e `shiftY` mede o deslocamento em frações da altura útil. Focal, FOV vertical, proporção, horizonte e altura constam no JSON.
- A máscara central preserva a perspectiva de entrada. Alterar a proporção altera a janela de composição. Abrir ou recolher um painel não redimensiona o canvas.
- A âncora de navegação guarda a pose do controlador para voltar à caminhada ou ao voo. A altura do painel é relativa ao terreno. A cota representa a posição Y da cena.
- A referência persistida guarda as matrizes do modelo e do entorno e a configuração espacial disponível. A fotografia guarda separadamente latitude, longitude, norte, fuso, data e hora solar. A restauração usa esse conjunto registrado ao recalcular o sol mesmo se a obra passar a ter outros parâmetros.

Para futura integração com o 3ds Max, converter o referencial Y-up para o Z-up da cena de origem e calibrar a unidade e o pivô de exportação do GLB. Ajustar distância focal, abertura efetiva e deslocamento vertical à convenção da câmera física usada no Max. Validar com pontos de controle no edifício, linha do horizonte, correspondência do recorte e direção solar. O JSON já fornece as matrizes necessárias para conferir essa correspondência.

## Persistência e rotas

A migration [2026-11-24_imagem_enquadramentos_3d.sql](../sql/2026-11-24_imagem_enquadramentos_3d.sql) cria `imagem_enquadramentos_3d`. A tabela guarda obra, imagem opcional, versão, registro de origem, autor, nome, composição JSON, revisão, chave de repetição, estado de preferência e aprovação, autoria, datas e a referência da captura.

O [script de migration](../scripts/migrate_fotografia_3d.php) executa o DDL e cadastra uma versão para cada GLB legado ainda sem registro, a partir do próprio arquivo. A reexecução preserva versões já cadastradas. O CLI precisa alcançar os arquivos GLB ativos com o mecanismo de armazenamento usado pelo ambiente.

Os endpoints estão em `MapaPavimento/api.php`.

| Método | Ação | Função |
|---|---|---|
| GET | `enquadramentos_listar` | Imagens, versões, composições e permissões |
| POST | `enquadramento_gravar` | Criar, editar, duplicar, preferir, aprovar ou excluir |
| POST | `enquadramento_capturar` | Persistir JPEG e miniatura após checar revisão e proporção |
| GET | `enquadramento_arquivo` | Servir o JPEG privado solicitado |
| GET | `modelo_3d_contexto`, `modelo_3d_arquivo` | Consultar e abrir uma versão específica do GLB |

Cada rota valida a sessão e a obra. Os POSTs exigem CSRF. Edição, exclusão e captura exigem autoria ou administração. Aprovação reutiliza a lista de administradores do Mapa/3D. As transações e chaves únicas controlam a revisão concorrente e a seleção simultânea. O GLB histórico é aberto sem ativar a versão para os demais usuários.

O armazenamento usa `FLOW_PHOTO_STORAGE_ROOT` quando configurado. O padrão é `flow-private/enquadramentos-3d` fora do document root. O serviço exige PHP GD com JPEG e permissão de escrita no diretório privado. Em instalação com vários servidores, a raiz deve apontar para armazenamento privado compartilhado, com o mesmo backup da base. O Apache em homologação usa um MySQL remoto. A migration e as operações da biblioteca foram executadas no MariaDB isolado.

## Arquivos da implementação

| Tipo | Arquivos |
|---|---|
| Criados no visualizador | `photo-studio.js`, `photo-studio.css`, `photo-optics.mjs`, `photo-library.js`, `photo-review.js` |
| Modificados no visualizador | `visualizador-glb-navegacao.html/js`, `flow-spatial-shell.css`, `empreendimento-map-bridge.js`, `exploration-mode.js/css`, `seagull-mode.js`, `solar-lighting.js`, `model-upload.js`, `model-measurement.js` |
| Servidor | `MapaPavimento/api.php`, `helpers/enquadramento_3d_helper.php`, `helpers/enquadramento_3d_captura.php`, migration SQL e script CLI |
| Testes e documentação | `tests/photo_optics_test.mjs`, `tests/enquadramentos_3d_test.php`, `tests/photo_capture_test.cjs`, `tests/photo_qa_database.php`, `tools/audit_fotografia_3d.cjs`, acompanhamento e evidências |

## Testes e evidências

- Navegador interno autenticado em ambiente oficial. Validados os fluxos de navegação, personagens, pavimentos, versões, medição, sol e configuração espacial. Em CIB_HYP, a seleção do pavimento-tipo abriu a página 1 do PDF com três ambientes e alternou entre planta e modelo 3D.
- Chrome automatizado com login oficial, modelo RKS_DCF/P00 e API PHP real para fotografia em banco e sessão descartáveis. Validou seleção 4:5, recorte após redimensionar, prévia limpa, gravação, duplicação, capturas de manhã/tarde, comparação, preferência, aprovação, exportação, marcador e reabertura após recarregar. Verificou também F e Esc durante a caminhada. A contagem de requisições ao GLB e avatar ficou constante durante o trabalho fotográfico.
- Testes de persistência e multipart passaram, incluindo exclusão, concorrência, imutabilidade, associação cruzada, acesso indevido, CSRF, JPEG de 1638 × 2048 e miniatura de 256 × 320. A verificação de óptica passou vinte combinações. Passaram também as suítes de posição solar, voo, colisão, medição, permissões do mapa e regras de PDF.
- Resoluções verificadas em desktop, notebook, ultrawide, iPad em ambas as orientações e mobile com 1920 × 1080, 1366 × 768, 2560 × 1080, 1180 × 820, 820 × 1180 e 390 × 844.

| Evidência | Arquivo |
|---|---|
| Orbital | [01-orbital.jpg](evidencias-fotografia-3d/01-orbital.jpg) |
| Voo | [02-voo.jpg](evidencias-fotografia-3d/02-voo.jpg) |
| Fotografia 4:5 | [03-fotografia.jpg](evidencias-fotografia-3d/03-fotografia.jpg) |
| Comparação | [04-comparacao.jpg](evidencias-fotografia-3d/04-comparacao.jpg) |
| Fotografia mobile | [05-fotografia-mobile.jpg](evidencias-fotografia-3d/05-fotografia-mobile.jpg) |
| Orbital no claro | [06-orbital-claro.jpg](evidencias-fotografia-3d/06-orbital-claro.jpg) |
| Caminhada | [07-caminhada.jpg](evidencias-fotografia-3d/07-caminhada.jpg) |

## Desempenho

O teste usa Chrome headless no mesmo computador e viewport de notebook, modelo RKS_DCF/P00 e Three.js r180. O comparativo da revisão `382e7939` usa a mesma entrega dos arquivos estáticos no próprio navegador. Mede o tempo até o entorno atingir `ready`, 90 intervalos de `requestAnimationFrame` após coleta de lixo e heap JavaScript. O voo mantém W pressionado. O heap exclui recursos de GPU e as medições têm variação de rede, cache e escalonamento do sistema.

| Medida | Antes | Depois |
|---|---:|---:|
| Entorno pronto | 9,65 s | 9,41 s |
| Orbital, mediana / p95 | 16,7 / 16,8 ms | 16,7 / 16,8 ms |
| Orbital, heap JS | 138 MiB | 138 MiB |
| Voo, mediana / p95 | 16,8 / 66,6 ms | 17,3 / 50,2 ms |
| Voo, heap JS | 302 MiB | 303 MiB |

A amostra comparável manteve o custo em orbital e teve variação pequena na mediana e no heap de voo. O p95 de voo variou entre execuções, inclusive na revisão anterior. Os resultados representam uma cena e um computador. [Amostra anterior](evidencias-fotografia-3d/antes.json) · [Amostra atual sem persistência](evidencias-fotografia-3d/depois-sem-persistencia.json) · [Rodada funcional](evidencias-fotografia-3d/depois.json).

## Limitações e pendências

- A instalação no ambiente compartilhado depende da migration, do acesso ao GLB legado identificado e da definição da raiz privada no ambiente de destino. Os commits desta implementação são locais.
- A imagem começa com a proporção cadastrada quando a base fornece largura e altura válidas. Na ausência desses campos, a fotografia começa com a proporção da navegação e permite ajuste manual. Os JPEG arredondam a dimensão secundária ao pixel mais próximo.
- A composição restaura o referencial óptico e solar. O entorno, texturas e condições do terreno são os que estiverem disponíveis quando a versão for aberta. Uma cópia para outra versão exige inspeção do alinhamento.
- Arquivos gerados e depois substituídos ou desvinculados podem permanecer no armazenamento privado. A API serve apenas o hash referenciado na linha atual. Limpeza de órfãos e quotas do armazenamento ficam para operação após política de retenção.
- As chaves estrangeiras preservam o vínculo com imagem, versão, obra e colaboradores referenciados. Uma exclusão física dessas entidades pode exigir tratamento explícito dos enquadramentos dependentes.
- A suíte legada `mapa_modelos_3d_test.php` parou porque o arquivo `Projeto-TES_TES.glb` estava indisponível. A revisão automática bloqueou a nova execução elevada por risco de escrita no banco compartilhado. A validação prática utilizou os GLBs acessíveis de RKS_DCF e CIB_HYP. A suíte legada pode ser executada em ambiente descartável com a fixture disponível.

## Reprodução

- `node tests/photo_optics_test.mjs`
- `php tests/enquadramentos_3d_test.php` e `node tests/photo_capture_test.cjs` com MariaDB descartável em loopback na porta 33791, PHP CLI, PHP-CGI e GD. As suites geram schemas com nomes aleatórios e removem seus próprios dados ao terminar.
- `node tools/audit_fotografia_3d.cjs` com Playwright disponível via `FLOW_PLAYWRIGHT_MODULE` e Apache local. O parâmetro `--baseline` carrega a revisão anterior. `--performance-only` mede o visualizador atual sem gerar composições.
