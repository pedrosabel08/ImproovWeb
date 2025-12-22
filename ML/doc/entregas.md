📦 Estrutura do domínio

Existem entregas, cada uma com em média 16 imagens

Cada imagem passa por etapas, representadas por status_id:

Ex.: P00, R00, R01, R02, EF

Dentro de cada etapa, a imagem possui status operacionais, representados por substatus_id:

TO-DO (não iniciado)

TEA (em andamento)

APR (em aprovação)

RVW (review)

DRV (finalizado / no drive)

🔁 Histórico

O sistema possui uma tabela historico_imagens

Cada mudança de etapa ou status gera um registro com:

imagem_id

status_id (etapa)

substatus_id (status)

data_movimento

Esse histórico é a linha do tempo real da imagem

Não existe data de criação formal da imagem; o início real é o primeiro registro no histórico

🎯 Estado final

O status final esperado depende da etapa

Para P00 → status final = DRV

Para R00, R01, R02 → status final = RVW

O último status válido de cada imagem pode ser identificado consultando o histórico

🧠 Estratégia de ML

O ML não começa pela entrega, começa pela imagem

Cada imagem gera múltiplos snapshots temporais antes do status final

Cada snapshot representa o estado da imagem em um ponto do tempo

📊 Dataset de treino

Cada linha do dataset representa um snapshot e contém, no mínimo:

imagem_id

etapa (status_id – categórico)

status (substatus_id – categórico)

horas_desde_inicio (tempo desde o primeiro movimento)

transicoes (quantidade de mudanças até o momento)

horas_restantes (target: tempo até o status final esperado)

Snapshots após o status final não são usados (evitar vazamento de dados).

🤖 Modelo

Tipo de problema: regressão

Objetivo: prever tempo restante até o status final

Modelos iniciais:

baseline estatístico

Random Forest Regressor

Avaliação:

MAE (erro médio absoluto)

split por imagem_id (nunca misturar snapshots da mesma imagem)