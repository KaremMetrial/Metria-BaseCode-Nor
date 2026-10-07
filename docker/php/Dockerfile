# === Docker PHP-FPM ===
# Usage: docker build -f docker/php/Dockerfile -t metrial-php .
FROM php:8.5-fpm-alpine

# UID/GID the FPM workers run as. Defaults match the typical Linux host user so
# that bind-mounted paths (storage/, bootstrap/cache/) are writable from both
# the host and the container without chown'ing files to the container's user.
ARG WWWUSER=1000
ARG WWWGROUP=1000

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    openssh-client \
    supervisor \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath sockets

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create the unprivileged user FPM will run as. Skip silently if the id is
# already taken by a base-image user (e.g. www-data at 82).
RUN (addgroup -g ${WWWGROUP} metrial 2>/dev/null || true) \
    && (adduser -u ${WWWUSER} -S -D -G metrial -s /bin/sh -h /home/metrial metrial 2>/dev/null || true) \
    && mkdir -p /home/metrial \
    && sed -i "s/^user = www-data/user = metrial/" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/^group = www-data/group = metrial/" /usr/local/etc/php-fpm.d/www.conf

# Set working directory
WORKDIR /var/www/html

# Enable all extensions (OPcache is built-in for PHP 8.5)
RUN docker-php-ext-enable pdo_mysql mbstring exif pcntl bcmath sockets

# Copy custom docker PHP-FPM config
COPY docker/php/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# Create log directories for Supervisor
RUN mkdir -p /var/log/supervisor

# Supervisor config for queue worker
COPY docker/php/supervisord.conf /etc/supervisor.conf

# Socket.IO sidecar writes to shared storage too; keep the tree owned correctly
# for image-baked files (bind-mounted paths are handled by the host user id).

# Default command
CMD ["supervisord", "-n", "-c", "/etc/supervisor.conf"]
