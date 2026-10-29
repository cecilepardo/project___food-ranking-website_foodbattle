# Installer et lancer FoodBattle en local

Ce document décrit l'installation sur un poste de développement.
Pour un serveur de production, voir [MISE-EN-PROD.md](MISE-EN-PROD.md).

## 1. Prérequis

| Outil | Version testée | Rôle |
|---|---|---|
| Docker | 29.8 | Fait tourner l'application et la base, sans rien installer d'autre sur le poste |
| Docker Compose | v5.6 (commande `docker compose`) | Démarre les deux conteneurs ensemble |
| Git | toute version récente | Récupérer le code |
| Bash + curl | ceux du système (Git Bash sous Windows) | Uniquement pour lancer les tests |

PHP, Apache et PostgreSQL n'ont **pas** à être installés sur le poste : ils sont dans les conteneurs.

Port utilisé : **8081** sur le poste (modifiable, voir § 8). La base n'est pas exposée hors de Docker.

## 2. Premier lancement

```bash
git clone <url-du-dépôt> foodbattle
cd foodbattle
docker compose up --build
```

Puis ouvrir http://localhost:8081.

Ce que fait cette commande :

1. **Construit l'image `app`** (`Dockerfile`) : PHP 8.3 + Apache, extension PostgreSQL,
   Apache configuré pour ne servir que `public/` (le reste du code n'est pas accessible depuis le navigateur),
   versions d'Apache et de PHP masquées.
2. **Démarre `db`** (PostgreSQL 16). Au **tout premier démarrage** seulement (volume de données vide),
   PostgreSQL exécute `database/init.sql` : tables, contraintes, établissements, plats, comptes et avis de démonstration.
3. **Démarre `app` une fois la base prête** (`depends_on` + `healthcheck` : `app` attend que `pg_isready` réponde).

En local, le dossier du projet est monté dans le conteneur : une modification de code est visible
au rechargement de la page, sans reconstruire l'image.

## 3. Comptes de démonstration

Créés par `init.sql`, **pour le local uniquement** (voir MISE-EN-PROD.md : ils ne doivent pas exister en production).

| Rôle | Email | Mot de passe | Pour tester |
|---|---|---|---|
| admin | `admin@foodbattle.local` | `foodbattle-admin` | Dashboard (plats, établissements), modération des avis |
| user | `user@foodbattle.local` | `foodbattle-user` | Vote, avis, parcours d'un visiteur inscrit |
| user | `gourmand1@foodbattle.local` … `gourmand38@foodbattle.local` | `foodbattle-demo` | Portent les votes et avis de démonstration |

Un compte créé depuis le site (`/connexion`) est toujours `user`. Un admin ne se crée qu'en base :

```bash
docker compose exec db psql -U foodbattle -c "UPDATE users SET role = 'admin' WHERE email = 'moi@exemple.fr';"
```

## 4. Au quotidien

| Besoin | Commande |
|---|---|
| Démarrer en arrière-plan | `docker compose up -d` |
| Arrêter (les données sont gardées) | `docker compose down` |
| Voir les logs PHP / Apache | `docker compose logs -f app` |
| Ouvrir la base en ligne de commande | `docker compose exec db psql -U foodbattle` |
| Reconstruire l'image | `docker compose up -d --build` |

**Quand reconstruire l'image ?** Seulement si le `Dockerfile` change (nouvelle extension, réglage PHP…).
Le code PHP, le CSS et le JS n'en ont pas besoin : ils sont montés depuis le poste.

## 5. Remettre la base à zéro

```bash
docker compose down -v   # -v supprime le volume de données
docker compose up -d     # init.sql est rejoué
```

**Tout est perdu** : comptes créés, votes, avis, plats ajoutés depuis le dashboard.
C'est aussi la seule façon d'appliquer une modification de `init.sql` (il n'est exécuté que sur une base vide).

## 6. Lancer les tests

L'application doit tourner (`docker compose up -d`). Depuis la racine du projet :

```bash
bash tests/auth.sh       # authentification, limitation des tentatives, redirections
bash tests/features.sh   # votes, avis, dashboard, sécurité, accessibilité…
```

- Chaque ligne affiche ✅ ou ❌ ; le script se termine en erreur s'il y a au moins un ❌.
- Les tests **écrivent dans la base locale** (comptes de test, votes) : jamais sur une base de production.
- Pour garder la réponse du serveur de chaque test en échec (PR #44) : `DUMP=/tmp/echecs bash tests/features.sh`.

Détails et écriture de nouveaux tests : `tests/README.md`.

## 7. Dépannage

| Symptôme | Cause | Solution |
|---|---|---|
| `port is already allocated` au démarrage | Le port 8081 est pris par un autre programme | Changer de port (§ 8) : `APP_PORT=8082 docker compose up -d` |
| « Trop de tentatives » sur `/connexion` | Limite de 20 formulaires par IP en 15 min. En local, toutes les requêtes ont la même IP (passerelle Docker) | `docker compose exec db psql -U foodbattle -c "DELETE FROM rate_limit_hits;"` |
| Compte bloqué après 5 mots de passe faux | Limite par compte (15 min) | Même commande que ci-dessus |
| Ancienne version d'une page juste après une modification | Cache PHP (OPcache), jusqu'à 2 s de retard | Recharger. Réglé par la PR #44 (`revalidate_freq = 0` en local), après `docker compose up -d --build` |
| `init.sql` modifié mais rien ne change | Il ne s'exécute que sur une base vide | § 5 (remise à zéro) |
| `app` ne démarre pas, attend `db` | La base n'a pas fini de démarrer, ou a échoué | `docker compose logs db` |
| Page 500 | Erreur PHP. En local, le détail s'affiche sur la page | `docker compose logs app` pour la trace complète |
| Photos des plats absentes | Elles viennent de images.pexels.com : pas de connexion Internet | L'image de secours s'affiche ; le reste du site fonctionne |

## 8. Configuration

Les réglages passent par des variables d'environnement, définies dans `compose.yaml`
et lues par `config/config.php`.

| Variable | Rôle | Valeur locale | En production |
|---|---|---|---|
| `APP_PORT` | Port du site sur le poste (`.env` ou ligne de commande) | `8081` | Selon le serveur (voir MISE-EN-PROD.md) |
| `APP_ENV` | `dev` affiche le détail des erreurs sur la page | `dev` | `prod` (valeur par défaut si absente) |
| `DB_HOST` | Hôte PostgreSQL | `db` (nom du service) | `db` |
| `DB_PORT` | Port PostgreSQL | `5432` | `5432` |
| `DB_NAME` | Nom de la base | `foodbattle` | au choix |
| `DB_USER` | Utilisateur PostgreSQL | `foodbattle` | au choix |
| `DB_PASSWORD` | Mot de passe PostgreSQL | `foodbattle_local` | **secret fort, obligatoire** |
| `BASE` | URL visée par les tests | `http://127.0.0.1:8081` | — (jamais de tests en production) |

Pour changer le port sans modifier `compose.yaml` : copier `.env.example` en `.env` et l'adapter
(`.env` n'est pas versionné).
