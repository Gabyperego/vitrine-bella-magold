FROM php:8.2-apache

# Habilita mod_rewrite do Apache
RUN a2enmod rewrite

# Copia os arquivos do projeto para o diretório padrão do Apache
COPY . /var/www/html/

# Configura permissões para permitir escrita no banco de dados local e dados
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/data

# Configura a porta do Apache para usar a variável $PORT exigida pelo Render (ou 80 por padrão)
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

ENV PORT=80

EXPOSE 80

CMD ["apache2-foreground"]

