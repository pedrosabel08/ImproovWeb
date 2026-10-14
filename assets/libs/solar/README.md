# Dependências solares locais

- SunCalc **1.9.0**, distribuição ESM do jsDelivr: https://cdn.jsdelivr.net/npm/suncalc@1.9.0/+esm
  - Origem/documentação da versão: https://github.com/mourner/suncalc/tree/v1.9.0
  - BSD-2-Clause, licença preservada em suncalc-LICENSE.
  - SHA-256: 3afd7211972bb573ff6344536f4eed10ad23d99593103685e61533d4939ebe82
  - Convenção desta versão: azimute em radianos, sul → oeste; altitude em radianos. Não atualizar sem revisar solar-position.mjs e seus testes, pois versões posteriores mudam a convenção.
- Luxon **3.7.2**, módulo ESM oficial: https://cdn.jsdelivr.net/npm/luxon@3.7.2/build/es6/luxon.mjs
  - Documentação: https://moment.github.io/luxon/api-docs/index.html
  - MIT, licença preservada em luxon-LICENSE.md.
  - SHA-256: b495ad5cabea3439d04387e6622f2c3fa81d319424d9d76d9e7f874ac5a0807a

Não há requisição a serviço externo para calcular Sol ou fuso. Luxon usa a base IANA do Intl do navegador. Os arquivos são servidos pela aplicação; o import map de Three.js preexistente continua sendo utilizado.
