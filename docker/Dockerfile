FROM php:8.4.11-fpm

ARG UID
ARG GID

ENV UID=${UID}
ENV GID=${GID}

# Install php-mysql driver & gd to handle screenshots
RUN apt update \
    && apt install -y zlib1g-dev libpng-dev libjpeg62-turbo-dev libwebp-dev
# Manual isntall because we don't appear to have libxdiff in the repos:
RUN curl -sSLf -o /tmp/libxdef.tag.gz http://www.xmailserver.org/libxdiff-0.23.tar.gz \
	&& mkdir /tmp/libxdef \
	&& cd /tmp/libxdef/ \
	&& tar -xzf /tmp/libxdef.tag.gz \
	&& unlink /tmp/libxdef.tag.gz \
	&& cd libxdiff-0.23 \
	&& ./configure --disable-shared --disable-dependency-tracking --with-pic \
	&& make && make install \
    && pecl install xdiff
RUN rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install mysqli pdo pdo_mysql gd \
    && docker-php-ext-enable xdiff

RUN usermod -u ${UID} www-data \
    && groupmod -g ${GID} www-data


# install net core runtime for modpeek
RUN dotnet_version=10.0.11 \
    && curl -fSL --output dotnet.tar.gz https://builds.dotnet.microsoft.com/dotnet/Runtime/$dotnet_version/dotnet-runtime-$dotnet_version-linux-x64.tar.gz \
    && dotnet_sha512='64c77a5f98d6dfc63393ed5d2fed47c2855c7cdd4322008b3b13e1d0b710aefc1fba7e56612f25f7e8a2b1b6cef5cf77cb3a834ff3685e723ad286703c3396d8' \
    && echo "$dotnet_sha512  dotnet.tar.gz" | sha512sum -c - \
    && mkdir -p /usr/share/dotnet \
    && tar -oxzf dotnet.tar.gz -C /usr/share/dotnet \
    && rm dotnet.tar.gz \
    && ln -s /usr/share/dotnet/dotnet /usr/bin/dotnet

ENV DOTNET_VERSION=10.0.11

WORKDIR /var/www/moddb/
