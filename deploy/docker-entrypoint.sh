#!/bin/sh
set -eu

port="${PORT:-10000}"
sed -i "s/10000/${port}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --database=pgsql
fi

if [ "${LARAVEL_CONFIG_CACHE:-true}" = "true" ]; then
    php artisan config:cache
fi

if [ "${RUN_TRADINGVIEW_WORKER:-false}" != "true" ]; then
    exec "$@"
fi

set +e

worker_pid=""
apache_pid=""
shutdown_requested=""
exit_status=1
cleanup_done=""

forward_signal() {
    signal="$1"
    shutdown_requested="$signal"

    if [ -n "$worker_pid" ]; then
        kill "-$signal" "$worker_pid" 2>/dev/null
    fi

    if [ -n "$apache_pid" ]; then
        kill "-$signal" "$apache_pid" 2>/dev/null
    fi
}

cleanup_children() {
    if [ -n "$cleanup_done" ]; then
        return
    fi
    cleanup_done=1
    trap - TERM INT

    if [ -n "$worker_pid" ]; then
        kill -TERM "$worker_pid" 2>/dev/null
    fi
    if [ -n "$apache_pid" ]; then
        kill -TERM "$apache_pid" 2>/dev/null
    fi

    attempts=0
    while [ "$attempts" -lt 10 ]; do
        worker_done=0
        apache_done=0

        if ! kill -0 "$worker_pid" 2>/dev/null; then
            worker_done=1
        fi
        if ! kill -0 "$apache_pid" 2>/dev/null; then
            apache_done=1
        fi

        if [ "$worker_done" -eq 1 ] && [ "$apache_done" -eq 1 ]; then
            break
        fi

        attempts=$((attempts + 1))
        sleep 1
    done

    if [ "$worker_done" -eq 0 ]; then
        kill -KILL "$worker_pid" 2>/dev/null
    fi
    if [ "$apache_done" -eq 0 ]; then
        kill -KILL "$apache_pid" 2>/dev/null
    fi

    wait "$worker_pid" 2>/dev/null
    wait "$apache_pid" 2>/dev/null
}

trap 'forward_signal TERM' TERM
trap 'forward_signal INT' INT
trap 'cleanup_children' EXIT

php artisan tradingview:start &
worker_pid=$!

"$@" &
apache_pid=$!

while :; do
    if [ -n "$shutdown_requested" ]; then
        if [ "$shutdown_requested" = "INT" ]; then
            exit_status=130
        else
            exit_status=143
        fi
        break
    fi

    if ! kill -0 "$apache_pid" 2>/dev/null; then
        wait "$apache_pid" 2>/dev/null
        exit_status=$?
        kill -TERM "$worker_pid" 2>/dev/null
        break
    fi

    if ! kill -0 "$worker_pid" 2>/dev/null; then
        wait "$worker_pid" 2>/dev/null
        exit_status=$?
        if [ "$exit_status" -eq 0 ]; then
            exit_status=1
        fi
        kill -TERM "$apache_pid" 2>/dev/null
        break
    fi

    sleep 1
done

exit "$exit_status"
