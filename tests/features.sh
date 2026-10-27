#!/usr/bin/env bash
# Tests HTTP des votes, avis, prix et de l'encodage, et de leurs interactions avec l'authentification
# (voir tests/README.md).
cd "$(dirname "$0")/.." || exit 1   # racine du projet : docker compose y trouve compose.yaml
source tests/lib.sh

rate() {  # rate JAR PLAT NOTE COMMENTAIRE (l'auteur est le compte connecté dans JAR)
    req "$1" GET "/plat/$2"; local t; t=$(token)
    req "$1" POST "/plat/$2/avis" --data-urlencode "token=$t" \
        --data-urlencode "score=$3" --data-urlencode "comment=$4"
}
vote() {  # vote JAR PLAT
    req "$1" GET "/plat/$2"; local t; t=$(token)
    req "$1" POST "/plat/$2/vote" --data-urlencode "token=$t"
}
new_account() {  # new_account JAR : inscrit un compte de test (fx-RUN-JAR@test.fr) et le laisse connecté
    step1 "$1" "fx-$RUN-$1@test.fr"
    register "$1" "fx-$RUN-$1@test.fr" "Fx$RUN$1" motdepasse
}
publish_test_reviews() {  # publie les avis des comptes de test, comme le ferait un modérateur
    sql "UPDATE ratings SET status = 'published' WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'fx-$RUN-%')" >/dev/null
}

section "Votes réservés aux comptes (PR #8 adaptée)"
DISH=5
req v GET "/plat/$DISH"
check "visiteur : lien « Connecte-toi pour voter », pas de formulaire de vote" \
    bash -c 'grep -q "Connecte-toi pour voter" <<<"$1" && ! grep -q "action=\"/plat/[0-9]*/vote\"" <<<"$1"' _ "$BODY"
t=$(token)
req v POST "/plat/$DISH/vote" --data-urlencode "token=$t"
check "visiteur POST /plat/$DISH/vote => 302 vers /connexion" [ "$CODE" = 302 -a "$LOC" = /connexion ]
req v GET "/plat/$DISH/participer"
check "visiteur GET /plat/$DISH/participer => 302 vers /connexion" [ "$CODE" = 302 -a "$LOC" = /connexion ]
new_account v
check "inscription depuis « participer » => renvoyé vers /plat/$DISH/participer" [ "$LOC" = "/plat/$DISH/participer" ]
req v GET "/plat/$DISH/participer"
check "… qui ramène sur la fiche du plat" [ "$CODE" = 302 -a "$LOC" = "/plat/$DISH" ]
req v GET "/plat/$DISH"
check "connecté : formulaire de vote + « Tu publies en tant que Fx…v »" \
    bash -c 'grep -q "action=\"/plat/[0-9]*/vote\"" <<<"$1" && grep -q "Tu publies en tant que <b>Fx[0-9]*v</b>" <<<"$1"' _ "$BODY"
before=$(sql "SELECT COUNT(*) FROM votes WHERE dish_id=$DISH")
vote v $DISH
check "1er vote => 302 vers /plat/$DISH?ok=1" [ "$CODE" = 302 -a "$LOC" = "/plat/$DISH?ok=1" ]
vote v $DISH
check "2e vote du même compte => 302 vers /plat/$DISH?deja=1" [ "$CODE" = 302 -a "$LOC" = "/plat/$DISH?deja=1" ]
req v GET "/plat/$DISH?deja=1"
check "message « Tu as déjà voté pour ce plat. » affiché" has 'Tu as déjà voté pour ce plat.'
check "un seul vote compté en base" [ "$(sql "SELECT COUNT(*) FROM votes WHERE dish_id=$DISH")" = $((before + 1)) ]
check "le vote est rattaché au compte" [ "$(sql "SELECT COUNT(*) FROM votes v JOIN users u ON u.id = v.user_id WHERE v.dish_id=$DISH AND u.email='fx-$RUN-v@test.fr'")" = 1 ]
req v POST "/plat/$DISH/vote"
check "vote sans jeton CSRF => 403" [ "$CODE" = 403 ]

section "PR #5 — contraintes en base"
check "statut d'avis inventé refusé par la base" grep -q 'ratings_status_check' <<<"$(sql "INSERT INTO ratings(dish_id,user_id,score,comment,status) VALUES(1,NULL,3,'x','spam')")"
check "doublon de vote (même compte, même plat) refusé par la base" grep -q 'duplicate key' <<<"$(sql "INSERT INTO votes(dish_id,user_id) SELECT 1, id FROM users WHERE nickname='Gourmand1'")"

section "Avis rattachés au compte (et corrections de la PR #3)"
new_account x
rate x 1 4 "<script>alert('xss')</script>"
check "avis avec <script> => enregistré (302)" [ "$CODE" = 302 ]
publish_test_reviews
req x GET /plat/1
check "XSS : commentaire affiché échappé" has "&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;"
check "XSS : aucune balise <script>alert exécutable" hasnt "<script>alert('xss')"
check "l'avis est signé du pseudo du compte" has "Fx${RUN}x · 4/5"
rate x 1 5 "Encore un avis"
check "2e avis du même compte sur le même plat => 302 vers /plat/1?deja-avis=1" [ "$CODE" = 302 -a "$LOC" = "/plat/1?deja-avis=1" ]
req x GET "/plat/1?deja-avis=1"
check "message « Tu as déjà donné ton avis sur ce plat. » affiché" has 'Tu as déjà donné ton avis sur ce plat.'
check "un seul avis du compte en base pour ce plat" [ "$(sql "SELECT COUNT(*) FROM ratings r JOIN users u ON u.id = r.user_id WHERE r.dish_id=1 AND u.email='fx-$RUN-x@test.fr'")" = 1 ]
req x GET /plat/4; t=$(token)
req x POST /plat/4/avis --data-urlencode "token=$t" --data "nickname=Admin&score=3&comment=usurpation"
publish_test_reviews
req x GET /plat/4
check "pseudo envoyé dans le formulaire (Admin) ignoré : l'avis porte le pseudo du compte" \
    bash -c 'grep -q "Fx[0-9]*x · 3/5" <<<"$1" && ! grep -q "Admin · 3/5" <<<"$1"' _ "$BODY"
req w GET /plat/1; t=$(token)
req w POST /plat/1/avis --data-urlencode "token=$t" --data "score=4&comment=anonyme"
check "visiteur POST /plat/1/avis => 302 vers /connexion" [ "$CODE" = 302 -a "$LOC" = /connexion ]
# Accents écrits déjà encodés en UTF-8 (%C3%A9 = « é ») : sous Windows, curl convertirait « é » en Latin-1.
req x GET /plat/2; t=$(token)
req x POST /plat/2/avis --data-urlencode "token=$t" --data "score=3" \
    --data "comment=$(printf '%%C3%%A9%.0s' {1..1000})"
check "commentaire de 1000 caractères accentués => accepté (mb_strlen)" [ "$CODE" = 302 ]
rate x 3 3 "$(printf 'c%.0s' {1..1001})"
check "commentaire de 1001 caractères => refusé (422)" [ "$CODE" = 422 ]
new_account d
rate d 6 2 "Avis du compte supprime"
publish_test_reviews
sql "DELETE FROM users WHERE email = 'fx-$RUN-d@test.fr'" >/dev/null
req d GET /plat/6
check "compte supprimé => son avis reste, signé « Compte désactivé »" has 'Compte désactivé · 2/5'
top=$(sql "SELECT d.name FROM dishes d
           ORDER BY (SELECT AVG(score) FROM ratings WHERE dish_id = d.id AND status = 'published') DESC NULLS LAST,
                    (SELECT COUNT(*) FROM votes WHERE dish_id = d.id) DESC, d.id LIMIT 1")
req x GET /classement
first=$(grep -o '<a href="/plat/[0-9]*">[^<]*</a>' <<<"$BODY" | head -1 | sed 's/<[^>]*>//g')
check "classement : le plat le mieux noté est en tête ($top)" [ "$first" = "$(sed "s/'/\&#039;/g" <<<"$top")" ]
notes=$(grep -A4 '<a href="/plat/' <<<"$BODY" | grep -oE '<td>([0-9],[0-9]|—)</td>' | sed 's/<[^>]*>//g; s/,/./; s/—/-1/')
check "classement : notes affichées dans l'ordre décroissant" bash -c '[ "$1" = "$(sort -rn <<<"$1")" ]' _ "$notes"
check "contrainte prix >= 0 en base" grep -q 'dishes_price_check' <<<"$(sql "UPDATE dishes SET price=-1 WHERE id=1")"
check "contrainte catégorie en base" grep -q 'dishes_category_check' <<<"$(sql "UPDATE dishes SET category='kebab' WHERE id=1")"

section "Modération des avis"
new_account m
rate m 7 4 "Avis a moderer $RUN"
check "nouvel avis => 302 vers /plat/7?avis=1" [ "$CODE" = 302 -a "$LOC" = "/plat/7?avis=1" ]
req m GET "/plat/7?avis=1"
check "message « en attente de modération » affiché" has 'Ton avis est en attente de modération'
check "… mais l'avis n'est pas encore visible" hasnt "Avis a moderer $RUN"
RID=$(sql "SELECT id FROM ratings WHERE comment = 'Avis a moderer $RUN'")
check "avis enregistré en attente (pending)" [ "$(sql "SELECT status FROM ratings WHERE id=$RID")" = pending ]
login a admin@foodbattle.local foodbattle-admin
req a GET /moderation
check "page de modération : bouton Publier pour l'avis" has "action=\"/moderation/$RID/publier\""
check "page de modération : bouton Refuser pour l'avis" has "action=\"/moderation/$RID/refuser\""
check "… libellé « Refuser » tant que l'avis est en attente" \
    bash -c "grep -A3 'action=\"/moderation/$RID/refuser\"' <<<\"\$0\" | grep -q '>Refuser<'" "$BODY"
check "avis en attente listé en premier" [ "$(grep -o 'action="/moderation/[0-9]*/' <<<"$BODY" | head -1)" = "action=\"/moderation/$RID/" ]
t=$(token)
req a POST "/moderation/$RID/publier"
check "publier sans jeton CSRF => 403" [ "$CODE" = 403 ]
req a POST "/moderation/$RID/publier" --data-urlencode "token=$t"
check "admin publie => 302 vers /moderation?fait=published" [ "$CODE" = 302 -a "$LOC" = "/moderation?fait=published" ]
check "… statut published en base" [ "$(sql "SELECT status FROM ratings WHERE id=$RID")" = published ]
req x GET /plat/7
check "… avis visible sur la fiche du plat" has "Avis a moderer $RUN"
req a GET "/moderation?fait=published"
check "message « L'avis a été publié. »" has "L&#039;avis a été publié.\|L'avis a été publié."
check "avis publié : plus de bouton Publier, bouton Refuser toujours là" \
    bash -c "! grep -q 'action=\"/moderation/$RID/publier\"' <<<\"\$0\" && grep -q 'action=\"/moderation/$RID/refuser\"' <<<\"\$0\"" "$BODY"
check "… et ce bouton s'appelle « Dépublier »" \
    bash -c "grep -A3 'action=\"/moderation/$RID/refuser\"' <<<\"\$0\" | grep -q '>Dépublier<'" "$BODY"
t=$(token)
req a POST "/moderation/$RID/refuser" --data-urlencode "token=$t"
check "admin refuse => 302 vers /moderation?fait=rejected" [ "$CODE" = 302 -a "$LOC" = "/moderation?fait=rejected" ]
check "… statut rejected en base" [ "$(sql "SELECT status FROM ratings WHERE id=$RID")" = rejected ]
req x GET /plat/7
check "… avis retiré de la fiche du plat" hasnt "Avis a moderer $RUN"
login u user@foodbattle.local foodbattle-user
req u GET /dashboard; t=$(token)
req u POST "/moderation/$RID/publier" --data-urlencode "token=$t"
check "user POST /moderation/…/publier => 403" [ "$CODE" = 403 ]
req a GET /moderation; t=$(token)
req a POST "/moderation/999999/publier" --data-urlencode "token=$t"
check "avis inexistant => 404" [ "$CODE" = 404 ]

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

section "Prix : maximum 999,99 et 2 décimales"
old_price=$(sql "SELECT price FROM dishes WHERE id=3")
edit_price() {  # edit_price PRIX : modifie le prix du plat 3 en admin
    req a GET /dashboard/plats/3/modifier; local t; t=$(token)
    req a POST /dashboard/plats/3/modifier --data-urlencode "token=$t" \
        --data-urlencode "name=$(sql "SELECT name FROM dishes WHERE id=3")" --data-urlencode "price=$1"
}
edit_price 999.99
check "prix 999.99 => enregistré" [ "$(sql "SELECT price FROM dishes WHERE id=3")" = 999.99 ]
edit_price 1000
check "prix 1000 => refusé proprement" has 'Vérifie le nom et le prix'
check "… prix inchangé en base" [ "$(sql "SELECT price FROM dishes WHERE id=3")" = 999.99 ]
edit_price 1e7
check "prix 1e7 => refusé proprement (plus d'erreur PDO)" bash -c "grep -q 'Vérifie le nom et le prix' <<<\"\$0\" && ! grep -q SQLSTATE <<<\"\$0\"" "$BODY"
edit_price 12.345
check "prix 12.345 => arrondi à 12.35 en base" [ "$(sql "SELECT price FROM dishes WHERE id=3")" = 12.35 ]
check "… et le formulaire affiche 12.35 (valeur réellement enregistrée)" has 'value="12.35"'
check "champ prix limité à 999.99 côté navigateur" has 'max="999.99"'
sql "UPDATE dishes SET price = $old_price WHERE id = 3" >/dev/null
req a GET /dashboard; t=$(token)
req a POST /dashboard/plats --data-urlencode "token=$t" --data-urlencode "name=Trop cher $RUN" \
    -d category=burger -d establishment_id=1 -d description=x -d price=1000000
check "ajout d'un plat à 1000000 => refusé proprement" bash -c "! grep -q SQLSTATE <<<\"\$0\"" "$BODY"
check "… aucun plat créé" [ "$(sql "SELECT COUNT(*) FROM dishes WHERE name='Trop cher $RUN'")" = 0 ]

section "Suppression d'un plat (admin)"
DEL=$(sql "INSERT INTO dishes(establishment_id,name,category,price) VALUES(1,'À supprimer $RUN','burger',5) RETURNING id" | head -1)
sql "INSERT INTO votes(dish_id,user_id) SELECT $DEL, id FROM users WHERE email='user@foodbattle.local'" >/dev/null
req u GET /dashboard; t=$(token)
req u POST "/dashboard/plats/$DEL/supprimer" --data-urlencode "token=$t"
check "user POST /supprimer => 403" [ "$CODE" = 403 ]
req a POST "/dashboard/plats/$DEL/supprimer"
check "admin sans jeton CSRF => 403" [ "$CODE" = 403 ]
check "… le plat est toujours en base" [ "$(sql "SELECT COUNT(*) FROM dishes WHERE id=$DEL")" = 1 ]
req a GET /dashboard; t=$(token)
check "bouton Supprimer = formulaire POST vers /dashboard/plats/$DEL/supprimer" has "action=\"/dashboard/plats/$DEL/supprimer\""
req a POST "/dashboard/plats/$DEL/supprimer" --data-urlencode "token=$t"
check "admin supprime => 302 vers /dashboard?deleted=1" [ "$CODE" = 302 -a "$LOC" = "/dashboard?deleted=1" ]
check "plat supprimé en base" [ "$(sql "SELECT COUNT(*) FROM dishes WHERE id=$DEL")" = 0 ]
check "ses votes supprimés (ON DELETE CASCADE)" [ "$(sql "SELECT COUNT(*) FROM votes WHERE dish_id=$DEL")" = 0 ]
req a GET "/dashboard?deleted=1"
check "message « Le plat a été supprimé. » affiché" has 'Le plat a été supprimé.'
req a POST "/dashboard/plats/999999/supprimer" --data-urlencode "token=$t"
check "plat inexistant => 404" [ "$CODE" = 404 ]

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

section "Champs envoyés sous forme de tableau (name[]=x)"
req z GET '/?q[]=x'
check "recherche q[] => page affichée, pas d'erreur PHP" bash -c '[ "$1" = 200 ] && ! grep -q Uncaught <<<"$2"' _ "$CODE" "$BODY"
req z GET '/?categorie[]=x'
check "filtre categorie[] => page affichée, pas d'erreur PHP" bash -c '[ "$1" = 200 ] && ! grep -q Uncaught <<<"$2"' _ "$CODE" "$BODY"
new_account z
req z POST /plat/1/vote --data 'token[]=x'
check "vote avec token[] => 403 (jeton invalide), pas d'erreur PHP" bash -c '[ "$1" = 403 ] && ! grep -q Uncaught <<<"$2"' _ "$CODE" "$BODY"
req z GET /plat/1; t=$(token)
req z POST /plat/1/avis --data "token=$t&score=3&comment[]=ok"
check "avis avec comment[] => 422, pas d'erreur PHP" bash -c '[ "$1" = 422 ] && ! grep -q Uncaught <<<"$2"' _ "$CODE" "$BODY"
req z POST /plat/1/avis --data "token=$t&score[]=3&comment=ok"
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

section "Format du pseudo"
# Valeurs déjà encodées en UTF-8 (voir tests/README.md, « Écrire un test portable »).
signup() {  # signup JAR PSEUDO_ENCODE : tente une inscription avec ce pseudo
    req "$1" GET /connexion; local t; t=$(token)
    req "$1" POST /connexion --data "token=$t&email=fmt-$RUN-$1%40test.fr"; t=$(token)
    req "$1" POST /connexion/inscription --data "token=$t&email=fmt-$RUN-$1%40test.fr&nickname=$2&password=motdepasse"
}
signup n1 '%E2%80%8B';                   check "pseudo = espace invisible (U+200B) => refusé" has 'Pseudo invalide'
signup n2 '%D0%90dmin';                  check "pseudo « Аdmin » avec un A cyrillique => refusé" has 'Pseudo invalide'
signup n3 'Bob%0ATesteur';               check "pseudo avec retour à la ligne => refusé" has 'Pseudo invalide'
signup n4 '%F0%9F%8D%94%F0%9F%8D%94%F0%9F%8D%94'; check "pseudo en emojis => refusé" has 'Pseudo invalide'
signup n5 'Ab';                          check "pseudo de 2 caractères => refusé" has 'Pseudo invalide'
signup n6 'Le%20%20Gourmet';             check "pseudo avec deux espaces de suite => refusé" has 'Pseudo invalide'
signup n7 'ADMIN';                       check "pseudo réservé « ADMIN » => refusé" has 'Ce pseudo est réservé.'
signup n8 'Compte%20d%C3%A9sactiv%C3%A9'; check "pseudo réservé « Compte désactivé » => refusé" has 'Ce pseudo est réservé.'
signup n9 "%C3%89lo%C3%AFse_$RUN"
check "pseudo « Éloïse_… » (accents, tiret bas) => accepté" [ "$CODE" = 302 ]
signup n10 "Le%20Gourmet%20$RUN"
check "pseudo « Le Gourmet … » (espaces simples) => accepté" [ "$CODE" = 302 ]

# Nouvelle section : les 10 inscriptions ci-dessus font 20 envois depuis la même IP, la limite de ThrottleAuth.
section "Format des avis et de la recherche"
new_account f
req f GET /plat/7; t=$(token)
req f POST /plat/7/avis --data-urlencode "token=$t" --data "score=4&comment=%E2%80%8B"
check "avis composé d'un seul caractère invisible => refusé (422)" [ "$CODE" = 422 ]
req f POST /plat/7/avis --data-urlencode "token=$t" --data "score=4&comment=bon%07"
check "avis avec un caractère de contrôle => refusé (422)" [ "$CODE" = 422 ]
req f POST /plat/7/avis --data-urlencode "token=$t" --data "score=4&comment=Ligne%201%0ALigne%202"
check "avis sur deux lignes => accepté" [ "$CODE" = 302 ]
req s GET '/?q=%25'
check "recherche « % » => cherchée comme un caractère (aucun plat)" hasnt 'href="/plat/'
req s GET '/?q=_'
check "recherche « _ » => cherchée comme un caractère (aucun plat)" hasnt 'href="/plat/'
req s GET '/?q=Smash'
check "recherche « Smash » => toujours fonctionnelle" has 'Le Double Smash'
req s GET "/?q=$(printf 'x%.0s' {1..200})"
check "recherche de 200 caractères => coupée à 100" \
    bash -c 'grep -q "name=\"q\" value=\"$(printf "x%.0s" {1..100})\"" <<<"$1" && ! grep -q "name=\"q\" value=\"x\{101\}" <<<"$1"' _ "$BODY"
sql "DELETE FROM users WHERE email LIKE 'fmt-$RUN-%@test.fr'" >/dev/null

section "Moyennes : seuls les avis publiés comptent"
# Moyenne attendue d'un plat, au format affiché par score() : « 3,7 », ou « — » sans avis publié.
expected_average() {
    sql "SELECT COALESCE(replace(to_char(ROUND(AVG(score), 1), 'FM0.0'), '.', ','), '—')
         FROM ratings WHERE dish_id = $1 AND status = 'published'"
}
D=$(sql "SELECT dish_id FROM ratings GROUP BY dish_id
         HAVING ROUND(AVG(score) FILTER (WHERE status = 'published'), 1) IS DISTINCT FROM ROUND(AVG(score), 1) LIMIT 1")
if [ -z "$D" ]; then
    note "aucun plat dont les avis en attente changeraient la moyenne : test non significatif"
else
    exp=$(expected_average "$D")
    req x GET /
    check "accueil : plat $D affiché ★ $exp/5 (avis en attente ignorés)" \
        bash -c 'grep -A14 "href=\"/plat/$1\"" <<<"$2" | grep -q "$3/5 ·"' _ "$D" "$BODY" "$exp"
    req x GET /classement
    if grep -q "href=\"/plat/$D\"" <<<"$BODY"; then
        check "classement : plat $D affiché $exp (avis en attente ignorés)" \
            bash -c 'grep -A4 "href=\"/plat/$1\"" <<<"$2" | grep -q "<td>$3</td>"' _ "$D" "$BODY" "$exp"
    fi
fi
# Le classement montre 10 plats sur 12 et met les plats sans avis publié en dernier :
# on retire les avis publiés des 3 plats les plus votés, le plus voté des trois arrive alors 10e.
TOP3=$(sql "SELECT string_agg(id::text, ',') FROM (SELECT d.id FROM dishes d
            ORDER BY (SELECT COUNT(*) FROM votes WHERE dish_id = d.id) DESC, d.id LIMIT 3) AS t")
TOP=${TOP3%%,*}
kept=$(sql "SELECT string_agg(id::text, ',') FROM ratings WHERE dish_id IN ($TOP3) AND status = 'published'")
[ -n "$kept" ] && sql "UPDATE ratings SET status = 'pending' WHERE id IN ($kept)" >/dev/null
req x GET /classement
check "classement : plat sans avis publié => « — » au lieu de « 0,0 »" \
    bash -c 'grep -A4 "href=\"/plat/$1\"" <<<"$2" | grep -q "<td>—</td>"' _ "$TOP" "$BODY"
check "classement : plat sans avis publié classé après les plats notés (10e)" \
    bash -c '[ "$(grep -o "<a href=\"/plat/[0-9]*\"" <<<"$2" | sed -n 10p)" = "<a href=\"/plat/$1\"" ]' _ "$TOP" "$BODY"
[ -n "$kept" ] && sql "UPDATE ratings SET status = 'published' WHERE id IN ($kept)" >/dev/null

section "Versions d'Apache et de PHP masquées (Dockerfile)"
hides_versions() {
    grep -qi '^server: Apache\s*$' "$DIR/headers" && ! grep -qi '^x-powered-by' "$DIR/headers"
}
for path in / /page-inexistante /assets/inexistant.css; do
    req ver GET "$path"
    check "GET $path => « Server: Apache » sans version, pas de X-Powered-By" hides_versions
done

section "Id trop grand pour la base => 404 au lieu d'un plantage"
# Les id sont des INTEGER PostgreSQL : au-delà de 2147483647, la requête SQL échouait (Fatal error).
not_found() { [ "$CODE" = 404 ] && no_php_error; }
for id in 2147483648 99999999999999999999; do
    req big GET "/plat/$id"
    check "visiteur GET /plat/$id => 404 sans erreur PHP" not_found
done
req big GET /plat/2147483647
check "GET /plat/2147483647 (id max, inexistant) => 404 sans erreur PHP" not_found
new_account big
req big GET /plat/1; t=$(token)
for path in /plat/2147483648/vote /plat/2147483648/avis; do
    req big POST "$path" --data-urlencode "token=$t" --data-urlencode score=4 --data-urlencode comment=x
    check "connecté POST $path => 404 sans erreur PHP" not_found
done
login adm admin@foodbattle.local foodbattle-admin
req adm GET /dashboard/plats/2147483648/modifier
check "admin GET /dashboard/plats/2147483648/modifier => 404 sans erreur PHP" not_found
req adm GET /moderation; t=$(token)
for path in /dashboard/plats/2147483648/supprimer /moderation/2147483648/publier /moderation/2147483648/refuser; do
    req adm POST "$path" --data-urlencode "token=$t"
    check "admin POST $path => 404 sans erreur PHP" not_found
done

section "En-têtes de sécurité (clickjacking, type de contenu)"
security_headers() {
    grep -qi '^x-frame-options: DENY' "$DIR/headers" && grep -qi '^x-content-type-options: nosniff' "$DIR/headers"
}
for path in / /plat/1 /classement /page-inexistante; do
    req hdr GET "$path"
    check "GET $path => X-Frame-Options: DENY et X-Content-Type-Options: nosniff" security_headers
done
login hdr admin@foodbattle.local foodbattle-admin
for path in /dashboard /moderation /dashboard/plats/1/modifier; do
    req hdr GET "$path"
    check "admin GET $path => X-Frame-Options: DENY et X-Content-Type-Options: nosniff" security_headers
done

section "Menu de l'administration (pages Plats, Établissements, Modération)"
req navv GET /dashboard/etablissements
check "visiteur GET /dashboard/etablissements => 302 vers /connexion" [ "$CODE" = 302 -a "$LOC" = /connexion ]
login navu user@foodbattle.local foodbattle-user
req navu GET /dashboard/etablissements
check "user GET /dashboard/etablissements => 403" [ "$CODE" = 403 ]

login nav admin@foodbattle.local foodbattle-admin
EST=$(sql "SELECT name FROM establishments ORDER BY id LIMIT 1")
req nav GET /dashboard/etablissements
check "admin GET /dashboard/etablissements => 200, « $EST » listé" bash -c '[ "$1" = 200 ] && grep -q "<td>$2</td>" <<<"$3"' _ "$CODE" "$EST" "$BODY"

# Une seule pilule active, celle de la page affichée (les liens sont sur une ligne chacun dans le partial)
active_link() {  # active_link CHEMIN
    [ "$(grep -o 'class="admin-nav__link" href="[^"]*" aria-current="page"' <<<"$BODY")" = "class=\"admin-nav__link\" href=\"$1\" aria-current=\"page\"" ]
}
for path in /dashboard /dashboard/etablissements /moderation; do
    req nav GET "$path"
    check "admin GET $path => menu admin, seule la pilule $path est active" active_link "$path"
done
req nav GET /dashboard/plats/1/modifier
check "page Modifier un plat => menu admin présent, aucune pilule active" \
    bash -c 'grep -q "class=\"admin-nav\"" <<<"$1" && ! grep -q "admin-nav__link[^>]*aria-current" <<<"$1"' _ "$BODY"

# Nettoyage : les comptes de test de ce script, avec leurs votes et leurs avis.
# (On supprime d'abord votes et avis : ON DELETE SET NULL les garderait sinon, signés « Compte désactivé ».)
sql "DELETE FROM ratings WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'fx-$RUN-%@test.fr') OR comment = 'Avis du compte supprime'" >/dev/null
sql "DELETE FROM votes WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'fx-$RUN-%@test.fr')" >/dev/null
sql "DELETE FROM users WHERE email LIKE 'fx-$RUN-%@test.fr'" >/dev/null

finish
