#!/usr/bin/env bash
# The same user journey against one setup: docker-compose.mono.yml (together) or docker-compose.yml (apart).
set -euo pipefail

compose_file="${1:?usage: tests/e2e/together-or-apart.sh docker-compose.mono.yml|docker-compose.yml}"
cd "$(dirname "$0")/../.."
compose=(docker compose -f "$compose_file")

if [[ "$compose_file" == *mono* ]]; then
    iam="http://localhost:${APP_PORT:-8000}/iam/api/v1"
    analytics="http://localhost:${APP_PORT:-8000}/analytics/api/v1"
    notifications="http://localhost:${APP_PORT:-8000}/notifications/api/v1"
else
    iam="http://localhost:${IAM_PORT:-8001}/iam/api/v1"
    analytics="http://localhost:${ANALYTICS_PORT:-8002}/analytics/api/v1"
    notifications="http://localhost:${NOTIFICATIONS_PORT:-8003}/notifications/api/v1"
fi

fail() { echo "FAIL: $*" >&2; exit 1; }

trap '"${compose[@]}" down -v >/dev/null 2>&1' EXIT
"${compose[@]}" up -d --build --wait

email="ana.$(date +%s)@example.test"
registered=$(curl -sf -X POST "$iam/users" -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -d "{\"name\":\"Ana\",\"email\":\"$email\",\"password\":\"secret-password\"}") || fail "iam refused the registration"
id=$(jq -r '.data.id' <<<"$registered")
token=$(jq -r '.data.token' <<<"$registered")
echo "registered user $id in iam"

get() { curl -sf -H 'Accept: application/json' -H "Authorization: Bearer $token" "$1"; }

# Events are asynchronous: each check retries for up to 30 seconds.
eventually() {
    local what="$1"; shift
    for _ in $(seq 30); do
        if "$@"; then echo "ok: $what"; return 0; fi
        sleep 1
    done
    fail "$what"
}

eventually "analytics recorded the signup, with the user copied locally" \
    bash -c "$(declare -f get); token='$token'; get '$analytics/signups?user_id=$id' | jq -e '.data[0].user.name == \"Ana\"' >/dev/null"
eventually "analytics reads the user from iam over RPC" \
    bash -c "$(declare -f get); token='$token'; get '$analytics/users/$id' | jq -e '.data.id == $id' >/dev/null"
eventually "notifications created the welcome notification" \
    bash -c "$(declare -f get); token='$token'; get '$notifications/notifications' | jq -e '.data | length == 1' >/dev/null"

echo "PASS: $compose_file"
