#!/bin/bash
# Recopie la production dans la copie de test (base + fichiers) ; la copie démarre ensuite avec son propre code
# (la branche dev, mise à jour par la CI) et applique ses mises à jour. La production n'est que lue.
# Usage : oriskill-test-refresh
set -euo pipefail

PROD=/opt/moodle
TEST=/opt/moodle-test

cd "$TEST"
docker compose config | head -1 | grep -qx 'name: moodle-test' || { echo "Nom de projet inattendu, arrêt." >&2; exit 1; }
# Première fois : l'image de la production, en attendant celle de dev envoyée par la CI.
if ! grep -q '^MOODLE_IMAGE=' .env; then
    grep '^MOODLE_IMAGE=' "$PROD/.env" >> .env
fi

echo "== Arrêt de la copie, base de test vidée"
docker compose up --no-start
docker compose stop moodle cron >/dev/null 2>&1 || true
docker compose up -d --wait db mailpit
docker compose exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS \`$MYSQL_DATABASE\`; CREATE DATABASE \`$MYSQL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"' 2>/dev/null

echo "== Base de la production -> copie"
(cd "$PROD" && docker compose exec -T db sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --default-character-set=utf8mb4 "$MYSQL_DATABASE"' 2>/dev/null) \
    | docker compose exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" --default-character-set=utf8mb4 "$MYSQL_DATABASE"' 2>/dev/null

echo "== Fichiers de la production -> copie (sans les caches)"
docker run --rm -v moodle_moodledata:/from:ro -v moodle-test_moodledata:/to alpine sh -c \
    'find /to -mindepth 1 -delete && cd /from && tar cf - --exclude=./cache --exclude=./localcache --exclude=./sessions --exclude=./temp --exclude=./trashdir --exclude=./lock . | tar xpf - -C /to'

echo "== Démarrage de la copie (mises à jour appliquées au démarrage)"
docker compose up -d --wait --wait-timeout 900
docker compose exec -T -u www-data moodle php < neutralize.php
echo "Copie de test à jour : https://test.oriskill.com"
