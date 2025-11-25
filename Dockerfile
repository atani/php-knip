# PHP-Knip Development and Testing Dockerfile
# Supports multiple PHP versions for compatibility testing

ARG PHP_VERSION=7.4

FROM php:${PHP_VERSION}-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    curl \
    icu-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-install intl mbstring

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for caching
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Copy source code
COPY . .

# Generate autoloader
RUN composer dump-autoload --optimize

# Default command runs tests
CMD ["vendor/bin/phpunit"]
