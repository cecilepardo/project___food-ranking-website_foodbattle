# FoodBattle (architecture MVC)

Migration du prototype `app/` vers une architecture MVC. Le comportement est identique à l'ancien site, bugs compris : chaque bug connu est marqué `BUG connu` dans le code.

## Lancer le projet

```
docker compose up --build
```

Puis ouvrir http://localhost:8081 (le port 8081 évite le conflit avec l'ancien site sur 8080).

Remettre la base à zéro : `docker compose down -v`, puis relancer.

## Organisation

```
public/index.php        Point d'entrée unique : toutes les URL passent ici
config/routes.php       Liste des URL et du controller appelé pour chacune
app/Core/               Routeur, rendu des vues, base de données, CSRF, helpers
app/Models/             Requêtes SQL uniquement
app/Controllers/        Lisent la requête, appellent les Models, choisissent la vue
app/Views/              HTML uniquement (layout.php, partials/ et un fichier par page)
public/assets/css/      variables → base → layout → components → pages
```

Règles :
- un **Model** ne contient pas de HTML et ne lit pas `$_GET` / `$_POST` ;
- un **Controller** ne contient ni SQL ni HTML ;
- une **View** ne fait pas de SQL ; tout texte affiché passe par `e()`.

## Correspondance avec l'ancien code

| Ancien fichier   | Nouvelle URL                      | Controller                                   |
|------------------|-----------------------------------|----------------------------------------------|
| `index.php`      | `/`                               | `HomeController::index`                      |
| `plat.php?id=1`  | `/plat/1`                         | `DishController::show`                       |
| `action.php`     | `POST /plat/1/vote`, `/plat/1/avis` | `DishController::vote`, `::rate`           |
| `classement.php` | `/classement`                     | `RankingController::index`                  |
| `dashboard.php`  | `/dashboard`, `POST /dashboard/plats` | `Admin\DashboardController::index`, `::store` |
| `modifier.php?id=1` | `/dashboard/plats/1/modifier`  | `Admin\DishController::edit`, `::update`     |
| `moderation.php` | `/moderation`                     | `Admin\ModerationController::index`          |

## Bugs connus (à corriger)

Rechercher `BUG connu` dans le projet pour les retrouver tous.
