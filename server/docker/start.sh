
#!/usr/bin/env bash
set -Eeuo pipefail

cd /var/www/html

# The database must be reachable before this step.
php artisan migrate --force

# Cache configuration using the deployment environment.
php artisan config:cache

exec apache2-foreground
