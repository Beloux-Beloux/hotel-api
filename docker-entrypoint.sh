#!/bin/bash
set -e

if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

if [ "$APP_ENV" = "production" ]; then
    php artisan migrate --force
fi

exec "$@"