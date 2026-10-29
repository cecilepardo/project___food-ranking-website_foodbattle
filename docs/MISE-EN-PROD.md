# Mettre FoodBattle en production

Ce document décrit le passage d'une installation locale ([INSTALLATION.md](INSTALLATION.md)) à un serveur
accessible au public. Il sépare **ce qui est déjà prêt** de **ce qui reste à faire**, et justifie chaque point.

> État : la configuration de production n'est pas encore en place dans le dépôt.
> La section 3 en donne le contenu proposé, à valider et à appliquer en équipe.

## 1. Local et production : ce qui change

| Point | Local (aujourd'hui) | Production (cible) | Pourquoi |
|---|---|---|---|
| Mode d'erreur | `APP_ENV=dev` : détail de l'erreur sur la page | `APP_ENV=prod` : page 500 sans détail, trace dans les logs | Un chemin de fichier ou une requête SQL aide un attaquant |
| `php.ini` | Absent : valeurs par défaut de PHP (`display_errors` actif) | `php.ini-production` | Une erreur survenue avant `index.php` ne doit rien afficher non plus |
| Code | Monté depuis le poste (modifiable à chaud) | Copié dans l'image | Le code en ligne est exactement celui qui a été construit et testé |
| Cache PHP (OPcache) | Relit les fichiers à chaque requête | Ne relit jamais (le code ne change pas dans l'image) | Plus rapide ; un changement de code passe par une nouvelle image |
| Secrets | En clair dans `compose.yaml` (`foodbattle_local`) | Fichier `.env.prod` hors Git, démarrage refusé s'il manque | Un secret versionné n'est plus secret |
| Comptes | Comptes de démo aux mots de passe publics | Aucun compte connu ; premier admin créé à l'installation | Les mots de passe de démo sont écrits dans le README |
| HTTPS | Non (http://localhost) | Oui, via un proxy (Caddy, Nginx…) | Mots de passe et cookie de session chiffrés sur le réseau |
| Adresse des visiteurs | Celle de la passerelle Docker | La vraie IP, transmise par le proxy | Sinon la limite de 20 tentatives par IP bloque tout le monde à la fois |
| Photos envoyées depuis le dashboard (#47) | Dans `public/uploads/` du poste | Dans un volume Docker dédié, sauvegardé | Sinon elles disparaissent à chaque reconstruction de l'image |
| Redémarrage | Manuel | Automatique (`restart: unless-stopped`) | Le site revient seul après un redémarrage du serveur |

## 2. Déjà prêt

Ces protections sont actives dans le code et n'ont rien à changer pour la production :

- Apache ne sert que `public/` : `app/`, `config/`, `database/` et `tests/` sont inaccessibles depuis le navigateur.
- Versions d'Apache et de PHP masquées (`ServerTokens Prod`, `expose_php = Off`).
- En-têtes `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
  `Content-Security-Policy` (nos propres fichiers uniquement, photos comprises) et `Referrer-Policy`.
- Page 500 sans détail technique dès que `APP_ENV` vaut autre chose que `dev`
  (et `prod` est la valeur par défaut si la variable manque).
- Cookie de session `HttpOnly`, `SameSite=Lax`, mode strict, identifiant renouvelé à la connexion.
- Jeton CSRF sur tous les formulaires, renouvelé à la connexion.
- Rôle admin relu en base à chaque page d'administration.
- Photos des plats hébergées sur le site : aucun service extérieur.
- Limitation des tentatives de connexion (par IP et par compte).
- Le site n'écoute que sur `127.0.0.1` : il n'est joignable qu'à travers le proxy de la machine.
- La base n'a aucun port ouvert hors de Docker.

## 3. À faire avant la première mise en ligne

### 3.1 Un fichier `compose.prod.yaml`

À part de `compose.yaml`, pour que l'usage local ne change pas.

```yaml
# Production : lancer avec
#   docker compose -f compose.prod.yaml --env-file .env.prod up -d --build
services:
  app:
    build:
      context: .
      args:
        APP_ENV: prod              # image de production (voir 3.4)
    restart: unless-stopped
    ports:
      - "127.0.0.1:${APP_PORT:-8080}:80"   # joignable seulement par le proxy HTTPS de la machine
    environment:
      APP_ENV: prod
      APP_HTTPS: "1"                        # voir 3.5
      DB_HOST: db
      DB_PORT: 5432
      DB_NAME: ${DB_NAME:?DB_NAME manquant dans .env.prod}
      DB_USER: ${DB_USER:?DB_USER manquant dans .env.prod}
      DB_PASSWORD: ${DB_PASSWORD:?DB_PASSWORD manquant dans .env.prod}
    volumes:
      # Seul dossier modifiable : les photos envoyées depuis le dashboard (#47).
      # Le reste du code est celui copié dans l'image.
      - foodbattle_uploads:/var/www/html/public/uploads
    depends_on:
      db:
        condition: service_healthy

  db:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_NAME:?}
      POSTGRES_USER: ${DB_USER:?}
      POSTGRES_PASSWORD: ${DB_PASSWORD:?}
    volumes:
      - foodbattle_prod_data:/var/lib/postgresql/data
      - ./database/init.sql:/docker-entrypoint-initdb.d/01-init.sql:ro
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER} -d $${POSTGRES_DB}"]
      interval: 3s
      timeout: 3s
      retries: 20

volumes:
  foodbattle_prod_data:
  foodbattle_uploads:
```

`${VAR:?message}` fait **échouer le démarrage** si la variable manque, au lieu de lancer le site avec une valeur vide.

Le fichier `.env.prod`, à créer sur le serveur seulement (jamais dans Git) :

```dotenv
APP_PORT=8080
DB_NAME=foodbattle
DB_USER=foodbattle
DB_PASSWORD=<secret de 32 caractères ou plus, ex. : openssl rand -base64 32>
```

À ajouter à `.gitignore` : `.env.prod`. À ajouter à `.dockerignore` : `tests/`, `docs/`, `.env.prod`
(inutiles ou sensibles dans l'image). `public/uploads/` y est déjà : les photos d'un poste de développement
ne partent pas dans l'image.

### 3.2 `config.php` : pas de mot de passe par défaut en production

Aujourd'hui, si `DB_PASSWORD` manque, `config.php` utilise `foodbattle_local`. En production, l'application
doit refuser de démarrer plutôt que se connecter avec un mot de passe connu :

```php
$env = getenv('APP_ENV') ?: 'prod';

// Le mot de passe local par défaut n'est accepté qu'en dev : ailleurs, son absence est une erreur de configuration.
$dbPassword = getenv('DB_PASSWORD');
if ($dbPassword === false || $dbPassword === '') {
    if ($env !== 'dev') {
        throw new RuntimeException('DB_PASSWORD manquant : configuration de production incomplète.');
    }
    $dbPassword = 'foodbattle_local';
}

return [
    'env'   => $env,
    'https' => getenv('APP_HTTPS') === '1',   // voir 3.5
    'db'    => [
        // … host, port, name, user inchangés …
        'password' => $dbPassword,
    ],
];
```

### 3.3 Comptes et données de démonstration hors de la production

`init.sql` mélange aujourd'hui la **structure** de la base (tables, contraintes) et des **données de démonstration**
(établissements et plats fictifs, 40 comptes dont un admin aux mots de passe publiés, votes, avis).

Proposition :

| Fichier | Contenu | Chargé en local | Chargé en production |
|---|---|---|---|
| `database/init.sql` | Extension, tables, contraintes | oui | oui |
| `database/demo.sql` | Établissements, plats, comptes, votes, avis de démo | oui (`compose.yaml`, en `02-demo.sql`) | **non** |

La production démarre avec une base vide ; l'admin crée établissements et plats depuis le dashboard.

**Créer le premier admin**, sans écrire de mot de passe dans un terminal :
1. s'inscrire normalement sur `https://<domaine>/connexion` ;
2. sur le serveur, lui donner le rôle admin :
   ```bash
   docker compose -f compose.prod.yaml --env-file .env.prod exec db \
     psql -U foodbattle -c "UPDATE users SET role = 'admin' WHERE email = 'admin@exemple.fr';"
   ```
3. se reconnecter pour voir apparaître le lien « Dashboard » dans l'en-tête
   (l'accès aux pages admin, lui, est vérifié en base dès la requête suivante).

### 3.4 Une image Docker locale ou de production

Le `Dockerfile` reçoit un argument `APP_ENV` (`dev` par défaut, pour que `compose.yaml` ne change pas) :

```dockerfile
ARG APP_ENV=dev

# php.ini : réglages recommandés par PHP selon l'environnement
# (en production : display_errors désactivé, erreurs envoyées dans les logs)
RUN if [ "$APP_ENV" = "prod" ]; then \
        cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    else \
        cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"; \
    fi

# Cache PHP : en dev, un fichier modifié est relu tout de suite ;
# en production, le code ne change pas dans l'image : inutile de vérifier les fichiers.
RUN if [ "$APP_ENV" = "prod" ]; then \
        echo 'opcache.validate_timestamps = 0' > /usr/local/etc/php/conf.d/opcache.ini; \
    else \
        echo 'opcache.revalidate_freq = 0' > /usr/local/etc/php/conf.d/opcache.ini; \
    fi
```

Ce bloc remplace la ligne `opcache-dev.ini` actuelle du `Dockerfile`.
Conséquence en production : toute modification du code passe par `--build` (voir § 5).

### 3.5 HTTPS

Le HTTPS est géré par un **proxy** installé sur le serveur (Caddy obtient et renouvelle seul le certificat) :

```
# /etc/caddy/Caddyfile
foodbattle.exemple.fr {
    reverse_proxy 127.0.0.1:8080
}
```

Le proxy parle à FoodBattle en HTTP : PHP ne voit donc pas le HTTPS, et le cookie de session n'est
**pas** marqué `Secure`. L'option `APP_HTTPS=1` (voir 3.2) corrige ce point dans `public/index.php` :

```php
$https = $config['https'] || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $https,
]);

if ($https) {
    // Le navigateur n'utilisera plus que le HTTPS pour ce site pendant 1 an
    header('Strict-Transport-Security: max-age=31536000');
}
```

Même règle pour le cookie du thème (`Theme::toggle()`), qui teste aujourd'hui `$_SERVER['HTTPS']` lui-même.

### 3.6 La vraie adresse IP des visiteurs

Derrière le proxy, toutes les requêtes arrivent avec l'adresse du proxy. Conséquence : la limite de
20 tentatives de connexion **par IP** s'appliquerait à tous les visiteurs ensemble.

Le proxy transmet l'adresse réelle dans l'en-tête `X-Forwarded-For`. Le module Apache `remoteip` la reprend,
**uniquement si la requête vient du proxy** (sinon n'importe qui pourrait inventer son adresse) :

```dockerfile
RUN if [ "$APP_ENV" = "prod" ]; then \
        a2enmod remoteip \
        && printf 'RemoteIPHeader X-Forwarded-For\nRemoteIPInternalProxy 172.16.0.0/12\n' \
           > /etc/apache2/conf-enabled/remoteip.conf; \
    fi
```

`172.16.0.0/12` couvre les réseaux Docker : avec le proxy sur la machine hôte, les requêtes arrivent
par la passerelle Docker. À ajuster si le proxy est ailleurs. Le code PHP (`ThrottleAuth`) n'a pas à changer :
il lit `REMOTE_ADDR`, que le module remplace.

### 3.7 Photos envoyées depuis le dashboard (#47)

L'admin peut joindre une photo à un plat ; elle est enregistrée dans `public/uploads/plats/`.

- **Volume dédié** (voir 3.1) : sans lui, le dossier fait partie de l'image et les photos
  sont **perdues à chaque mise à jour** (`--build`).
- **Droits d'écriture** : Apache (utilisateur `www-data`) doit pouvoir écrire dans le dossier.
  Un volume neuf appartient à `root` ; à créer dans l'image avec le bon propriétaire :
  ```dockerfile
  RUN mkdir -p /var/www/html/public/uploads/plats \
      && chown -R www-data:www-data /var/www/html/public/uploads
  ```
  (Docker recopie ce propriétaire dans le volume à sa création.)
- **Aucun script exécuté dans ce dossier** : le site renomme déjà chaque fichier
  (`{id}.jpg|png|webp`) après avoir vérifié son contenu. Par précaution, si un bug contournait ce contrôle,
  Apache refuse en plus d'exécuter du PHP à cet endroit :
  ```dockerfile
  RUN printf '<Directory /var/www/html/public/uploads>\n    php_admin_flag engine off\n</Directory>\n' \
      > /etc/apache2/conf-enabled/uploads.conf
  ```
- **Sauvegarde** : les photos ne sont pas dans la base ; elles se sauvegardent à part (§ 6).

### 3.8 Logs

Les erreurs PHP et les accès Apache partent dans la sortie du conteneur :
`docker compose -f compose.prod.yaml logs -f app`.

Pour éviter qu'ils remplissent le disque, limiter leur taille dans `compose.prod.yaml` (pour `app` et `db`) :

```yaml
    logging:
      options:
        max-size: "10m"
        max-file: "5"
```

## 4. Déployer : de la machine vide au site en ligne

Prérequis : un serveur Linux avec Docker et Docker Compose, un nom de domaine qui pointe vers lui,
les ports 80 et 443 ouverts (pour le proxy HTTPS).

1. Installer Caddy (ou un autre proxy) et écrire le `Caddyfile` (§ 3.5).
2. Récupérer le code, sur la version validée :
   ```bash
   git clone <url-du-dépôt> /opt/foodbattle && cd /opt/foodbattle
   git checkout <tag ou commit validé>
   ```
3. Créer `.env.prod` (§ 3.1), lisible par son seul propriétaire : `chmod 600 .env.prod`.
4. Construire et démarrer :
   ```bash
   docker compose -f compose.prod.yaml --env-file .env.prod up -d --build
   ```
5. Vérifier : `docker compose -f compose.prod.yaml ps` (deux services `running`, `db` en `healthy`),
   puis ouvrir `https://<domaine>`.
6. Créer le premier admin (§ 3.3).
7. Ajouter établissements et plats depuis le dashboard.
8. Mettre en place la sauvegarde (§ 6) et dérouler la liste de vérifications (§ 7).

## 5. Mettre à jour le site

```bash
cd /opt/foodbattle
git fetch && git checkout <nouvelle version>
docker compose -f compose.prod.yaml --env-file .env.prod up -d --build
```

Les données sont conservées (volume `foodbattle_prod_data`). Pendant la reconstruction, le site reste
en ligne ; il est indisponible quelques secondes au redémarrage du conteneur `app`.

**Si la nouvelle version modifie la structure de la base** : `init.sql` ne s'exécute **pas** sur une base existante.
Il faut écrire le changement dans un fichier SQL à part (ex. `database/migrations/2026-11-02-ajout-colonne.sql`),
**sauvegarder** (§ 6), puis l'appliquer :

```bash
docker compose -f compose.prod.yaml --env-file .env.prod exec -T db \
  psql -U foodbattle -d foodbattle < database/migrations/2026-11-02-ajout-colonne.sql
```

et reporter le même changement dans `init.sql`, pour les nouvelles installations.

## 6. Sauvegarder et restaurer la base

**Sauvegarde** (format compressé de PostgreSQL) :

```bash
docker compose -f compose.prod.yaml --env-file .env.prod exec -T db \
  pg_dump -U foodbattle -Fc foodbattle > /opt/sauvegardes/foodbattle-$(date +%F).dump
```

À automatiser une fois par jour (cron), en gardant au moins 7 jours, et à copier **hors du serveur**.

**Restauration** (remplace les données actuelles) :

```bash
docker compose -f compose.prod.yaml --env-file .env.prod exec -T db \
  pg_restore -U foodbattle -d foodbattle --clean --if-exists < foodbattle-2026-11-02.dump
```

**Photos envoyées** (#47), qui ne sont pas dans la base :

```bash
docker run --rm -v foodbattle_uploads:/uploads -v /opt/sauvegardes:/backup alpine \
  tar czf /backup/uploads-$(date +%F).tar.gz -C /uploads .
```

Restauration : même commande avec `tar xzf /backup/uploads-<date>.tar.gz -C /uploads`.
Le nom exact du volume est préfixé par le nom du projet : `docker volume ls` pour le vérifier.

Une sauvegarde n'est fiable que si sa restauration a été testée : le faire au moins une fois,
sur une installation locale.

## 7. Vérifications avant la mise en ligne

- [ ] `.env.prod` existe, `chmod 600`, absent de Git ; `DB_PASSWORD` long et aléatoire
- [ ] Le site démarre avec `compose.prod.yaml` ; `APP_ENV=prod` dans le conteneur
- [ ] Aucun compte de démo : `SELECT email FROM users;` ne renvoie que les vrais comptes
- [ ] Un admin existe et accède au dashboard
- [ ] `https://` fonctionne ; `http://` redirige vers `https://`
- [ ] Le cookie `PHPSESSID` porte `Secure` ; l'en-tête `Strict-Transport-Security` est présent
- [ ] Une URL inexistante affiche la page 404 du site ; une erreur, la page 500 **sans détail**
- [ ] Les en-têtes `Content-Security-Policy`, `X-Frame-Options`, `Referrer-Policy` sont présents
- [ ] `/config/config.php`, `/database/init.sql`, `/.env.prod` répondent 404
- [ ] La base n'est pas joignable depuis l'extérieur (aucun port 5432 ouvert)
- [ ] Après 20 tentatives de connexion depuis une machine, une **autre** machine peut encore se connecter (vraie IP, § 3.6)
- [ ] Une photo envoyée depuis le dashboard s'affiche, et survit à `up -d --build` (volume, § 3.7)
- [ ] `/uploads/plats/` n'exécute pas de PHP (§ 3.7)
- [ ] Une sauvegarde (base **et** photos) a été faite et restaurée avec succès

## 8. Limites connues

- **Pas d'outil de migration** : les évolutions de la base s'appliquent à la main (§ 5).
- **Un seul serveur** : les sessions sont des fichiers dans le conteneur `app`. Un redémarrage
  déconnecte les utilisateurs ; plusieurs serveurs demanderaient des sessions partagées (en base, par exemple).
- **Pas d'envoi d'email** : pas de mot de passe oublié. Un admin peut remettre un mot de passe en base.
- **Photos envoyées** (#47) : pas de redimensionnement ni de remplacement de la photo d'un plat existant
  (la page Modifier ne gère que le nom et le prix).
- **Métadonnées des photos envoyées** : elles sont gardées telles quelles, y compris la position GPS
  d'une photo prise au téléphone. Les retirer demande de réencoder l'image (extension PHP GD, non installée).
- **Énumération des emails** : l'étape 1 de la connexion indique si un email a un compte. Choix d'ergonomie
  (connexion et inscription dans un seul formulaire), limité par les 20 tentatives par IP.
