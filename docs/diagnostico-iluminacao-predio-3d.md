# Diagnóstico de iluminação do Prédio 3D

O preenchimento convencional recuperou a leitura da fachada sombreada no `CIB_HYP.glb`. A sombra solar projetada permaneceu visível, com os mesmos pixels na região amostrada. O ambiente procedural de navegação acrescentou pouco brilho no enquadramento testado.

## Como repetir no Flow

1. Abra a URL do visualizador da obra com `iluminacao=diagnostico` na query string. Se a URL já tiver parâmetros, acrescente `&iluminacao=diagnostico`.
2. No Hub de Inspeção, abra **Sol → Comparação de iluminação A/B/C**. A opção **A — Solar da obra** usa a configuração solar vigente. Para restaurá-la, use **Restaurar ajustes** em **Ambiente · diagnóstico** e deixe o HDRI em **Automático**. As capturas históricas desta página usaram o preenchimento original.
3. Enquadre a fachada, escolha um horário com Sol acima do horizonte para avaliar as sombras e alterne entre A, B e C. **Registrar A/B/C** percorre os modos, aquecendo cada configuração por pelo menos 1 s e medindo os intervalos de renderização por pelo menos 2 s. Baixe os três PNGs e o JSON antes de iniciar outra rodada.
4. Para separar a resposta da iluminação da aparência dos materiais, marque **Cinza-claro nas superfícies opacas** e faça uma segunda rodada. Vidros, superfícies transparentes e recortes de alpha mantêm seus materiais na comparação.

O diagnóstico aplica as alterações depois da atualização automática do ambiente e restaura as referências e os valores originais ao fim de cada renderização. O lote é descartado se câmera, enquadramento, Sol, GLB ou configurações de renderização registradas mudarem durante a comparação. Desligar o Sol volta à navegação normal, e religá-lo retoma A. Remover o parâmetro da URL desativa o diagnóstico.

Para repetir o ensaio local no mesmo viewer, abra `visualizador-glb-navegacao.html?src=./CIB_HYP.glb&iluminacao=diagnostico`. Esse caminho usa São Paulo e Norte 0° como referência de teste. No Flow autenticado, valem os dados espaciais da obra.

## Resultados

Ensaio em Chrome com Radeon RX 570, no visualizador de navegação do Flow com o GLB local, fora do contexto autenticado de uma obra e de seu terreno. A mesma câmera ortográfica foi usada em A, B e C, a 944 × 563 com DPR 1, ACES Filmic e exposição 1. Em 19/11/2026 às 15h no fuso `America/Sao_Paulo`, o Sol ficou em azimute 265,97°, elevação 46,32° e intensidade 2,381. O mapa PCFSoft permaneceu em 2048 × 2048. O HDRI seletivo estava carregado, com escala de reflexo 0,40.

| Modo | Hemisférica | LightProbe | `scene.environment` | Fachada sombreada com o GLB | FPS GLB / cinza |
| --- | ---: | ---: | --- | --- | ---: |
| A — Original | 0,06 | 0,20 | `null` | 66,2 / 255. Fachada escura, com perda de separação entre janelas, sacadas e lajes. | 21,1 / 20,2 |
| B — Preenchimento convencional | 1,65 | Inativa | `null` | 153,4 / 255. Janelas, sacadas e volumes ficam mais legíveis. | 19,8 / 23,6 |
| C — Preenchimento + ambiente | 1,65 | Inativa | Procedural de navegação, intensidade 1 | 156,4 / 255. Leve ganho de brilho sobre B, com leitura muito próxima. | 20,3 / 18,8 |

O brilho da tabela é a média de RGB ponderado no PNG sRGB, na mesma região da face sombreada da torre alta. A versão cinza registrou 41,7 em A, 114,3 em B e 117,3 em C. A melhora com material neutro confirma que a resposta ao preenchimento também ocorre sem os parâmetros PBR da fachada original.

A única direcional manteve posição, alvo, cor, intensidade e mapa de sombras nos três modos. Uma amostra de 2.660 pixels na região da sombra projetada no solo permaneceu idêntica entre A, B e C em cada rodada. Os três modos registraram 6.280 chamadas de desenho e cerca de 3,93 milhões de triângulos por quadro nas condições do teste. As janelas curtas de FPS oscilaram entre rodadas e são insuficientes para atribuir um custo estável a B ou C.

- [Comparativo visual com recortes das seis capturas](../output/predio-3d-iluminacao/comparativo.png)
- Capturas originais e leituras. [A](../output/predio-3d-iluminacao/iluminacao-A-glb.png), [B](../output/predio-3d-iluminacao/iluminacao-B-glb.png), [C](../output/predio-3d-iluminacao/iluminacao-C-glb.png), [JSON](../output/predio-3d-iluminacao/iluminacao-glb.json)
- Capturas com material cinza e leituras. [A](../output/predio-3d-iluminacao/iluminacao-A-cinza.png), [B](../output/predio-3d-iluminacao/iluminacao-B-cinza.png), [C](../output/predio-3d-iluminacao/iluminacao-C-cinza.png), [JSON](../output/predio-3d-iluminacao/iluminacao-cinza.json)

## O que acontece na transição

- Ao ativar o estudo solar, o coordenador remove o ambiente global, recalcula a hemisférica com as cores e a intensidade atmosféricas e agenda a LightProbe. A hemisférica mantém transição de cor e intensidade a cada quadro enquanto o alvo muda. Ao desligar o estudo, a navegação restaura a hemisférica convencional e o ambiente procedural. Voltar ao Sol após pré-visualizar C restaurou o diagnóstico em A. [Coordenador do ambiente](../MapaPavimento/predio-3d/solar-environment.js)
- O hook especular zera a irradiância difusa do mapa nos materiais com HDRI seletivo. Os caminhos da hemisférica e da LightProbe continuam separados no shader. Em C, um dos dois materiais PBR do GLB recebeu o ambiente procedural. O outro conservou seu HDRI explícito, que tem precedência no Three.js r180. [Hook de materiais reflexivos](../MapaPavimento/predio-3d/material-reflections.js)
- Uma rodada adicional de A em 1264 × 563 isolou os controles. A mesma região marcou 66,2 com os padrões, 89,5 quando apenas a hemisférica subiu de 0,06 para 0,30 e 204,7 quando apenas a LightProbe subiu de 0,20 para 2,00. Os dois controles chegaram aos pixels dessa fachada. O controle atmosférico permite até 0,30 de hemisférica, usa cores diferentes e escala o valor-base com a elevação. [Padrão](../output/predio-3d-iluminacao/iluminacao-tuning-padrao.json), [Hemisférica 0,30](../output/predio-3d-iluminacao/iluminacao-hemisferica-030.json), [LightProbe 2,00](../output/predio-3d-iluminacao/iluminacao-probe-200.json)

## Causa e correção candidata

No GLB testado, a causa demonstrada é a baixa contribuição difusa da configuração solar original sobre as faces sombreadas. B recuperou a leitura com o mesmo Sol e com o ambiente global ainda desligado. C teve benefício marginal nesse enquadramento. Os testes de intensidade e de material cinza reduzem a probabilidade de um bloqueio geral da iluminação difusa ou de uma peculiaridade dos materiais como causa principal. O peso de texturas, oclusão e materiais personalizados na obra relatada ainda depende de repetir a rodada no empreendimento afetado.

Para a correção definitiva, testar primeiro a calibração de um preenchimento hemisférico diurno na luz existente, com a menor intensidade que recupere os detalhes sem reduzir demais o contraste com as superfícies iluminadas. Avaliar a contribuição combinada com a LightProbe ao ajustar o dia e a transição para o crepúsculo, evitando somar duas contribuições fortes. Manter o Sol, seu mapa de sombras e os reflexos seletivos. O resultado de C oferece pouca justificativa para aplicar ambiente procedural global como parte da correção. Esta etapa implementou somente o diagnóstico.
