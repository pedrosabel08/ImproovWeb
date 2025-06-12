# **Documento de Especificação de Requisitos – Flow Review Externo**

## **1. Visão Geral**

O **Flow Review Externo** é um módulo de revisão de arquivos/documentos voltado para permitir a aprovação de entregas por usuários externos (clientes), integrando-se ao fluxo interno de trabalho. A solução visa centralizar e rastrear interações com revisores externos, promovendo maior controle e agilidade no processo de revisão de imagens ou documentos.

---

## **2. Requisitos Funcionais (RF)**

|Código|Descrição|
|---|---|
|**RF01**|O sistema deve permitir que os gestores submetam arquivos para revisão por meio do Dashboard.|
|**RF02**|O sistema deve gerar um link exclusivo para cada revisão que será enviado ao revisor externo.|
|**RF03**|O revisor externo deve informar seu nome e e-mail para acessar o conteúdo da revisão.|
|**RF04**|O revisor externo poderá visualizar o arquivo enviado para revisão.|
|**RF05**|O revisor externo poderá realizar as seguintes ações sobre o arquivo: **Aprovar**, **Comentar**, **Solicitar Ajustes** ou **Reprovar**.|
|**RF06**|O sistema deve registrar todas as ações do revisor externo, incluindo comentários, com data, nome e e-mail.|
|**RF07**|Os gestores devem ser notificados no sistema e via Slack quando o revisor responder.|
|**RF08**|O sistema deve permitir o controle de acesso via sessão PHP, com tempo de expiração.|
|**RF09**|O sistema deve permitir mais de uma rodada de revisão por tarefa (nova submissão para o mesmo link ou um novo link).|
|**RF10**|O sistema deve exibir os status da imagem: **Pendente**, **Aprovado**, **Aprovado com Ajustes**, **Reprovado**.|
|**RF11**|Deve haver uma tela acessível por `/review/{codigo-da-obra}` para visualização da revisão por parte do revisor.|
|**RF12**|O sistema deve permitir a consulta ao histórico de revisões por parte dos gestores.|

---

## **3. Requisitos Não Funcionais (RNF)**

| Código    | Descrição                                                                                                    |
| --------- | ------------------------------------------------------------------------------------------------------------ |
| **RNF01** | O sistema deve ser acessível por qualquer plataforma (desktop, tablet ou smartphone).                        |
| **RNF02** | O layout do módulo de revisão deve seguir o padrão visual do sistema atual.                                  |
| **RNF03** | O sistema deve utilizar sessões PHP para controle de acesso do revisor, com tempo configurável de expiração. |
| **RNF04** | O sistema não deve depender de integrações externas (e.g., Google Drive, APIs) nesta fase.                   |
| **RNF05** | O módulo deve ter tempo de resposta adequado para garantir boa experiência do usuário externo.               |
| **RNF06** | A aplicação deve manter registro seguro e consistente das ações realizadas por revisores e gestores.         |

---

## **4. Regras de Negócio (RN)**

| Código   | Descrição                                                                                                                                    |
| -------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| **RN01** | Apenas gestores podem cadastrar arquivos para revisão e iniciar um fluxo de aprovação externa.                                               |
| **RN02** | O link gerado é único para cada envio e permite apenas acesso com preenchimento de nome e e-mail.                                            |
| **RN03** | As respostas dos revisores devem alterar automaticamente o status do arquivo.                                                                |
| **RN04** | Os gestores devem ser notificados imediatamente após qualquer ação do revisor externo.                                                       |
| **RN05** | Caso o revisor não responda dentro do prazo estabelecido (caso configurado), o sistema pode alertar os gestores. _(Comportamento a definir)_ |
| **RN06** | Cada ação de revisão (aprovação, comentário, ajuste, reprovação) deve ser registrada com ID do revisor, mensagem, e data/hora.               |
| **RN07** | Gestores podem reabrir a revisão, submetendo novamente arquivos para nova rodada, vinculada à mesma ou nova tarefa.                          |
| **RN08** | O histórico de revisões deve ser armazenado e acessível apenas para a equipe interna com permissões adequadas.                               |