FROM php:7.4-apache

ARG UID
ARG GID

ENV UID=${UID}
ENV GID=${GID}

# Install php-mysql driver & gd to handle screenshots
RUN apt update \
    && apt install -y zlib1g-dev libpng-dev \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install mysqli pdo pdo_mysql gd

# enable apache rewrite module
RUN a2enmod rewrite

RUN usermod -u ${UID} www-data \
    && groupmod -g ${GID} www-data
