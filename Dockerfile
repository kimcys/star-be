FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql

# Listen on an unprivileged port so the whole server - master process
# included - can run as www-data instead of root. php -S (the
# previous base) is explicitly documented by PHP as dev-only and unfit
# for a public network; apache is the production-grade swap.
RUN sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf

RUN mkdir -p /var/run/apache2 /var/lock/apache2 \
    && chown -R www-data:www-data /var/log/apache2 /var/run/apache2 /var/lock/apache2

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD ["php", "bin/healthcheck.php"]

CMD ["apache2-foreground"]
