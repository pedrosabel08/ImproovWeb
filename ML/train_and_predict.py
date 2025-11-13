import argparse
import json
import os
import sys
from typing import List, Dict

import joblib
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.pipeline import Pipeline
from sklearn.linear_model import LogisticRegression
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report, accuracy_score

MODEL_PATH = os.path.join(os.path.dirname(__file__), 'model.joblib')


def build_pipeline() -> Pipeline:
    return Pipeline([
        ('tfidf', TfidfVectorizer(
            lowercase=True,
            strip_accents='unicode',
            analyzer='word',
            ngram_range=(1, 2),
            min_df=2
        )),
        ('clf', LogisticRegression(max_iter=1000, n_jobs=None, solver='lbfgs', multi_class='auto'))
    ])


def train(csv_path: str) -> Dict:
    df = pd.read_csv(csv_path)
    df = df.dropna(subset=['imagem_nome', 'tipo_imagem'])
    X = df['imagem_nome'].astype(str)
    y = df['tipo_imagem'].astype(str)

    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42, stratify=y)

    pipe = build_pipeline()
    pipe.fit(X_train, y_train)

    y_pred = pipe.predict(X_test)
    acc = accuracy_score(y_test, y_pred)
    report = classification_report(y_test, y_pred, output_dict=True)

    joblib.dump({'pipeline': pipe, 'labels': sorted(y.unique())}, MODEL_PATH)

    return {
        'status': 'ok',
        'model_path': MODEL_PATH,
        'accuracy': acc,
        'labels': sorted(y.unique()),
        'report': report
    }


def _load_model():
    if not os.path.exists(MODEL_PATH):
        raise FileNotFoundError('Modelo não encontrado. Treine primeiro.')
    data = joblib.load(MODEL_PATH)
    return data['pipeline'], data.get('labels')


def predict_json(items: List[str]) -> List[Dict]:
    pipe, labels = _load_model()
    preds = pipe.predict(items)
    if hasattr(pipe.named_steps['clf'], 'predict_proba'):
        proba = pipe.predict_proba(items)
    else:
        proba = None

    out = []
    for i, text in enumerate(items):
        entry = {
            'input': text,
            'predicted': str(preds[i])
        }
        if proba is not None:
            # highest probability for predicted class
            max_p = float(max(proba[i]))
            entry['confidence'] = round(max_p, 4)
        out.append(entry)
    return out


def main():
    parser = argparse.ArgumentParser(description='Treinar e prever tipo_imagem a partir do nome da imagem')
    sub = parser.add_subparsers(dest='cmd')

    p_train = sub.add_parser('train', help='Treinar modelo')
    p_train.add_argument('--csv', required=True, help='Caminho do CSV com colunas imagem_nome,tipo_imagem')

    p_predict = sub.add_parser('predict', help='Prever com modelo treinado')
    p_predict.add_argument('--items', required=True, help='JSON array de strings (nomes de imagem)')

    args = parser.parse_args()
    try:
        if args.cmd == 'train':
            result = train(args.csv)
            print(json.dumps(result, ensure_ascii=False))
        elif args.cmd == 'predict':
            items = json.loads(args.items)
            if not isinstance(items, list):
                raise ValueError('--items deve ser um array JSON')
            result = predict_json([str(x) for x in items])
            print(json.dumps({'status': 'ok', 'predictions': result}, ensure_ascii=False))
        else:
            parser.print_help()
            sys.exit(2)
    except Exception as e:
        print(json.dumps({'status': 'error', 'message': str(e)}), file=sys.stdout)
        sys.exit(1)


if __name__ == '__main__':
    main()
