#!/usr/bin/env bash
# Exercises production images against disposable data. Never uses the checkout .env.
set -Eeuo pipefail
umask 077
project="metrial-smoke-${GITHUB_RUN_ID:-$$}"
export DEPLOY_PROJECT="$project"
work=$(mktemp -d)
repo=$(cd "$(dirname "$0")/../.." && pwd)
export RUNTIME_ENV_FILE="$work/runtime.env"
export APP_IMAGE=${APP_IMAGE:-metrial-ci-app:local}
export WEB_IMAGE=${WEB_IMAGE:-metrial-ci-web:local}
export SOCKET_IMAGE=${SOCKET_IMAGE:-metrial-ci-socket:local}
compose() { docker compose --env-file "$work/runtime.env" --env-file "$work/images.env" -f "$repo/docker/production/compose.yml" -f "$work/ports.yml" "$@"; }
cleanup() {
    status=$?
    if (( status != 0 )); then
        compose logs --tail=80 app web socketio >&2 || true
        socket_id=$(compose ps -q socketio)
        if [[ -n "$socket_id" ]]; then docker inspect --format '{{json .State.Health}}' "$socket_id" >&2 || true; fi
    fi
    compose down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf "$work"
    exit "$status"
}
trap cleanup EXIT
secret=$(openssl rand -hex 32)
cat > "$work/runtime.env" <<ENV
DEPLOY_PROJECT=$project
APP_NAME=Metrial
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:$(openssl rand -base64 32)
APP_URL=https://smoke.invalid
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=metrial_smoke
DB_USERNAME=metrial
DB_PASSWORD=$secret
DB_ROOT_PASSWORD=${secret}root
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=$secret
REDIS_PREFIX=${project}_
REDIS_CLIENT=predis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_CONNECTION=default
SOCKET_TOKEN_SECRET=$secret
CORS_ORIGIN=https://smoke.invalid
PAYMENT_PROVIDER=stripe
STRIPE_SECRET=smoke-only-not-a-real-key
STRIPE_WEBHOOK_SECRET=smoke-only-not-a-real-secret
STRIPE_LIVEMODE=true
SMS_PROVIDER=twilio
TWILIO_ACCOUNT_SID=smoke-only
TWILIO_AUTH_TOKEN=smoke-only
TWILIO_FROM=+15005550006
ENV
printf 'APP_IMAGE=%s\nWEB_IMAGE=%s\nSOCKET_IMAGE=%s\n' "$APP_IMAGE" "$WEB_IMAGE" "$SOCKET_IMAGE" > "$work/images.env"
cat > "$work/ports.yml" <<'YAML'
services:
  web:
    ports: !override
      - '127.0.0.1::8080'
YAML
compose up -d --wait --wait-timeout 180 db redis
compose run --rm --no-deps app php artisan migrate --force --no-interaction
compose up -d --wait --wait-timeout 180 app socketio web queue scheduler
compose exec -T app php artisan app:readiness
endpoint=$(compose port web 8080)
curl --fail --silent --show-error "http://$endpoint/up" >/dev/null
curl --fail --silent --show-error "http://$endpoint/api/v1/categories" >/dev/null
for path in /.env /composer.json /test.php; do
    status=$(curl --silent --output /dev/null --write-out '%{http_code}' "http://$endpoint$path")
    [[ "$status" == 403 || "$status" == 404 ]] || { echo "Unexpected exposure: $path ($status)" >&2; exit 1; }
done
[[ $(compose exec -T app id -u) != 0 ]] || { echo 'App runs as root' >&2; exit 1; }
compose exec -T app sh -c 'test ! -f .env && test ! -d vendor/phpunit && test ! -d node_modules'
echo 'Production image smoke test passed; provider credentials were inert test fixtures.'
