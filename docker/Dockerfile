FROM wordpress:7.0.2-php8.5-apache
ARG SUBDIR=blog

# handoff A2: hide the PHP version from response headers (expose_php=Off).
COPY docker/security.ini /usr/local/etc/php/conf.d/security.ini

# handoff A1/A2: security response headers, server fingerprint removal and
# xmlrpc blocking. security.conf needs mod_headers. mod_rewrite is what
# WordPress's own .htaccess uses for pretty permalinks — the base image already
# enables both, so this is a guard against a base image that stops doing so
# rather than something we turn on ourselves.
RUN a2enmod headers rewrite
COPY docker/security.conf  /etc/apache2/conf-enabled/security.conf
COPY docker/wordpress.conf /etc/apache2/conf-enabled/wordpress.conf

RUN echo 'ok' >> index.html
WORKDIR /var/www/html/${SUBDIR}
