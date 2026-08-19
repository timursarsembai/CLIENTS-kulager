FROM php:8.3-apache

# .htaccess из public_html должен работать так же, как на хостинге
RUN a2enmod rewrite headers expires deflate \
    && printf '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' > /etc/apache2/conf-available/kulager.conf \
    && a2enconf kulager

# Расширения CMS: работа с базой и обработка изображений
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip \
    && rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/conf.d/kulager.ini
