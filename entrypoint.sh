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
# Redirect both stdout and stderr to the container log so PHP fatal errors are visible
php-fpm -F 2>&1 &
PHP_PID=$!

# Wait for PHP-FPM to bind to port 9000 (up to 10 s) instead of a blind sleep
echo "Waiting for PHP-FPM to become ready..."
for i in $(seq 1 10); do
    if timeout 1 bash -c 'echo > /dev/tcp/127.0.0.1/9000' 2>/dev/null; then
        echo "PHP-FPM is ready (attempt $i)"
        break
    fi
    # Check whether the process already died while we were waiting
    if ! kill -0 "$PHP_PID" 2>/dev/null; then
        wait "$PHP_PID"
        echo "ERROR: PHP-FPM exited unexpectedly during startup (exit code $?)"
        exit 1
    fi
    echo "PHP-FPM not ready yet, waiting... ($i/10)"
    sleep 1
done

# Final check — if we exhausted the loop without a successful connect, abort
if ! timeout 1 bash -c 'echo > /dev/tcp/127.0.0.1/9000' 2>/dev/null; then
    echo "ERROR: PHP-FPM did not start within 10 seconds"
    if ! kill -0 "$PHP_PID" 2>/dev/null; then
        wait "$PHP_PID"
        echo "ERROR: PHP-FPM exit code: $?"
    fi
    exit 1
fi

# Monitor PHP-FPM in the background and log if it crashes after startup
(
    wait "$PHP_PID"
    EXIT_CODE=$?
    if [ $EXIT_CODE -ne 0 ]; then
        echo "ERROR: PHP-FPM crashed with exit code $EXIT_CODE"
    fi
) &

echo "Starting Nginx..."
nginx -g "daemon off;"

# If Nginx exits, bring down the whole container so Railway restarts it
wait $PHP_PID
