#!/bin/sh
set -eu

php /var/www/html/database/migrate.php
php /var/www/html/database/bootstrap.php
exec apache2-foreground
