FROM php:8.3-cli-bookworm

# Build tools + Oracle Instant Client runtime dependency (libaio1).
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        autoconf \
        build-essential \
        pkg-config \
        libaio1 \
        libicu-dev \
        unzip \
        zip \
        curl \
        git \
        libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Oracle Instant Client (Basic Lite + SDK), downloaded directly from Oracle
# (no login required for these zip packages). "instantclient" is a stable
# symlink to whatever versioned directory the zip extracts to, so the rest
# of this Dockerfile doesn't need to know the exact release number.
RUN mkdir -p /opt/oracle && cd /opt/oracle \
    && curl -sSfO https://download.oracle.com/otn_software/linux/instantclient/instantclient-basiclite-linuxx64.zip \
    && curl -sSfO https://download.oracle.com/otn_software/linux/instantclient/instantclient-sdk-linuxx64.zip \
    && unzip -qo instantclient-basiclite-linuxx64.zip \
    && unzip -qo instantclient-sdk-linuxx64.zip \
    && rm -f instantclient-basiclite-linuxx64.zip instantclient-sdk-linuxx64.zip \
    && ln -s /opt/oracle/instantclient_* /opt/oracle/instantclient \
    && echo "/opt/oracle/instantclient" > /etc/ld.so.conf.d/oracle-instantclient.conf \
    && ldconfig

# OCI8 (recommended driver) and PDO_OCI (the other driver this project tests).
RUN printf "instantclient,/opt/oracle/instantclient\n" | pecl install oci8 \
    && docker-php-ext-enable oci8

RUN docker-php-ext-configure pdo_oci --with-pdo-oci=instantclient,/opt/oracle/instantclient \
    && docker-php-ext-install pdo_oci

RUN docker-php-ext-install intl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
