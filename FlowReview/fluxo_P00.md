# 🧭 Mapa Mental – Fluxo Específico do Processo P00

```
PROCESSO P00 🚀
│
├── 🔎 DETECÇÃO P00
│   ├── Função Finalização aprovada 
│   ├── Imagem com status P00 
│   └── Verifica entrega em entregas_itens 
│         └── Marca como "Entrega Pendente" 
│
├── 🎞️ REGISTRO DE ÂNGULOS
│   ├── Todos os ângulos aprovados → tabela angulos_imagens
│   │     ├── imagem_id 
│   │     ├── historico_id 
│   │     └── entrega_item_id 
│   └── Esses ângulos ficam aguardando liberação 
│
├── 🌐 FLOW REVIEW – LIBERAÇÃO
│   ├── Entrega liberada pela gestão 
│   ├── Exibição diferenciada:
│   │     ├── Imagem principal (visão completa) 
│   │     └── Galeria dos ângulos opcionais 
│   └── Cliente deve escolher um 
│
├── 🧑‍💻 AÇÃO DO CLIENTE
│   ├── Escolhe ângulo → Submit Decision
│   │     ├── Captura ângulo escolhido 
│   │     ├── Observação opcional 
│   │     ├── Atualiza imagem_decisao_angulos 
│   │     ├── Notifica gestão 
│   │     ├── Move JPG para caminho final no servidor 
│   │     └── Notifica colaborador da finalização 
│   │
│   └── ❌ NÃO GOSTOU DE NENHUM
│         ├── Botão "Refazer ângulos" 
│         ├── Cliente insere observação 
│         └── Gestão é notificada 
│
├── 🔄 TRATAMENTO DE NOVAS SUGESTÕES
│   ├── Gestão avalia observações 
│   ├── Repassa para produção 
│   ├── Produção gera novos ângulos 
│   └── Volta ao início do ciclo P00 
│
└── 🏁 FINALIZAÇÃO
    ├── Ângulo escolhido definido 
    ├── Produção segue com render final 
    └── Fluxo normal de revisão continua 
```