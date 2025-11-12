FROM docker.io/node:20-bullseye AS vite
ARG APP_ENV=production
WORKDIR /build
COPY ./ ./

# clean up
RUN rm -rf tests
RUN rm -rf .git
RUN rm -rf flows

# Install dependencies and build assets
# Pass APP_ENV to build.sh to determine if npm run build should be executed
RUN ./build.sh ${APP_ENV}

FROM docker.io/php:8.3-fpm-bullseye
ARG APP_ENV=production
ARG WWWGROUP=1000
ARG WWWUSER=1000
ENV APP_ENV=${APP_ENV}
WORKDIR /usr/share/nginx/html

RUN set -eux; \
  apt-get update && apt-get install -y \
    gnupg g++ procps openssl git unzip zlib1g-dev libzip-dev libfreetype6-dev \
    libpng-dev libjpeg-dev libicu-dev libonig-dev libxslt1-dev acl cron nginx \
    libssh2-1-dev libssh2-1 jq graphviz iputils-ping lsb-release wget vim supervisor \
    default-mysql-client \
  && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install the MySQL client from Debian repositories
RUN apt-get update \
    && apt-get install -y default-mysql-client \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# --- Add Yarn + NodeJS (for frontend builds or tooling) - for Vite server
RUN if [ "$APP_ENV" = "local" ]; then \
      curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
      apt-get install -y nodejs && \
      corepack enable && \
      corepack prepare yarn@stable --activate && \
      apt-get clean && \
      rm -rf /var/lib/apt/lists/*; \
    fi

# Install PHP Redis using PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

RUN if [ "$APP_ENV" = "local" ]; then \
      pecl install xdebug && docker-php-ext-enable xdebug; \
    fi

RUN pecl install ssh2-1.3.1

# Install additional PHP extensions
RUN docker-php-ext-install \
        pdo pdo_mysql zip xsl gd intl opcache exif mbstring soap pcntl bcmath calendar
RUN docker-php-ext-configure gd --with-jpeg --with-freetype
RUN docker-php-ext-enable ssh2


# Kopieer alleen het nodige uit de Node build stage
COPY --from=vite /build/artisan ./
COPY --from=vite /build/*.lock ./
COPY --from=vite /build/*.js ./
COPY --from=vite /build/*.json ./
COPY --from=vite /build/*.php ./
COPY --from=vite /build/.docker/start.sh /start.sh
COPY --from=vite /build/app ./app
COPY --from=vite /build/bootstrap ./bootstrap
COPY --from=vite /build/config ./config
COPY --from=vite /build/public/ ./public/
COPY --from=vite /build/resources ./resources
COPY --from=vite /build/routes ./routes
COPY --from=vite /build/lang ./lang
COPY --from=vite /build/docs ./docs
COPY --from=vite /build/database ./database
COPY --from=vite /build/artisan ./artisan
COPY --from=vite /build/node_modules ./node_modules
COPY --from=vite /build/packages ./packages

# Create sail user and group (required for Laravel Sail)
# Use same approach as Laravel Sail: create with fixed UID, adjust in start script
# Handle case where WWWGROUP might be empty or non-numeric by using default 1000
RUN set -eux; \
    if [ -z "$WWWGROUP" ] || ! [ "$WWWGROUP" -eq "$WWWGROUP" ] 2>/dev/null; then \
        WWWGROUP_VAL=1000; \
    else \
        WWWGROUP_VAL=$WWWGROUP; \
    fi; \
    groupadd --force -g $WWWGROUP_VAL sail; \
    useradd -ms /bin/bash --no-user-group -g $WWWGROUP_VAL -u 1337 sail

# Zorg dat Laravel zijn cache paths heeft vóór composer install. These steps are fixes for;php artisan package:discover --ansi
RUN mkdir -p ./storage/framework/{cache,sessions,views,testing} \
    && mkdir -p ./bootstrap/cache \
    && chown -R www-data:www-data ./storage ./bootstrap/cache \
    && chmod -R 775 ./storage ./bootstrap/cache
ENV VIEW_COMPILED_PATH=/usr/share/nginx/html/storage/framework/views


# Install Composer and PHP dependencies (ensure vendor/autoload.php exists)
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN git config --global --add safe.directory /usr/share/nginx/html
RUN set -eux; \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"; \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer; \
    php -r "unlink('composer-setup.php');"; \
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader


RUN mkdir -p /usr/local/etc/php-fpm.d \
    && rm -f /usr/local/etc/php-fpm.d/www.conf
COPY .docker/php/fpm-cloud.conf /usr/local/etc/php-fpm.d/zz-fpm.conf

COPY  --from=vite /build/.docker/php/php.ini /usr/local/etc/php/conf.d/docker-php-config.ini
COPY  --from=vite /build/.docker/nginx/templates/default.cloud.conf /etc/nginx/sites-enabled/default
COPY  --from=vite /build/.docker/nginx/nginx.conf /etc/nginx/nginx.conf

RUN mkdir -p /var/log/nginx /var/log/php-fpm /var/log/supervisor \
    && chown -R www-data:www-data /usr/share/nginx/html /var/log /run /etc/nginx

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
COPY --from=vite /build/.docker/mysql/init-n8n.sql /docker/mysql/
COPY --from=vite /build/.docker/mysql/init-forms.sql /docker/mysql/
COPY --from=vite /build/docker-compose-prod.yml /docker/docker-compose.yml
# TODO security wise, red flag. Change this CI implmementation later to a vault.
COPY --from=vite /build/.env.prod /docker/.env.prod

RUN mkdir -p ./storage && touch ./storage/installed

COPY --from=vite /build/.docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY --from=vite /build/.docker/json-logger.sh /usr/share/nginx/html/.docker/json-logger.sh
RUN chmod +x /usr/share/nginx/html/.docker/json-logger.sh
# temporary for mail server
COPY --from=vite /build/./docker/config /docker/config
#COPY --from=vite /build/./mailserver.env /docker/mailserver.env
#COPY --from=vite /build.docker/update-flows-n8n.sh /usr/local/bin/update-flows-n8n.sh
COPY --from=vite /build/docker/loki/loki-config.yml ./docker/loki/loki-config.yml
COPY --from=vite /build/docker/promtail/promtail-config.yml ./docker/promtail/promtail-config.yml


COPY --from=vite /build/.docker/execute-folder-scripts.sh ./execute-folder-scripts.sh
RUN chmod 0755 ./execute-folder-scripts.sh

RUN chmod +x /start.sh
ENTRYPOINT ["/bin/bash", "/start.sh"]

EXPOSE 8080
EXPOSE 80
