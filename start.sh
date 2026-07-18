#!/bin/bash
set -e

# Attendre que la DB soit prête
sleep 5

# Lancer les migrations
php artisan migrate --force

# Créer le lien storage
php artisan storage:link --force

# Optimiser Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Démarrer PHP-FPM et Nginx
php artisan serve --host=0.0.0.0 --port=${PORT:-10000}