#!/usr/bin/env bash
# iam registers a user, who logs in through the Node gateway; notifications mails a welcome from its
# copy of iam's users; analytics journals both events and computes its hourly datasets; the client
# reads its inbox through the gateway. Needs Redis on localhost and Node.
set -euo pipefail
cd "$(dirname "$0")"

export APP_KEY=base64:$(printf 'example-key-example-key-example!' | base64)
export MICROSERVICES_RPC_SECRET=example-secret GATEWAY_SECRET=gateway-secret
export DB_CONNECTION=sqlite CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=log REDIS_PREFIX= MICROSERVICES_STREAM_DRIVER=redis

for app in iam notifications analytics; do
    mkdir -p $app/storage/framework/{cache,sessions,views} $app/storage/logs $app/bootstrap/cache
    (cd $app && composer install --quiet --no-interaction && rm -f bootstrap/cache/*.php && php artisan package:discover --quiet && rm -f database/database.sqlite && touch database/database.sqlite && php artisan migrate --force --quiet)
done

redis-cli del microservices:events > /dev/null

pids=()
(cd iam && exec php -S 127.0.0.1:8001 -t public > /dev/null 2>&1) & pids+=($!)
(cd notifications && exec php -S 127.0.0.1:8002 -t public > /dev/null 2>&1) & pids+=($!)
(cd notifications && exec php artisan stream:consume > /dev/null) & pids+=($!)
(cd analytics && exec php artisan stream:consume > /dev/null) & pids+=($!)
(exec node gateway/index.mjs) & pids+=($!)
trap 'kill "${pids[@]}" 2> /dev/null' EXIT
sleep 2

(cd iam && php artisan users:register Ada ada@example.com correct-horse > /dev/null)
token=$(curl -s -X POST http://127.0.0.1:8000/iam/api/v1/login -H 'Content-Type: application/json' \
    -d '{"email":"ada@example.com","password":"correct-horse"}' | node -pe 'JSON.parse(require("fs").readFileSync(0)).token')
sleep 3

inbox=$(curl -s -H "Authorization: Bearer $token" http://127.0.0.1:8000/notifications/api/v1/notifications)
stats=$(cd analytics && php artisan analytics:compute > /dev/null && php artisan analytics:stats)
output="inbox: $inbox"$'\n'"$stats"
echo "$output"
[ "$output" = $'inbox: ["welcome"]\nsignups: 1, mails: 1' ]
[ "$(curl -s -o /dev/null -w '%{http_code}' -H 'Authorization: Bearer wrong' http://127.0.0.1:8000/notifications/api/v1/notifications)" = 401 ]
