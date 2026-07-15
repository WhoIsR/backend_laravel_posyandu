# VPS Deploy Prep: Ubuntu + Nginx

Target deploy ini untuk backend Laravel + Flask ML API di satu VPS Ubuntu. Aplikasi Flutter tetap hanya memanggil Laravel REST API. Flask hanya hidup di `127.0.0.1:5000` dan tidak diekspos publik.

## Target Server

- Host: `167.172.71.213`
- Web server: Nginx
- PHP: PHP-FPM 8.3 atau versi PHP 8.2+
- Database: MySQL/MariaDB
- Laravel path: `/var/www/posyandu-ml-backend/backend`
- Flask path: `/var/www/posyandu-ml-backend/ml-api`
- Laravel public URL sementara: `http://167.172.71.213/api`
- HTTPS disarankan setelah ada domain.

## Keamanan Kredensial

Password VPS tidak boleh disimpan di repo, script, atau `.env`. Karena password pernah dibagikan di chat, rotasi password atau pindah ke SSH key sangat disarankan setelah deploy.

## 1. Login dan Paket Server

```bash
ssh root@167.172.71.213
apt update && apt upgrade -y
apt install -y nginx mysql-server unzip git curl python3-venv python3-pip
apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip php8.3-bcmath
```

Install Composer jika belum ada:

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```

## 2. Ambil Repo Backend

```bash
mkdir -p /var/www
cd /var/www
git clone https://github.com/WhoIsR/backend_laravel_posyandu.git posyandu-ml-backend
chown -R www-data:www-data /var/www/posyandu-ml-backend
```

Jika repo private, gunakan deploy key atau clone dari sesi yang sudah punya akses GitHub.

## 3. Setup Laravel

```bash
cd /var/www/posyandu-ml-backend/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```env
APP_NAME="Posyandu ML"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://167.172.71.213

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=posyandu_ml
DB_USERNAME=posyandu_ml
DB_PASSWORD=<password-db-kuat>

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

ML_API_URL=http://127.0.0.1:5000
ML_API_TIMEOUT=5
```

Jangan commit `.env`.

## 4. Setup Database

```bash
mysql
```

```sql
CREATE DATABASE posyandu_ml CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'posyandu_ml'@'localhost' IDENTIFIED BY '<password-db-kuat>';
GRANT ALL PRIVILEGES ON posyandu_ml.* TO 'posyandu_ml'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Lanjut migrasi dan seed:

```bash
cd /var/www/posyandu-ml-backend/backend
php artisan migrate --seed --force
php artisan config:cache
php artisan route:cache
```

## 5. Setup Flask ML API Internal

```bash
cd /var/www/posyandu-ml-backend/ml-api
python3 -m venv .venv
.venv/bin/pip install --upgrade pip
.venv/bin/pip install -r requirements.txt
```

Pasang service:

```bash
cp /var/www/posyandu-ml-backend/deploy/systemd/posyandu-ml-flask.service.example /etc/systemd/system/posyandu-ml-flask.service
systemctl daemon-reload
systemctl enable --now posyandu-ml-flask
systemctl status posyandu-ml-flask --no-pager
curl http://127.0.0.1:5000/health
```

Response harus berisi `status=ok` dan `model_loaded=true`.
Template service memakai 1 Gunicorn worker agar model Random Forest tidak diload berkali-kali di VPS RAM kecil. Jika VPS sudah lebih besar, worker bisa dinaikkan setelah diuji ulang dengan `free -h` dan smoke test prediksi.

## 6. Setup Nginx

```bash
cp /var/www/posyandu-ml-backend/deploy/nginx/posyandu-ml.conf.example /etc/nginx/sites-available/posyandu-ml
ln -s /etc/nginx/sites-available/posyandu-ml /etc/nginx/sites-enabled/posyandu-ml
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
```

Pastikan permission Laravel:

```bash
chown -R www-data:www-data /var/www/posyandu-ml-backend
chmod -R ug+rwx /var/www/posyandu-ml-backend/backend/storage /var/www/posyandu-ml-backend/backend/bootstrap/cache
```

## 7. Queue Worker

Repo memakai `QUEUE_CONNECTION=database`. Untuk demo MVP, banyak aksi berjalan sinkron, tetapi queue worker tetap disiapkan agar notifikasi/job Laravel aman jika dipakai.

```bash
cp /var/www/posyandu-ml-backend/deploy/systemd/posyandu-ml-queue.service.example /etc/systemd/system/posyandu-ml-queue.service
systemctl daemon-reload
systemctl enable --now posyandu-ml-queue
systemctl status posyandu-ml-queue --no-pager
```

## 8. Smoke Test VPS

```bash
curl http://167.172.71.213/api/health
```

Jika endpoint health Laravel tidak tersedia, gunakan login demo:

```bash
curl -X POST http://167.172.71.213/api/login \
  -H "Content-Type: application/json" \
  -d '{"nik_nip":"3271010101010001","password":"password"}'
```

Akun demo:

- Kader: `3271010101010001` / `password`
- Bidan: `197801012006042001` / `password`

## 9. Mobile Build untuk VPS

Di repo mobile:

```powershell
flutter build apk --debug --dart-define=API_BASE_URL=http://167.172.71.213/api
```

Untuk rilis yang lebih aman, gunakan domain dan HTTPS:

```powershell
flutter build apk --debug --dart-define=API_BASE_URL=https://<domain>/api
```

## 10. HTTPS Setelah Ada Domain

Setelah domain mengarah ke VPS:

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d <domain>
nginx -t
systemctl reload nginx
```

Lalu ubah:

- `APP_URL=https://<domain>`
- Flutter `API_BASE_URL=https://<domain>/api`

## Guardrail PRD

- Laravel tetap satu-satunya API publik.
- Flask ML API hanya internal di `127.0.0.1:5000`.
- Hasil ML hanya skrining awal, bukan diagnosis.
- Kader tidak mendapat akses PMT stok, validasi medis, atau laporan bidan.
- Tidak menambah fitur di luar PRD.
