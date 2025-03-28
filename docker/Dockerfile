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


# install mono for modpeek
ENV MONO_VERSION 6.12.0.182

RUN apt-get update && \
    apt-get install -y --no-install-recommends gnupg dirmngr ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && export GNUPGHOME="$(mktemp -d)" \
    && gpg --batch --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 3FA7E0328081BFF6A14DA29AA6A19B38D3D831EF \
    && gpg --batch --export --armor 3FA7E0328081BFF6A14DA29AA6A19B38D3D831EF > /etc/apt/trusted.gpg.d/mono.gpg.asc \
    && gpgconf --kill all \
    && rm -rf "$GNUPGHOME" \
    && apt-key list | grep Xamarin \
    && apt-get purge -y --auto-remove gnupg dirmngr

#TODO(Rennorb) @cleanup: use slimmer mono distribution
# I think that mono-complete is not really required for this, but we need System.IO-Compression and I already spent 2 hours trying to figure out a slimmer version without success.
RUN echo "deb https://download.mono-project.com/repo/debian stable-buster/snapshots/$MONO_VERSION main" > /etc/apt/sources.list.d/mono-official-stable.list \
  && apt-get update \
  && apt-get install -y mono-complete \
  && rm -rf /var/lib/apt/lists/* /tmp/*