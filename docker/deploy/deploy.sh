#!/usr/bin/env bash
# cPanel deployment: immutable code releases, shared secrets/storage, explicit rollback.
set -Eeuo pipefail
umask 027
root=${1:?Usage: deploy.sh /absolute/deploy/root RELEASE_ID [deploy|rollback]}
release_id=${2:?Missing release ID}
mode=${3:-deploy}
[[ "$root" =~ ^/[A-Za-z0-9/_-]+$ && "$root" != / ]] || exit 2
[[ "$release_id" =~ ^[a-f0-9]{40}-[0-9]+-[0-9]+$ ]] || exit 2
[[ "$mode" == deploy || "$mode" == rollback ]] || exit 2
[[ -f "$root/shared/deploy.conf" && -f "$root/shared/.env" && -f "$root/shared/socket.env" ]] || { echo 'Missing shared server configuration' >&2; exit 2; }
# This is an administrator-owned shell configuration, never supplied by CI.
# shellcheck source=/dev/null
source "$root/shared/deploy.conf"
: "${PHP_BIN:?}" "${DB_DATABASE:?}" "${APP_HEALTH_URL:?}" "${SOCKET_HEALTH_URL:?}"
[[ "$DB_DATABASE" =~ ^[A-Za-z0-9_]+$ && "$APP_HEALTH_URL" == https://* && "$SOCKET_HEALTH_URL" == https://* ]] || exit 2
exec 9>"$root/deploy.lock"
flock -n 9 || { echo 'Another deployment is running' >&2; exit 1; }
release="$root/releases/$release_id"
previous=$(readlink "$root/current" || true)
maintenance=false
trap 'result=$?; if (( result != 0 )); then echo "Deployment failed; inspect current release and backup. No database rollback was attempted." >&2; if [[ "$maintenance" == true ]]; then echo "Maintenance mode remains enabled for recovery." >&2; fi; fi' EXIT
if [[ "$mode" == deploy ]]; then
    [[ ! -e "$release" ]] || { echo 'Release already exists; use a new run or explicit rollback' >&2; exit 1; }
    (cd "$root/incoming/$release_id" && sha256sum --check release.tar.gz.sha256)
    mkdir -p "$release"
    tar -xzf "$root/incoming/$release_id/release.tar.gz" -C "$release" --no-same-owner
else
    [[ -f "$release/artisan" ]] || { echo 'Rollback release does not exist' >&2; exit 1; }
fi
mkdir -p "$root/shared/storage/app/public" "$root/shared/storage/app/private" "$root/shared/storage/framework/cache/data" "$root/shared/storage/framework/sessions" "$root/shared/storage/framework/views" "$root/shared/storage/logs" "$root/shared/socket-tmp" "$release/bootstrap/cache"
ln -sfn "$root/shared/.env" "$release/.env"
ln -sfn "$root/shared/socket.env" "$release/docker/socketio/.env"
ln -sfn "$root/shared/socket-tmp" "$release/docker/socketio/tmp"
if [[ -d "$release/storage" && ! -L "$release/storage" ]]; then rmdir "$release/storage"; fi
ln -sfn "$root/shared/storage" "$release/storage"
ln -sfn "$root/shared/storage/app/public" "$release/public/storage"
artisan() { "$PHP_BIN" "$release/artisan" "$@" --no-interaction; }
# Bootstrap before any downtime; Composer's platform check rejects unsupported PHP.
artisan config:cache
artisan route:cache
artisan event:cache
artisan app:readiness --configuration-only
# Cron entries must use these same locks. Wait for active jobs before migration.
exec 8>"$root/queue.lock"
flock -w 180 8
exec 7>"$root/scheduler.lock"
flock -w 180 7
artisan down --retry=60
maintenance=true
if [[ "$mode" == deploy ]]; then
    : "${MYSQLDUMP_BIN:=mysqldump}"
    [[ -f "$root/shared/mysql-backup.cnf" ]] || { echo 'Missing database backup credentials' >&2; exit 1; }
    mkdir -p "$root/backups"
    backup="$root/backups/$release_id.sql.gz"
    (umask 077; "$MYSQLDUMP_BIN" --defaults-extra-file="$root/shared/mysql-backup.cnf" --single-transaction --no-tablespaces --routines --triggers --set-gtid-purged=OFF "$DB_DATABASE" | gzip -c > "$backup.tmp")
    gzip -t "$backup.tmp"
    mv "$backup.tmp" "$backup"
    artisan migrate --force
fi
artisan app:readiness
ln -sfn "$release" "$root/current.next"
mv -Tf "$root/current.next" "$root/current"
# Passenger watches the shared tmp directory across code releases.
touch "$root/shared/socket-tmp/restart.txt"
artisan queue:restart
artisan up
maintenance=false
curl --fail --silent --show-error --retry 6 --retry-all-errors --retry-delay 5 --max-time 15 "$APP_HEALTH_URL" >/dev/null
curl --fail --silent --show-error --retry 6 --retry-all-errors --retry-delay 5 --max-time 15 "$SOCKET_HEALTH_URL" >/dev/null
if [[ -n "$previous" && "$previous" != "$release" ]]; then
    ln -sfn "$previous" "$root/previous.next"
    mv -Tf "$root/previous.next" "$root/previous"
fi
echo "Activated cPanel release $release_id ($mode)"
