FROM php:8.2-apache

# Enable Apache mod_rewrite for clean URLs and routing
RUN a2enmod rewrite

# Copy all project files to the default Apache web root
COPY . /var/www/html/

# Ensure Apache reads custom configurations if using .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Expose port 80 for web traffic
EXPOSE 80
