#!/bin/bash
# Attend la base, installe Moodle au premier démarrage, applique les mises à jour ensuite.
set -euo pipefail

cd /var/www/moodle
chown www-data:www-data /var/www/moodledata

as_www() { runuser -u www-data -- "$@"; }

if [ "${1:-}" = "cron" ]; then
    echo "[moodle] cron : exécution toutes les 60 s"
    while true; do
        as_www php admin/cli/cron.php || echo "[moodle] cron en échec, nouvel essai dans 60 s"
        sleep 60
    done
fi

status=down
for _ in $(seq 1 60); do
    status=$(php /usr/local/bin/moodle-db-status.php)
    [ "$status" != "down" ] && break
    echo "[moodle] base de données indisponible, attente..."
    sleep 2
done
if [ "$status" = "down" ]; then
    echo "[moodle] impossible de joindre la base de données" >&2
    exit 1
fi

if [ "$status" = "empty" ]; then
    echo "[moodle] première installation (quelques minutes)..."
    as_www php admin/cli/install_database.php \
        --agree-license \
        --lang="${MOODLE_LANG:-fr}" \
        --adminuser="${MOODLE_ADMIN_USER:-admin}" \
        --adminpass="${MOODLE_ADMIN_PASSWORD:?MOODLE_ADMIN_PASSWORD est requis}" \
        --adminemail="${MOODLE_ADMIN_EMAIL:-admin@example.com}" \
        --fullname="${MOODLE_SITE_FULLNAME:-Moodle}" \
        --shortname="${MOODLE_SITE_SHORTNAME:-moodle}"
else
    echo "[moodle] Moodle déjà installé, application des mises à jour éventuelles..."
    as_www php admin/cli/upgrade.php --non-interactive
fi

as_www php /usr/local/bin/moodle-fr-templates.php
as_www php /usr/local/share/oriskill/oriskill-storefront.php
as_www php /usr/local/share/oriskill/oriskill-branding.php

exec "$@"
