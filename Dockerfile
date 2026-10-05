
FROM php:8.2-apache

# Install MySQLi extension
RUN docker-php-ext-install mysqli

# Enable Apache URL rewriting
RUN a2enmod rewrite

# Copy project files into Apache document root
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Set ownership for Apache
RUN chown -R www-data:www-data /var/www/html/

# Keep application files readable
RUN find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

# Apache listens on port 80
EXPOSE 80