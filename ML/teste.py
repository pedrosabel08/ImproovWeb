import os
import sys
from urllib.parse import quote_plus
from dataclasses import dataclass
try:
    from dotenv import load_dotenv
except Exception:
    load_dotenv = None

import pandas as pd
from sqlalchemy import create_engine


DATASET_SQL = r"""
WITH ordered AS (
    SELECT
        hi.imagem_id,
        sm.nome_status AS etapa,
        ssm.nome_substatus AS status,
        hi.status_id AS etapa_id,
        hi.substatus_id AS status_id,
        hi.data_movimento,
        LAG(hi.status_id) OVER (PARTITION BY hi.imagem_id ORDER BY hi.data_movimento) AS etapa_prev
    FROM historico_imagens hi
    LEFT JOIN status_imagem sm ON sm.idstatus = hi.status_id
    LEFT JOIN substatus_imagem ssm ON ssm.id = hi.substatus_id
),
runs AS (
    SELECT
        o.*,
        SUM(CASE WHEN o.etapa_prev IS NULL OR o.etapa_prev <> o.etapa_id THEN 1 ELSE 0 END) OVER (PARTITION BY o.imagem_id ORDER BY o.data_movimento) AS etapa_seq
    FROM ordered o
),
run_features AS (
    SELECT
        r.*,
        MIN(r.data_movimento) OVER (PARTITION BY r.imagem_id, r.etapa_seq) AS inicio_etapa,
        CASE
            WHEN r.etapa = 'P00' THEN 'DRV'
            WHEN r.etapa IN ('R00', 'R01', 'R02') THEN 'RVW'
            ELSE NULL
        END AS status_final_esperado,
        MIN(
            CASE
                WHEN (
                    (r.etapa = 'P00' AND r.status = 'DRV')
                    OR (r.etapa IN ('R00', 'R01', 'R02') AND r.status = 'RVW')
                ) THEN r.data_movimento
                ELSE NULL
            END
        ) OVER (PARTITION BY r.imagem_id, r.etapa_seq) AS fim_etapa
    FROM runs r
),
snapshots AS (
    SELECT
        rf.imagem_id,
        rf.etapa,
        rf.status,
        rf.inicio_etapa,
        rf.fim_etapa,
        rf.status_final_esperado,
        rf.data_movimento,
        ROW_NUMBER() OVER (PARTITION BY rf.imagem_id, rf.etapa_seq ORDER BY rf.data_movimento) AS transicoes
    FROM run_features rf
    WHERE rf.fim_etapa IS NOT NULL
      AND rf.status_final_esperado IS NOT NULL
      AND rf.data_movimento < rf.fim_etapa
)
SELECT
    s.imagem_id,
    s.etapa,
    s.status,
    TIMESTAMPDIFF(MINUTE, s.inicio_etapa, s.data_movimento) / 60.0 AS horas_desde_inicio,
    s.transicoes,
    TIMESTAMPDIFF(MINUTE, s.data_movimento, s.fim_etapa) / 60.0 AS horas_restantes
FROM snapshots s
ORDER BY s.imagem_id, s.data_movimento;
"""


@dataclass(frozen=True)
class TrainResult:
    mae_baseline: float
    mae_rf: float
    n_train: int
    n_test: int
    n_images_train: int
    n_images_test: int


def _get_db_url() -> str:
    # ensure .env is loaded if available (project root)
    if load_dotenv is not None:
        project_root = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
        dotenv_path = os.path.join(project_root, ".env")
        if os.path.exists(dotenv_path):
            try:
                load_dotenv(dotenv_path, override=False)
            except Exception:
                pass

    url = (
        os.getenv("IMPROOV_MYSQL_URL")
        or os.getenv("DATABASE_URL")
        or os.getenv("MYSQL_URL")
    )
    if url:
        return url

    host = os.getenv("DB_HOST")
    user = os.getenv("DB_USERNAME")
    password = os.getenv("DB_PASSWORD")
    database = os.getenv("DB_DATABASE")
    port = os.getenv("DB_PORT") or "3306"

    if host and user and password is not None and database:
        user_enc = quote_plus(user)
        pass_enc = quote_plus(password)
        db_enc = quote_plus(database)
        host_enc = host.strip()
        port_enc = port.strip()
        return f"mysql+mysqlconnector://{user_enc}:{pass_enc}@{host_enc}:{port_enc}/{db_enc}"

    raise RuntimeError(
        "Defina a conexão MySQL em IMPROOV_MYSQL_URL (ou DATABASE_URL/MYSQL_URL) "
        "ou então DB_HOST/DB_USERNAME/DB_PASSWORD/DB_DATABASE (opcional DB_PORT). "
        "Ex.: mysql+mysqlconnector://user:senha@localhost:3306/banco"
    )


def load_dataset(engine) -> pd.DataFrame:
    df = pd.read_sql_query(DATASET_SQL, engine)
    df["etapa"] = df["etapa"].astype(str)
    df["status"] = df["status"].astype(str)
    df["horas_desde_inicio"] = pd.to_numeric(df["horas_desde_inicio"], errors="coerce")
    df["transicoes"] = pd.to_numeric(df["transicoes"], errors="coerce")
    df["horas_restantes"] = pd.to_numeric(df["horas_restantes"], errors="coerce")
    df = df.dropna(subset=["imagem_id", "etapa", "status", "horas_desde_inicio", "transicoes", "horas_restantes"])
    return df


def _baseline_predict_median_by_stage_status(train_df: pd.DataFrame, test_df: pd.DataFrame) -> pd.Series:
    overall = float(train_df["horas_restantes"].median())
    med = (
        train_df.groupby(["etapa", "status"], dropna=False)["horas_restantes"]
        .median()
        .to_dict()
    )

    def predict_row(row) -> float:
        return float(med.get((row["etapa"], row["status"]), overall))

    return test_df.apply(predict_row, axis=1)


def train_and_evaluate(df: pd.DataFrame, random_state: int = 42) -> TrainResult:
    from sklearn.compose import ColumnTransformer
    from sklearn.ensemble import RandomForestRegressor
    from sklearn.metrics import mean_absolute_error
    from sklearn.model_selection import GroupShuffleSplit
    from sklearn.pipeline import Pipeline
    from sklearn.preprocessing import OneHotEncoder

    features = ["etapa", "status", "horas_desde_inicio", "transicoes"]
    target = "horas_restantes"

    X = df[features]
    y = df[target]
    groups = df["imagem_id"]

    splitter = GroupShuffleSplit(n_splits=1, test_size=0.2, random_state=random_state)
    (train_idx, test_idx) = next(splitter.split(X, y, groups=groups))
    X_train, X_test = X.iloc[train_idx], X.iloc[test_idx]
    y_train, y_test = y.iloc[train_idx], y.iloc[test_idx]
    groups_train = groups.iloc[train_idx]
    groups_test = groups.iloc[test_idx]

    train_df = df.iloc[train_idx]
    test_df = df.iloc[test_idx]

    y_pred_baseline = _baseline_predict_median_by_stage_status(train_df, test_df)
    mae_baseline = float(mean_absolute_error(y_test, y_pred_baseline))

    pre = ColumnTransformer(
        transformers=[
            ("cat", OneHotEncoder(handle_unknown="ignore"), ["etapa", "status"]),
            ("num", "passthrough", ["horas_desde_inicio", "transicoes"]),
        ]
    )

    model = RandomForestRegressor(
        n_estimators=400,
        random_state=random_state,
        n_jobs=-1,
        min_samples_leaf=2,
    )

    pipe = Pipeline([("pre", pre), ("regressor", model)])
    pipe.fit(X_train, y_train)
    y_pred = pipe.predict(X_test)
    mae_rf = float(mean_absolute_error(y_test, y_pred))

    # Importâncias (top 20) para dar explicabilidade inicial
    try:
        feature_names = pipe.named_steps["pre"].get_feature_names_out()
        importances = pipe.named_steps["regressor"].feature_importances_
        imp_df = (
            pd.DataFrame({"feature": feature_names, "importance": importances})
            .sort_values("importance", ascending=False)
            .head(20)
        )
        print("\nTop 20 features por importância (RF):")
        print(imp_df.to_string(index=False))
    except Exception:
        pass

    return TrainResult(
        mae_baseline=mae_baseline,
        mae_rf=mae_rf,
        n_train=int(len(X_train)),
        n_test=int(len(X_test)),
        n_images_train=int(groups_train.nunique()),
        n_images_test=int(groups_test.nunique()),
    )


def train_per_stage(df: pd.DataFrame, min_images: int = 30, random_state: int = 42):
    stages = sorted(df["etapa"].unique())
    results = []
    for etapa in stages:
        sub = df[df["etapa"] == etapa].copy()
        n_images = sub["imagem_id"].nunique()
        n_rows = len(sub)
        if n_images < min_images:
            print(f"Pulando etapa {etapa}: apenas {n_images} imagens (<{min_images})")
            continue
        print(f"\nTreinando para etapa {etapa}: {n_rows} snapshots | {n_images} imagens")
        try:
            res = train_and_evaluate(sub, random_state=random_state)
            results.append((etapa, n_rows, n_images, res))
            print(f"{etapa}: MAE baseline={res.mae_baseline:.2f}h | MAE RF={res.mae_rf:.2f}h")
        except Exception as exc:
            print(f"Erro ao treinar etapa {etapa}: {exc}")
    return results


def main() -> int:
    import argparse

    parser = argparse.ArgumentParser(
        description="Gera dataset de snapshots por imagem/etapa e treina baseline + RF para prever horas_restantes."
    )
    parser.add_argument(
        "--db-url",
        default=None,
        help="Override do MySQL URL. Se omitido, usa IMPROOV_MYSQL_URL/DATABASE_URL/MYSQL_URL.",
    )
    parser.add_argument("--random-state", type=int, default=42)
    parser.add_argument("--per-stage", action="store_true", help="Treina modelos separadamente por etapa (etapa=status_imagem.nome_status)")
    parser.add_argument("--min-images", type=int, default=30, help="Número mínimo de imagens por etapa para treinar")
    args = parser.parse_args()

    db_url = args.db_url or _get_db_url()
    engine = create_engine(db_url, pool_pre_ping=True)

    print("Carregando dataset do historico_imagens...")
    df = load_dataset(engine)
    print(f"Linhas (snapshots): {len(df):,} | Imagens: {df['imagem_id'].nunique():,}")

    if df.empty:
        print("Dataset vazio. Verifique se existem imagens com etapas P00/R00/R01/R02 concluídas (DRV/RVW).")
        return 2

    if args.per_stage:
        print("Treinando por etapa (split por imagem_id dentro de cada etapa)...")
        results = train_per_stage(df, min_images=args.min_images, random_state=args.random_state)
        print("\nResumo por etapa:")
        for etapa, n_rows, n_images, res in results:
            print(f"- {etapa}: rows={n_rows:,} images={n_images:,} | MAE baseline={res.mae_baseline:.2f}h | MAE RF={res.mae_rf:.2f}h")
    else:
        print("Treinando e avaliando (split por imagem_id)...")
        res = train_and_evaluate(df, random_state=args.random_state)

        print("\nResultado:")
        print(
            " | ".join(
                [
                    f"MAE baseline={res.mae_baseline:.2f}h",
                    f"MAE RF={res.mae_rf:.2f}h",
                    f"train={res.n_train:,} ({res.n_images_train:,} imagens)",
                    f"test={res.n_test:,} ({res.n_images_test:,} imagens)",
                ]
            )
        )
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(f"Erro: {exc}", file=sys.stderr)
        raise
