FROM php:8.2-cli

# Composer (multi-stage to keep final image small)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP deps first (cacheable layer)
COPY composer.json /app/
COPY composer.lock* /app/
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

COPY profebot.php /app/
COPY profebot.html /app/
COPY profebot.css /app/
COPY profebot.js /app/
COPY router.php /app/
COPY favicon.svg /app/
COPY materiales/ /app/materiales/
COPY question_cache.json /app/
COPY google*.html /app/

EXPOSE 8080
CMD php -S 0.0.0.0:${PORT:-8080} /app/router.php
