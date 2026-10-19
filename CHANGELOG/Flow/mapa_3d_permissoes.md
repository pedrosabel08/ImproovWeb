# Permissões do mapa arquitetônico e prédio 3D — 19/10/2026

Administração centralizada em `helpers/mapa_permissoes_helper.php`: colaboradores Nicolle (1), André L. (9) e Pedro (21), com sessão autenticada. Nível geral de acesso não concede administração espacial.

- Mapa: envio de PDF, inclusão/edição/exclusão de ambientes, pontos, camadas e área útil exclusivos dos administradores. Seleção de documento/página, criação de subtipo e associação de legado também restritas. Consulta, seleção de pavimentos, zoom e inspeção operacional preservados.
- 3D: seletor de modelo, envio, versões, configurações espaciais e alteração de vínculo ocultos para os demais. Controles inicialmente ocultos até a API confirmar a permissão. Configuração espacial continua sendo lida para orientar Norte e Sol.
- API: as onze operações de escrita exigem administração antes de processar payload, consultar banco ou armazenar arquivos. Consulta de versões também exige administração. CSRF e autorização de acesso à obra preservados.
- Viewcube: o botão referenciava `modelBounds`, variável local de outro método. Sem pavimento selecionado, agora calcula o Box3 do modelo carregado e ignora limites vazios. Enquadramento de pavimentos preservado.
- Versões dos imports atualizadas para evitar uso de módulos administrativos em cache.

## Validação

- Navegador no ambiente oficial `https://improov/ImproovWeb/`, autenticado como Pedro e depois Pedro Henrique. CIB_HYP/102, modelo P00/4, planta Térreo.
- Administrador: controles presentes no mapa e no viewer. Usuário comum: controles ocultos; planta, ambiente e mapa incorporado acessíveis; ida/volta entre planta e prédio funcionando.
- Viewcube: vistas frente/superior/perspectiva sem pavimento no administrador; frente com pavimento e direita/perspectiva sem pavimento no usuário comum. `aria-pressed` confirma conclusão; console sem erros no viewer.
- Layout de leitura conferido em desktop, notebook 1366x768, iPad 1024x768 e 768x1024, mobile 390x844, sem overflow horizontal. Overrides restaurados.
- `tests/mapa_permissoes_test.php`: lista dos três IDs aceita e outros usuários recusados, inclusive com nível geral elevado.
- `tests/mapa_permissoes_api_test.php`: 12 operações recusadas com HTTP 403 para perfil comum, sem acesso ao banco ou gravação de conteúdo.
- PHP e módulos JavaScript alterados passaram na verificação de sintaxe. Teste independente `floor_navigation_test.mjs` não executou por ausência do módulo local Three r180 exigido pelo runner; comportamento do viewcube validado no navegador.

Evidências: `mapa_admin.jpg`, `mapa_leitura.jpg`, `predio_admin.jpg`, `predio_leitura.jpg`.
