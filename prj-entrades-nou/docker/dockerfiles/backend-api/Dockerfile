# Imatge dev — PHP 8.3 + extensions Postgres; vendor via composer al build (sense artisan a build).
FROM php:8.3-cli-bookworm

RUN apt-get update \
  && apt-get install -y --no-install-recommends libpq-dev git unzip curl ca-certificates \
  && docker-php-ext-install pdo pdo_pgsql \
  && rm -rf /var/lib/apt/lists/*

# Composer sense imatge auxiliar (evita COPY --from=composer fallos de pull/cache a Windows/BuildKit).
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
  && composer --version

WORKDIR /var/www/html

COPY backend-api/composer.json backend-api/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts

COPY backend-api/ ./

RUN composer dump-autoload -o

EXPOSE 8000
