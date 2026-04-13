# Imatge dev — PHP 8.3-FPM + Nginx + extensions Postgres; vendor via composer al build (sense artisan a build).
# Nginx fa de reverse proxy cap a PHP-FPM (millor rendiment que `php artisan serve`).
FROM php:8.3-fpm-bookworm

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    libpq-dev git unzip curl ca-certificates nginx postgresql-client \
  && docker-php-ext-install pdo pdo_pgsql opcache \
  && rm -rf /var/lib/apt/lists/*

COPY docker/dockerfiles/backend-api/opcache-dev.ini /usr/local/etc/php/conf.d/opcache-dev.ini

# Composer sense imatge auxiliar (evita COPY --from=composer fallos de pull/cache a Windows/BuildKit).
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
  && composer --version

WORKDIR /var/www/html

COPY backend-api/composer.json backend-api/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts

COPY backend-api/ ./

RUN composer dump-autoload -o

# Nginx: un sol vhost al port 8000 (alineat amb docker-compose).
COPY docker/dockerfiles/backend-api/nginx-laravel.conf /etc/nginx/sites-available/laravel
RUN rm -f /etc/nginx/sites-enabled/default \
  && ln -sf /etc/nginx/sites-available/laravel /etc/nginx/sites-enabled/laravel \
  && nginx -t

EXPOSE 8000
