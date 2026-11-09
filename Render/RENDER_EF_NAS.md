# Render em alta a partir do NAS

O fluxo atual utiliza **snapshots persistidos do job correspondente ao render
aprovado**, com seleção explícita do `.max` final. Não executa inspeção local nem
remota e não cria jobs CommandLine.

Consulte [DEADLINE_SNAPSHOTS.md](DEADLINE_SNAPSHOTS.md) para diagnóstico, arquitetura,
estrutura de dados, migrations, configurações, testes e homologação.

A migration `2026-11-06_render_ef_nas.sql` permanece necessária para UUID, seleção
de arquivo, reserva por imagem e histórico. É complementada por
`2026-11-06_deadline_job_snapshots.sql`. Ambas permanecem pendentes no banco real.

Execução real desligada; nenhum worker operacional foi reiniciado e nenhum render
ou job de inspeção foi submetido nesta entrega.
