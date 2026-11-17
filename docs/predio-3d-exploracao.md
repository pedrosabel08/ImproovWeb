# Câmera de inspeção

No Visualizador 3D do Mapa de Pavimento, clique em **Posicionar câmera** no canto inferior esquerdo e selecione o terreno ou um pavimento. Selecione um pavimento na vista aérea primeiro quando precisar expor seu interior. A câmera abre em primeira pessoa e ocupa toda a janela. Use **WASD** ou as setas para andar, arraste para olhar e use a roda para ajustar o enquadramento. **V** alterna a perspectiva, **Esc** volta à vista aérea e o ícone de pessoa permite escolher outro ponto. Em telas de toque há setas na tela.

O modo carrega `assets/person/Josh.glb` após escolher um ponto válido. Usa o DEM já alinhado ao GLB ou a malha renderizada quando o entorno vem do cache. Sem DEM, usa as superfícies disponíveis do modelo e do entorno plano, se houver. A câmera acompanha pisos, rampas e degraus transitáveis. A passagem entre pavimentos depende das conexões presentes no modelo. A colisão usa as faces estáticas do empreendimento, inclusive vidro. A sombra usa a mesma luz direcional do painel Sol.

## Regenerar o personagem

Mantenha os quatro FBX originais do Mixamo em `assets/person/` (fontes locais; o aplicativo publica apenas `Josh.glb`). A conversão é feita fora do navegador com Node.js, os importadores/exportadores do Three.js r180 e o ImageMagick (`magick` no PATH). Não é preciso instalar Blender ou adicionar dependências ao aplicativo.

```powershell
node tools/convert-flow-josh.mjs CAMINHO_PARA_PACOTE_THREE
node tests/exploration_collision_test.mjs --three-module=CAMINHO_PARA_PACOTE_THREE/build/three.module.js
```

O conversor verifica a hierarquia e o comprimento dos ossos, os vínculos de cada peça e poses ao longo dos clipes. Consolida a pele em um esqueleto de 65 ossos, converte centímetros em metros, remove a translação horizontal dos quadris e mantém a vertical. Os cinco mapas são incorporados ao GLB com resolução adaptada à distância de câmera. O brilho FBX é convertido em rugosidade PBR; o importador Three.js limita a quatro influências por vértice. O GLB do empreendimento não é usado na conversão.
