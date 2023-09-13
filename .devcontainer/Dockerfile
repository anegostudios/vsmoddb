FROM mcr.microsoft.com/devcontainers/php:1-8.2-bullseye

# Install MariaDB client
RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
    && apt-get install -y mariadb-client \ 
    && apt-get clean -y && rm -rf /var/lib/apt/lists/*

# Install php-mysql driver
RUN docker-php-ext-install mysqli pdo pdo_mysql

# copy the apache config and enable the site
COPY vsmoddb.conf /etc/apache2/sites-available/
RUN ln -s /etc/apache2/sites-available/vsmoddb.conf /etc/apache2/sites-enabled/vsmoddb.conf
# enable apache rewrite module
RUN a2enmod rewrite
