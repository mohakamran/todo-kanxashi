#!/usr/bin/env bash
# ------------------------------------------------------------------------------
# コンテナ起動時の初期化スクリプト。
# Container start-up initialization script.
#   1. .env とアプリキーを準備する
#      Prepare .env and the application key
#   2. 待ち受けポートを設定する（Railway 等の $PORT に対応）
#      Configure the listening port (supports $PORT on Railway and similar PaaS)
#   3. DB が応答するまでリトライしつつマイグレーション/シードを実行する
#      Run migrations/seeds, retrying until the database responds
#   4. supervisor（nginx + php-fpm）を起動する
#      Launch supervisor (nginx + php-fpm)
#
# ローカルの docker-compose でも Railway でも同じ手順で起動できるようにしている。
# The same sequence works for local docker-compose and for Railway.
# ------------------------------------------------------------------------------
set -e

cd /var/www/html

# ---------------------------------------------------------------------------
# 1) .env とアプリキーの準備。
# 1) Prepare .env and the application key.
# ---------------------------------------------------------------------------
if [ ! -f .env ]; then
    cp .env.example .env
fi

# APP_KEY が環境変数でも .env でも未設定の場合のみ生成する。
# Generate a key only when APP_KEY is absent from both the environment and .env.
# PaaS 側で APP_KEY を設定している場合に毎デプロイで鍵が変わるのを防ぐ。
# This prevents the key from changing on every deploy when the PaaS supplies APP_KEY.
if [ -z "${APP_KEY:-}" ] && ! grep -q "^APP_KEY=base64:" .env; then
    echo "APP_KEY not provided; generating a local one."
    php artisan key:generate --force
fi

# ---------------------------------------------------------------------------
# 2) 待ち受けポートの設定。
# 2) Configure the listening port.
# ---------------------------------------------------------------------------
# Railway などは $PORT を注入する。未指定ならローカル用に 80 を使う。
# Railway and similar platforms inject $PORT; fall back to 80 for local use.
LISTEN_PORT="${PORT:-80}"
echo "Configuring nginx to listen on port ${LISTEN_PORT}."
sed -i "s/__PORT__/${LISTEN_PORT}/" /etc/nginx/sites-available/default

# ---------------------------------------------------------------------------
# 3) マイグレーションとシード。
# 3) Migrations and seeding.
# ---------------------------------------------------------------------------
# DB の接続方法は環境により異なる（compose は DB_HOST、Railway は DB_URL）。
# Connection settings differ per environment (DB_HOST for compose, DB_URL on Railway),
# そのため TCP を直接見るのではなく、マイグレーション自体をリトライして待機する。
# so instead of probing TCP directly we simply retry the migration until it succeeds.
# シーダーは firstOrCreate のため冪等で、再実行しても重複しない。
# The seeder uses firstOrCreate, so it is idempotent and safe to re-run.
attempt=1
max_attempts=30
until php artisan migrate --force --seed; do
    if [ "${attempt}" -ge "${max_attempts}" ]; then
        echo "Database was not reachable after ${max_attempts} attempts; aborting."
        exit 1
    fi
    echo "  ...database not ready (attempt ${attempt}/${max_attempts}); retrying in 2s"
    attempt=$((attempt + 1))
    sleep 2
done
echo "Migrations complete."

# 設定・ルートのキャッシュ最適化（本番相当の挙動）。
# Cache config & routes for production-like behavior.
php artisan config:cache
php artisan route:cache

# 上記コマンドは root で実行されるため、実行時に php-fpm(www-data) が書き込めるよう
# storage / bootstrap/cache の所有権を戻しておく（ログ書き込み失敗の防止）。
# The commands above run as root, so hand ownership of storage / bootstrap/cache back to
# www-data (the php-fpm user) to keep runtime writes — such as logging — working.
chown -R www-data:www-data storage bootstrap/cache

# ---------------------------------------------------------------------------
# 4) supervisor を起動（フォアグラウンド）。
# 4) Start supervisor in the foreground.
# ---------------------------------------------------------------------------
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/app.conf
