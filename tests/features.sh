#!/usr/bin/env bash
# Tests HTTP des votes, avis, prix et de l'encodage, et de leurs interactions avec l'authentification
# (voir tests/README.md).
cd "$(dirname "$0")/.." || exit 1   # racine du projet : docker compose y trouve compose.yaml
source tests/lib.sh

rate() {  # rate JAR PLAT PSEUDO NOTE COMMENTAIRE
    req "$1" GET "/plat/$2"; local t; t=$(token)
    req "$1" POST "/plat/$2/avis" --data-urlencode "token=$t" --data-urlencode "nickname=$3" \
        --data-urlencode "score=$4" --data-urlencode "comment=$5"
}
vote() {  # vote JAR PLAT
    req "$1" GET "/plat/$2"; local t; t=$(token)
    req "$1" POST "/plat/$2/vote" --data-urlencode "token=$t"
}

section "PR #8 — un seul vote par plat, avec message"
DISH=5
before=$(sql "SELECT COUNT(*) FROM votes WHERE dish_id=$DISH")
vote v $DISH
check "1er vote => 302 vers /plat/$DISH?ok=1" [ "$CODE" = 302 -a "$LOC" = "/plat/$DISH?ok=1" ]
vote v $DISH
check "2e vote => 302 vers /plat/$DISH?deja=1" [ "$CODE" = 302 -a "$LOC" = "/plat/$DISH?deja=1" ]
req v GET "/plat/$DISH?deja=1"
check "message « Tu as déjà voté pour ce plat. » affiché" has 'Tu as déjà voté pour ce plat.'
check "un seul vote compté en base" [ "$(sql "SELECT COUNT(*) FROM votes WHERE dish_id=$DISH")" = $((before + 1)) ]
req v POST "/plat/$DISH/vote"
check "vote sans jeton CSRF => 403" [ "$CODE" = 403 ]

section "PR #5 — contraintes en base"
check "statut d'avis inventé refusé par la base" grep -q 'ratings_status_check' <<<"$(sql "INSERT INTO ratings(dish_id,nickname,score,comment,status) VALUES(1,'x',3,'x','spam')")"
check "doublon de vote refusé par la base" grep -q 'duplicate key' <<<"$(sql "INSERT INTO votes(dish_id,visitor_key) VALUES(1,'seed-1-0')")"

section "PR #3 — corrections"
rate x 1 "Testeur$RUN" 4 "<script>alert('xss')</script>"
check "avis avec <script> => enregistré (302)" [ "$CODE" = 302 ]
req x GET /plat/1
check "XSS : commentaire affiché échappé" has "&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;"
check "XSS : aucune balise <script>alert exécutable" hasnt "<script>alert('xss')"
rate x 1 "$(printf 'n%.0s' {1..61})" 3 "ok"
check "pseudo de 61 caractères => refusé (422)" [ "$CODE" = 422 ]
# Accents écrits déjà encodés en UTF-8 (%C3%A9 = « é ») : sous Windows, curl convertirait « é » en Latin-1.
req x GET /plat/1; t=$(token)
req x POST /plat/1/avis --data-urlencode "token=$t" --data "nickname=Eloise$RUN&score=3" \
    --data "comment=$(printf '%%C3%%A9%.0s' {1..1000})"
check "commentaire de 1000 caractères accentués => accepté (mb_strlen)" [ "$CODE" = 302 ]
rate x 1 "Ok$RUN" 3 "$(printf 'c%.0s' {1..1001})"
check "commentaire de 1001 caractères => refusé (422)" [ "$CODE" = 422 ]
top=$(sql "SELECT d.name FROM dishes d ORDER BY (SELECT COUNT(*) FROM votes WHERE dish_id=d.id) DESC, d.id LIMIT 1")
req x GET /classement
first=$(grep -o '<a href="/plat/[0-9]*">[^<]*</a>' <<<"$BODY" | head -1 | sed 's/<[^>]*>//g')
check "classement : le plat le plus voté est en tête ($top)" [ "$first" = "$(sed "s/'/\&#039;/g" <<<"$top")" ]
check "contrainte prix >= 0 en base" grep -q 'dishes_price_check' <<<"$(sql "UPDATE dishes SET price=-1 WHERE id=1")"
check "contrainte catégorie en base" grep -q 'dishes_category_check' <<<"$(sql "UPDATE dishes SET category='kebab' WHERE id=1")"

section "PR #3 × auth — modification du prix réservée à l'admin"
login u user@foodbattle.local foodbattle-user
req u GET /dashboard/plats/2/modifier
check "user GET /dashboard/plats/2/modifier => 403" [ "$CODE" = 403 ]
login a admin@foodbattle.local foodbattle-admin
req a GET /dashboard/plats/2/modifier; t=$(token)
old_price=$(sql "SELECT price FROM dishes WHERE id=2")
req a POST /dashboard/plats/2/modifier --data-urlencode "token=$t" \
    --data "name=Le%20Jardin%20Fum%C3%A9&price=11.90"
check "admin modifie le prix => enregistré en base (11.90)" [ "$(sql "SELECT price FROM dishes WHERE id=2")" = 11.90 ]
sql "UPDATE dishes SET price = $old_price WHERE id = 2" >/dev/null   # le nom n'a pas changé : seul le prix est restauré

section "PR #7 — encodage UTF-8"
req w GET '/?q=%FF'
check "GET avec octet invalide (%FF) => 400" [ "$CODE" = 400 ]
req w GET '/?q=cr%C3%A8me'
check "GET avec accent valide (crème) => 200" [ "$CODE" = 200 ]
req w GET '/?q[]=%FF'
check "GET tableau avec octet invalide => 400 (array_walk_recursive)" [ "$CODE" = 400 ]

section "PR #7 × auth — UTF-8 invalide sur la connexion"
req w GET /connexion; t=$(token)
req w POST /connexion --data "token=$t&email=%FF%FE@test.fr"
check "email en UTF-8 invalide => 400" [ "$CODE" = 400 ]
check "… refusé avant le routeur : non compté par ThrottleAuth" [ "$(sql "SELECT COUNT(*) FROM rate_limit_hits")" = 0 ]
req w GET /connexion; t=$(token)
req w POST /connexion --data-urlencode "token=$t" --data "email=%C3%A9lodie$RUN@test.fr"
check "email avec accent (UTF-8 valide) => pas d'erreur, refus propre (format)" bash -c "[ $CODE = 200 ] && grep -q 'Saisis une adresse email valide.' <<<\"\$0\"" "$BODY"

section "Interactions auth × votes (limites connues)"
vote y 7
login y user@foodbattle.local foodbattle-user
vote y 7
if [ "$LOC" = "/plat/7?ok=1" ]; then
    note "un visiteur qui vote puis se connecte peut revoter : la connexion change l'identifiant de session (visitor_key) → relier les votes à user_id"
else
    check "vote après connexion bloqué" true
fi

section "Champs envoyés sous forme de tableau (name[]=x)"
req z GET '/?q[]=x'
check "recherche q[] => page affichée, pas d'erreur PHP" bash -c '[ "$1" = 200 ] && ! grep -q Uncaught <<<"$2"' _ "$CODE" "$BODY"
req z GET '/?categorie[]=x'
check "filtre categorie[] => page affichée, pas d'erreur PHP" bash -c '[ "$1" = 200 ] && ! grep -q Uncaught <<<"$2"' _ "$CODE" "$BODY"
req z POST /plat/1/vote --data 'token[]=x'
check "vote avec token[] => 403 (jeton invalide), pas d'erreur PHP" bash -c '[ "$1" = 403 ] && ! grep -q Uncaught <<<"$2"' _ "$CODE" "$BODY"
req z GET /plat/1; t=$(token)
req z POST /plat/1/avis --data "token=$t&nickname[]=x&score=3&comment=ok"
check "avis avec nickname[] => 422, pas d'erreur PHP" bash -c '[ "$1" = 422 ] && ! grep -q Uncaught <<<"$2"' _ "$CODE" "$BODY"
req z POST /plat/1/avis --data "token=$t&nickname=x&score=3&comment[]=ok"
check "avis avec comment[] => 422, pas d'erreur PHP" bash -c '[ "$1" = 422 ] && ! grep -q Uncaught <<<"$2"' _ "$CODE" "$BODY"
req z POST /plat/1/avis --data "token=$t&nickname=x&score[]=3&comment=ok"
check "avis avec score[] => 422, pas d'erreur PHP" bash -c '[ "$1" = 422 ] && ! grep -q Uncaught <<<"$2"' _ "$CODE" "$BODY"
login za admin@foodbattle.local foodbattle-admin
req za GET /dashboard; t=$(token)
DISH_FIELDS="token=$t&name=Tableau$RUN&price=10&establishment_id=1&category=burger&description=ok"
req za POST /dashboard/plats --data "${DISH_FIELDS/name=Tableau$RUN/name[]=x}"
check "ajout de plat avec name[] => message de validation, pas d'erreur PHP" bash -c 'grep -q "Vérifie les champs du plat." <<<"$1" && ! grep -q Uncaught <<<"$1"' _ "$BODY"
req za POST /dashboard/plats --data "${DISH_FIELDS/category=burger/category[]=burger}"
check "ajout de plat avec category[] => message de validation, pas d'erreur PHP" bash -c 'grep -q "Vérifie les champs du plat." <<<"$1" && ! grep -q Uncaught <<<"$1"' _ "$BODY"
req za POST /dashboard/plats --data "${DISH_FIELDS/price=10/price[]=10}"
check "ajout de plat avec price[] => message de validation, pas d'erreur PHP" bash -c 'grep -q "Vérifie les champs du plat." <<<"$1" && ! grep -q Uncaught <<<"$1"' _ "$BODY"
req za POST /dashboard/plats --data "${DISH_FIELDS/description=ok/description[]=ok}"
check "ajout de plat avec description[] => accepté sans description (302), pas d'erreur PHP" bash -c '[ "$1" = 302 ] && ! grep -q Uncaught <<<"$2"' _ "$CODE" "$BODY"
req za GET /dashboard/plats/1/modifier; t=$(token)
req za POST /dashboard/plats/1/modifier --data "token=$t&name[]=x&price=10"
check "modification de plat avec name[] => message de validation, pas d'erreur PHP" bash -c 'grep -q "Vérifie le nom et le prix." <<<"$1" && ! grep -q Uncaught <<<"$1"' _ "$BODY"
sql "DELETE FROM dishes WHERE name = 'Tableau$RUN'" >/dev/null

section "Moyennes affichées (constat)"
# Plats dont la moyenne calculée sur tous les avis diffère de celle des seuls avis publiés.
wrong=$(sql "SELECT COUNT(*) FROM (SELECT dish_id FROM ratings GROUP BY dish_id
             HAVING ROUND(AVG(score) FILTER (WHERE status = 'published'), 1) IS DISTINCT FROM ROUND(AVG(score), 1)) AS t")
if [ "$wrong" != 0 ]; then
    note "$wrong plat(s) : la moyenne affichée compte les avis en attente de modération (Dish::search et Dish::ranking, filtre status manquant)"
else
    check "moyennes calculées sur les seuls avis publiés" true
fi

# Nettoyage : les avis créés par ce script.
sql "DELETE FROM ratings WHERE nickname IN ('Testeur$RUN', 'Eloise$RUN')" >/dev/null

finish
