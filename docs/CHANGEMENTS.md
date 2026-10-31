# Ce qui a changé depuis le prototype

Reprise du prototype laissé par Thomas : 58 pull requests relues et mergées du 22 au 29 octobre 2026,
chacune avec ses tests. Les numéros (#…) renvoient aux pull requests du dépôt d'origine, dans l'organisation Simplon
(dépôt privé : liens accessibles aux membres de la promotion).

## 1. Anomalies corrigées

### Sécurité
| Problème du prototype | Correction | PR |
|---|---|---|
| Textes affichés sans échappement (XSS) | Toute sortie passe par `e()` | [#3](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/3) |
| Dashboard et modération ouverts à tous | Comptes, rôles `user` / `admin`, middlewares par groupe de routes | [#1](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/1), [#6](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/6) |
| Formulaires sans protection CSRF | Jeton sur chaque formulaire POST, renouvelé à la connexion | socle, [#45](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/45) |
| Connexion sans limite d'essais | 20 envois / IP / 15 min et 5 mots de passe faux / compte (connexions réussies non comptées) | [#4](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/4), [#38](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/38) |
| Requêtes piégées (UTF-8 invalide, champs en tableau, caractères de contrôle) | Refusées proprement (400) au lieu d'erreurs PHP | [#7](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/7), [#12](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/12), [#51](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/51) |
| Redirection vers un autre site possible | Seuls les chemins du site sont acceptés | [#52](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/52) |
| En-têtes de sécurité absents, versions affichées | `X-Frame-Options`, `nosniff`, CSP, `Referrer-Policy` ; versions masquées | [#24](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/24), [#26](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/26), [#45](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/45) |
| Droits gardés après une rétrogradation ou une suppression de compte | Rôle et compte relus en base | [#45](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/45), [#50](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/50) |

### Bugs
| Problème du prototype | Correction | PR |
|---|---|---|
| Prix mal enregistré, sans limite | Prix enregistré, arrondi au centime, 0 à 999,99 € | [#3](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/3), [#16](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/16) |
| Classement par nombre de votes | Classement par note moyenne, puis votes | [#22](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/22) |
| Moyennes comptant les avis non publiés | Seuls les avis publiés comptent | [#18](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/18) |
| Votes multiples possibles | Un vote et un avis par compte et par plat | [#5](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/5), [#13](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/13) |
| Avis publiés sans modération | Statut « en attente » par défaut | [#35](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/35) |
| Fiche plat sans note ni votes | Note moyenne et nombre de votes affichés | [#35](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/35) |
| Id inexistant ou trop grand => erreur | Page 404 | [#23](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/23) |
| Mauvaise méthode HTTP => 404 | 405 avec l'en-tête `Allow` | [#35](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/35) |
| Erreurs 500 dans des cas limites (double inscription, compte supprimé…) | Message clair à la place | [#49](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/49), [#50](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/50), [#51](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/51) |
| Avis trop long qui débordait, emojis composés refusés | Texte contenu dans sa carte, emojis acceptés | [#35](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/35) |

## 2. Fonctionnalités ajoutées

| Fonctionnalité | PR |
|---|---|
| Connexion et inscription en un seul formulaire, en deux étapes ; retour à la page demandée | [#1](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/1), [#2](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/2), [#6](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/6) |
| Voter et donner son avis avec son compte | [#13](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/13), [#8](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/8) |
| Modération des avis : publier, refuser, filtrer par statut | [#20](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/20), [#32](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/32) |
| **Dashboard des plats (CRUD)** : liste triable, ajout (avec photo), modification, suppression | [#14](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/14), [#40](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/40), [#41](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/41), [#47](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/47), [#54](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/54) |
| Ajout d'un établissement | [#29](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/29) |
| Recherche par plat **ou** par établissement | [#39](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/39) |
| Thème sombre (par défaut) et clair | [#19](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/19) |
| Afficher / masquer le mot de passe | [#21](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/21) |
| Pages d'erreur du site (403, 404, 405, 413, 500) et mentions légales | [#37](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/37), [#54](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/54) |
| **Roue du destin** « Je ne sais pas quoi manger ! » avec filtre par catégorie | [#56](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/56), [#57](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/57), [#58](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/58) |

### Le dashboard couvre les quatre opérations, en base PostgreSQL
| Opération | Où | Persistance |
|---|---|---|
| Consulter | `/dashboard` : tableau triable par lieu, catégorie, prix | `SELECT` |
| Ajouter | Formulaire « Ajouter un plat » (photo facultative) | `INSERT` + fichier photo |
| Modifier | `/dashboard/plats/{id}/modifier` (nom, prix) | `UPDATE` |
| Supprimer | Bouton « Supprimer », avec confirmation | `DELETE` (votes et avis supprimés avec) |

## 3. Interface et expérience

- Refonte visuelle à partir de la maquette Figma, design system en variables CSS ([#19](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/19), [#25](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/25))
- Administration en trois pages avec une navigation commune ([#27](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/27), [#30](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/30), [#31](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/31)), en-tête réorganisé ([#28](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/28))
- **Mobile** : en-tête sur deux lignes, tableaux en cartes, aucun débordement à 320 px ([#33](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/33), [#42](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/42))
- **Accessibilité** (audit WCAG 2.2 AA) : lien d'évitement, titres d'onglet, erreurs reliées aux champs,
  contrastes, focus visible, mouvement réduit ([#42](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/42), [#43](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/43))
- Photos des plats hébergées sur le site ([#46](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/46))

## 4. Qualité et outillage

- **Tests HTTP** (Bash + curl) : environ 380 vérifications, lancées avant chaque PR ([#11](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/11), [#44](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/44))
- **Audit de sécurité** et corrections ([#45](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/45)), relecture croisée de chaque PR
- **Refacto** de simplification, sans changement de comportement ([#55](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/55))
- **Documentation** : installation, mise en production, architecture ([#48](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/48))
- Version de PHP figée pour des installations identiques ([#9](https://github.com/SIMPLON-DIST-CDA-P9-200473/foodbattle-CL-LM/pull/9))

## 5. Limites connues
- La page « Modifier » ne change que le nom et le prix (pas la catégorie, la description ni la photo).
- Pas de mot de passe oublié (aucun envoi d'email).
- Voir aussi `ARCHITECTURE.md` § 10 et `MISE-EN-PROD.md` § 8.
