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
RUN dotnet_version=8.0.17 \
    && curl -fSL --output dotnet.tar.gz https://builds.dotnet.microsoft.com/dotnet/Runtime/$dotnet_version/dotnet-runtime-$dotnet_version-linux-x64.tar.gz \
    && dotnet_sha512='d573388bd64deb12e6464fc6a4240f6ed7cd1c6b17370096304b8ac2d800318c3af36f8185a3b780afe52b041824deef9d377a96a6baed759c8283829054649a' \
    && echo "$dotnet_sha512  dotnet.tar.gz" | sha512sum -c - \
    && mkdir -p /usr/share/dotnet \
    && tar -oxzf dotnet.tar.gz -C /usr/share/dotnet \
    && rm dotnet.tar.gz \
    && ln -s /usr/share/dotnet/dotnet /usr/bin/dotnet

ENV DOTNET_VERSION=8.0.17

WORKDIR /var/www/moddb/
