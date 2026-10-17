FROM php:8.2-fpm

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        libbz2-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libxpm-dev \
        libvpx-dev \
        libonig-dev \
        libxml2-dev \
        libtidy-dev \
        libzip-dev \
        libxslt1-dev \
        libssh2-1-dev \
        wget \
        nano \
        htop \
        iotop \
        lsof \
        unzip \
        cron && \
    rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    bz2 \
    gd \
    tidy \
    soap \
    zip \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    sodium \
    sqlite3 \
    redis \
    xsl \
    xmlrpc \
    opcache \
    ssh2

RUN pecl install pcov \
    && docker-php-ext-enable pcov \
    && echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/pcov.ini \
    && echo "pcov.directory=/var/www/html" >> /usr/local/etc/php/conf.d/pcov.ini
    
RUN docker-php-ext-install pcntl

WORKDIR /var/www/html

COPY ./.docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
