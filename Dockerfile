# ╔════════════════════════════════════════════════════════════════════╗
# ║             PARKALOT SYSTEM - DOCKER CONFIGURATION                 ║
# ║                                                                    ║
# ║  This Dockerfile defines a containerized environment for the      ║
# ║  ParkaLot parking management system, enabling consistent          ║
# ║  deployment across development, staging, and production.          ║
# ╚════════════════════════════════════════════════════════════════════╝

# Base image: PHP 8.1 with Apache
FROM php:8.1-apache

# Set maintainer label
LABEL maintainer="ParkaLot Development Team"
LABEL version="1.0"
LABEL description="ParkaLot Parking Management System"

# ─────────────────────────────────────────────────────────────────────
# SYSTEM DEPENDENCIES
# ─────────────────────────────────────────────────────────────────────

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# ─────────────────────────────────────────────────────────────────────
# PHP EXTENSIONS
# ─────────────────────────────────────────────────────────────────────

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        gd \
        zip \
        opcache

# ─────────────────────────────────────────────────────────────────────
# APACHE CONFIGURATION
# ─────────────────────────────────────────────────────────────────────

# Enable Apache modules
RUN a2enmod rewrite headers alias

# Configure Apache document root
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Add alias for API folder (outside document root)
RUN echo '<Directory /var/www/html/api>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n\
Alias /api /var/www/html/api' >> /etc/apache2/sites-available/000-default.conf

# ─────────────────────────────────────────────────────────────────────
# PHP CONFIGURATION
# ─────────────────────────────────────────────────────────────────────

# Copy custom PHP configuration
RUN echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "session.cookie_secure = 1" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "session.use_strict_mode = 1" >> /usr/local/etc/php/conf.d/custom.ini

# Enable OPcache for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=4000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.revalidate_freq=60" >> /usr/local/etc/php/conf.d/opcache.ini

# ─────────────────────────────────────────────────────────────────────
# APPLICATION SETUP
# ─────────────────────────────────────────────────────────────────────

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# ─────────────────────────────────────────────────────────────────────
# HEALTHCHECK
# ─────────────────────────────────────────────────────────────────────

# Add healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/api/index.php?route=garages || exit 1

# ─────────────────────────────────────────────────────────────────────
# EXPOSE AND RUN
# ─────────────────────────────────────────────────────────────────────

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
