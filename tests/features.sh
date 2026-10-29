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
check "statut par défaut d'un avis en base : pending (init.sql)" grep -q "^'pending'" <<<"$(sql "SELECT column_default FROM information_schema.columns WHERE table_name = 'ratings' AND column_name = 'status'")"

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
notes=$(grep -A4 '<a href="/plat/' <<<"$BODY" | grep -oE '<td[^>]*>([0-9],[0-9]|—)</td>' | sed 's/<[^>]*>//g; s/,/./; s/—/-1/')
# [ -n ] : sans note trouvée (motif HTML périmé), la comparaison passerait à vide
check "classement : notes affichées dans l'ordre décroissant" bash -c '[ -n "$1" ] && [ "$1" = "$(sort -rn <<<"$1")" ]' _ "$notes"
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

section "Modération : filtre par statut"
# L'avis $RID est refusé (section précédente).
active_filter() { grep -A1 'aria-current="true"' <<<"$BODY" | grep -q "$1"; }   # active_filter "Refusés (3)"
ok_page() { [ "$CODE" = 200 ] && no_php_error; }
for s in pending:'En attente':'Publié' published:'Publiés':'En attente' rejected:'Refusés':'Publié'; do
    IFS=: read -r status label other <<<"$s"
    n=$(sql "SELECT COUNT(*) FROM ratings WHERE status = '$status'")
    req a GET "/moderation?statut=$status"
    check "?statut=$status : pilule « $label ($n) » active" active_filter "$label ($n)"
    check "?statut=$status : $n avis listés, aucun « $other »" \
        bash -c '[ "$(grep -c "<td[^>]*>$1</td>" <<<"$2")" = 0 ] && [ "$(grep -o "action=\"/moderation/[0-9]*/" <<<"$2" | sort -u | wc -l)" -eq "$3" ]' \
        _ "$other" "$BODY" "$n"
done
req a GET "/moderation?statut=pending"
check "?statut=pending : l'avis refusé $RID n'est pas listé" hasnt "action=\"/moderation/$RID/"
req a GET "/moderation?statut=rejected"
check "?statut=rejected : l'avis refusé $RID est listé" has "action=\"/moderation/$RID/publier\""
check "… le formulaire garde le filtre (champ caché statut=rejected)" has 'name="statut" value="rejected"'
t=$(token)
req a POST "/moderation/$RID/publier" --data-urlencode "token=$t" --data-urlencode statut=rejected
check "publier depuis le filtre => 302 vers /moderation?statut=rejected&fait=published" \
    [ "$CODE" = 302 -a "$LOC" = "/moderation?statut=rejected&fait=published" ]
req a POST "/moderation/$RID/refuser" --data-urlencode "token=$t" --data-urlencode statut=spam
check "filtre inconnu renvoyé => ignoré (302 vers /moderation?fait=rejected)" [ "$CODE" = 302 -a "$LOC" = "/moderation?fait=rejected" ]
total=$(sql "SELECT COUNT(*) FROM ratings")
for q in "" "?statut=spam" "?statut%5B%5D=pending"; do   # %5B%5D = [] (statut envoyé en tableau)
    req a GET "/moderation$q"
    check "/moderation$q : 200 sans erreur PHP" ok_page
    check "/moderation$q : pilule « Tous ($total) » active" active_filter "Tous ($total)"
done

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
check "… puis retour au tableau des plats (redirection vers /dashboard?updated=1)" [ "$LOC" = "/dashboard?updated=1" ]
req a GET "$LOC"
check "… avec le message « Le plat a été modifié. »" has 'Le plat a été modifié.'
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
req a GET /dashboard/plats/3/modifier   # après l'enregistrement on revient au tableau : on rouvre le formulaire
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

section "Tri du tableau du dashboard (admin)"
# Noms des plats dans l'ordre d'affichage du tableau
dish_order() { grep -o 'data-label="Plat">[^<]*' <<<"$BODY" | sed 's/.*>//'; }
# Vrai si les plats donnés sont affichés dans cet ordre (in_order A B C : A avant B, B avant C)
in_order() {
    local list previous=0 line name
    list=$(dish_order)
    for name in "$@"; do
        line=$(grep -nxF "$name" <<<"$list" | cut -d: -f1)
        [ -n "$line" ] && [ "$line" -gt "$previous" ] || return 1
        previous=$line
    done
}
req a GET '/dashboard?tri=prix&ordre=asc'
# Plats choisis pour que l'ordre attendu contredise l'ordre d'ajout (id) : le test échoue si le tri est ignoré
check "tri prix croissant => 8,90 € / 10,50 € / 12,50 € / 14 €" in_order 'Le Veggie Tempo' 'Le Classique' 'Le Double Smash' 'La Quatre Fromages'
check "… colonne Prix marquée aria-sort=\"ascending\"" has 'aria-sort="ascending"'
check "… le lien Prix inverse l'ordre" has 'href="/dashboard?tri=prix&amp;ordre=desc"'
req a GET '/dashboard?tri=prix&ordre=desc'
check "tri prix décroissant => ordre inversé" in_order 'La Quatre Fromages' 'Le Veggie Tempo'
check "… colonne Prix marquée aria-sort=\"descending\"" has 'aria-sort="descending"'
req a GET '/dashboard?tri=lieu&ordre=asc'
check "tri par lieu => Lille (Le Comptoir Smash, Pizza Minuit, Taco Tempo), puis Roubaix, puis Tourcoing" \
    in_order 'Le Double Smash' 'La Minuit' 'Le Poulet Crunch' 'Le Nordiste' 'Le Trois Sauces'
req a GET '/dashboard?tri=lieu&ordre=desc'
check "tri par lieu décroissant => Tourcoing avant Lille" in_order 'Le Trois Sauces' 'Le Double Smash'
req a GET '/dashboard?tri=categorie&ordre=asc'
check "tri par catégorie => burgers, puis pizzas, puis tacos" in_order 'Le Classique' 'La Minuit' 'Le Poulet Crunch'
check "… colonnes Ville et Catégorie affichées" \
    bash -c 'grep -q "data-label=\"Ville\">Lille<" <<<"$1" && grep -q "data-label=\"Catégorie\">Pizzas<" <<<"$1"' _ "$BODY"
req a GET "/dashboard?tri=n'importe+quoi&ordre=desc"
check "tri inconnu => 200 et ordre par défaut (Le Double Smash en premier)" \
    [ "$CODE" = 200 -a "$(dish_order | head -1)" = 'Le Double Smash' ]
check "… aucune colonne marquée comme triée" hasnt 'aria-sort'
req a GET '/dashboard?tri[]=prix&ordre[]=desc'
check "tri[]=… => 200, ordre par défaut" [ "$CODE" = 200 -a "$(dish_order | head -1)" = 'Le Double Smash' ]

section "Ajout d'un établissement (admin)"
add_establishment() {  # add_establishment JAR NOM VILLE ADRESSE (déjà encodés pour l'URL)
    req "$1" GET /dashboard/etablissements; local t; t=$(token)
    req "$1" POST /dashboard/etablissements --data-urlencode "token=$t" \
        --data "name=$2" --data "city=$3" --data "address=$4"
}
count_establishments() { sql "SELECT COUNT(*) FROM establishments WHERE name LIKE '%$RUN%'"; }
req anon POST /dashboard/etablissements --data "name=Visiteur+$RUN&city=Lille&address=1+rue"
check "visiteur POST /dashboard/etablissements => 302 vers /connexion" [ "$CODE" = 302 -a "$LOC" = /connexion ]
add_establishment u "User+$RUN" Lille "1+rue"
check "user POST /dashboard/etablissements => 403" [ "$CODE" = 403 ]
req a POST /dashboard/etablissements --data "name=Sans+jeton+$RUN&city=Lille&address=1+rue"
check "admin sans jeton CSRF => 403" [ "$CODE" = 403 ]
check "… aucun établissement créé" [ "$(count_establishments)" = 0 ]
req a GET /dashboard/etablissements
check "formulaire « Ajouter un établissement » affiché" has 'action="/dashboard/etablissements"'
add_establishment a "Chez+Ren%C3%A9+$RUN" "Villeneuve-d%E2%80%99Ascq" "2+all%C3%A9e+des+Tilleuls"
check "admin ajoute => 302 vers /dashboard/etablissements?added=1" \
    [ "$CODE" = 302 -a "$LOC" = "/dashboard/etablissements?added=1" ]
check "établissement enregistré en base, accents compris" \
    [ "$(sql "SELECT city || ' | ' || address FROM establishments WHERE name = 'Chez René $RUN'")" = "Villeneuve-d’Ascq | 2 allée des Tilleuls" ]
req a GET "/dashboard/etablissements?added=1"
check "message « L'établissement a été ajouté. » affiché" has 'établissement a été ajouté.'
check "… listé dans la table des établissements" has "<td[^>]*>Chez René $RUN</td>"
req a GET /dashboard
check "… et proposé dans « Ajouter un plat »" has ">Chez René $RUN</option>"
add_establishment a "+++" Lille "1+rue"
check "nom vide (espaces) => refusé" has "Vérifie les champs de l&#039;établissement."
add_establishment a "Long+$RUN" "$(printf 'v%.0s' {1..81})" "1+rue"
check "ville de 81 caractères => refusée" has "Vérifie les champs de l&#039;établissement."
add_establishment a "Long+$RUN" Lille "$(printf 'a%.0s' {1..256})"
check "adresse de 256 caractères => refusée" has "Vérifie les champs de l&#039;établissement."
check "… aucun établissement invalide en base" [ "$(count_establishments)" = 1 ]
sql "DELETE FROM establishments WHERE name LIKE '%$RUN%'" >/dev/null

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
# Se termine par un espace sans largeur (U+200B) : refusé. Envoyé déjà encodé (%E2%80%8B) :
# sous Windows, curl réencode les octets passés en argument et le caractère deviendrait visible.
req f GET /plat/8; t=$(token)
req f POST /plat/8/avis --data-urlencode "token=$t" --data "score=2&comment=Trop+bien%E2%80%8B"
check "avis refusé => 422, fiche réaffichée avec le message d'erreur" \
    bash -c '[ "$1" = 422 ] && grep -q "Vérifie les champs de ton avis." <<<"$2"' _ "$CODE" "$BODY"
check "… le commentaire saisi est conservé" has '>Trop bien'
check "… la note choisie est conservée" has 'value="2" selected'
# « Top en famille 👨‍👩‍👧 ❤️‍🔥 », encodé pour la même raison
req f POST /plat/8/avis --data-urlencode "token=$t" \
    --data "score=5&comment=Top+en+famille+%F0%9F%91%A8%E2%80%8D%F0%9F%91%A9%E2%80%8D%F0%9F%91%A7+%E2%9D%A4%EF%B8%8F%E2%80%8D%F0%9F%94%A5"
check "avis avec des emojis composés (👨‍👩‍👧, ❤️‍🔥) => accepté" [ "$CODE" = 302 ]
req s GET '/?q=%25'
check "recherche « % » => cherchée comme un caractère (aucun plat)" hasnt 'href="/plat/'
req s GET '/?q=_'
check "recherche « _ » => cherchée comme un caractère (aucun plat)" hasnt 'href="/plat/'
req s GET '/?q=Smash'
check "recherche « Smash » => toujours fonctionnelle" has 'Le Double Smash'
req s GET '/?q=Planque'
check "recherche « Planque » (établissement) => ses plats sont listés" \
    bash -c 'grep -q "Le Nordiste" <<<"$1" && grep -q "Le Classique" <<<"$1"' _ "$BODY"
check "… et seulement eux" hasnt 'Le Double Smash'
req s GET '/?q=Planque&categorie=pizza'
check "recherche « Planque » + catégorie pizza => aucun plat (le filtre catégorie s'applique)" hasnt 'href="/plat/'
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
exp=$(expected_average 1)
v=$(sql "SELECT COUNT(*) FROM votes WHERE dish_id = 1")
req x GET /plat/1
check "fiche plat : note moyenne et nombre de votes affichés (★ $exp/5 · $v votes)" has "$exp/5 · $v vote"
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
            bash -c 'grep -A4 "href=\"/plat/$1\"" <<<"$2" | grep -q "<td[^>]*>$3</td>"' _ "$D" "$BODY" "$exp"
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
    bash -c 'grep -A4 "href=\"/plat/$1\"" <<<"$2" | grep -q "<td[^>]*>—</td>"' _ "$TOP" "$BODY"
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

section "Méthode HTTP non acceptée => 405 au lieu de 404"
req m GET /plat/1/vote
check "GET /plat/1/vote (POST uniquement) => 405" [ "$CODE" = 405 ]
check "… avec l'en-tête « Allow: POST »" grep -qi '^allow: POST' "$DIR/headers"
req m POST /classement
check "POST /classement (GET uniquement) => 405, « Allow: GET »" \
    bash -c '[ "$1" = 405 ] && grep -qi "^allow: GET" "$2"' _ "$CODE" "$DIR/headers"
check "HEAD /classement => 200, comme GET" [ "$(curl -s -o /dev/null -I -w '%{http_code}' "$BASE/classement")" = 200 ]
req m GET /page-inexistante
check "adresse inconnue => toujours 404" [ "$CODE" = 404 ]

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
check "admin GET /dashboard/etablissements => 200, « $EST » listé" bash -c '[ "$1" = 200 ] && grep -q "<td[^>]*>$2</td>" <<<"$3"' _ "$CODE" "$EST" "$BODY"

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

# Lien Dashboard du header : allumé sur toute l'administration
dashboard_link() {  # valeur d'aria-current sur le lien Dashboard du header (vide si absent)
    grep -o 'class="site-header__link" href="/dashboard"[^>]*' <<<"$BODY" | grep -o 'aria-current="[a-z]*"'
}
req nav GET /dashboard
check "header : Dashboard en aria-current=\"page\" sur /dashboard" [ "$(dashboard_link)" = 'aria-current="page"' ]
for path in /dashboard/etablissements /moderation /dashboard/plats/1/modifier; do
    req nav GET "$path"
    check "header : Dashboard en aria-current=\"true\" sur $path" [ "$(dashboard_link)" = 'aria-current="true"' ]
done
req nav GET /classement
check "header : Dashboard sans aria-current hors administration" [ -z "$(dashboard_link)" ]

section "Nom de plat compté en caractères (pas en octets)"
# 61 « é » = 61 caractères mais 122 octets : refusés à tort tant qu'on comptait avec strlen.
E61=$(printf '%%C3%%A9%.0s' {1..61})
E121=$(printf '%%C3%%A9%.0s' {1..121})
login pb admin@foodbattle.local foodbattle-admin
req pb GET /dashboard; t=$(token)
req pb POST /dashboard/plats --data-urlencode "token=$t" --data "name=${E61}+$RUN" \
    --data establishment_id=1 --data category=burger --data price=5
check "ajout d'un plat au nom de 72 caractères accentués (133 octets) => accepté" [ "$CODE" = 302 -a "$LOC" = "/dashboard?added=1" ]
PB=$(sql "SELECT id FROM dishes WHERE name LIKE '%$RUN'")
req pb POST /dashboard/plats --data-urlencode "token=$t" --data "name=$E121" \
    --data establishment_id=1 --data category=burger --data price=5
check "ajout d'un plat au nom de 121 caractères => refusé" has 'Vérifie les champs du plat.'
req pb GET "/dashboard/plats/$PB/modifier"; t=$(token)
req pb POST "/dashboard/plats/$PB/modifier" --data-urlencode "token=$t" --data "name=${E61}+bis+$RUN" --data price=6
check "modification avec un nom de 76 caractères accentués => acceptée" [ "$(sql "SELECT char_length(name) FROM dishes WHERE id=$PB")" = $((61 + 5 + ${#RUN})) ]
sql "DELETE FROM dishes WHERE name LIKE '%$RUN'" >/dev/null

section "Bouton thème : retour vers une page qui existe"
req th GET "/classement"
check "page GET : le bouton thème revient sur la page (back=/classement)" has 'name="back" value="/classement"'
step1 th user@foodbattle.local
password th user@foodbattle.local mauvais-mot-de-passe
check "page affichée après un POST (mot de passe faux) : back=/ au lieu de /connexion/mot-de-passe" \
    bash -c 'grep -q "name=\"back\" value=\"/\"" <<<"$1" && ! grep -q "name=\"back\" value=\"/connexion" <<<"$1"' _ "$BODY"
req th GET /connexion/mot-de-passe
check "… car /connexion/mot-de-passe n'existe pas en GET (405)" [ "$CODE" = 405 ]

section "Pages d'erreur (403, 404) et mentions légales"
error_page() {  # error_page CODE TITRE : page d'erreur du site (code en grand, titre, en-tête et pied de page)
    [ "$CODE" = "$1" ] && grep -q "error-page__code\" aria-hidden=\"true\">$1<" <<<"$BODY" \
        && grep -q "<title>$2 · FoodBattle" <<<"$BODY" && grep -q 'class="site-header"' <<<"$BODY" \
        && grep -q 'href="/mentions-legales"' <<<"$BODY" && hasnt 'Fatal error' && hasnt 'Uncaught'
}
req ep GET /adresse-inexistante
check "adresse inconnue => page 404 du site" error_page 404 "Page introuvable"
req ep GET /plat/999999
check "plat inexistant => page 404 du site" error_page 404 "Page introuvable"
check "… avec « Ce plat n'existe pas ou a été supprimé. »" has "Ce plat n&#039;existe pas ou a été supprimé."
req ep POST /theme
check "formulaire sans jeton CSRF => page 403 du site" error_page 403 "Accès refusé"
req ep POST /classement
check "méthode non acceptée (POST /classement) => page 405 du site" error_page 405 "Action impossible"
login ep user@foodbattle.local foodbattle-user
req ep GET /dashboard
check "user sur /dashboard => page 403 du site" error_page 403 "Accès refusé"
check "… avec « Accès réservé aux administrateurs. »" has "Accès réservé aux administrateurs."
req ep GET /mentions-legales
check "GET /mentions-legales => 200, éditeurs et contact affichés" \
    bash -c '[ "$1" = 200 ] && grep -q "Cécile et Ludwig" <<<"$2" && grep -q "mailto:contact@foodbattle.local" <<<"$2"' _ "$CODE" "$BODY"
check "… sections Mentions légales, Données personnelles, Cookies" \
    bash -c 'grep -q "id=\"mentions-legales\"" <<<"$1" && grep -q "id=\"donnees-personnelles\"" <<<"$1" && grep -q "id=\"cookies\"" <<<"$1"' _ "$BODY"

# Nettoyage : les comptes de test de ce script, avec leurs votes et leurs avis.
# (On supprime d'abord votes et avis : ON DELETE SET NULL les garderait sinon, signés « Compte désactivé ».)
sql "DELETE FROM ratings WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'fx-$RUN-%@test.fr') OR comment = 'Avis du compte supprime'" >/dev/null
sql "DELETE FROM votes WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'fx-$RUN-%@test.fr')" >/dev/null
sql "DELETE FROM users WHERE email LIKE 'fx-$RUN-%@test.fr'" >/dev/null

# En dernier : on coupe la base quelques secondes pour provoquer une vraie erreur serveur.
section "Accessibilité (lien d'évitement, titres d'onglet, classement en cartes)"
for path in / /classement /plat/1 /connexion /mentions-legales /nexiste-pas; do
    req anon GET "$path"
    check "$path : lien « Aller au contenu » vers <main id=\"contenu\">" \
        bash -c 'grep -q "<a class=\"skip-link\" href=\"#contenu\">Aller au contenu</a>" <<<"$1" && grep -q "<main class=\"page\" id=\"contenu\">" <<<"$1"' _ "$BODY"
done
req anon GET /plat/1
check "fiche plat : le nom du plat est dans le titre de l'onglet" has '<title>Le Double Smash · FoodBattle</title>'
login a11y admin@foodbattle.local foodbattle-admin
req a11y GET /dashboard/plats/1/modifier
check "modification : le nom du plat est dans le titre de l'onglet" has '<title>Modifier Le Double Smash · FoodBattle</title>'
check "… et dans le titre de la page" has '<h1>Modifier Le Double Smash</h1>'
req anon GET /classement
check "classement : tableau en cartes sur mobile (pas de défilement horizontal à 320 px)" \
    bash -c 'grep -q "class=\"table table--cards\"" <<<"$1" && grep -q "data-label=\"Plat\"" <<<"$1"' _ "$BODY"
req a11y GET /dashboard
check "dashboard : « Supprimer » complété du nom du plat pour les lecteurs d'écran" \
    has 'Supprimer<span class="visually-hidden"> Le Double Smash</span>'
check "… et « Modifier » aussi" has 'Modifier<span class="visually-hidden"> Le Double Smash</span>'
check "dashboard : libellé « Trier par » des pilules de tri (mobile)" has '<p class="sort-label" aria-hidden="true">Trier par</p>'
req a11y GET /moderation
check "modération : boutons complétés par « l'avis de … sur … »" has "<span class=\"visually-hidden\"> l&#039;avis de "
req anon GET /
check "accueil : bouton des cartes retiré du parcours clavier (le titre est déjà un lien)" \
    has 'card__action" href="/plat/[0-9]*" tabindex="-1" aria-hidden="true"'
check "accueil : flèche « → » masquée aux lecteurs d'écran" has 'Voir le classement <span aria-hidden="true">→</span>'
req anon GET /plat/1
check "fiche plat : restaurant et prix ne sont plus des titres (aucun <h3>)" hasnt '<h3'
check "… le restaurant n'est plus un <h2>" has '<p class="dish-detail__restaurant">'
check "fiche plat : « ♥ » et « ← » masqués aux lecteurs d'écran" \
    bash -c 'grep -q "<span aria-hidden=\"true\">♥</span>" <<<"$1" && grep -q "<span aria-hidden=\"true\">←</span>" <<<"$1"' _ "$BODY"

section "Accessibilité : erreurs reliées à leur champ"
INVALID='aria-invalid="true" aria-describedby="form-error"'
step1 err-e pas-un-email
check "email invalide => champ email signalé invalide et relié au message" has "autocomplete=\"email\" required autofocus $INVALID>"
check "… message d'erreur avec id=\"form-error\"" has 'id="form-error" role="alert"'
req err-ok GET /connexion
check "sans erreur => aucun champ signalé invalide" hasnt 'aria-invalid'
step1 err-p user@foodbattle.local; password err-p user@foodbattle.local faux
check "mot de passe faux => champ mot de passe signalé invalide" has "autocomplete=\"current-password\" required autofocus $INVALID>"
for i in 1 2 3 4 5; do sql "INSERT INTO rate_limit_hits (key) VALUES ('login:user@foodbattle.local')" >/dev/null; done
step1 err-b user@foodbattle.local; password err-b user@foodbattle.local faux
check "compte bloqué => 429" [ "$CODE" = 429 ]
check "… le mot de passe n'est pas en cause : champ non signalé invalide" hasnt 'aria-invalid'
sql "DELETE FROM rate_limit_hits" >/dev/null
taken=$(sql "SELECT nickname FROM users WHERE email='user@foodbattle.local'")
step1 err-r "err-$RUN@test.fr"; register err-r "err-$RUN@test.fr" "$taken" motdepasse
check "pseudo déjà pris => pseudo signalé invalide, relié au message et à son aide" \
    has 'aria-invalid="true" aria-describedby="form-error nickname-hint"'
check "… mot de passe non signalé, mais toujours relié à son aide" has 'autocomplete="new-password" required aria-describedby="password-hint">'
step1 err-r2 "err2-$RUN@test.fr"; register err-r2 "err2-$RUN@test.fr" "Err$RUN" court
check "mot de passe trop court => mot de passe signalé invalide, pas le pseudo" \
    bash -c 'grep -q "aria-describedby=\"form-error password-hint\"" <<<"$1" && grep -q "aria-describedby=\"nickname-hint\">" <<<"$1"' _ "$BODY"
login err-a user@foodbattle.local foodbattle-user
req err-a GET /plat/2; t=$(token)
req err-a POST /plat/2/avis --data-urlencode "token=$t" --data "score=4" --data-urlencode $'comment=Caract\x01re interdit'
check "avis refusé => commentaire signalé invalide" has "required $INVALID>"

section "Page 500 (base de données coupée)"
docker compose stop db >/dev/null 2>&1
req ep GET /
check "base coupée => page 500 du site, sans trace PHP brute" error_page 500 "Erreur du serveur"
docker compose start db >/dev/null 2>&1
for _ in $(seq 1 30); do req ep GET /; [ "$CODE" = 200 ] && break; sleep 1; done
check "base relancée => le site répond de nouveau (200)" [ "$CODE" = 200 ]

finish
