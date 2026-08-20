# ------------------------------------------------------------------------------
# ToDo アプリ用の PHP-FPM + Nginx イメージ。
# PHP-FPM + Nginx image for the ToDo app.
# 単一コンテナ内で supervisor により nginx と php-fpm を起動する。
# A single container runs both nginx and php-fpm under supervisor.
# ------------------------------------------------------------------------------
FROM php:8.2-fpm-bookworm

# 必要な OS パッケージと PHP 拡張をインストールする。
# Install required OS packages and PHP extensions.
#  - pdo_mysql: MySQL 接続 / MySQL connectivity
#  - nginx / supervisor: Web サーバーとプロセス管理 / web server & process supervision
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        libzip-dev \
        unzip \
    && docker-php-ext-install pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer をイメージにコピーする（公式イメージから）。
# Copy Composer from the official image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 依存関係のインストールはソースコピーより先に行い、Docker のレイヤーキャッシュを活かす。
# Install dependencies before copying the full source to leverage Docker layer caching.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-dev --no-scripts --prefer-dist --no-progress

# アプリ本体をコピーする。
# Copy the application source.
COPY . .

# オートローダーを最適化する。
# Optimize the autoloader.
# ビルド時点では .env / APP_KEY が無いため、artisan を起動する composer スクリプトは実行しない（--no-scripts）。
# At build time there is no .env / APP_KEY yet, so skip composer scripts that boot artisan (--no-scripts).
# パッケージ discover は失敗しても続行できるよう `|| true` で保護する。
# Guard package discovery with `|| true` so the build proceeds even if it cannot boot.
RUN composer dump-autoload --optimize --no-scripts \
    && php artisan package:discover --ansi || true

# storage / bootstrap/cache に書き込み権限を与える。
# Grant write permissions on storage and bootstrap/cache.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Nginx / supervisor / エントリーポイントの設定を配置する。
# Place the nginx, supervisor and entrypoint configuration.
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

# 起動時: DB 待機 → マイグレーション → supervisor 起動。
# On start: wait for DB → migrate → launch supervisor.
ENTRYPOINT ["entrypoint.sh"]
