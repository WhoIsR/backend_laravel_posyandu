import os
import json
import pickle
from pathlib import Path

from flask import Flask, jsonify, request
import xgboost as xgb


DEFAULT_FEATURES = [
    "Umur (bulan)",
    "Jenis Kelamin Encoded",
    "Tinggi Badan (cm)",
    "Kelompok Usia",
    "TB per Bulan",
    "Penghasilan",
    "Jumlah Keluarga",
]

FEATURES = json.loads(Path(__file__).with_name("features.json").read_text()) if Path(__file__).with_name("features.json").exists() else DEFAULT_FEATURES

RISK_LABELS = {
    0: "rendah",
    1: "sedang",
    2: "tinggi",
}


def create_app(config=None):
    app = Flask(__name__)
    default_model = Path(__file__).with_name("stunting_model.json")
    if not default_model.exists():
        default_model = Path(__file__).with_name("stunting_model.pkl")

    app.config.update(
        MODEL_PATH=os.environ.get("MODEL_PATH", str(default_model)),
        MODEL_VERSION=os.environ.get("MODEL_VERSION", "xgboost_v1"),
    )
    if config:
        app.config.update(config)

    app.model = load_model(app.config["MODEL_PATH"])

    @app.get("/health")
    def health():
        return jsonify({
            "status": "ok",
            "model_loaded": app.model is not None,
            "model_version": app.config["MODEL_VERSION"],
        })

    @app.post("/predict")
    def predict():
        payload = request.get_json(silent=True) or {}
        features = payload.get("features") or {}
        missing = [name for name in FEATURES if name not in features]

        if missing:
            return jsonify({
                "message": "Fitur input belum lengkap.",
                "missing_features": missing,
            }), 422

        try:
            row = [[float(features[name]) for name in FEATURES]]
        except (TypeError, ValueError):
            return jsonify({"message": "Semua fitur harus berupa angka."}), 422

        predicted_class = int(app.model.predict(row)[0])
        probabilities = probabilities_for(app.model, row, predicted_class)

        return jsonify({
            "predicted_class": predicted_class,
            "risk_level": RISK_LABELS.get(predicted_class, "rendah"),
            "probability": probabilities,
            "model_version": app.config["MODEL_VERSION"],
        })

    return app


def load_model(path):
    model_path = Path(path)
    if not model_path.exists():
        raise FileNotFoundError(f"Model file not found: {model_path}")

    if model_path.suffix.lower() == ".json":
        model = xgb.XGBClassifier()
        model.load_model(model_path)
        return model

    with model_path.open("rb") as file:
        return pickle.load(file)


def probabilities_for(model, row, predicted_class):
    if hasattr(model, "predict_proba"):
        values = model.predict_proba(row)[0]
        return {
            "rendah": float(values[0]),
            "sedang": float(values[1]),
            "tinggi": float(values[2]),
        }

    return {
        "rendah": 1.0 if predicted_class == 0 else 0.0,
        "sedang": 1.0 if predicted_class == 1 else 0.0,
        "tinggi": 1.0 if predicted_class == 2 else 0.0,
    }


app = create_app()


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=int(os.environ.get("PORT", 5000)))
