import os
import pickle
import tempfile
import unittest


class DummyModel:
    def predict(self, rows):
        return [1]

    def predict_proba(self, rows):
        return [[0.18, 0.62, 0.20]]


class MlApiTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.NamedTemporaryFile(delete=False, suffix=".pkl")
        pickle.dump(DummyModel(), self.tmp)
        self.tmp.close()
        os.environ["MODEL_PATH"] = self.tmp.name
        os.environ["MODEL_VERSION"] = "random_forest_v1"

        from app import create_app

        self.app = create_app({"TESTING": True})
        self.client = self.app.test_client()

    def tearDown(self):
        os.unlink(self.tmp.name)

    def test_health_returns_ok(self):
        response = self.client.get("/health")

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.get_json()["status"], "ok")

    def test_predict_returns_prd_response_shape(self):
        response = self.client.post("/predict", json={
            "features": {
                "Umur (bulan)": 31,
                "Jenis Kelamin Encoded": 1,
                "Tinggi Badan (cm)": 87.5,
                "Kelompok Usia": 3,
                "TB per Bulan": 2.73,
                "Penghasilan": 1,
                "Jumlah Keluarga": 4,
            }
        })

        self.assertEqual(response.status_code, 200)
        payload = response.get_json()
        self.assertEqual(payload["predicted_class"], 1)
        self.assertEqual(payload["risk_level"], "sedang")
        self.assertEqual(payload["model_version"], "random_forest_v1")
        self.assertEqual(set(payload["probability"].keys()), {"rendah", "sedang", "tinggi"})

    def test_predict_rejects_missing_feature(self):
        response = self.client.post("/predict", json={
            "features": {
                "Umur (bulan)": 31,
            }
        })

        self.assertEqual(response.status_code, 422)
        self.assertIn("Tinggi Badan (cm)", response.get_json()["missing_features"])

    def test_predict_rejects_unscaled_income(self):
        response = self.client.post("/predict", json={
            "features": {
                "Umur (bulan)": 31,
                "Jenis Kelamin Encoded": 1,
                "Tinggi Badan (cm)": 87.5,
                "Kelompok Usia": 3,
                "TB per Bulan": 2.73,
                "Penghasilan": 2500000,
                "Jumlah Keluarga": 4,
            }
        })

        self.assertEqual(response.status_code, 422)
        self.assertIn("Penghasilan", response.get_json()["errors"])

    def test_runtime_rejects_legacy_xgboost_artifact(self):
        from app import load_model

        legacy_model = os.path.join(os.path.dirname(__file__), "..", "stunting_model.json")
        with self.assertRaisesRegex(ValueError, "Random Forest"):
            load_model(legacy_model)


if __name__ == "__main__":
    unittest.main()
