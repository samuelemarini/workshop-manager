# Asset (Webpack Encore): compilati qui, nell'immagine finale non serve Node.
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY app/package.json app/yarn.lock ./
RUN yarn install --frozen-lockfile --non-interactive
COPY app/webpack.config.mjs ./
COPY app/assets ./assets
RUN yarn encore production

FROM webdevops/php-apache:8.4 AS base
ENV PHP_DATE_TIMEZONE=Europe/Rome
ENV TZ=Europe/Rome
ENV PUID=1000
ENV PGID=1000
ENV WEB_DOCUMENT_ROOT='/app/public/'
ENV php.session.cookie_secure=false
ENV php.memory_limit=256M
# documenti allegati fino a 30 MB (AttachmentStorage::MAX_SIZE)
ENV php.upload_max_filesize=32M
ENV php.post_max_size=100M

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

# Sviluppo (docker-compose.yml): il codice e' montato da ./app, Node serve per yarn encore dev.
FROM base AS dev
RUN curl -sL https://deb.nodesource.com/setup_22.x -o nodesource_setup.sh && bash nodesource_setup.sh \
    && apt-get install -y nodejs && corepack enable && rm nodesource_setup.sh

# Produzione (docker-compose.prod.yml): ultimo stadio, quello che si costruisce di default.
FROM base AS prod
ENV APP_ENV=prod
COPY app /app
WORKDIR /app

# vendor/ e public/build non sono nel repo: si ricostruiscono qui.
RUN composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress --no-scripts \
    && php bin/console cache:warmup \
    && mkdir -p uploads \
    && chmod -R 777 var uploads
COPY --from=assets /app/public/build /app/public/build

# Migration, dati di base e primo admin all'avvio (vedi il file): l'entrypoint webdevops esegue questi script da root.
COPY docker/entrypoint.d/ /opt/docker/provision/entrypoint.d/
