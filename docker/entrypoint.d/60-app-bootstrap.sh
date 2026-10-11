#!/bin/sh
set -e

ROLE="${CONTAINER_ROLE:-}"

if [ -z "$ROLE" ]; then
    exit 0
fi

cd "${APP_BASE_DIR:-/var/www/html}"

KEY_DIR="storage/app/.secrets"
KEY_FILE="$KEY_DIR/app-key"

log() {
    echo "[bootstrap:$ROLE] $1"
}

if [ "${APP_ENV:-production}" = "local" ] && [ "$ROLE" = "app" ]; then
    if [ ! -f vendor/autoload.php ]; then
        log "Installing Composer dependencies"
        composer install --no-interaction --no-progress --prefer-dist
    fi
    if [ ! -d node_modules/.bin ]; then
        log "Installing npm dependencies"
        npm ci --no-audit --no-fund
    fi
fi

[ -f .env ] || cp .env.example .env

if [ -z "${APP_KEY:-}" ]; then
    mkdir -p "$KEY_DIR"
    if [ ! -s "$KEY_FILE" ]; then
        if [ "$ROLE" = "app" ]; then
            log "Generating application key"
            php artisan key:generate --show --no-ansi > "$KEY_FILE"
        else
            log "Waiting for the app container to generate the application key"
            attempts=0
            until [ -s "$KEY_FILE" ]; do
                attempts=$((attempts + 1))
                if [ "$attempts" -gt 60 ]; then
                    log "Application key not found after 120s"
                    exit 1
                fi
                sleep 2
            done
        fi
    fi
    key="$(cat "$KEY_FILE")"
    sed -i "s|^APP_KEY=.*|APP_KEY=${key}|" .env
fi

log "Waiting for the database at ${DB_HOST}:${DB_PORT:-3306}"
attempts=0
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306).";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { exit(1); }' 2>/dev/null; do
    attempts=$((attempts + 1))
    if [ "$attempts" -gt 60 ]; then
        log "Database not reachable after 120s"
        exit 1
    fi
    sleep 2
done

if [ "$ROLE" = "app" ]; then
    php artisan config:clear --no-ansi >/dev/null
    log "Running migrations"
    php artisan migrate --force --isolated --no-ansi
    log "Seeding demo accounts and the sample catalog (safe to repeat)"
    php artisan db:seed --force --no-ansi
    php artisan storage:link --no-ansi >/dev/null 2>&1 || true
fi

if [ "${APP_ENV:-production}" != "local" ]; then
    php artisan optimize --no-ansi >/dev/null
fi

if [ "$ROLE" = "app" ]; then
    cat <<BANNER

==============================================================
  Store is ready:   ${APP_URL:-http://localhost:8080}
  Admin:            admin@example.com / password
  Customer:         customer@example.com / password
==============================================================

BANNER
fi
