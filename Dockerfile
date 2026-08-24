# syntax=docker/dockerfile:1.7
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json ./
RUN npm install --no-audit --no-fund
COPY vite.config.js ./
COPY tailwind.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM python:3.13-alpine AS documentation
WORKDIR /app
COPY requirements-docs.txt ./
RUN pip install --no-cache-dir -r requirements-docs.txt
COPY mkdocs.yml ./
COPY docs ./docs
RUN mkdocs build --strict

FROM php:8.4-cli AS vendor
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libpng-dev libxml2-dev unzip \
    && docker-php-ext-install dom gd mbstring xmlwriter \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

FROM php:8.4-apache AS runtime
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV LOG_CHANNEL=stderr
ENV MAIL_MAILER=log
ENV OKATANA_REQUIRE_TWO_FACTOR=false
RUN apt-get update \
    && apt-get install -y --no-install-recommends gosu libcurl4-openssl-dev libonig-dev libpng-dev libpq-dev libsqlite3-dev libxml2-dev \
    && docker-php-ext-install curl dom gd mbstring opcache pcntl pdo_mysql pdo_pgsql pdo_sqlite xml xmlwriter \
    && a2enmod rewrite headers \
    && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY --from=documentation /app/public/docs ./public/docs
COPY docker/entrypoint.sh /usr/local/bin/okatana-entrypoint
COPY docker/php-upload.ini /usr/local/etc/php/conf.d/okatana-upload.ini
RUN chmod +x /usr/local/bin/okatana-entrypoint \
    && mkdir -p /data \
    && chown -R www-data:www-data storage bootstrap/cache database /data
EXPOSE 80
ENTRYPOINT ["okatana-entrypoint"]
CMD ["apache2-foreground"]
