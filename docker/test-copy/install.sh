#!/bin/bash
# Installe la copie de test d'Oriskill (https://test.oriskill.com) à côté de la production, sans toucher à
# celle-ci : son propre dossier (/opt/moodle-test), sa propre base, ses propres fichiers, le port 8081.
# À lancer une seule fois sur le serveur, depuis le dossier qui contient ce script :  sudo bash install.sh
# Prérequis : un enregistrement DNS A « test.oriskill.com » vers l'adresse du serveur.
set -euo pipefail

PROD=/opt/moodle
TEST=/opt/moodle-test
HERE=$(cd "$(dirname "$0")" && pwd)
OWNER=${SUDO_USER:-ubuntu}

[ "$(id -u)" = 0 ] || { echo "À lancer avec sudo." >&2; exit 1; }
[ -f "$PROD/.env" ] || { echo "Production introuvable dans $PROD." >&2; exit 1; }

echo "== 1. Place sur le disque système : anciennes images de l'application (récupérables sur GitHub)"
used=$(docker ps -a --format '{{.Image}}' | sort -u)
for img in $(docker images --format '{{.Repository}}:{{.Tag}}' | grep -E '^(ghcr\.io/[^/]+/moodle|moodle-local):'); do
    if ! grep -qxF "$img" <<<"$used"; then
        docker rmi "$img" >/dev/null && echo "   retirée : $img"
    fi
done
df -h / | tail -1

echo "== 2. Dossier $TEST"
mkdir -p "$TEST"
cp "$PROD/docker-compose.yml" "$TEST/docker-compose.yml"
cp "$HERE/docker-compose.override.yml" "$TEST/docker-compose.override.yml"
if [ ! -f "$TEST/.env" ]; then
    # Les réglages de la production, sauf ce qui doit être propre à la copie (adresse, port, base, e-mails).
    grep -vE '^(COMPOSE_|MOODLE_HTTP_PORT|MAILPIT_|MOODLE_WWWROOT|MOODLE_SSLPROXY|MOODLE_DB_PASSWORD|MYSQL_ROOT_PASSWORD|MOODLE_SMTP_|MOODLE_STAGING)' \
        "$PROD/.env" > "$TEST/.env"
    cat >> "$TEST/.env" <<EOF
COMPOSE_PROJECT_NAME=moodle-test
COMPOSE_PROFILES=mail-test
MOODLE_HTTP_PORT=127.0.0.1:8081
MAILPIT_HTTP_PORT=127.0.0.1:8026
MOODLE_WWWROOT=https://test.oriskill.com
MOODLE_SSLPROXY=true
MOODLE_DB_PASSWORD=$(openssl rand -hex 16)
MYSQL_ROOT_PASSWORD=$(openssl rand -hex 16)
MOODLE_SMTP_HOST=mailpit:1025
EOF
fi
chown -R "$OWNER:$OWNER" "$TEST"
chmod 600 "$TEST/.env"
cd "$TEST"
# Garde-fou : la copie ne doit jamais prendre le nom (donc les conteneurs et les volumes) de la production.
docker compose config | head -1 | grep -qx 'name: moodle-test' || { echo "Nom de projet inattendu, arrêt." >&2; exit 1; }

echo "== 3. Commande de recopie"
install -m 755 "$HERE/refresh.sh" /usr/local/bin/oriskill-test-refresh
install -m 644 "$HERE/neutralize.php" "$TEST/neutralize.php"

echo "== 4. Caddy : test.oriskill.com, protégé par un mot de passe"
if ! grep -q '^test.oriskill.com' /etc/caddy/Caddyfile; then
    password=$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-16)
    hash=$(caddy hash-password --plaintext "$password")
    cp /etc/caddy/Caddyfile "/etc/caddy/Caddyfile.avant-copie-test"
    sed "s|@HASH@|$hash|" "$HERE/Caddyfile.test" >> /etc/caddy/Caddyfile
    if caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null 2>&1; then
        systemctl reload caddy
        printf 'Copie de test : https://test.oriskill.com\nIdentifiant : oriskill\nMot de passe : %s\n' "$password" \
            > "$TEST/acces-copie-test.txt"
        chown "$OWNER:$OWNER" "$TEST/acces-copie-test.txt"
        chmod 600 "$TEST/acces-copie-test.txt"
    else
        cp "/etc/caddy/Caddyfile.avant-copie-test" /etc/caddy/Caddyfile
        echo "Configuration Caddy refusée : fichier remis comme avant." >&2
        exit 1
    fi
fi

echo "== 5. Première recopie de la production"
sudo -u "$OWNER" /usr/local/bin/oriskill-test-refresh

echo
cat "$TEST/acces-copie-test.txt"
echo "(ces accès sont gardés dans $TEST/acces-copie-test.txt)"
