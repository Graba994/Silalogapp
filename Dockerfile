# Użyj oficjalnego obrazu PHP 8.2 z serwerem Apache
FROM php:8.2-apache

# Włącz mod_rewrite dla Apache (przydatne dla "ładnych" linków w przyszłości)
RUN a2enmod rewrite

# Zainstaluj potrzebne rozszerzenia PHP
RUN docker-php-ext-install gd zip

# Ustaw katalog roboczy wewnątrz kontenera
WORKDIR /var/www/html

# Skopiuj cały kod aplikacji z bieżącego folderu do kontenera
COPY . /var/www/html
