FROM php:8.3.36-apache

# Extension PostgreSQL pour PDO
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Apache ne sert que public/ : app/, config/ et database/ restent invisibles depuis le navigateur
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite

# Ne pas révéler les versions d'Apache et de PHP dans les en-têtes (Server, X-Powered-By)
RUN sed -ri -e 's!^ServerTokens .*!ServerTokens Prod!' /etc/apache2/conf-available/security.conf \
    && echo 'expose_php = Off' > /usr/local/etc/php/conf.d/security.ini

COPY . /var/www/html/
