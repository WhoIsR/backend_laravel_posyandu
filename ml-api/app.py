import os
import json
import pickle
from pathlib import Path

from flask import Flask, jsonify, request

try:
    import xgboost as xgb
except ImportError:
    xgb = None


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
            clean = {name: float(features[name]) for name in FEATURES}
        except (TypeError, ValueError):
            return jsonify({"message": "Semua fitur harus berupa angka."}), 422

        errors = validate_features(clean)
        if errors:
            return jsonify({"message": "Nilai fitur belum valid.", "errors": errors}), 422

        row = [[clean[name] for name in FEATURES]]
        predicted_class = int(app.model.predict(row)[0])
        probabilities = probabilities_for(app.model, row, predicted_class)

        return jsonify({
            "predicted_class": predicted_class,
            "risk_level": RISK_LABELS.get(predicted_class, "rendah"),
            "probability": probabilities,
            "model_version": app.config["MODEL_VERSION"],
        })

    return app


def validate_features(features):
    errors = {}
    if not 0 <= features["Umur (bulan)"] <= 60:
        errors["Umur (bulan)"] = "Harus berada pada rentang 0-60 bulan."
    if features["Jenis Kelamin Encoded"] not in (0.0, 1.0):
        errors["Jenis Kelamin Encoded"] = "Harus 0 atau 1."
    if not 40 <= features["Tinggi Badan (cm)"] <= 130:
        errors["Tinggi Badan (cm)"] = "Harus berada pada rentang 40-130 cm."
    if features["Kelompok Usia"] not in (0.0, 1.0, 2.0, 3.0, 4.0):
        errors["Kelompok Usia"] = "Harus berada pada rentang 0-4."
    if features["TB per Bulan"] <= 0:
        errors["TB per Bulan"] = "Harus lebih dari 0."
    if features["Penghasilan"] not in (1.0, 2.0, 3.0):
        errors["Penghasilan"] = "Harus 1, 2, atau 3 sesuai kategori training."
    if not 1 <= features["Jumlah Keluarga"] <= 20:
        errors["Jumlah Keluarga"] = "Harus berada pada rentang 1-20."
    return errors


def load_model(path):
    model_path = Path(path)
    if not model_path.exists():
        raise FileNotFoundError(f"Model file not found: {model_path}")

    if model_path.suffix.lower() == ".json":
        if xgb is None:
            raise RuntimeError("xgboost is required to load JSON model files.")
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
