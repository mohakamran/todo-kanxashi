#!/usr/bin/env bash
# ------------------------------------------------------------------------------
# コンテナ起動時の初期化スクリプト。
# Container start-up initialization script.
#   1. .env が無ければ生成し、アプリキーを作成する
#      Create .env and the app key if missing
#   2. MySQL が接続可能になるまで待機する
#      Wait until MySQL is reachable
#   3. マイグレーションとシードを実行する
#      Run migrations and seeders
#   4. supervisor（nginx + php-fpm）を起動する
#      Launch supervisor (nginx + php-fpm)
# ------------------------------------------------------------------------------
set -e

cd /var/www/html

# 1) .env とアプリキーの準備。
# 1) Prepare .env and the application key.
if [ ! -f .env ]; then
    cp .env.example .env
fi
if ! grep -q "^APP_KEY=base64:" .env; then
    php artisan key:generate --force
fi

# 2) DB の待機（TCP 接続が通るまでリトライ）。
# 2) Wait for the DB (retry until the TCP port accepts connections).
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
echo "Waiting for database at ${DB_HOST}:${DB_PORT} ..."
until bash -c "cat < /dev/null > /dev/tcp/${DB_HOST}/${DB_PORT}" 2>/dev/null; do
    sleep 2
    echo "  ...still waiting for ${DB_HOST}:${DB_PORT}"
done
echo "Database is up."

# 3) マイグレーション + シード（初回のみ実データを投入）。
# 3) Migrate + seed (populates sample data on first run).
php artisan migrate --force --seed

# 設定・ルートのキャッシュ最適化（本番相当の挙動）。
# Cache config & routes for production-like behavior.
php artisan config:cache
php artisan route:cache

# 4) supervisor を起動（フォアグラウンド）。
# 4) Start supervisor in the foreground.
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/app.conf
