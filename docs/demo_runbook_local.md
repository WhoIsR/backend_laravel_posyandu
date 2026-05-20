# Demo Runbook Local Backend Posyandu ML

Dokumen ini dipakai untuk menjalankan demo lokal backend Laravel, database MySQL/MariaDB, dan Flask ML API.

## Urutan Start Service

1. Jalankan MySQL/MariaDB lokal, misalnya dari XAMPP.
2. Jalankan Flask ML API di port `5000`.
3. Jalankan Laravel API di port `8000`.
4. Jalankan atau install aplikasi Flutter Android dengan base URL `http://10.0.2.2:8000/api`.

## Setup Database

Pastikan database `posyandu_ml` sudah ada.

```powershell
cd backend
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
```

Nilai penting di `backend\.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=posyandu_ml
DB_USERNAME=root
DB_PASSWORD=
ML_API_URL=http://127.0.0.1:5000
```

## Jalankan Flask ML API

```powershell
cd ml-api
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
.\.venv\Scripts\python.exe app.py
```

Cek health:

```powershell
Invoke-RestMethod http://127.0.0.1:5000/health
```

Response demo yang diharapkan memiliki `status=ok` dan `model_loaded=True`.

## Jalankan Laravel API

```powershell
cd backend
composer install
php artisan serve --host=0.0.0.0 --port=8000
```

## Akun Demo

- Kader: `3271010101010001` / `password`
- Bidan: `197801012006042001` / `password`

## Smoke Test API

Login Kader:

```powershell
Invoke-RestMethod -Method Post http://127.0.0.1:8000/api/login `
  -ContentType "application/json" `
  -Body '{"nik_nip":"3271010101010001","password":"password"}'
```

Login Bidan:

```powershell
Invoke-RestMethod -Method Post http://127.0.0.1:8000/api/login `
  -ContentType "application/json" `
  -Body '{"nik_nip":"197801012006042001","password":"password"}'
```

## Verifikasi Wajib Sebelum Demo

```powershell
cd backend
php artisan test
```

```powershell
cd ml-api
.\.venv\Scripts\python.exe -m unittest discover -s tests
```

## Guardrail PRD

- Laravel adalah satu-satunya API publik untuk aplikasi mobile.
- Flask ML API hanya dipanggil internal oleh Laravel melalui `ML_API_URL`.
- Hasil ML adalah skrining awal, bukan diagnosis medis.
- Tidak ada fitur di luar PRD seperti offline mode, OCR, chat, peta statistik, iOS, atau integrasi eksternal.
