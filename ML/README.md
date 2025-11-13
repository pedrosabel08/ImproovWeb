# ML: Classificar tipo_imagem a partir do nome da imagem

Este protótipo usa Python + scikit-learn para treinar um classificador (Logistic Regression com TF‑IDF) que recebe `imagem_nome` e prevê `tipo_imagem`.

## Requisitos
- Python 3 instalado no Windows (no PATH como `python` ou `py -3`)
- Pacotes:

```
pip install -r ML/requirements-ml.txt
```

## Como treinar
1. Com o Apache/XAMPP rodando, acesse:
   - `http://localhost/ImproovWeb/Pagamento/ml_api.php?action=train`
2. O endpoint exporta os dados da tabela `imagens_cliente_obra` (colunas `imagem_nome`, `tipo_imagem`), treina o modelo e grava em `ML/model.joblib`.
3. A resposta JSON inclui acurácia (`accuracy`) e o relatório por classe.

Caso o Python não esteja como `python`, edite `Pagamento/ml_api.php` e altere a variável `$python` para `py -3` ou o caminho completo do executável.

## Como prever
Envie um POST com JSON (array de nomes `imagem_nome`) para o endpoint de predição:

- URL: `http://localhost/ImproovWeb/Pagamento/ml_api.php?action=predict`
- Corpo (JSON):

```json
["1.ARQ_Salao de festas 1","Fachada principal A","Planta 102 B"]
```

Resposta (exemplo):
```json
{
  "status": "ok",
  "predictions": [
    {"input": "1.ARQ_Salao de festas 1", "predicted": "Imagem Interna", "confidence": 0.86},
    {"input": "Fachada principal A", "predicted": "Fachada", "confidence": 0.92},
    {"input": "Planta 102 B", "predicted": "Planta Humanizada", "confidence": 0.88}
  ]
}
```

## Observações
- O modelo usa apenas o texto de `imagem_nome`. Para melhorar, podemos incluir outros campos (ex.: função, obra, cliente) ou regras de pós-processamento.
- Este é um experimento inicial. Avalie a acurácia e ajuste `ngram_range`, `min_df` e o algoritmo conforme necessário.
- Segurança: `ml_api.php` usa `shell_exec` para chamar Python. Mantenha o endpoint interno/limitado a admins.
