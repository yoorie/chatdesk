# Optional container image (VPS / Coolify). Shared hosting does not need this.
FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libicu-dev libonig-dev cron \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_mysql mbstring bcmath gd zip intl opcache \
 && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf
WORKDIR /var/www/html
COPY . /var/www/html
RUN ln -sf /var/www/html/storage/.env /var/www/html/.env \
 && chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache && chmod 775 /var/www/html
EXPOSE 80
