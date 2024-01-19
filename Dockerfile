FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    default-mysql-client \
    && docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

CMD ["php-fpm", "-F"]
