# Etapas, caminho e filtros de Entregas

Os detalhes de Entregas e o card da página de projeto usam o mesmo JavaScript e a mesma folha `Entregas/detalhes.css`.

O fragmento `includes/entrega-detalhe-modal.php` mantém a mesma estrutura nas duas telas. No desktop e iPad landscape, o modal apresenta informações/reviews à esquerda e imagens à direita, com rolagens independentes e rodapé fixo. Em portrait e mobile, os painéis são empilhados. Busca, tipo e etapa ficam visíveis; situação, status e responsável podem ser abertos em “Mais filtros”, com indicação de quantos estão ativos.

A identificação visual combina nome, ícone e cor: Caderno/Filtro em rosa, Modelagem em laranja, Composição em amarelo, Finalização em verde, Render em roxo e Pós-produção em azul. Estados de entrega como RVW preservam suas próprias identificações.

## Identificação da produção

`helpers/entrega_imagem_etapa_helper.php` é um modelo de leitura. Não altera funções, renders, aprovações nem estados de entrega.

- Funções reais e declarações de planejamento alimentam as etapas. Funções canceladas, não aplicáveis e responsáveis operacionais de dispensa são desconsiderados.
- Caderno/Filtro continua aberto enquanto qualquer membro aplicável estiver aberto. Unidades explícitas Modelagem/Composição preservam a Composição como representante quando os estados coincidem.
- Alteração aparece em Finalização. R00 aceita funções de imagens ainda em P00, conforme a seleção existente de Entregas.
- Funções correntes só são usadas no ciclo correspondente. Funções de Alteração também precisam de registro no ciclo em `alteracoes`.
- Render usa imagem + status da entrega, exclui arquivados e seleciona a tentativa vigente. O cache do Deadline distingue uma fila explicitamente reportada.
- Render concluído aguarda aprovação; Pós exige encaminhamento associado ao mesmo render. `status_pos = 0` indica conclusão. Planta Humanizada e P00 mantêm o fluxo sem Pós.
- Uma reabertura registrada de Finalização posterior à tentativa devolve a imagem à Finalização. Refação do job permanece em Render.
- Atividades simultâneas são preservadas; a primeira etapa aberta na ordem operacional é destacada.
- Quando não há evidência suficiente, aparece “Etapa a confirmar”. Uma entrega mais antiga da mesma imagem/ciclo não recebe o estado vivo de uma entrega posterior.

O status de entrega permanece independente da produção. Concluir uma etapa não registra automaticamente a entrega.

## Pasta real da obra

`Entregas/get_caminho_entrega.php` autentica e autoriza a obra antes da consulta. O helper `helpers/obra_nas_path_helper.php` lista os anos disponíveis no NAS e procura uma única pasta com a nomenclatura exata da obra. O ano não vem da data da entrega nem do relógio.

A raiz SFTP usa `IMPROOV_SFTP_CLIENTES_ROOT`, com padrão `/mnt/clientes`. A apresentação mantém o compartilhamento Windows `Z:` já usado pelo sistema. Credenciais vêm da configuração central existente.

O vínculo encontrado fica em cache privado no diretório temporário do servidor durante 24 horas, por servidor, raiz, obra e nomenclatura. Não há migration. Duplicidade, ausência ou indisponibilidade não geram caminho presumido. A consulta é assíncrona e não bloqueia a listagem. O botão de copiar só habilita com caminho identificado.

Entregas padrão usam `04.Finalizacao/{ciclo}`; P00 usa o caminho relativo registrado e sua versão. O indicador reutiliza Thinking Orbs global.

## Filtros

Busca sem distinção de acentos, tipo de imagem, etapa, situação da produção, status da imagem e responsável atual podem ser combinados. Etapa/situação/responsável precisam corresponder à mesma atividade quando há simultaneidade.

O contador filtrado não altera o progresso geral. Selecionar todos considera somente imagens visíveis e habilitadas. Mudar filtros limpa seleções de ações, sem alterar estados de imagens entregues. Abertura de outra entrega reinicia os filtros. Atualizações de RVW_DONE e remoção de imagens atualizam as opções e contadores.

## Verificação

O botão direito em uma imagem abre os atalhos **Flow Review** e **Ver card da Imagem** em nova aba. Ambos usam a função real da etapa atual. Em Render, usam Finalização/Alteração (Modelagem em P00); em Pós-produção, usam a função de Pós, sem confundir IDs de render ou encaminhamento com IDs de função. Sem função registrada no ciclo, os atalhos ficam indisponíveis com explicação. O menu preserva RVW_DONE e remoção da entrega e se ajusta às bordas da tela.

- `php tools/test_entrega_imagem_etapa.php`: regras de negócio e caminhos, sem banco e sem NAS.
- `php tools/test_page_metadata.php` e `php tools/test_auth_navigation.php`: regressão dos padrões centrais.
- Lint PHP e `node --check Entregas/script.js`.
- Navegação autenticada nas origens oficiais; filtros combinados, seleção visível, vazio, limpeza, detalhes da imagem, caminhos padrão/P00 e cinco resoluções.

As três APIs de consulta respondem 401 JSON sem sessão e verificam autorização da obra antes de fornecer os novos dados.
