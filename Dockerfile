FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Allow .htaccess to work (fixes 404 on all routes)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Install PostgreSQL client libraries and PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Set recommended PHP settings
RUN echo "upload_max_filesize = 20M" >> /usr/local/etc/php/php.ini \
    && echo "post_max_size = 20M" >> /usr/local/etc/php/php.ini \
    && echo "max_execution_time = 120" >> /usr/local/etc/php/php.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/php.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/php.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/php.ini

# Set the working directory
WORKDIR /var/www/html

# Copy application source
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 80
EXPOSE 80
# Enable Apache MPM prefork tuning
RUN echo "<IfModule mpm_prefork_module>\n\
    StartServers 4\n\
    MinSpareServers 4\n\
    MaxSpareServers 10\n\
    MaxRequestWorkers 75\n\
    MaxConnectionsPerChild 1000\n\
</IfModule>" > /etc/apache2/conf-available/mpm-tuning.conf \
&& a2enconf mpm-tuning