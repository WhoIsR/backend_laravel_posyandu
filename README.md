# Posyandu ML Backend

Repo ini berisi sisi server untuk MVP aplikasi Posyandu ML sesuai PRD:

- `backend/` Laravel REST API untuk auth, balita, sesi Posyandu, pengukuran, skrining, rujukan, validasi bidan, PMT, notifikasi, dan laporan PDF.
- `ml-api/` Flask service untuk prediksi model XGBoost melalui endpoint internal.
- `docs/design/` dokumen UI/UX Ledger Posyandu sebagai acuan implementasi mobile.

Flutter tidak ada di repo ini. Aplikasi mobile memanggil Laravel, dan Laravel memanggil Flask secara internal. Untuk VPS, gunakan `ML_API_URL=http://127.0.0.1:5000` agar Flask tidak perlu diekspos publik.

## Menjalankan Laravel

```powershell
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

Set variabel penting di `backend/.env`:

```env
APP_URL=http://localhost:8000
ML_API_URL=http://127.0.0.1:5000
```

## Menjalankan ML API

```powershell
cd ml-api
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
python app.py
```

Model default adalah `ml-api/stunting_model.json`. File `.pkl` tetap disimpan sebagai fallback sesuai kebutuhan demo.

## Test

```powershell
cd backend
php artisan test
```

```powershell
cd ml-api
python -m unittest discover -s tests
```

## Guardrail PRD

- Flutter tidak memanggil Flask langsung.
- Hasil ML hanya skrining awal: `Risiko rendah`, `Perlu perhatian`, atau `Perlu ditinjau bidan`.
- Kader tidak mengakses stok PMT, validasi medis, manajemen akun, atau laporan administratif bidan.
- Tidak ada fitur di luar PRD seperti offline mode, iOS, chat, OCR, peta statistik, atau integrasi sistem eksternal.
