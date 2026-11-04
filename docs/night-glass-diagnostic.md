# Vidros noturnos — diagnóstico e correção (obra 102 / v0008)

## Causas verificadas

- A aba aberta tinha `nightFactor=1`, mas ainda executava a configuração antiga: emissão linear `[0.2, 0.0924154, 0.0294055]`, e não a intensidade 1 / cor #FFA34D salvas no arquivo. Os imports aninhados usavam uma versão fixa. Agora a entrada PHP publica um import map com hash do conteúdo de `environment-config.mjs`; o efeito resolve a configuração por esse mapa a cada navegação/reload.
- O shader anterior somava emissão linear **depois** de `colorspace_fragment` (sRGB) e dividia pelo alpha 0.16. Além de misturar espaços de cor, isso amplificava os canais em 6.25 vezes e permitia clipping/desaturação. O shader não estava deixando de compilar; o parâmetro `compiled` isoladamente não provava uma composição correta.
- O alpha 0.16 deixava 84% da fachada/cena atrás do vidro participar da composição, diluindo a leitura do interior aceso. A transmissão no runtime já era 0, conforme a adaptação compartilhada do viewer; não era transmissão física 1 do GLB nesse estágio.

## Teste geométrico temporário

Uma amostra distribuída de 48 meshes foi exibida com um único MeshBasicMaterial vermelho, opaco, sem tone mapping, mantendo o teste de profundidade e o lado normal do material. As superfícies apareceram vermelhas. Geometria e desenho da amostra estavam presentes. Os materiais originais foram restaurados; botão, material diagnóstico e leitura temporária da GPU foram removidos do código final.

Evidência local: `tmp/mapa-3d-qa/vidro-diagnostico-opaco.png`.

## Correção

Emissão linear nativa de MeshPhysicalMaterial/MeshStandardMaterial, sem soma posterior à conversão de cor e sem divisão pelo alpha. O hook de reflexos continua encadeado, sem alterar ShaderChunk global. Intensidade máxima 1 e cor #FFA34D foram mantidas.

`nightOpacity=0.72` representa a cobertura aparente do interior iluminado. Opacidade e emissão interpolam pelo mesmo fator noturno, com 600 ms de suavização. O material continua transparente e reflexivo à noite. Durante o dia, opacidade e emissão voltam exatamente ao estado anterior ao efeito. Clones usam o estado original da fonte, evitando acumular cobertura/emissão. Não há novas meshes, geometrias, luzes ou bloom.

## Valores observados no navegador interno autenticado

| Estado em 03/11/2026 | Fator estabilizado | Opacidade | Emissão linear |
| --- | --- | --- | --- |
| 20:00 | 1 | 0.72 | [1, 0.3662526, 0.07421357] |
| 18:45 | 0.53409737 | 0.45909453 | [0.53409737, 0.19561455, 0.03963727] |
| 12:00 | 0 | 0.16 | [0, 0, 0] |

A leitura temporária direta da GPU confirmou `emissive=[1,0.36625260,0.07421357]` e `opacity=0.72000003`. ACESFilmicToneMapping (4), exposição 1, saída sRGB, premultipliedAlpha false, NormalBlending, transmission 0 e IOR 1.45. As 4314 meshes compartilham um material do efeito.

## Verificações

`night_glass_test.mjs`, `material_reflections_test.mjs`, `solar_environment_cache_test.mjs` e `solar_environment_test.mjs`, usando Three r180 local quando exigido. Testes cobrem compartilhamento, multimaterial, clones, fator intermediário, restauração, isolamento por obra e preservação do hook de reflexos. Sintaxe PHP/JS e diff sem erros de whitespace.

Revisão visual no navegador interno: 1920×1080, 1366×768, iPad 1024×768 / 768×1024 e mobile 390×844, usando Enquadrar após redimensionar. Console sem erros ao concluir. O hash `dbff955c2ee8` do import map foi comparado com o SHA-256 do arquivo local. Evidências finais: `tmp/mapa-3d-qa/vidro-corrigido-noite.png` e `vidro-corrigido-dia.png`.

A aparência é uma representação de superfícies acesas; não calcula iluminação física dos ambientes internos ou da vizinhança. A opacidade noturna maior é deliberada, enquanto o comportamento diurno é restaurado. OSM, cálculo solar e critérios de HDRI não foram modificados nesta correção.
