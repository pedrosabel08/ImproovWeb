# Reverter lote de Review aprovado/resolvido por engano

1. Abra o PowerShell em `C:\xampp\htdocs\ImproovWeb`.
2. Simule a reversão, sem alterar o banco:

   ```powershell
   php manutencao\reverter_review_batch.php --batch-id=88
   ```

3. Confira o JSON: o status precisa estar `RESOLVED` e os lotes/imagens de Pré-Alteração devem ser exatamente os que precisam sair.
4. Aplique somente após conferir:

   ```powershell
   php manutencao\reverter_review_batch.php --batch-id=88 --apply
   ```

5. Atualize Entregas e Pré-Alteração. O lote deve ficar aberto novamente em Entregas, e as imagens do batch não devem mais aparecer na Pré-Alteração.

O utilitário só executa a reversão para lotes `RESOLVED`. Ele remove apenas os itens e vínculos da Pré-Alteração que pertencem ao `review_batch` informado; um lote de Pré-Alteração só é excluído se ficar vazio.
