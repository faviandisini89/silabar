# Gunakan image resmi PHP 8.2 dengan Apache
FROM php:8.2-apache

# Install dependencies sistem yang diperlukan Laravel (termasuk SQLite & PostgreSQL)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libsqlite3-dev \
    libpq-dev \
    zip \
    unzip \
    sqlite3 \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install ekstensi PHP (mendukung SQLite & PostgreSQL)
RUN docker-php-ext-install pdo pdo_sqlite pdo_pgsql mbstring exif pcntl bcmath gd

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

# Pastikan folder database dan file sqlite ada jika menggunakan sqlite
RUN mkdir -p /var/www/html/database && touch /var/www/html/database/database.sqlite

# Berikan izin akses folder storage, cache, dan database
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Expose port 80 (Render otomatis mengarahkan traffic ke sini)
EXPOSE 80

# Script startup: migrasi otomatis, link storage, cache, dan jalankan apache
CMD ["bash", "-c", "php artisan storage:link || true && php artisan migrate --force || true && php artisan config:cache && php artisan route:cache && php artisan view:cache && apache2-foreground"]
