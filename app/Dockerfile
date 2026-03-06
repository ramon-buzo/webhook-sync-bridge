FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx autoconf g++ make && \
    pecl install redis && \
    docker-php-ext-enable redis && \
    apk del autoconf g++ make

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json /var/www/html/
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader

COPY nginx.conf /etc/nginx/nginx.conf
COPY public/ /var/www/html/public/
COPY src/ /var/www/html/src/

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]