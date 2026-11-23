# Câmera de inspeção

No Visualizador 3D do Mapa de Pavimento, escolha **Josh · caminhada** no seletor de navegação e clique no terreno ou em um pavimento. Selecione um pavimento na vista aérea primeiro quando precisar expor seu interior. A câmera abre em primeira pessoa e ocupa toda a janela. Use **WASD** ou as setas para andar, arraste para olhar e use a roda para ajustar o enquadramento. **V** alterna a perspectiva, **Esc** volta à vista aérea e o ícone de pessoa permite escolher outro ponto. Em telas de toque há setas na tela.

O modo carrega `assets/person/Josh.glb` após escolher um ponto válido. Usa o DEM já alinhado ao GLB ou a malha renderizada quando o entorno vem do cache. Sem DEM, usa as superfícies disponíveis do modelo e do entorno plano, se houver. A câmera acompanha pisos, rampas e degraus transitáveis. A passagem entre pavimentos depende das conexões presentes no modelo. A colisão usa as faces estáticas do empreendimento, inclusive vidro. A sombra usa a mesma luz direcional do painel Sol.

## Modo Gaivota

Escolha **Gaivota · voo**. Use **W/S** para acelerar, frear e, mantendo **S**, recuar. **A/D** viram a gaivota, **Espaço/Shift** controlam a altitude, arrastar orienta o voo e ajusta o ângulo da câmera, e a roda muda a distância. Em telas de toque, use os botões direcionais e de altitude. **Esc** ou **Sair do voo** devolve a câmera à navegação convencional. Ao alternar no mesmo modelo, Josh e Gaivota podem retomar a última posição válida.

Selecione um pavimento na lista e clique em **Voar à altura do pavimento**. A referência é a base do pavimento em coordenadas do modelo, com folga limitada a metade da sua altura. O controle ajusta a altitude até lá e cede aos comandos manuais. Se o trajeto vertical for bloqueado por uma laje ou pelo relevo, contorne o obstáculo antes de tentar novamente. O recorte de pavimentos é o mesmo da vista convencional e o voo pausa durante a atualização do índice de colisão.

O controlador usa a malha visível do GLB, incluindo as faces recortadas e o vidro, as edificações do entorno e o DEM já carregado. A colisão varre uma esfera central em subpassos e consulta a superfície do terreno. A área horizontal segue o perímetro geográfico quando disponível. Em GLBs sem entorno, usa uma margem ao redor do modelo e a base do modelo como referência de solo. O teto do voo fica acima do ponto mais alto do GLB. O raio central aproxima o corpo, então pontas das asas podem tocar superfícies. A câmera recua até o ponto livre no seu eixo de visão. A aproximação pode mostrar cortes nas bordas da imagem em passagens muito estreitas. O índice representa triângulos estáticos e não acompanha deformações animadas ou transparência por textura.

O arquivo `seagull-2.glb` é carregado quando o primeiro voo está pronto. O seletor **Animação da gaivota** permite testar `flap`, `planer` e `planer 2` individualmente e mostra suas durações lidas do GLB. No automático, `flap` entra na aceleração e subida, `planer` no voo estável e `planer 2` na descida. Os pesos dos clipes mudam gradualmente no loop do visualizador. A câmera segue o controlador, separada da animação do esqueleto.

Teste do controlador e do GLB com Node.js 24 e o pacote Three.js usado pelo visualizador:

```powershell
node tests/seagull_flight_test.mjs --three-module=CAMINHO_PARA_PACOTE_THREE/build/three.module.js
```

## Regenerar o personagem

Mantenha os quatro FBX originais do Mixamo em `assets/person/` (fontes locais; o aplicativo publica apenas `Josh.glb`). A conversão é feita fora do navegador com Node.js, os importadores/exportadores do Three.js r180 e o ImageMagick (`magick` no PATH). Não é preciso instalar Blender ou adicionar dependências ao aplicativo.

```powershell
node tools/convert-flow-josh.mjs CAMINHO_PARA_PACOTE_THREE
node tests/exploration_collision_test.mjs --three-module=CAMINHO_PARA_PACOTE_THREE/build/three.module.js
```

O conversor verifica a hierarquia e o comprimento dos ossos, os vínculos de cada peça e poses ao longo dos clipes. Consolida a pele em um esqueleto de 65 ossos, converte centímetros em metros, remove a translação horizontal dos quadris e mantém a vertical. Os cinco mapas são incorporados ao GLB com resolução adaptada à distância de câmera. O brilho FBX é convertido em rugosidade PBR; o importador Three.js limita a quatro influências por vértice. O GLB do empreendimento não é usado na conversão.
