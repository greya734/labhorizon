#!/bin/bash
set -e

cd /var/www/html

# Génère la clé d'application si absente (utile en premier démarrage)
if [ -z "$(php artisan tinker --execute='echo config(\"app.key\");' 2>/dev/null)" ]; then
    php artisan key:generate --force || true
fi

# Lien symbolique storage -> public/storage
php artisan storage:link || true

# Migrations (désactivable via RUN_MIGRATIONS=false)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

# Caches de config/route/view pour la prod (désactivable via APP_ENV=local)
if [ "${APP_ENV:-production}" != "local" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
