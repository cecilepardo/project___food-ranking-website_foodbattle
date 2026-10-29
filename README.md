# FoodBattle (architecture MVC)

Refonte du prototype `app/` en architecture MVC, avec correction des failles et des bugs relevés à l'audit (voir « Corrections apportées »).

## Ressources

- **Maquettes et design system** : [Figma](https://www.figma.com/design/vKmUDbfqhsOdcx5HXXaDwa)
  (thèmes sombre et clair, composants, page d'accueil)
- **Suivi des tâches** : [Trello](https://trello.com/b/RobkiE9o/foodbattle)

Le Figma sert de référence visuelle. Les valeurs réellement utilisées par le site
(polices, espacements, arrondis) sont dans `public/assets/css/tokens.css`, les couleurs dans
`theme-dark.css` et `theme-light.css`.
Si l'un change, on met l'autre à jour.

## Documentation

- [Ce qui a changé depuis le prototype](docs/CHANGEMENTS.md) : anomalies corrigées, fonctionnalités ajoutées, avec les PR en preuve
- [Architecture](docs/ARCHITECTURE.md) : trajet d'une requête, dossiers et règles, routes, données, front, choix et limites
- [Design system](docs/DESIGN-SYSTEM.md) : intention et public visé, typographie, couleurs et contrastes, composants
- [Gestion de projet](docs/GESTION-PROJET.md) : méthode agile, rôles, outils, règles Git, chiffres
- [Installer et lancer en local](docs/INSTALLATION.md) : prérequis, commandes, comptes de démo, tests, dépannage
- [Mettre en production](docs/MISE-EN-PROD.md) : ce qui change, configuration proposée, déploiement, sauvegardes, vérifications

## Lancer le projet

```
docker compose up --build
```

Puis ouvrir http://localhost:8081 (le port 8081 évite le conflit avec l'ancien site sur 8080).

Remettre la base à zéro : `docker compose down -v`, puis relancer.

### Comptes de démonstration (local uniquement)

| Rôle  | Email                    | Mot de passe       |
|-------|--------------------------|--------------------|
| admin | `admin@foodbattle.local` | `foodbattle-admin` |
| user  | `user@foodbattle.local`  | `foodbattle-user`  |
| user  | `gourmand1@foodbattle.local` … `gourmand38@foodbattle.local` | `foodbattle-demo` |

Les 38 gourmands portent les votes et les avis de démonstration.

Connexion et inscription passent par la même page (`/connexion`) : on saisit son email, puis son mot de passe
si le compte existe, sinon un pseudo et un mot de passe pour le créer. Un compte créé ainsi est toujours `user` ;
un admin ne se crée qu'en base.

Voter et donner son avis demandent d'être connecté : un vote et un avis par compte et par plat,
signés du pseudo du compte. L'avis d'un compte supprimé reste, affiché « Compte désactivé ».

Après connexion, on revient sur la page demandée (ex. `/moderation`), sinon sur `/dashboard` (admin) ou `/` (user).
Un utilisateur déjà connecté qui ouvre `/connexion` est renvoyé vers cet accueil (`RequireGuest`).

### Limitation des tentatives (anti brute-force)

- **Par adresse IP** : 20 formulaires de connexion/inscription envoyés en 15 min, puis blocage (`ThrottleAuth`).
- **Par compte** : 5 mots de passe faux en 15 min, puis blocage du compte (le bon mot de passe est aussi refusé).

En local, toutes les requêtes arrivent avec la même IP (passerelle Docker) : en cas de blocage pendant les tests,
`docker compose exec db psql -U foodbattle -c "DELETE FROM rate_limit_hits;"`

## Organisation

```
public/index.php        Point d'entrée unique : toutes les URL passent ici
config/routes.php       Liste des URL et du controller appelé pour chacune
app/Core/               Routeur, rendu des vues, base de données, CSRF, authentification, limiteur de tentatives, helpers
app/Models/             Requêtes SQL uniquement
app/Controllers/        Lisent la requête, appellent les Models, choisissent la vue
app/Middlewares/        Vérifications exécutées par le routeur avant le controller (ex. RequireAdmin)
app/Views/              HTML uniquement (layout.php, partials/ et un fichier par page)
public/assets/css/      fonts → tokens → theme-dark / theme-light → base → layout → components → pages
```

Règles :
- un **Model** ne contient pas de HTML et ne lit pas `$_GET` / `$_POST` ;
- un **Controller** ne contient ni SQL ni HTML ;
- une **View** ne fait pas de SQL ; tout texte affiché passe par `e()` ;
- toute route d'administration est déclarée **dans** le groupe `RequireAdmin` de `config/routes.php`.

## Correspondance avec l'ancien code

| Ancien fichier   | Nouvelle URL                      | Controller                                   |
|------------------|-----------------------------------|----------------------------------------------|
| `index.php`      | `/`                               | `HomeController::index`                      |
| `plat.php?id=1`  | `/plat/1`                         | `DishController::show`                       |
| `action.php`     | `POST /plat/1/vote`, `/plat/1/avis` | `DishController::vote`, `::rate`           |
| `classement.php` | `/classement`                     | `RankingController::index`                  |
| `dashboard.php`  | `/dashboard`, `POST /dashboard/plats` | `Admin\DashboardController::index`, `::store` |
| `modifier.php?id=1` | `/dashboard/plats/1/modifier`  | `Admin\DishController::edit`, `::update`     |
| `moderation.php` | `/moderation`, `POST /moderation/1/publier`, `/refuser` | `Admin\ModerationController::index`, `::publish`, `::reject` |
| (nouveau)        | `/dashboard/etablissements`, `POST /dashboard/etablissements` | `Admin\EstablishmentController::index`, `::store` |

## Corrections apportées

- **Sécurité** : sorties échappées (XSS), connexion par compte avec rôle admin, jeton CSRF sur chaque formulaire,
  limitation des tentatives de connexion, cookie de session `HttpOnly` / `SameSite`, erreurs masquées hors dev,
  en-têtes `X-Frame-Options` / `X-Content-Type-Options`, versions d'Apache et de PHP masquées.
- **Bugs** : filtre tacos, classement par note moyenne, prix enregistré, votes uniques par compte,
  moyennes calculées sur les seuls avis publiés, id hors limites → 404, UTF-8 invalide → 400.
- **Fonctionnalités** : modération réelle des avis (publier / refuser, filtre par statut),
  suppression d'un plat, ajout d'un établissement.

## Tests

Le site doit tourner (`docker compose up -d`), puis :

```
bash tests/auth.sh
bash tests/features.sh
```

Voir `tests/README.md` pour le détail.
