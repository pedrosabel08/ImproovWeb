# Calibração final de iluminação · CIB_HYP · obra 102

O estudo solar da obra 102 agora usa uma hemisférica de **0,50 quando o Sol está a 30° de elevação ou mais**. A curva reduz o preenchimento até **0,20 no horizonte** e retorna suavemente ao nível noturno existente de **0,60 a partir de −6°**. O ajuste é selecionado pelo ID da obra do modelo carregado. As outras obras continuam com a configuração anterior.

## Comparação visual

O ensaio usou o modelo 4 (P00), o GLB e o terreno da obra, o HDRI automático e o Norte salvo de 84,7°. As capturas de cada enquadramento mantiveram a câmera ortográfica, ACES Filmic, exposição 1 e DPR 1. A data foi 19/11/2026, no fuso America/Sao_Paulo. Foram capturadas 29 variantes na vista principal, oito na vista oposta e sete registros adicionais do controlador aplicado.

| Faixa testada | Resultado |
| --- | --- |
| 0,40 | Ganha leitura sobre 0,06. Na vista oposta das 16h, a face sombreada chegou a 101,6 de brilho médio no PNG. |
| **0,50** | A mesma face chegou a **109,7** e a face ao Sol a **167,9**. Sacadas e janelas ficaram mais legíveis, com separação clara entre as faces. |
| 0,60 | A face sombreada chegou a 117,0; a face ao Sol, a 171,6. O ganho adicional veio com menor separação entre as duas faces. |

As médias usam RGB ponderado na faixa de 0 a 255, em duas regiões fixas da torre na vista oposta. No pôr do sol, manter 0,50 constante elevou a média da região sombreada de aproximadamente 4 para 75. A curva escolhida elevou a mesma região para aproximadamente 37, preservando mais da aparência do fim do dia. Entre 18h41 e 18h42, a direcional passou de 0,166 a zero conforme o cálculo astronômico, e a hemisférica permaneceu em aproximadamente 0,20.

| Hora local | Elevação solar | Hemisférica original | Hemisférica aplicada | Observação |
| --- | ---: | ---: | ---: | --- |
| 08h30 | 41,47° | 0,060 | 0,500 | A primeira torre ganha definição na face sem Sol, com o lado iluminado ainda distinguível. |
| 12h00 | 82,35° | 0,060 | 0,500 | A sombra nas sacadas fica mais legível quando o Sol incide de forma oblíqua na face direita. |
| 16h00 | 34,90° | 0,060 | 0,500 | A vista principal tem as duas faces em sombra. A vista oposta confirma a separação entre a fachada ao Sol e a sombreada. |
| 18h41 | +0,20° | 0,009 | 0,200 | O edifício mantém o contorno e parte do relevo com Sol rasante. |
| 18h42 | −0,004° | 0,009 | 0,200 | A direcional zera e o preenchimento cruza o horizonte sem degrau. |
| 19h00 | −3,69° | 0,404 | 0,468 | A face opaca clareia gradualmente, com a emissão das janelas na mesma fase. |
| 20h00 | −15,46° | 0,600 | 0,600 | As janelas mantêm a emissão noturna. Os 4.725 pixels amostrados numa área da fachada coincidiram com o original. |

O patamar diurno aproveita a leitura observada em 0,50, e as duas transições usam a interpolação suave existente por elevação. O controlador mantém a transição de 600 ms quando o relógio solar recebe um novo alvo. A LightProbe existente continua com a mesma intensidade e curva originais, inclusive no escurecimento ao redor do horizonte e na transição à noite.

- [Antes e depois com o controlador aplicado](../output/iluminacao-final-obra102/antes-depois.png)
- [Todas as intensidades na vista principal](../output/iluminacao-final-obra102/matriz-principal.png)
- [Fachadas na orientação oposta às 16h e no pôr do sol](../output/iluminacao-final-obra102/matriz-oposta.png)
- [44 PNGs integrais, parâmetros JSON e tabelas CSV](../output/iluminacao-final-obra102/obra102-capturas-e-medicoes.zip)

## Validação técnica no Flow integrado

O controlador aplicado reproduziu os sete valores esperados com a mesma câmera, posição solar, projeção de sombras, exposição e resolução da referência em cada horário. Os registros conservaram os mesmos três materiais PBR visíveis, os dois materiais com HDRI seletivo, a mesma escala de reflexos e a LightProbe. O ambiente global permaneceu nulo. A configuração e os parâmetros de emissão das janelas coincidiram com os da referência nos sete horários.

Foram mantidas as três luzes do estudo solar, o mapa de sombras PCFSoft de 2048 × 2048 e o mesmo caminho de renderização. Na navegação normal, entrar no **PAVTO TIPO 20** e retornar ao empreendimento manteve a hemisférica em 0,500, a LightProbe em 0,200 e o Sol em 2,79 às 12h. Desligar o estudo retomou a iluminação convencional de 1,650 / 2,25 e religá-lo retornou à calibração da obra. A comparação A/B/C ficou acessível pelo parâmetro técnico `iluminacao=diagnostico`. A opção A mostra o estudo solar vigente. A instrumentação temporária das medições foi removida.

### Desempenho observado

Na rodada principal, os pares original → 0,50 foram **9,02 → 9,10 FPS** às 08h30, **9,71 → 9,81** às 12h e **10,11 → 10,00** às 16h. As contagens registradas em todas as variantes foram de 6.254 chamadas de desenho e 4.067.042 triângulos por quadro. A vista oposta e a rodada final do controlador tiveram amostras próximas de 1 FPS em vários momentos. A cadência variável do navegador limita a comparação de desempenho entre rodadas. O escopo do ensaio foi um dia, uma configuração espacial e dois enquadramentos da obra de referência.

### Arquivos e testes

- [environment-config.mjs](../MapaPavimento/predio-3d/environment-config.mjs) define a curva e o perfil por obra. [solar-environment.js](../MapaPavimento/predio-3d/solar-environment.js) aplica o perfil à hemisférica existente e restaura os ajustes técnicos ao trocar de perfil.
- [solar-lighting.js](../MapaPavimento/predio-3d/solar-lighting.js) atualiza a faixa do controle técnico e as leituras na troca de iluminação. [solar-lighting-diagnostic.js](../MapaPavimento/predio-3d/solar-lighting-diagnostic.js) registra o perfil efetivo no modo A.
- [viewer-environment.js](../MapaPavimento/predio-3d/viewer-environment.js) e [visualizador-glb-navegacao.js](../MapaPavimento/predio-3d/visualizador-glb-navegacao.js) atualizam as versões dos módulos carregados pelo navegador.
- [solar_environment_test.mjs](../tests/solar_environment_test.mjs) verifica a continuidade da curva e a equivalência da fórmula anterior para outras obras. [solar_environment_cache_test.mjs](../tests/solar_environment_cache_test.mjs) verifica a interpolação de 600 ms, o isolamento entre as obras 102 e 133 e o patamar noturno.
- Também passaram os testes de posição e projeção solar, sombras, HDRI seletivo, vidros noturnos e diagnóstico A/B/C. Os arquivos PNG e os valores de FPS foram conferidos com os três JSONs do navegador em `predio-3d/logs`.
