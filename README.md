# Oriskill — plateforme de formation (Moodle avec Docker + CI/CD GitHub Actions)

Site de production : https://oriskill.com

Moodle 5.3 (PHP 8.4 / Apache) + MySQL 8.4, avec un pipeline qui teste,
publie l'image sur GitHub Container Registry (GHCR) et déploie sur un serveur via SSH.

```
.
├── moodle/                    # code source Moodle 5.3 (copié tel quel dans l'image)
├── Dockerfile                 # image Moodle (PHP 8.4 + Apache + dossier moodle/)
├── docker-compose.yml         # services : db (MySQL), moodle, cron
├── docker/
│   ├── apache-moodle.conf     # Apache : DocumentRoot moodle/public + routeur r.php
│   ├── config.php             # config Moodle lue depuis les variables d'env
│   ├── entrypoint.sh          # attend la DB, installe ou met à jour Moodle
│   ├── db-status.php          # état de la DB (down / empty / installed)
│   └── php.ini                # réglages PHP recommandés
├── .env.example               # modèle de configuration (à copier en .env)
└── .github/workflows/ci-cd.yml
```

## 1. Lancer Moodle en local

Prérequis : Docker Desktop démarré.

```powershell
Copy-Item .env.example .env     # puis changez les mots de passe dans .env
docker compose up -d --build
docker compose logs -f moodle   # suivre l'installation (3 à 10 min la 1re fois)
```

Ouvrez http://localhost:8080 et connectez-vous avec `MOODLE_ADMIN_USER` / `MOODLE_ADMIN_PASSWORD`.

Commandes utiles :

| Action | Commande |
|---|---|
| Arrêter | `docker compose down` |
| Tout supprimer (données comprises) | `docker compose down -v` |
| Purger les caches Moodle | `docker compose exec -u www-data moodle php admin/cli/purge_caches.php` |
| Sauvegarder la base | `docker compose exec db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" moodle' > backup.sql` |

> `MOODLE_WWWROOT` doit correspondre **exactement** à l'URL utilisée dans le navigateur,
> sinon Moodle redirige ou affiche une erreur.

## 2. Le pipeline CI/CD

Fichier : `.github/workflows/ci-cd.yml`

| Job | Quand | Ce qu'il fait |
|---|---|---|
| **lint** | chaque push / pull request | hadolint (Dockerfile), shellcheck, `php -l`, validation compose |
| **test** | après lint | build de l'image, installation complète de Moodle, vérifie que `/login/index.php` répond 200 |
| **publish** | push sur `main` | pousse `ghcr.io/<compte>/moodle:latest` et `:<sha du commit>` |
| **deploy** | après publish, si `DEPLOY_ENABLED=true` | copie `docker-compose.yml` sur le serveur, met à jour `MOODLE_IMAGE`, `pull` + `up -d` |

Au redémarrage, le conteneur lance automatiquement `admin/cli/upgrade.php` : les
mises à jour de Moodle se font donc en modifiant l'image puis en poussant sur `main`.

## 3. Mettre le projet sur GitHub

1. Sur https://github.com/new créez un dépôt **vide** nommé `moodle-docker`
   (sans README, sans .gitignore).
2. Dans ce dossier :

```powershell
git remote add origin https://github.com/<votre-compte>/moodle-docker.git
git push -u origin main
```

L'onglet **Actions** du dépôt montre alors lint → test → publish. Le job deploy est
ignoré tant que le déploiement n'est pas configuré (étape 4).

## 4. Activer le déploiement automatique (optionnel)

Il faut un serveur Linux (VPS) accessible en SSH avec Docker installé
(`curl -fsSL https://get.docker.com | sh`).

**Sur le serveur :**

```bash
sudo mkdir -p /opt/moodle && sudo chown $USER /opt/moodle
cd /opt/moodle
nano .env          # contenu de .env.example avec vos vraies valeurs, par ex.
                   # MOODLE_WWWROOT=https://moodle.mondomaine.fr
                   # MOODLE_HTTP_PORT=80   (ou 8080 derrière un reverse proxy)
sudo usermod -aG docker $USER   # l'utilisateur SSH doit pouvoir lancer docker
```

**Clé SSH dédiée au déploiement** (sur votre PC) :

```powershell
ssh-keygen -t ed25519 -f moodle_deploy -N '""' -C "github-actions"
```

Ajoutez le contenu de `moodle_deploy.pub` à `~/.ssh/authorized_keys` sur le serveur.

**Dans GitHub** → *Settings* → *Environments* → créez l'environnement `OriSkill`
(vous pouvez y exiger une approbation manuelle avant chaque déploiement), puis
ajoutez-y ces **secrets** :

| Secret | Valeur |
|---|---|
| `SSH_HOST` | IP ou nom du serveur |
| `SSH_USER` | utilisateur SSH |
| `SSH_PRIVATE_KEY` | contenu complet du fichier `moodle_deploy` (clé privée) |
| `SSH_PORT` | *(optionnel)* défaut `22` |
| `DEPLOY_PATH` | *(optionnel)* défaut `/opt/moodle` |

Enfin, *Settings* → *Secrets and variables* → *Actions* → onglet **Variables** :
créez `DEPLOY_ENABLED` = `true`.

Le prochain push sur `main` déploiera automatiquement. Vous pouvez aussi relancer
le pipeline à la main depuis l'onglet Actions (*Run workflow*).

### HTTPS en production

Placez un reverse proxy (Caddy, Nginx, Traefik) devant le port de Moodle, puis dans
le `.env` du serveur : `MOODLE_WWWROOT=https://...` et `MOODLE_SSLPROXY=true`.

### Domaine personnalisé d'une vitrine formateur

Un formateur peut relier son propre domaine à sa vitrine (Mon espace formateur → Ma vitrine →
« Nom de domaine personnalisé »). Il fait pointer le domaine et `www` vers l'IP du serveur
(enregistrements A), et Caddy sert la vitrine sur ce domaine avec un certificat créé à la demande.

À ajouter une fois dans le `Caddyfile` du serveur (à côté du bloc `oriskill.com` existant) :

```caddyfile
{
    on_demand_tls {
        # Certificat seulement pour le domaine d'un formateur actif (200), sinon refus (404).
        ask https://oriskill.com/local/oriskill/domaincheck.php
    }
}

https:// {
    tls {
        on_demand
    }
    # La page d'accueil du domaine est la vitrine du formateur.
    @root path /
    handle @root {
        rewrite * /local/oriskill/creator.php?domain={host}
        reverse_proxy 127.0.0.1:8080 {
            header_up Host oriskill.com
        }
    }
    # Ses pages de capture : /p/nom-de-la-page (le formulaire y est renvoyé, même adresse).
    @capture path_regexp capture ^/p/([a-z0-9-]+)/?$
    handle @capture {
        rewrite * /local/oriskill/capture.php?domain={host}&slug={re.capture.1}
        reverse_proxy 127.0.0.1:8080 {
            header_up Host oriskill.com
        }
    }
    # Les fiches de ses formations : /formation/numéro.
    @course path_regexp course ^/formation/([0-9]+)/?$
    handle @course {
        rewrite * /local/oriskill/coursepage.php?domain={host}&id={re.course.1}
        reverse_proxy 127.0.0.1:8080 {
            header_up Host oriskill.com
        }
    }
    # Ses bons de commande : /bdc/nom-du-bon.
    @bdc path_regexp bdc ^/bdc/([a-z0-9-]+)/?$
    handle @bdc {
        rewrite * /local/oriskill/order.php?domain={host}&slug={re.bdc.1}
        reverse_proxy 127.0.0.1:8080 {
            header_up Host oriskill.com
        }
    }
    # Tout le reste (formations, paiement, connexion) se passe sur oriskill.com.
    handle {
        redir https://oriskill.com{uri} 301
    }
}
```

Le choix d'une offre, la connexion et le paiement se font ensuite sur oriskill.com.

Puis `sudo systemctl reload caddy`. Le formulaire du formateur indique si le domaine pointe déjà
vers le serveur.

## Changer de version de Moodle

Remplacez le contenu du dossier `moodle/` par la nouvelle version (sans y laisser de
`config.php`), commitez puis poussez sur `main` : la CI reconstruit l'image et, au
démarrage, le conteneur lance la mise à jour de la base (`upgrade.php`).
Depuis Moodle 5.1, le code exposé au web est dans `moodle/public/` (le DocumentRoot
Apache pointe dessus) et `config.php` se trouve à la racine de `moodle/`.
