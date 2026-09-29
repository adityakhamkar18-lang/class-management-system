FROM php:8.2-apache

# Install MySQL/MariaDB PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy project files
WORKDIR /var/www/html
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Render provides the PORT environment variable
CMD ["bash", "-c", "PORT=${PORT:-10000}; sed -i \"s/Listen 80/Listen ${PORT}/\" /etc/apache2/ports.conf; sed -i \"s/<VirtualHost \\*:80>/<VirtualHost *:${PORT}>/\" /etc/apache2/sites-available/000-default.conf; apache2-foreground"]