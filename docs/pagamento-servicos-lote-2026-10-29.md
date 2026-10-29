# Alterações de serviços salvas em conjunto

No modal de serviços, cada ação adiciona um item à lista de alterações pendentes. O usuário pode remover itens, descartar a lista ou salvar o conjunto. Enquanto houver alterações pendentes, a navegação entre colaboradores, confirmação e conclusão ficam bloqueadas; fechar o modal preserva a lista e informa como retomá-la. Recarregar ou fechar a página apresenta aviso de alterações não salvas.

Ao salvar, a API valida cada item e publica uma única decisão e revisão em transação. A lista de operações e os motivos individuais ficam registrados no histórico imutável. Se qualquer item for inválido, a transação inteira é revertida. A chave de idempotência e a versão esperada protegem o retry e a concorrência. Após sucesso, a interface recarrega os valores e prepara o PDF uma vez. A operação de salvar não confirma o adendo.

O rascunho é mantido na memória da aba até salvar ou descartar. Em caso de erro transitório após o envio, o mecanismo de retry existente mantém a chave e o conteúdo; o modal também oferece Tentar novamente. O servidor limita o conjunto a 100 operações e mantém as validações, autorização e CSRF existentes.

Validação: `php tests/fechamento_servicos_lote_test.php` cobre transação atômica, rollback, revisão única, valores, motivo por item, PDF, concorrência, idempotência e payload inválido; passaram também 39 regras de planta, 30 verificações de pendências mensais e 830 de metadata. No navegador interno, usando MySQL 8 isolado e dados sintéticos, duas alterações foram acumuladas e salvas juntas; PDF, descarte, aviso e bloqueios foram conferidos. A tela autenticada oficial foi acessada por Financeiro → Pagamento → Fechamento. Desktop, notebook, iPad landscape/portrait e mobile foram inspecionados. Capturas estão em `output/ui/servicos-lote-20261029/`.

O pacote `output/pagamento-servicos-lote-20261029.zip` inclui esta alteração, os serviços de planta e o gerador de PDF da entrega anterior. Não foi feito deploy. Não houve alteração de pagamentos ou valores reais. Após publicar, atualize as revisões antigas e confira o novo adendo.
