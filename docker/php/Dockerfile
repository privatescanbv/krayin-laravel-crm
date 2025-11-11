FROM docker.io/node:20-bullseye AS vite
WORKDIR /build
COPY ./ ./

# clean up
RUN rm -rf tests
RUN rm -rf .git
RUN rm -rf flows

# Install dependencies and build assets
RUN ./build.sh

FROM docker.io/php:8.3-fpm-bullseye

RUN apt-get update \
&& apt upgrade -y \
&& apt-get install -y \
    gnupg \
    g++ \
    procps \
    openssl \
    git \
    unzip \
    zlib1g-dev \
    libzip-dev \
    libfreetype6-dev \
    libpng-dev \
    libjpeg-dev \
    libicu-dev  \
    libonig-dev \
    libxslt1-dev \
    acl \
    cron \
    nginx \
    libssh2-1-dev \
    libssh2-1 \
    jq \
    graphviz \
    iputils-ping \
    lsb-release \
    wget \
    vim \
    supervisor \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
#    bash \

# Install the MySQL client from Debian repositories
RUN apt-get update \
    && apt-get install -y default-mysql-client \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP Redis using PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

RUN pecl install ssh2-1.3.1

# Install additional PHP extensions
RUN docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip \
        xsl \
        gd \
        intl \
        opcache \
        exif \
        mbstring \
        soap \
        pcntl \
        bcmath \
        calendar

RUN docker-php-ext-configure gd --with-jpeg --with-freetype

RUN docker-php-ext-enable ssh2

# Setup application files
WORKDIR /usr/share/nginx/html
COPY --chown=www-data:www-data ./ ./


# Install Composer and PHP dependencies (ensure vendor/autoload.php exists)
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN git config --global --add safe.directory /usr/share/nginx/html
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');" \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && php artisan vendor:publish --tag=log-viewer-assets --force \
    && composer clear-cache


COPY  .docker/php/fpm-cloud.conf /usr/local/etc/php-fpm.d/zz-lento-fpm.conf
COPY  .docker/php/php.ini /usr/local/etc/php/conf.d/docker-php-config.ini
COPY  .docker/nginx/templates/default.cloud.conf /etc/nginx/sites-enabled/default
COPY  .docker/nginx/nginx.conf /etc/nginx/nginx.conf

RUN chown -R www-data:www-data /usr/share/nginx/html /var/log /run /etc/nginx

COPY --chown=www-data:www-data --from=vite /build/public/build public/build
COPY --chown=www-data:www-data --from=vite /build/public/admin/build public/admin/build
COPY --chown=www-data:www-data --from=vite /build/public/webform/build public/webform/build



# Ensure storage directories have correct permissions for log writing
RUN mkdir -p /usr/share/nginx/html/storage/logs \
    && chown -R www-data:www-data /usr/share/nginx/html/storage \
    && mkdir -p /usr/share/nginx/html/storage/framework/cache \
    && mkdir -p /usr/share/nginx/html/storage/framework/sessions \
    && mkdir -p /usr/share/nginx/html/storage/framework/views \
    && mkdir -p /usr/share/nginx/html/bootstrap/cache \
    && chown -R www-data:www-data /usr/share/nginx/html/storage \
    && chown -R www-data:www-data /usr/share/nginx/html/bootstrap/cache \
    && chmod -R 775 /usr/share/nginx/html/storage \
    && chmod -R 775 /usr/share/nginx/html/bootstrap/cache

RUN rm -rf /usr/share/nginx/html/.env

# These files are not used within the container, but copied in the server
COPY .docker/mysql/init-n8n.sql /docker/mysql/
COPY .docker/mysql/init-forms.sql /docker/mysql/
COPY docker-compose-prod.yml /docker/docker-compose.yml
# TODO security wise, red flag. Change this CI implmementation later to a vault.
COPY .env.prod /docker/.env.prod
RUN mkdir -p ./storage && touch ./storage/installed

COPY .docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
# temporary for mail server
COPY ./docker/config /docker/config
COPY ./mailserver.env /docker/mailserver.env
#COPY .docker/update-flows-n8n.sh /usr/local/bin/update-flows-n8n.sh

COPY .docker/execute-folder-scripts.sh ./execute-folder-scripts.sh
RUN chmod 0755 ./execute-folder-scripts.sh

COPY .docker/start.sh /start.sh
RUN chmod +x /start.sh
ENTRYPOINT ["/bin/bash", "/start.sh"]

EXPOSE 8080
EXPOSE 80
