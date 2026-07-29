# Backlog de migração do padrão de UI do Flow

## Objetivo

Este documento acompanha a migração gradual dos módulos ao Guia oficial de desenvolvimento do Flow. Ele não define regras canônicas: as regras estão em .github/instructions/ui-styles.instructions.md.

Atualize este backlog quando uma auditoria identificar divergência ou quando uma migração for concluída. Não copie regras do guia para cá.

## Critério de prioridade

| Prioridade | Quando usar |
| --- | --- |
| P0 | Infraestrutura compartilhada que afeta muitas telas, temas ou acessibilidade. |
| P1 | Módulo ativo, com alto volume de uso ou divergência visual que prejudica operação. |
| P2 | Módulo ativo com divergência localizada, mas sem impacto crítico. |
| P3 | Legado estável: migrar somente junto de demanda funcional. |

## Critério de encerramento por módulo

Uma migração é concluída quando o módulo:

- usa tokens semânticos para superfícies, texto, bordas, foco e estados;
- funciona em light e dark por prefers-color-scheme;
- não cria dependência, padrão visual, helper ou CSS duplicado;
- valida loading, vazio, erro, sucesso, foco e responsividade aplicáveis;
- preserva contratos de sessão, API, sidebar e integrações existentes;
- passa pela validação prática do fluxo afetado.

## Fundação compartilhada

| Item | Prioridade | Estado atual | Divergências e resultado esperado |
| --- | --- | --- | --- |
| Taxonomia de tokens | P0 | Parcialmente repetida entre módulos | Consolidar nomenclatura semântica e paridade light/dark; não criar alias redundante. |
| Sidebar e infraestrutura global | P0 | Compartilhada e rica em permissões, badges e URLs | Preservar contrato; garantir que consumidores não copiem sua estrutura. |
| Sessão, notificações e feedback | P1 | Toastify, SweetAlert2 e diálogos próprios coexistem | Definir critério de escolha e preservar as APIs já usadas. |

## Família operacional tokenizada

| Módulos de referência | Prioridade | Suporte de tema | Foco da migração |
| --- | --- | --- | --- |
| Render | P1 | Light e dark por tokens | Consolidar estados, filtros móveis, KPI, skeleton e empty state como referência operacional. |
| Entregas | P1 | Light e dark, com extensões de domínio | Normalizar componentes compartilháveis sem apagar fluxos de entrega e revisão. |
| Alteração e Atividade | P2 | Dark presente; verificar consistência completa | Eliminar duplicações de tokens, estados e filtros quando houver demanda funcional. |

## Dashboards operacionais

| Módulos de referência | Prioridade | Suporte de tema | Foco da migração |
| --- | --- | --- | --- |
| Gestão | P1 | Dark-first; verificar paridade light | Formalizar KPI, quick action, painel, atualização parcial e estados independentes. |
| Página Principal | P2 | Dark presente em áreas específicas | Reduzir sobreposições históricas e validar modais, kanban e responsividade. |
| Dashboards complementares | P2 | Variável | Aplicar critério de painel/KPI e evitar re-render amplo. |

## Fluxos especializados

| Módulos de referência | Prioridade | Suporte de tema | Foco da migração |
| --- | --- | --- | --- |
| Flow Block | P1 | Dark próprio; light pendente de auditoria | Preservar prefixo e vocabulário de issue; mapear tokens semânticos e estados acessíveis. |
| Flow Review | P2 | Tema e responsividade próprios | Reduzir divergência sem reescrever o fluxo de aprovação. |
| Flow Drive e Flow Referências | P2 | Tema parcial | Reutilizar modal, upload, feedback e tokens onde forem compatíveis. |

## Legado suportado

| Área | Prioridade | Dependências | Diretriz |
| --- | --- | --- | --- |
| Calendário | P3 | Bootstrap e jQuery | Não expandir Bootstrap; migrar apenas com demanda funcional. |
| DataTables e tabelas históricas | P3 | jQuery/DataTables | Manter contratos; adotar padrão canônico ao criar superfície nova ao redor delas. |
| CSS histórico e duplicado | P3 | Varia por módulo | Corrigir incrementalmente, sem reset global ou reescrita sem demanda. |

## Registro de migrações

| Data | Módulo | Item migrado | Evidência de validação | Responsável |
| --- | --- | --- | --- | --- |
| — | — | — | — | — |
