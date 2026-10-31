FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql mbstring curl \
    && a2enmod rewrite headers \
    && printf '<Directory /var/www/html>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/blynine.conf \
    && a2enconf blynine \
    && rm -rf /var/lib/apt/lists/*
