# Ce qui a changé depuis le prototype

Reprise du prototype laissé par Thomas : 58 pull requests relues et mergées du 22 au 29 octobre 2026,
chacune avec ses tests. Les numéros (#…) renvoient aux pull requests GitHub.

## 1. Anomalies corrigées

### Sécurité
| Problème du prototype | Correction | PR |
|---|---|---|
| Textes affichés sans échappement (XSS) | Toute sortie passe par `e()` | #3 |
| Dashboard et modération ouverts à tous | Comptes, rôles `user` / `admin`, middlewares par groupe de routes | #1, #6 |
| Formulaires sans protection CSRF | Jeton sur chaque formulaire POST, renouvelé à la connexion | socle, #45 |
| Connexion sans limite d'essais | 20 envois / IP / 15 min et 5 mots de passe faux / compte (connexions réussies non comptées) | #4, #38 |
| Requêtes piégées (UTF-8 invalide, champs en tableau, caractères de contrôle) | Refusées proprement (400) au lieu d'erreurs PHP | #7, #12, #51 |
| Redirection vers un autre site possible | Seuls les chemins du site sont acceptés | #52 |
| En-têtes de sécurité absents, versions affichées | `X-Frame-Options`, `nosniff`, CSP, `Referrer-Policy` ; versions masquées | #24, #26, #45 |
| Droits gardés après une rétrogradation ou une suppression de compte | Rôle et compte relus en base | #45, #50 |

### Bugs
| Problème du prototype | Correction | PR |
|---|---|---|
| Prix mal enregistré, sans limite | Prix enregistré, arrondi au centime, 0 à 999,99 € | #3, #16 |
| Classement par nombre de votes | Classement par note moyenne, puis votes | #22 |
| Moyennes comptant les avis non publiés | Seuls les avis publiés comptent | #18 |
| Votes multiples possibles | Un vote et un avis par compte et par plat | #5, #13 |
| Avis publiés sans modération | Statut « en attente » par défaut | #35 |
| Fiche plat sans note ni votes | Note moyenne et nombre de votes affichés | #35 |
| Id inexistant ou trop grand => erreur | Page 404 | #23 |
| Mauvaise méthode HTTP => 404 | 405 avec l'en-tête `Allow` | #35 |
| Erreurs 500 dans des cas limites (double inscription, compte supprimé…) | Message clair à la place | #49, #50, #51 |
| Avis trop long qui débordait, emojis composés refusés | Texte contenu dans sa carte, emojis acceptés | #35 |

## 2. Fonctionnalités ajoutées

| Fonctionnalité | PR |
|---|---|
| Connexion et inscription en un seul formulaire, en deux étapes ; retour à la page demandée | #1, #2, #6 |
| Voter et donner son avis avec son compte | #13, #8 |
| Modération des avis : publier, refuser, filtrer par statut | #20, #32 |
| **Dashboard des plats (CRUD)** : liste triable, ajout (avec photo), modification, suppression | #14, #40, #41, #47, #54 |
| Ajout d'un établissement | #29 |
| Recherche par plat **ou** par établissement | #39 |
| Thème sombre (par défaut) et clair | #19 |
| Afficher / masquer le mot de passe | #21 |
| Pages d'erreur du site (403, 404, 405, 413, 500) et mentions légales | #37, #54 |
| **Roue du destin** « Je ne sais pas quoi manger ! » avec filtre par catégorie | #56, #57, #58 |

### Le dashboard couvre les quatre opérations, en base PostgreSQL
| Opération | Où | Persistance |
|---|---|---|
| Consulter | `/dashboard` : tableau triable par lieu, catégorie, prix | `SELECT` |
| Ajouter | Formulaire « Ajouter un plat » (photo facultative) | `INSERT` + fichier photo |
| Modifier | `/dashboard/plats/{id}/modifier` (nom, prix) | `UPDATE` |
| Supprimer | Bouton « Supprimer », avec confirmation | `DELETE` (votes et avis supprimés avec) |

## 3. Interface et expérience

- Refonte visuelle à partir de la maquette Figma, design system en variables CSS (#19, #25)
- Administration en trois pages avec une navigation commune (#27, #30, #31), en-tête réorganisé (#28)
- **Mobile** : en-tête sur deux lignes, tableaux en cartes, aucun débordement à 320 px (#33, #42)
- **Accessibilité** (audit WCAG 2.2 AA) : lien d'évitement, titres d'onglet, erreurs reliées aux champs,
  contrastes, focus visible, mouvement réduit (#42, #43)
- Photos des plats hébergées sur le site (#46)

## 4. Qualité et outillage

- **Tests HTTP** (Bash + curl) : environ 380 vérifications, lancées avant chaque PR (#11, #44)
- **Audit de sécurité** et corrections (#45), relecture croisée de chaque PR
- **Refacto** de simplification, sans changement de comportement (#55)
- **Documentation** : installation, mise en production, architecture (#48)
- Version de PHP figée pour des installations identiques (#9)

## 5. Limites connues
- La page « Modifier » ne change que le nom et le prix (pas la catégorie, la description ni la photo).
- Pas de mot de passe oublié (aucun envoi d'email).
- Voir aussi `ARCHITECTURE.md` § 10 et `MISE-EN-PROD.md` § 8.
