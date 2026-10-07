#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
mode=${1:-deploy}
: "${DEPLOY_HOST:?}" "${DEPLOY_USER:?}" "${DEPLOY_PATH:?}" "${SSH_PRIVATE_KEY:?}" "${SSH_KNOWN_HOSTS:?}" "${RELEASE_ID:?}"
DEPLOY_PORT=${DEPLOY_PORT:-22}
DEPLOY_ENVIRONMENT=${DEPLOY_ENVIRONMENT:-production}
[[ "$mode" == deploy || "$mode" == rollback ]] || exit 2
[[ "$DEPLOY_ENVIRONMENT" == staging || "$DEPLOY_ENVIRONMENT" == production ]] || exit 2
[[ "$DEPLOY_HOST" =~ ^[A-Za-z0-9][A-Za-z0-9.-]*$ && "$DEPLOY_USER" =~ ^[A-Za-z0-9_][A-Za-z0-9_-]*$ ]] || exit 2
[[ "$DEPLOY_PATH" =~ ^/[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*$ && "$DEPLOY_PATH" != / && "$DEPLOY_PORT" =~ ^[0-9]+$ ]] || exit 2
[[ "$RELEASE_ID" =~ ^[a-f0-9]{40}-[0-9]+-[0-9]+$ ]] || exit 2
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
printf '%s\n' "$SSH_PRIVATE_KEY" > "$work/key"
printf '%s\n' "$SSH_KNOWN_HOSTS" > "$work/known_hosts"
options=(-i "$work/key" -o "UserKnownHostsFile=$work/known_hosts" -o StrictHostKeyChecking=yes -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=15)
target="$DEPLOY_USER@$DEPLOY_HOST"
if [[ "$mode" == deploy ]]; then
    ssh "${options[@]}" -p "$DEPLOY_PORT" "$target" "mkdir -p '$DEPLOY_PATH/incoming/$RELEASE_ID'"
    scp "${options[@]}" -P "$DEPLOY_PORT" dist/release.tar.gz dist/release.tar.gz.sha256 docker/deploy/deploy.sh "$target:$DEPLOY_PATH/incoming/$RELEASE_ID/"
    ssh "${options[@]}" -p "$DEPLOY_PORT" "$target" "bash '$DEPLOY_PATH/incoming/$RELEASE_ID/deploy.sh' '$DEPLOY_PATH' '$RELEASE_ID' deploy '$DEPLOY_ENVIRONMENT'"
else
    ssh "${options[@]}" -p "$DEPLOY_PORT" "$target" "bash '$DEPLOY_PATH/incoming/$RELEASE_ID/deploy.sh' '$DEPLOY_PATH' '$RELEASE_ID' rollback '$DEPLOY_ENVIRONMENT'"
fi
