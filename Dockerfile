FROM php:8.2-apache
COPY . /var/www/html/
RUN chmod -R 777 /var/www/html/
