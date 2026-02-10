FROM php:8.2-apache

# ==============================
# 1. Dependencias del sistema
# ==============================
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pgsql \
        pdo_pgsql \
        intl \
        mbstring \
        zip \
        gd \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ==============================
# 2. Apache config
# Habilitar módulos Apache necesarios
RUN a2enmod rewrite headers expires

# Root público (CodeIgniter / Laravel)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Permitir .htaccess
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' \
    /etc/apache2/apache2.conf

# ==============================
# 3. PHP producción
# ==============================
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# ==============================
# 4. Composer
# ==============================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ==============================
# 5. Copiar proyecto
# ==============================
WORKDIR /var/www/html/public
COPY . .

# ==============================
# 6. Dependencias PHP
# ==============================
RUN if [ -f composer.json ]; then \
    composer install --no-dev --optimize-autoloader --no-interaction; \
    fi

# ==============================
# 7. Permisos (CodeIgniter)
# ==============================
RUN mkdir -p writable/cache writable/logs writable/session writable/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 writable

# ==============================
# 8. .htaccess automático
# ==============================
RUN if [ ! -f public/.htaccess ]; then \
    echo '<IfModule mod_rewrite.c>\n\
RewriteEngine On\n\
RewriteBase /\n\
RewriteCond %{REQUEST_FILENAME} !-f\n\
RewriteCond %{REQUEST_FILENAME} !-d\n\
RewriteRule ^(.*)$ index.php/$1 [L]\n\
</IfModule>' > public/.htaccess; \
    fi

# ==============================
# 9. Puerto dinámico (Render)
# ==============================
ENV PORT=10000
EXPOSE 10000

RUN echo '#!/bin/bash\n\
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf\n\
cat > /etc/apache2/sites-available/000-default.conf << EOF\n\
<VirtualHost *:${PORT}>\n\
    ServerAdmin webmaster@localhost\n\
    DocumentRoot /var/www/html/public\n\
    \n\
    <Directory /var/www/html/public>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    \n\
    ErrorLog \${APACHE_LOG_DIR}/error.log\n\
    CustomLog \${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>\n\
EOF\n\
exec apache2-foreground' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]
