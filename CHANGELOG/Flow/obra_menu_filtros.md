# Menu e filtros da obra — 19/10/2026

## Navegação

- Barra de links com Prédio 3D, Tabela, Mapa de pavimentos, Histórico e Mais. Flow Review permanece como ação destacada.
- Mais organizado em Obra, Produção e Links. Links contém Review, Fotográfico e Google Earth. Os IDs e handlers de Briefing, Handoff, ALMA, Plano R00 e demais recursos foram preservados.
- Google Earth utiliza `obra.google_earth`, campo já existente. URLs HTTP/HTTPS são normalizadas; sem URL válida, Google Earth e Review ficam visíveis como indisponíveis, com título explicativo, sem destino fictício. Nenhum cadastro foi alterado.
- Prédio 3D ancora no hero e é oculto para obras sem modelo. Tabela ancora no início dos filtros, considerando o header. Mapa de pavimentos abre o workspace existente `MapaPavimento/index.php?obra_id=...`, com a obra selecionada. Histórico mantém a âncora existente.
- Seção ativa sublinhada conforme scroll, foco e suporte a movimento reduzido. Menu compacto mantém os mesmos agrupamentos, com rolagem própria. Escape fecha e devolve foco sem conflitar com os handlers de outros menus.
- Dropdown e menu compacto montados fora do stacking context animado do container para ficarem acima do renderer flutuante.

## Filtros

- Busca com ícone, rótulos permanentes acima dos cinco campos, placeholders Todos e botão Limpar filtros usando a função já existente.
- Select2, seleção múltipla e regras de filtragem preservados. Cores e bordas usam tokens do tema; opções, tags e foco possuem contraste adequado. Nomes acessíveis adicionados às seleções e buscas.
- Grid responsivo, largura fixa sincronizada ao placeholder e espaço para o minimap. No mobile, os filtros permanecem no fluxo da página para não cobrir a tabela com uma barra alta.
- Tabela, cards e cálculos não foram reimplementados. Nenhuma alteração em cena, câmera ou iluminação 3D.

## Arquivos

- `Dashboard/obra.php`: marcação do menu/filtros, importações e largura/altura do placeholder dos filtros fixos.
- `Dashboard/scriptObra.js`: apresentação e nomes acessíveis do Select2, mantendo os listeners existentes.
- `Dashboard/obra-navigation.js`: navegação, links opcionais, menu e seção ativa.
- `Dashboard/obra-navigation.css`: estilos restritos ao menu e aos filtros.

## Validação

Sessão autenticada no Edge conectado, em `https://improov/ImproovWeb/`, seguindo navegação real. Mantida alternativa ao IAB indisponível registrada nas etapas anteriores.

- CIB_HYP/102: Fachada → 5 imagens; Fachada + Imagem Interna → 18; Coworking → 1. Limpar filtros → 42, busca e seleções vazias.
- Mapa aberto pela navegação com obra 102 selecionada; retorno pelo histórico.
- Mais acima do canvas, grupos e links corretos. Review/Google Earth indisponíveis por ausência de cadastro; Fotográfico e Flow Review preservados. Destino externo de Google Earth não foi aberto porque não há URL cadastrada nesta obra.
- Menu compacto: agrupamentos, rolagem até Links/Flow Review, navegação para Tabela, Escape e foco no hamburger.
- Desktop, notebook 1366×768, iPad landscape 1024×768, portrait 768×1024 e mobile 390×844. Sem overflow horizontal novo e sem campos cobertos pelo minimap.
- ABF_ALM/85: hero e acesso Prédio 3D ocultos, mapa para obra 85 e resumo com 78 imagens.
- Sintaxe PHP/JavaScript e diff sem erros de whitespace. Console sem erros nas verificações. Viewport restaurado ao final.

Evidências: `obra_menu_header.jpg` e `obra_filtros.jpg`.
