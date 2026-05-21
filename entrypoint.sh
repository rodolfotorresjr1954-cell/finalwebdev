#!/bin/bash
set -e

# Run production migrations automatically — capture stderr so failures are visible
echo "Running database migrations..."
MIGRATION_OUTPUT=$(php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>&1) || {
    echo "ERROR: Database migrations failed (exit code $?):"
    echo "$MIGRATION_OUTPUT"
    exit 1
}
echo "$MIGRATION_OUTPUT"

echo "Starting PHP-FPM..."
# Pipe PHP-FPM's stdout and stderr directly to the container's stdout (fd 1)
# so every fatal error, warning, and startup message appears in Railway logs.
php-fpm -F > /proc/1/fd/1 2>&1 &
PHP_PID=$!

# When this script exits for any reason, kill PHP-FPM so the container fully stops
cleanup() {
    echo "Shutting down PHP-FPM (pid $PHP_PID)..."
    kill "$PHP_PID" 2>/dev/null || true
    wait "$PHP_PID" 2>/dev/null || true
}
trap cleanup EXIT

# Wait for PHP-FPM to bind to port 9000 (up to 10 s) instead of a blind sleep
echo "Waiting for PHP-FPM to become ready..."
for i in $(seq 1 10); do
    if timeout 1 bash -c 'echo > /dev/tcp/127.0.0.1/9000' 2>/dev/null; then
        echo "PHP-FPM is ready (attempt $i)"
        break
    fi
    # Check whether the process already died while we were waiting
    if ! kill -0 "$PHP_PID" 2>/dev/null; then
        echo "ERROR: PHP-FPM exited unexpectedly during startup"
        exit 1
    fi
    echo "PHP-FPM not ready yet, waiting... ($i/10)"
    sleep 1
done

# Final check — if we exhausted the loop without a successful connect, abort
if ! timeout 1 bash -c 'echo > /dev/tcp/127.0.0.1/9000' 2>/dev/null; then
    echo "ERROR: PHP-FPM did not start within 10 seconds"
    exit 1
fi

# Watchdog: poll PHP-FPM liveness every 5 s; if it dies, stop Nginx so the
# container exits and Railway restarts it. This runs entirely via kill signals
# and never calls wait(), so it cannot trigger "not a child of this shell".
watchdog() {
    while true; do
        sleep 5
        if ! kill -0 "$PHP_PID" 2>/dev/null; then
            # Capture the exit code so we know whether it was a crash or clean stop
            wait "$PHP_PID" 2>/dev/null
            PHP_EXIT=$?
            echo "ERROR: PHP-FPM (pid $PHP_PID) died with exit code $PHP_EXIT — stopping Nginx"
            nginx -s quit 2>/dev/null || true
            return
        fi
    done
}
watchdog &
WATCHDOG_PID=$!

echo "Starting Nginx..."
# nginx -g "daemon off;" is the foreground process; the script blocks here.
# When Nginx exits (gracefully or otherwise), execution continues below.
nginx -g "daemon off;"

# Nginx has exited — stop the watchdog and let the EXIT trap clean up PHP-FPM
kill "$WATCHDOG_PID" 2>/dev/null || true
echo "Nginx has exited — container will stop"
