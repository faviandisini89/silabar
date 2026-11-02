# Panduan Lengkap Deploy SILABAR (Laravel) di Render (Free Tier)

Panduan ini menjelaskan langkah demi langkah mendeploy aplikasi Laravel **SILABAR** ke **[Render.com](https://render.com)** menggunakan paket **Free Web Service**.

---

## 📌 Karakteristik & Batasan Free Tier Render

Sebelum mulai, pahami cara kerja paket gratis Render:
1. **Gratis 100%:** 512 MB RAM dan 0.1 vCPU.
2. **Auto-Sleep (Tidur Otomatis):** Jika tidak ada pengunjung selama 15 menit, server akan *standby/sleep*. Saat ada yang membuka web lagi, proses *wake up* membutuhkan waktu sekitar **30–50 detik** untuk loading pertama kali.
3. **Penyimpanan Ephemeral:** Di Web Service gratis, file yang diupload ke disk lokal akan reset saat server restart/redeploy. Untuk aplikasi produksi dengan upload file besar, disarankan menggunakan cloud storage (seperti Cloudinary / AWS S3 / Supabase Storage).

---

## 🛠️ Langkah 1: Siapkan File Dockerfile di Proyek

Render tidak menyediakan runtime PHP bawaan secara native, sehingga cara terbaik dan paling stabil untuk menjalankan Laravel di Render adalah menggunakan **Docker** (tetap masuk kuota Free Web Service).

Buat file bernama `Dockerfile` di folder utama (root) proyek `silabar`:

```dockerfile
# Gunakan image resmi PHP 8.2 dengan Apache
FROM php:8.2-apache

# Install dependencies sistem yang diperlukan Laravel
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libsqlite3-dev \
    zip \
    unzip \
    sqlite3 \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install ekstensi PHP
RUN docker-php-ext-install pdo pdo_sqlite mbstring exif pcntl bcmath gd

# Aktifkan mod_rewrite Apache untuk routing Laravel
RUN a2enmod rewrite

# Ubah DocumentRoot Apache ke folder public/ Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Ambil Composer versi terbaru
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Salin source code proyek
WORKDIR /var/www/html
COPY . .

# Install dependencies composer (produksi, tanpa dev packages)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Berikan izin akses folder storage dan cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 80 (Render otomatis mengarahkan traffic ke sini)
EXPOSE 80

# Script startup: migrasi, link storage, dan jalankan apache
CMD ["bash", "-c", "php artisan storage:link || true && php artisan config:cache && php artisan route:cache && php artisan view:cache && apache2-foreground"]
```

> **Tips:** Commit file `Dockerfile` ini ke repository GitHub Anda (`git add Dockerfile`, `git commit -m "Add Dockerfile for Render"`, `git push`).

---

## 🗄️ Langkah 2: Pilihan Database

Di Render, ada 2 opsi termudah untuk database:

### Opsi A: SQLite (Paling Cepat & Praktis)
Jika hanya untuk keperluan demo/tugas kampus, SQLite sudah langsung berjalan tanpa perlu setup server database tambahan.
- Pastikan file `database/database.sqlite` ada di repo atau ter-create saat build.
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=/var/www/html/database/database.sqlite`

### Opsi B: PostgreSQL Gratis (Cocok untuk Multi-User)
Render menyediakan database PostgreSQL gratis selama 90 hari, atau Anda bisa memakai **[Neon.tech](https://neon.tech)** / **[Supabase](https://supabase.com)** (Free Tier permanen):
1. Buat database di Render: Klik tombol **New +** $\to$ pilih **PostgreSQL**.
2. Masukkan nama DB (misal: `silabar_db`).
3. Setelah selesai, Render akan memberikan `Internal Database URL` atau kredensial host, database, user, dan password.

---

## 🚀 Langkah 3: Deploy di Dashboard Render

1. Buka [dashboard.render.com](https://dashboard.render.com) dan login menggunakan akun GitHub Anda.
2. Klik tombol **New +** di pojok kanan atas, lalu pilih **Web Service**.
3. Pilih opsi **Build and deploy from a Git repository**, lalu klik **Next**.
4. Cari dan pilih repository `silabar` Anda (klik **Connect**).
5. Isi konfigurasi dasar:
   * **Name:** `silabar` (atau nama pilihan Anda, ini akan jadi subdomain: `silabar.onrender.com`).
   * **Region:** Pilih **Singapore** (paling cepat dan latency rendah dari Indonesia).
   * **Branch:** `main` (atau branch tempat code Anda berada).
   * **Root Directory:** Kosongkan (biarkan default).
   * **Runtime:** Pilih **Docker**.
   * **Instance Type:** Pilih **Free** ($0 / month).

---

## 🔐 Langkah 4: Isi Environment Variables

Gulir ke bawah ke bagian **Environment Variables** (atau tab *Environment*), lalu tambahkan key-value berikut:

| Key | Value | Catatan |
|---|---|---|
| `APP_NAME` | `SILABAR` | Nama aplikasi |
| `APP_ENV` | `production` | Mode produksi |
| `APP_KEY` | `base64:...` | Ambil dari file `.env` lokal Anda atau generate via `php artisan key:generate --show` |
| `APP_DEBUG` | `false` | Matikan debug demi keamanan |
| `APP_URL` | `https://silabar.onrender.com` | Sesuaikan dengan domain yang diberikan Render |
| `LOG_CHANNEL` | `stderr` | Agar log error tampil di tab Logs Render |
| `DB_CONNECTION` | `sqlite` | Atau `pgsql` jika pakai PostgreSQL |
| `DB_DATABASE` | `/var/www/html/database/database.sqlite` | Jika pakai SQLite |

> **Jika Menggunakan PostgreSQL (Render / Neon):**
> Ganti konfigurasi DB dengan:
> * `DB_CONNECTION`: `pgsql`
> * `DB_HOST`: Host dari dashboard database
> * `DB_PORT`: `5432`
> * `DB_DATABASE`: Nama database
> * `DB_USERNAME`: Username database
> * `DB_PASSWORD`: Password database

---

## 🎬 Langkah 5: Jalankan Build & Verifikasi

1. Klik tombol **Create Web Service**.
2. Render akan otomatis mendownload image, menginstall composer dependencies, dan menjalankan Apache.
3. Anda dapat memantau proses instalasi melalui tab **Logs**.
4. Begitu status berubah menjadi **Live**, aplikasi sudah bisa diakses melalui URL:  
   `https://[nama-service].onrender.com`

---

## 💡 Tips & Trik Mengatasi Batasan Render Free

1. **Menghindari Cold-Boot (Server Tertidur):**
   Gunakan layanan gratis seperti **[cron-job.org](https://cron-job.org)** atau **[UptimeRobot](https://uptimerobot.com)** untuk melakukan *ping* ke URL aplikasi Anda setiap 10–14 menit sekali. Hal ini menjaga server tetap *awake* pada jam kerja/presentasi.
2. **Menjalankan Artisan Migrate / Seed Pertama Kali:**
   Buka tab **Shell** di dashboard Render untuk service Anda, lalu jalankan:
   ```bash
   php artisan migrate --force
   # Jika butuh data awal:
   php artisan db:seed --force
   ```
3. **Melihat Error Log:**
   Jika muncul halaman *500 Server Error*, buka tab **Logs** di Render untuk melihat pesan error spesifik secara realtime.

