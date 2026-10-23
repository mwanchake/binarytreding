FROM node:22-bookworm-slim AS frontend

WORKDIR /src

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN npm run production

FROM php:8.2-apache-bookworm

ENV APACHE_DOCUMENT_ROOT=/var/www/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libonig-dev \
        libpq-dev \
        libxml2-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" curl mbstring pdo_pgsql pcntl opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .
COPY --from=frontend /src/public /var/www/public

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && ln -s ../storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY deploy/apache-ports.conf /etc/apache2/ports.conf
COPY deploy/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

EXPOSE 10000

ENTRYPOINT ["docker-entrypoint"]
CMD ["apache2-foreground"]
