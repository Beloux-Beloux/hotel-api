# Utiliser une image PHP officielle
FROM php:8.2-fpm

# Installer les dépendances système et les extensions PHP
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm \
    && apt-get clean

# Installer les extensions PHP nécessaires
RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /app

# Copier les fichiers du projet
COPY . .

# Installer les dépendances Composer (sans dev pour la production)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copier .env.example en .env (Render injectera les vraies valeurs via les variables d'env)
RUN cp .env.example .env || true

# Générer la clé d'application
RUN php artisan key:generate --force

# Optimiser Laravel
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

# Créer le lien storage
RUN php artisan storage:link --force || true

# Donner les permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Exposer le port
EXPOSE 9000

# Commande de démarrage
CMD php artisan serve --host=0.0.0.0 --port=${PORT:-10000}