FROM php:8.4-cli AS build

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql zip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

# The Wayfinder Vite plugin shells out to `php artisan wayfinder:generate`
# during `npm run build`, which needs a bootable app - this .env is only
# used for that build step and is removed before it reaches the runtime
# image below.
RUN cp .env.example .env && php artisan key:generate --ansi

RUN npm ci && npm run build

FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev nginx supervisor \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY --from=build /app ./
RUN rm -f .env

# See docker/php-fpm-pool.conf for why clear_env=no matters here.
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-minitor.conf
COPY docker/nginx.conf /etc/nginx/sites-enabled/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
