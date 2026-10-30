# Gestion de projet

Équipe de deux : **Cécile** (cecilepardo) et **Ludwig** (Munkkkk).
Projet mené du 20 au 30 octobre 2026, sur une stack (PHP natif) qu'aucun de nous deux ne maîtrisait au départ.

## 1. Méthode : agile, une tâche après l'autre

- **Deux dailies par jour**, ritualisés : le matin, on choisit ensemble les tâches de chacun ; en fin de journée,
  on fait le point. Entre les deux, des **points au fil de l'eau** dès qu'une décision est à prendre
  ou qu'une PR mérite d'être expliquée.
- **Une tâche après l'autre, en concertation** : pas de plan figé sur dix jours, mais des priorités revues
  chaque matin selon ce que le code et les tests révélaient.
- **Priorités** : d'abord l'architecture et la sécurité (le socle dont tout dépend), puis les anomalies
  du prototype, le dashboard, l'interface, et enfin les ajouts (roue du destin).

## 2. Rôles

Faute de maîtriser PHP au départ, nous avons tenu les rôles de **Product Owner, chef de projet et lead dev** :
décider quoi faire et dans quel ordre, découper, vérifier, relire, accepter ou refuser chaque changement.

**Claude Code a été notre « petite main », et nous les deux cerveaux derrière.**
Nous l'avons encadré ainsi :
- **aucun code écrit sans validation** : la proposition est montrée et expliquée, puis écrite seulement après accord ;
- **une étape à la fois**, comprise avant de passer à la suivante (nous devons savoir expliquer chaque ligne) ;
- **tests systématiques** avant chaque PR, et preuves dans la description (résultats des tests, captures) ;
- **relecture humaine croisée** de chaque PR par l'autre membre de l'équipe ;
- **les décisions restent les nôtres** : design, priorités, compromis (ex. limite de 2 Mo pour les photos,
  pour la vitesse du site sur mobile ; bordure discrète des champs assumée).

## 3. Outils

| Outil | Usage |
|---|---|
| **Trello** | Tâches attribuées pendant les dailies, puis déplacées : backlog → en cours → terminé |
| **GitHub** | Une branche et une pull request par tâche, relecture croisée avant chaque merge |
| **Figma** | Maquette et design system (thèmes sombre et clair, composants) |
| **Docker** | Même environnement pour les deux postes, lancé en une commande |
| **Claude Code** | Assistant de développement, encadré comme décrit ci-dessus |

Liens : [Trello](https://trello.com/b/RobkiE9o/foodbattle) · [Figma](https://www.figma.com/design/vKmUDbfqhsOdcx5HXXaDwa)

## 4. Git et GitHub

| Règle | Pourquoi |
|---|---|
| Une branche par tâche, préfixée (`feat/`, `fix/`, `docs/`, `chore/`, `style/`, `refactor/`) | On sait ce que contient une branche avant de l'ouvrir |
| Personne ne merge sa propre PR | Chaque changement est vu par deux personnes |
| Intégrer `main` par **merge**, jamais par rebase | L'historique reste vrai et rien n'est réécrit sur une branche partagée |
| Tests lancés avant chaque PR | Aucune régression ne part dans `main` |
| Description de PR avec preuves (tests, captures) et risques | Le relecteur sait quoi vérifier |
| Vérifier les conflits avec les PR ouvertes avant d'en ouvrir une | Pas de mauvaise surprise au merge quand on travaille en parallèle |

### En chiffres

| Indicateur | Valeur |
|---|---|
| Pull requests | 58, toutes mergées |
| Relecture croisée | 58 sur 58 (30 PR de Cécile mergées par Ludwig, 28 de Ludwig mergées par Cécile) |
| Commits (hors merges) | 35 de Ludwig, 32 de Cécile |
| Tests | environ 380 vérifications automatiques |

## 5. Documentation

| Document | Contenu |
|---|---|
| `README.md` | Présentation, lancement rapide, organisation du code |
| `docs/INSTALLATION.md` | Installer et lancer en local, dépannage |
| `docs/MISE-EN-PROD.md` | Passer en production : configuration, déploiement, sauvegardes, vérifications |
| `docs/ARCHITECTURE.md` | Trajet d'une requête, dossiers et règles, données, choix et limites |
| `docs/CHANGEMENTS.md` | Anomalies corrigées et fonctionnalités ajoutées, avec les PR en preuve |
| `docs/GESTION-PROJET.md` | Méthode, rôles, outils, règles Git |
| `tests/README.md` | Lancer et écrire les tests |

## 6. Ce que nous retenons

- **Le socle d'abord** : l'architecture et la sécurité posées tôt ont rendu chaque ajout plus simple et plus sûr.
- **Relire et auditer paie** : des problèmes ont été trouvés avant d'arriver dans `main`, en relecture
  (un test qui échouait seulement sur Mac, des photos de plus de 8 Mo qui cassaient la page)
  ou lors des audits (une redirection possible vers un autre site).
- **Ne pas maîtriser une stack n'empêche pas de la piloter**, à condition de tout comprendre et vérifier avant d'accepter.
