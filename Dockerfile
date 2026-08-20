FROM php:8.2-apache

# Enable mysqli extension
RUN docker-php-ext-install mysqli

# Enable Apache mod_rewrite (needed for .htaccess routing)
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set Apache document root to project root
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Copy composer files first (layer caching)
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction || true

# Copy the rest of the app
COPY . .

# Ensure uploads folder is writable
RUN mkdir -p uploads/profiles uploads/docs uploads/ocr uploads/index \
    && chown -R www-data:www-data uploads consultation_summaries logs 2>/dev/null || true

EXPOSE 80