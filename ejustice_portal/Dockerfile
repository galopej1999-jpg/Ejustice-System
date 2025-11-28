# PHP-FPM Dockerfile for eJustice Portal
# Builds a PHP-FPM image with required extensions

FROM php:8.1-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libssl-dev \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql mbstring zip exif pcntl bcmath gd opcache

# Enable recommended PHP settings
RUN { \
    echo "memory_limit=512M"; \
    echo "upload_max_filesize=64M"; \
    echo "post_max_size=64M"; \
    echo "max_execution_time=300"; \
} > /usr/local/etc/php/conf.d/custom-php.ini

# Create application directory
WORKDIR /var/www/html

# Copy application code
COPY . /var/www/html

# Ensure storage directories exist and have correct permissions
RUN mkdir -p /var/www/html/storage/documents && chown -R www-data:www-data /var/www/html/storage && chmod -R 775 /var/www/html/storage

# Expose php-fpm port (for nginx to connect)
EXPOSE 9000

# Use default php-fpm command
CMD ["php-fpm"]
