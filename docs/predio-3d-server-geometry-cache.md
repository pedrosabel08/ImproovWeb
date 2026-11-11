# Cache persistente do Prédio 3D no XAMPP

O cache é desabilitado quando FLOW_GEO_CACHE_ENABLED está ausente ou tem valor diferente de 1.

## Ativação local

Instale Node.js 24 no computador do XAMPP. Na raiz do projeto, execute:

    npm ci --prefix tools/geo-cache --ignore-scripts --no-audit --no-fund

No .env **local**, configure:

    FLOW_GEO_CACHE_ENABLED=1
    FLOW_GEO_CACHE_NODE_WINDOWS=C:/Program Files/nodejs/node.exe
    FLOW_GEO_CACHE_NODE_LINUX=/opt/node24/bin/node

As duas variáveis podem coexistir no mesmo arquivo. O PHP escolhe a variável conforme o sistema operacional. Se a variável específica estiver ausente, usa FLOW_GEO_CACHE_NODE quando configurada. Sem nenhuma delas, tenta o caminho padrão correspondente e, se ele não existir, procura node no PATH. Uma configuração explícita inválida mantém o fallback convencional do visualizador.

O caminho deve corresponder ao executável que a conta do Apache pode executar. Essa conta precisa de leitura nos GLBs, nos caches OSM/DEM e em tools/geo-cache, e de escrita em cache/flow-geometry. Confirme que a configuração Apache aplica o .htaccess da raiz. Uma URL dentro de cache/flow-geometry deve devolver 403 mesmo em sessão autenticada.

Abra a obra pelo dashboard. O primeiro acesso usa a geração convencional. Quando os dados OSM e DEM estiverem no disco, o PHP enfileira o job e inicia um processo Node destacado. Reabra após a publicação para confirmar geometryCache.source = server no atributo data-geo-metrics do #viewport. O modo geo_debug continua usando a construção convencional.

Para desabilitar, remova a configuração ou use FLOW_GEO_CACHE_ENABLED=0 e reabra a página. Se a variável tiver sido definida no ambiente do serviço Apache, altere-a nesse ambiente e reinicie o serviço. Os arquivos no disco podem permanecer para reutilização posterior.

## Publicação e proteção

- A API valida sessão, acesso à obra, modelo e versão solicitada antes de procurar o pacote. O cache fica em cache/flow-geometry, bloqueado pela regra Apache da raiz.
- O PHP calcula a chave usando parâmetros geográficos, conteúdo do GLB, conteúdo OSM/DEM e versão derivada dos arquivos do gerador. A assinatura do alinhamento no pacote usa limites, âncora, altitude, matrizes, vértices e índices do apoio. O navegador compara essa assinatura com o modelo aberto antes de instalar qualquer malha.
- O hash completo do GLB é conservador. Uma mudança no arquivo pode exigir nova geração mesmo se a superfície de apoio continuar igual.
- O lock da fila protege a criação da tentativa. A concessão por chave dura 120 s e há até dois jobs ativos. Um supervisor encerra a geração após 90 s. Cada tentativa usa um diretório próprio, para que um processo atrasado não consiga publicar no lugar de um job novo.
- O worker publica geometry.bin e depois manifest.json por renomeação. O PHP serve somente pacotes completos com tamanho e SHA-256 correspondentes. O navegador verifica SHA-256, alinhamento e layout binário. Em caso de falha, usa o fluxo convencional. Tentativas e ponteiros antigos são removidos oportunisticamente após 14 dias.
- O pacote guarda posições, normais, origem e atributos de malha. Os materiais, sombras e iluminação são aplicados pelo navegador. O Node usa apenas entradas locais e não precisa ficar residente.

A métrica presentationMs registra o tempo desde o início da navegação até a cena instalada passar por dois quadros do navegador. environmentPresentationMs mede o trecho desde que o entorno começou a carregar. Os tempos são proxies da apresentação, e não uma leitura da tela ou uma medição de GPU.

O teste da fila pode ser executado com o PHP do XAMPP, com a flag desligada:

    C:/xampp/php/php.exe tests/mapa_geo_cache_test.php

## Resultado local

O ensaio autenticado usou uma obra com 83.630 triângulos de entorno. O pacote de 6.024.895 bytes foi comparado com a geração convencional no Chrome. As posições, normais, origem e métricas DEM coincidiram.

| Medida | Resultado |
|---|---:|
| Navegação até apresentação, miss | 10,29 s |
| Navegação até apresentação, hit (3 reaberturas) | mediana de 3,25 s (3,03–3,49 s) |
| Entorno até apresentação, hit | mediana de 503 ms |
| Consulta da API, hit | mediana de 277 ms |
| Transferência do pacote | mediana de 53 ms |
| Verificação no navegador | mediana de 5,3 ms |
| Restauração no navegador | mediana de 30 ms |
| Preparação das entradas no Node | 899 ms |
| Geração no Node | 3,38 s |
| Serialização no Node | 2,0 ms |

O ensaio cobriu duas abas simultâneas após corromper um byte do pacote, fallback com executável Node ausente, chaves distintas ao variar Norte ou GLB, 401 sem sessão e 403 no acesso direto ao arquivo. A flag local foi removida ao fim. A reutilização entre duas contas autorizadas continua pendente de uma segunda conta de desenvolvimento.

## Cache híbrido no navegador e revalidação do GLB

Com a flag principal ligada, o navegador guarda o pacote verificado em IndexedDB. O armazenamento usa SHA-256 como identidade do pacote, mantém uma referência por obra, modelo e versão e limita a ocupação a 96 MiB e 16 pacotes por origem. A política LRU considera o último uso. Gravação e limpeza ocorrem na mesma transação, inclusive com abas concorrentes.

Ao reabrir, o navegador envia o SHA candidato à mesma API autorizada. A API calcula a chave atual e devolve um manifesto pequeno se o SHA ainda corresponder. Somente depois disso o navegador lê e verifica o pacote local. Sem correspondência, o servidor pode entregar o pacote novo na mesma consulta. Uma entrada inválida, ausente ou inacessível no IndexedDB leva a uma tentativa pelo servidor. Se faltar um pacote autorizado e íntegro, o visualizador usa a geração convencional. A assinatura de alinhamento continua obrigatória antes da instalação. O armazenamento pertence à origem do navegador e deve ser tratado como dado local da sessão de uso do dispositivo. Em dispositivos compartilhados, use perfis de navegador separados ou limpe os dados do site conforme a política interna.

O GLB envia um ETag SHA-256 calculado a partir do arquivo aberto. As respostas válidas usam private, no-cache, must-revalidate. A API autentica a sessão, autoriza a obra e confere o vínculo do modelo antes de responder 304. O navegador reutiliza o corpo, mas continua decodificando o GLB e enviando a cena à GPU. A flag opcional FLOW_GEO_GLB_CACHE_ENABLED=0 desliga apenas a revalidação HTTP. A flag principal FLOW_GEO_CACHE_ENABLED=0 desliga todo o cache híbrido e mantém o fluxo convencional.

A listagem de modelos reaproveita a consulta que autorizou a obra. As rotas que já precisam abrir ou resolver o arquivo fazem isso uma vez. No navegador, o enquadramento inicial reaproveita os limites calculados e a árvore dos pavimentos é criada uma vez na instalação. O perfil mostrou muitas tarefas Draco, mas a fila e o formato dos modelos foram mantidos. Alterá-los sem uma comparação representativa poderia afetar a preparação de outros GLBs.

## Procedimento para homologação Linux e VPS

1. Em ambiente Linux autorizado e separado da produção, instale Node 24 e execute npm ci --prefix tools/geo-cache --ignore-scripts --no-audit --no-fund. Use o lockfile do repositório. Os decodificadores WebP e Draco usam WASM e o gerador não exige Chrome, ImageMagick nem serviço residente.
2. Valide o mesmo GLB, tile DEM e payload OSM usados no ensaio Windows, com os SHA-256 originais. Rode a comparação Node × Chrome e exija igualdade dos buffers e do alinhamento antes de liberar o uso no VPS. Os arquivos reais devem permanecer em ambiente autorizado.
3. Confirme que a conta do PHP consegue ler os modelos locais, caches OSM/DEM e módulos Node, executar o binário Node e criar arquivos, locks e renomeações em cache/flow-geometry. Use disco local e renomeações no mesmo sistema de arquivos. Verifique proc_open e os limites do PHP-FPM/Apache com os modelos maiores.
4. Garanta que o servidor HTTP negue acesso direto a cache/flow-geometry e uploads/model-3d, inclusive por caminhos com prefixo de produção. No Apache, aplique a regra existente e confirme AllowOverride. Se houver Nginx servindo estáticos, negue explicitamente os dois prefixos no vhost e valide com HTTP 403/404. A API deve continuar retornando 401 sem sessão, inclusive em requisições condicionais.
5. Mantenha a flag em 0 até concluir a homologação e receber autorização de ativação. Na janela de teste autorizada, configure FLOW_GEO_CACHE_NODE com o caminho absoluto do Node e FLOW_GEO_CACHE_ENABLED=1. Verifique primeiro um projeto de homologação com dois colaboradores autorizados.
6. Acompanhe espaço, arquivos failed, quantidade de tentativas, misses recorrentes, tempo dos jobs e métricas geometryCache em amostras autenticadas. Há limite de dois jobs ativos, timeout de 90 s e limpeza oportunista de tentativas antigas após 14 dias. Para limpeza operacional, atue apenas no diretório protegido e fora de jobs ativos.
7. Para rollback, configure FLOW_GEO_CACHE_ENABLED=0, recarregue o serviço se a variável vier do ambiente do processo e reabra o visualizador. Os arquivos podem permanecer no disco e o IndexedDB deixa de ser consultado pelo produto. Confirme a geração convencional antes de encerrar a janela.

O VPS não foi modificado. A execução em Linux e o teste com uma segunda conta autorizada ainda precisam de ambientes e credenciais de homologação.

## Resultado integrado no XAMPP

Cinco amostras por cenário, mesma obra, Chrome headless, métrica de retorno do primeiro render com prédio e entorno anexados. O cenário frio abaixo simula uma resposta 204 do cache geométrico para medir de forma repetível o fallback com OSM/DEM disponíveis. Uma abertura fria real disparou o job Node e apresentou o entorno em 10,34 s.

| Cenário | Mediana | Mínimo–máximo |
|---|---:|---:|
| Fallback convencional, sem pacote | 7,09 s | 6,95–8,95 s |
| Cache do servidor, revalidação GLB ativa | 2,33 s | 2,25–2,67 s |
| IndexedDB, revalidação GLB ativa | 2,31 s | 2,19–2,46 s |
| IndexedDB, rodada anterior à revalidação GLB | 2,23 s | 2,13–2,34 s |

O manifesto medido teve 141 bytes de corpo em vez de aproximadamente 6,02 MB. A revalidação autorizada devolveu HTTP 304 sem corpo. No perfil aquecido, o GLB passou de aproximadamente 5,43 MB transferidos para apenas cabeçalhos; no loopback isso não produziu redução demonstrável na latência total. A resposta de contexto (539 ms), o parse (457 ms) e a instalação (252 ms) continuam relevantes. A meta de 1–2 s não foi comprovada. Em uma ligação hipotética de 20–100 Mbit/s, os 5,43 MB evitados equivalem a cerca de 0,43–2,17 s de transferência ideal, sem incluir TTFB, hashing ou parse. Não há medições do VPS.

A comparação final na mesma obra encontrou igualdade dos buffers de posição e normal do pacote com a geração convencional, além de origem e métricas DEM iguais em 83.630 triângulos. As capturas com câmera e horário fixos tiveram RMSE normalizado de 0,000189. A regressão integrada também verificou outra aba, novo login, LRU, pacote local corrompido, IndexedDB indisponível, falha de rede, manifesto sem sessão e revalidação GLB sem autorização. O navegador interno confirmou 83.630 triângulos pelo fluxo convencional após restaurar a flag para desligado.
