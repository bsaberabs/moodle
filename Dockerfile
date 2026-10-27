# Image Moodle : PHP 8.4 + Apache + code Moodle du dossier moodle/ (5.3)
FROM php:8.4-apache-bookworm

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions gd intl mysqli zip soap opcache exif \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Code Moodle (appartient à root : le serveur web ne peut pas le modifier).
# Depuis Moodle 5.1 seul le sous-dossier public/ est exposé par Apache.
COPY moodle/ /var/www/moodle/
RUN echo "ok" > /var/www/moodle/public/healthz.txt \
    && mkdir -p /var/www/moodledata \
    && chown www-data:www-data /var/www/moodledata

COPY docker/apache-moodle.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-moodle.ini"
COPY docker/config.php /var/www/moodle/config.php
COPY docker/db-status.php /usr/local/bin/moodle-db-status.php
COPY docker/moderncommerce-fr-templates.php /usr/local/bin/moodle-fr-templates.php
COPY docker/oriskill-storefront.php docker/oriskill-storefront-fr.json /usr/local/share/oriskill/
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/moodle-entrypoint

WORKDIR /var/www/moodle
VOLUME /var/www/moodledata
EXPOSE 80

HEALTHCHECK --interval=15s --timeout=5s --start-period=15m --retries=3 \
    CMD curl -fsS -o /dev/null http://localhost/healthz.txt || exit 1

ENTRYPOINT ["moodle-entrypoint"]
CMD ["apache2-foreground"]
