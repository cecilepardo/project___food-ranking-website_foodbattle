# Tests HTTP

Ces scripts jouent le rôle d'un navigateur avec `curl` contre l'application lancée en local,
puis vérifient les réponses (statut HTTP, redirection, contenu de la page) et l'état de la base.

## Lancer

Depuis la racine du projet, application démarrée (`docker compose up -d`) :

```
bash tests/auth.sh       # authentification, limitation des tentatives, redirections
bash tests/features.sh   # votes, avis, prix, encodage, et leurs interactions avec l'authentification
```

Chaque ligne affiche ✅ (réussi), ❌ (échec) ou ⚠️ (constat connu, à corriger plus tard).
Un script termine en erreur s'il y a au moins un ❌ : lancez-les avant chaque PR.

## Précautions

- Ils **écrivent dans la base locale** : comptes `nouveau-…@test.fr`, votes, et vident `rate_limit_hits`.
  Les avis de test sont supprimés en fin de script. Jamais sur une base de production.
- Ils supposent les comptes de démo du README principal.
- Base en vrac après beaucoup d'essais : `docker compose down -v && docker compose up -d`.

## Écrire un test portable (macOS, Linux, Windows)

Sous Windows (Git Bash), `curl` convertit en Latin-1 les accents passés en argument, et le site
les refuse (400, encodage invalide). Tout caractère non ASCII **envoyé au serveur** s'écrit donc
déjà encodé en UTF-8, avec `--data` : `--data "name=Fum%C3%A9"` (« é » = `%C3%A9`).
Même règle pour les requêtes `sql "…"` : pas d'accent dedans.

## Ajouter un test

```bash
section "Ce que je teste"
req visiteur GET /une-page            # remplit $CODE, $LOC, $BODY
check "la page s'affiche" [ "$CODE" = 200 ]
check "le titre est là" has '<h1>Mon titre</h1>'
```

Les fonctions disponibles sont décrites dans `tests/lib.sh`.
