# Diagnóstico do fechamento em produção — 29/10/2026

## Causa confirmada

O deploy do fechamento ficou incompleto: a dependência `Contratos/services/ContratoPdfService.php` permanece em uma versão anterior em produção. A classe remota oferece somente os métodos públicos `__construct` e `gerarPdf`; falta `renderizarHtml`, chamado por `Pagamento/services/FechamentoDocumentoService.php` ao materializar o PDF do adendo.

Reprodução isolada no servidor, executada como `improov`, com HTML fictício e sem chamada ao banco:

```text
Error: Call to undefined method ContratoPdfService::renderizarHtml()
```

O log de produção registra `action=gerar`, `code=OPERATION_FAILED`, `exception=Error` nas tentativas do fechamento de outubro de 2026. A API converte esse erro na mensagem genérica apresentada pelo usuário. O frontend preserva a operação pendente para repetir com a mesma chave, bloqueando outras ações até sua conclusão.

## Verificações

- Login autenticado no navegador interno em `https://improov/ImproovWeb/`, navegação Financeiro → Pagamento → Fechamento; leitura da competência de outubro.
- Login contextual na URL de produção informada pelo usuário; listagem de outubro carregada. Nenhum botão que gera, confirma ou paga documentos foi acionado.
- Comparação SHA-256: os arquivos PHP/JS examinados do fechamento em `Pagamento`, inclusive serviços e API financeira, estão iguais aos locais.
- O template `Contratos/templates/adendo_modelo.html` também está igual. A dependência `ContratoPdfService.php` diverge, e a diferença corresponde à extração da renderização para o método público ausente.
- Flag habilitada no PHP-FPM 8.3 e storage privado configurado, pertencente a `improov`, com subdiretórios privados. Extensões `gd`, `mbstring`, `dom`, `xml` e `intl` presentes no PHP CLI remoto.
- Versão local do serviço: lint aprovado; `renderizarHtml` com HTML fictício produziu PDF válido de 1.354 bytes, sem gravar documentos ou acessar o banco.

## Correção preparada, não publicada

Publicar a versão local de `Contratos/services/ContratoPdfService.php` no mesmo caminho relativo da instalação de produção:

```text
/home/improov/web/improov.com.br/public_html/flow/ImproovWeb/Contratos/services/ContratoPdfService.php
```

O pacote `output/diagnostico-fechamento/correcao-fechamento.zip` contém somente esse arquivo, preservando o caminho relativo. Antes da substituição, preservar uma cópia da versão remota. Após publicar, verificar que o PHP-FPM reconhece `renderizarHtml` e invalidar o OPcache desse arquivo caso a configuração impeça a atualização automática.

SHA-256 do arquivo preparado: `6bf415f7baf5d849ca4b5d5d97f151f932a3df76a7e30af7ced19d5b6adfd3c0`. SHA-256 do arquivo remoto observado: `4f19debb8937532c460241fd72a0d83530b76896bb0fd896eefda51a450f5144`.

Depois, usar **Tentar novamente** na sessão que apresentou o erro, preservando a chave idempotente. Conferir a geração e a abertura do PDF antes de prosseguir com a revisão humana. Não apagar operações pendentes nem modificar manualmente registros financeiros para contornar a falha.

Não foi realizado deploy, migração, confirmação de adendo ou fechamento, alteração de valores ou pagamento. A correção ainda exige publicação e validação no fluxo de produção.

## Observação independente

O log também contém tentativas anteriores de visualizar um arquivo documental ausente. Isso deve ser reavaliado depois da publicação: local e produção usam storages distintos, e atualizar o código não transfere PDFs existentes. Nenhum arquivo documental foi copiado, recriado ou confirmado nesta investigação.
