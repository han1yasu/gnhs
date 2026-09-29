FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install curl pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-security.conf /etc/apache2/conf-available/app-security.conf
RUN a2enconf app-security \
    && mkdir -p /var/www/html/uploads/avatars \
    && chown -R www-data:www-data /var/www/html/uploads

WORKDIR /var/www/html
COPY . /var/www/html
COPY docker/start.sh /usr/local/bin/gnhs-start
RUN chmod +x /usr/local/bin/gnhs-start \
    && chown -R www-data:www-data /var/www/html/uploads

EXPOSE 80
CMD ["gnhs-start"]
