FROM php:8.3-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        unzip \
        git \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Smarty into /opt/vendor instead of /var/www/html/vendor.
# /var/www/html is bind-mounted from the host (./www) at runtime, which would
# hide/overwrite anything installed under it during the image build.
WORKDIR /opt/build
COPY composer.json /opt/build/composer.json
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && mv /opt/build/vendor /opt/vendor \
    && rm -rf /opt/build

WORKDIR /var/www/html
