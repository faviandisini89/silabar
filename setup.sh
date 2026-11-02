#!/usr/bin/env bash
# SILABAR installer: builds the Laravel skeleton around the SILABAR files, installs the Python service,
# migrates and seeds demo data. Safe to re-run. Needs: php >= 8.2, composer, python >= 3.10.
set -euo pipefail
cd "$(dirname "$0")"

need() { command -v "$1" >/dev/null 2>&1 || { echo "Perlu '$1' terpasang dulu."; exit 1; }; }
need php; need composer; need python3

php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || { echo "PHP 8.2 atau lebih baru diperlukan."; exit 1; }

# 1) Laravel skeleton. SILABAR files already in place are never overwritten (cp -n).
if [ ! -f artisan ]; then
  echo "==> Membuat kerangka Laravel (composer create-project)..."
  composer create-project laravel/laravel .silabar-skeleton --no-interaction --prefer-dist
  cp -rn .silabar-skeleton/. .
  rm -rf .silabar-skeleton
fi
rm -f tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php   # skeleton examples assume "/" returns 200
composer install --no-interaction --prefer-dist

# 2) register the SILABAR service provider (role middleware, policies, sidebar badges)
php -r '
$f = "bootstrap/providers.php"; $s = file_get_contents($f);
if (strpos($s, "SilabarServiceProvider") === false) {
    $s = preg_replace("/(AppServiceProvider::class,)/", "$1\n    App\\\\Providers\\\\SilabarServiceProvider::class,", $s, 1);
    file_put_contents($f, $s);
}'

# 3) environment
[ -f .env ] || cp .env.example .env
grep -q '^ANALYTICS_URL=' .env || cat env.additions >> .env
php artisan key:generate --force
mkdir -p database && touch database/database.sqlite

# 4) database + demo data (about 20 seconds)
php artisan migrate:fresh --seed --force

# 5) Python analytics service
echo "==> Memasang layanan analitik Python..."
python3 -m venv analytics/.venv
analytics/.venv/bin/pip install --quiet --upgrade pip
analytics/.venv/bin/pip install --quiet -r analytics/requirements.txt

cat <<'DONE'

Selesai. Jalankan tiga proses ini (masing-masing di terminal sendiri):

  1) php artisan serve                      # aplikasi di http://127.0.0.1:8000
  2) php artisan schedule:work              # jam: terlambat, pengingat, statistik, analisis mingguan
  3) cd analytics && TZ=Asia/Jakarta \
       DATABASE_URL=sqlite:///$(cd .. && pwd)/database/database.sqlite \
       ANALYTICS_TOKEN=ganti-dengan-token-rahasia \
       .venv/bin/uvicorn silabar_analytics.api:app --port 8001

Lalu jalankan analisis pertama:  php artisan analytics:run
Akun demo (kata sandi silabar123): rina@mhs.silabar.id, agus@silabar.id, admin@silabar.id
DONE
