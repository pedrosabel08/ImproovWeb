# Portal do Cliente — diagnóstico e decisão de arquitetura

Data: 16/09/2026. Escopo: projetar Partes 1 e 2; implementar apenas a Parte 1.
Fonte de experiência: leitura integral das 19 páginas de “ÁREA DO CLIENTE IMPROOV.pdf”. O prompt prevalece sobre o PDF quanto a permissões e limites funcionais.

## Diagnóstico confirmado no código e no MySQL 8.0.46

* `obra` é o projeto; `cliente` é o cliente. Nome real/completo, local e status já existem. `status_obra=0` representa obra ativa; `1`, inativa. Não criar outro projeto/cliente.
* `contato_cliente` centraliza nome, e-mail normalizado único, telefone e atividade. `obra_contato` já tem vínculo único obra/contato, ativação lógica e FKs. O campo legado `disciplina` é texto singular, inadequado para escolhas múltiplas por obra. Não convertê-lo nem sobrescrevê-lo.
* `contact_architecture.php` resolve identidade e vínculos. Atenção: seu helper de vínculo reativa vínculos; o Portal precisa rejeitar removidos ANTES de chamá-lo.
* `Briefing/lib.php` / `BriefingExt` já possuem `external_auth_session`, cookie HttpOnly/SameSite, hash de token, CSRF, OTP por e-mail, expiração e limitação de tentativas. `external_otp_challenge` está amarrado ao link de Briefing. Estender esse escopo para Portal, preservando os consumidores existentes. Nunca criar Briefing artificial para autenticar.
* `FlowReviewExt` tem identidade separada (`usuario_externo`/`login_tokens`) e regras legadas de cargo/IDs. Não é a base escolhida para novos contatos.
* `categorias` é catálogo real do FlowDrive: Arquitetônico, Referências, Paisagismo, Luminotécnico, Estrutural, Alterações, Ângulo definido. `tipo_imagem` distingue Fachada, Interna, Externa etc.; não representa disciplina. Não criar cópia desses catálogos. Não existe catálogo relacional de disciplinas. Interiores deve compartilhar a categoria Arquitetônico, com disciplina própria.
* `briefing_tipo_imagem` / `briefing_requisitos_arquivo` já guardam requisitos operacionais por obra, tipo de imagem, categoria e formato; `briefing_requisitos_arquivo_log` audita recebido/validado/dispensado. São configuração operacional, não solicitação curada ao cliente. Sugestões leem requisitos CLIENTE da própria obra, agrupando formatos por disciplina/categoria; na ausência deles, sugerem a categoria sem inventar formatos obrigatórios. A curadoria completa os formatos antes de publicar.
* `arquivos` centraliza arquivo físico, categoria, obra, versão e status; FlowDrive armazena via SFTP e renomeia/versiona segundo regras internas, atualizando requisitos legados. BriefingExt possui anexos próprios de perguntas, MIME/limite/checksum/download autenticado. Nenhum desses endpoints será exposto ao Portal. A futura entrada externa terá autorização própria e adaptador para `arquivos`.
* Internamente existem `usuario.nivel_acesso`, `usuario_cargo`/`cargo` e revisor designado no Briefing; não foi encontrada ACL genérica por obra. Gestão (nível 1) configura o Portal e designa um usuário ativo como curador da obra. Curador opera somente suas obras. Nenhum nome/ID pessoal é hardcoded.
* FlowConnect tem eventos, outbox, políticas e notificações; Briefing usa eventos HISTORY_ONLY. O Portal registra eventos transacionais próprios, com payload e identidade, aptos a posterior consumo pelo FlowConnect. Nesta rodada não há envio automático de convites/notificações: convite é link compartilhável pelo usuário.

## Modelo e ownership

`portal_projeto` é extensão 1:1 de obra: curador (usuario), contato central via obra_contato, abertura/encerramento de inscrições, token de convite somente em hash, revisão otimista e data de preparação. `portal_participante` estende o vínculo existente, guardando ingresso e remoção explícita do Portal; não duplica nome/e-mail/telefone. Não importar silenciosamente todos os contatos da obra como usuários do Portal. Remoção do Portal não desativa identidade nem altera acesso legado de Briefing.

`flow_disciplina` é catálogo do Flow, relacionado por FK a `categorias`; não é catálogo de materiais. `portal_projeto_disciplina` e `portal_participante_disciplina` são relações; FKs compostas impedem selecionar disciplina de outra obra. Perfil sem disciplina é válido. Disciplina nunca concede/restringe autorização. Administrador é um único vínculo designado e não entra no predicado de permissão externa.

Preparação usa handlers pequenos, identificados por chave, inicialmente `disciplinas`; o resultado relacional alimenta as sugestões. Novas perguntas poderão ter tabela de resposta tipada e handler sem alterar identidade, equipe ou materiais. Não se cria motor de formulários nem JSON para relações.

`portal_solicitacao` tem uma solicitação inicial por projeto, estado RASCUNHO/PUBLICADA, versão e autor/data de publicação. `portal_material` é o objeto estável, FK para categoria existente e disciplina do projeto, título curado, momento INICIO/DURANTE, contexto, observação, revisão humana e remoção lógica. `portal_material_formato` normaliza os formatos aceitos. `portal_material_origem` liga sugestões aos requisitos legados, sem substituir seus estados.

`portal_evento` mantém eventos append-only pela aplicação, ator interno/contato, objeto, data e metadados de alterações. JSON é usado somente como evidência de auditoria, nunca para membership, disciplinas ou formatos. Mutação e evento são atômicos. Publicação é evento `materials.published`, idempotente, fronteira da Parte 1.

## Estados, concorrência e transições

Configuração e equipe vivem independentemente da solicitação. Disciplinas podem ser definidas por qualquer participante ativo, sem aprovação do contato central. Preparação grava seleção e gera sugestões em uma transação; não espera cadastro de toda a equipe. Alterações concorrentes conferem revisão e bloqueiam o projeto. Remoção de disciplina é rejeitada enquanto houver participante ou material ativo associado; depois da publicação mudanças na seleção do projeto ficam reservadas à evolução da Parte 2, mas participantes continuam escolhendo disciplinas e entrando.

Curador revisa cada sugestão, adiciona/remove material e publica somente com disciplinas, ao menos um item ativo, contexto e formatos de todos os itens preenchidos/revisados. A publicação não valida arquivos, não altera requisitos operacionais nem libera Briefing. Itens publicados ficam imutáveis nesta rodada. Inscrições permanecem abertas até fechamento explícito ou obra inativa. Revogação/rotação de link invalida o link anterior, incluindo desafios OTP pendentes.

## Segurança

Token aleatório de 256 bits identifica convite, não sessão. API externa resolve obra pelo token, exige identidade verificada e membership ativo em todas as leituras privadas. Conhecer link de outra obra não concede acesso com sessão da obra atual; ingresso requer OTP naquele convite. Removidos são recusados mesmo após nova autenticação; reativação só interna. Estado de obra, Portal, vínculo e identidade é revalidado no servidor. Sessão externa é reaproveitada, não a sessão interna.

Mutações exigem POST, JSON e CSRF (inclusive pré-autenticação). OTP tem hash, TTL, uso único, bloqueio transacional, cooldown e limite por projeto/e-mail/IP, com limite global por IP. Falhas retornam mensagens controladas e HTTP adequados, sem SQL/stack. Token bruto não vai para auditoria. Cabeçalhos no-store/no-referrer e CSP reduzem vazamento. Sem URLs de armazenamento ou códigos operacionais no DTO externo.

## Camada de experiência

Presenter converte domínio em momento/título/contexto/ação/próximo passo. Home: preparar projeto → organizar materiais → solicitação preparada. Uma ação dominante por tela; Equipe permanece acessível. Exterior apresenta apenas nome público, cliente, localização, pessoas/disciplinas e itens publicados. Rascunhos e trilha técnica permanecem internos. Ajuda humana está sempre presente.

## Parte 2 — desenho, NÃO implementado

1. Envio: futura `portal_material_envio` relaciona material estável, obra, autor contato, sequência e `arquivos.idarquivo`; FK composta garante mesma obra. Upload autenticado, CSRF, allowlist/MIME, limite, checksum, quarentena, armazenamento privado e idempotência; compensação para falhas entre banco/SFTP. Materiais adicionais usam material de origem ESPONTANEO, sem transformar toda referência em requisito essencial.
2. Versões: envios são imutáveis, sequência única por material e `substitui_envio_id`; arquivos anteriores continuam auditáveis. Nome externo continua o título curado. Não reutilizar o número de versão calculado por categoria do FlowDrive como identidade do material.
3. Conferência: futura `portal_material_avaliacao` guarda decisão, envio avaliado, curador, motivo e instante. Estado de atendimento separado de momento/importância: AGUARDANDO → RECEBIDO → VALIDADO / ATUALIZACAO_NECESSARIA; DISPENSADO só por curador. Receber nunca equivale a validar. Adaptador mapeia requisitos legados da tabela de origem, sem o cliente chamar endpoints internos.
4. Ainda não temos: futura `portal_material_resposta` guarda indisponibilidade, observação/previsão e autor. Não conta como validado. Reenvio preserva a solicitação visual e produz novo envio.
5. Promoção: futura revisão da solicitação e evento `material.needed_now`, com preservação do momento anterior e ação dominante atualizada. Não existe endpoint de promoção nesta rodada.
6. Base pronta: futura `portal_base_decisao`, curador/data/justificativa, fotografia dos requisitos essenciais e exceções. Elegibilidade calculada a partir de validação/dispensa; decisão humana explícita e idempotente. Evento `base.ready` libera um Briefing existente através de serviço/adaptador, nunca do frontend. Reabertura e invalidação posterior terão regra explícita. Nenhuma dessas tabelas/transições da Parte 2 é criada agora.

## Compatibilidade e operação

Migration aditiva, versionada, executada via CLI; nenhum DDL em request. Pré-requisitos: arquitetura de contatos e acesso externo v2 aplicados, MySQL >=8.0.16, engine InnoDB. Rollback separado, recusando perda de dados; depois de uso preferir rollback da aplicação mantendo estruturas. As alterações locais em Backup são alheias à tarefa e serão preservadas.

## Evidências de validação

Os resultados efetivamente executados serão registrados em `docs/portal-cliente-validacao.md`. A conexão deste checkout aponta a um banco remoto compartilhado; não confundir URL local com banco isolado. Testes devem usar fixtures identificadas, sem convites reais, sem alterar obras/contatos existentes e sem notificações externas.
