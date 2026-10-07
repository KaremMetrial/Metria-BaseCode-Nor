#!/usr/bin/env bash
set -Eeuo pipefail
repo=$(cd "$(dirname "$0")/../.." && pwd)
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
cd "$repo"
[[ -f public/build/manifest.json ]] || { echo 'Run npm ci and npm run build first' >&2; exit 1; }
# Allowlist application code; never archive the working tree wholesale.
tar --exclude=bootstrap/cache --exclude=public/storage --exclude=public/hot --exclude='*.sqlite*' --exclude='.env*' -cf - app bootstrap config database lang public resources routes artisan composer.json composer.lock | tar -xf - -C "$work"
mkdir -p "$work/bootstrap/cache" "$work/docker/socketio/public"
cp docker/socketio/{app.js,auth.cjs,env.cjs,socket.io.config.cjs,package.json,package-lock.json} "$work/docker/socketio/"
composer install --working-dir="$work" --no-dev --no-scripts --classmap-authoritative --prefer-dist --no-interaction --no-progress
npm --prefix "$work/docker/socketio" ci --omit=dev --ignore-scripts
mkdir -p "$repo/dist"
tar -czf "$repo/dist/release.tar.gz" -C "$work" .
(cd "$repo/dist" && sha256sum release.tar.gz > release.tar.gz.sha256)
echo 'Release packaged with production dependencies and built assets; secrets are server-owned.'
