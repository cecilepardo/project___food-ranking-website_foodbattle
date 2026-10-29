#!/usr/bin/env bash
# Tests HTTP de l'authentification (voir tests/README.md).
cd "$(dirname "$0")/.." || exit 1   # racine du projet : docker compose y trouve compose.yaml
source tests/lib.sh

section "Visiteur"
req v GET /
check "GET / => 200" [ "$CODE" = 200 ]
check "header : lien Connexion" has 'href="/connexion"'
check "header : plus de lien Inscription" hasnt 'href="/inscription"'
check "header : pas de lien Dashboard" hasnt 'href="/dashboard"'
check "cookie de session HttpOnly + SameSite=Lax" grep -qi 'set-cookie: PHPSESSID=.*HttpOnly.*SameSite=Lax' "$DIR/headers"
req v GET /dashboard
check "GET /dashboard => 302 vers /connexion" [ "$CODE" = 302 -a "$LOC" = /connexion ]
req v GET /inscription
check "ancienne route GET /inscription => 404" [ "$CODE" = 404 ]
req v GET /connexion/mot-de-passe
check "GET /connexion/mot-de-passe (POST uniquement) => 405" [ "$CODE" = 405 ]

section "Étape 1 : email"
req v GET /connexion
check "étape 1 : un seul champ, l'email (pas de mot de passe)" hasnt 'type="password"'
req v POST /connexion --data "email=user@foodbattle.local"
check "POST /connexion sans jeton CSRF => 403" [ "$CODE" = 403 ]
step1 v pas-un-email
check "email invalide => reste à l'étape 1 avec erreur" has 'Saisis une adresse email valide.'
req v POST /connexion --data "token[]=x&email[]=x"
check "champs en tableau => pas d'erreur PHP (ni 500, ni erreur affichée)" no_php_error

section "Étape 2a : compte existant (user)"
req u GET /connexion; before=$(sid u)
step1 u '  USER@FoodBattle.local '
check "email connu (casse/espaces) => étape mot de passe" has 'Bon retour'
check "étape 2a : email normalisé affiché" has '<strong>user@foodbattle.local</strong>'
check "étape 2a : email renvoyé dans un champ autocomplete=username" has 'autocomplete="username"'
check "étape 2a : lien « Ce n'est pas mon email »" has 'href="/connexion"'
password u user@foodbattle.local mauvais-mdp
check "mauvais mot de passe => message précis" has 'Mot de passe incorrect.'
check "mauvais mot de passe => NON réaffiché" hasnt 'mauvais-mdp'
password u user@foodbattle.local foodbattle-user
check "bon mot de passe => 302 vers /" [ "$CODE" = 302 -a "$LOC" = / ]
check "identifiant de session régénéré" [ "$(sid u)" != "$before" ]
req u GET /
check "header : bouton Déconnexion, pas de Dashboard" bash -c "grep -q 'action=\"/deconnexion\"' <<<\"\$0\" && ! grep -q 'href=\"/dashboard\"' <<<\"\$0\"" "$BODY"
req u GET /dashboard
check "user GET /dashboard => 403" [ "$CODE" = 403 ]

section "Déconnexion"
req u GET /; t=$(token)
req u POST /deconnexion --data-urlencode "token=$t"
check "POST /deconnexion => 302 vers /" [ "$CODE" = 302 -a "$LOC" = / ]
req u GET /dashboard
check "après déconnexion, /dashboard => 302 /connexion" [ "$CODE" = 302 -a "$LOC" = /connexion ]

section "Étape 2a : admin"
step1 a admin@foodbattle.local
password a admin@foodbattle.local foodbattle-admin
check "login admin => 302 vers /dashboard" [ "$CODE" = 302 -a "$LOC" = /dashboard ]
req a GET /dashboard
check "admin GET /dashboard => 200 + lien Dashboard" bash -c "[ $CODE = 200 ] && grep -q 'href=\"/dashboard\"' <<<\"\$0\"" "$BODY"
req a GET /moderation
check "admin GET /moderation => 200" [ "$CODE" = 200 ]

section "Étape 2b : nouvel email (inscription)"
NEW="nouveau-$RUN@test.fr"; NICK="Nouveau$RUN"
step1 r "$NEW"
check "email inconnu => étape inscription" has 'Aucun compte pour'
check "étape 2b : email affiché" has "<strong>$NEW</strong>"
register r "$NEW" "$NICK" court
check "mot de passe < 8 => refusé, reste à l'étape 2b" bash -c "grep -q 'entre 8 et 72' <<<\"\$0\" && grep -q 'Aucun compte pour' <<<\"\$0\"" "$BODY"
check "pseudo réaffiché après erreur" has "value=\"$NICK\""
register r "$NEW" "$NICK" "$(printf 'a%.0s' {1..73})"
check "mot de passe > 72 octets => refusé" has 'entre 8 et 72'
t=$(token)
req r POST /connexion/inscription --data-urlencode "token=$t" --data-urlencode "email=$NEW" \
    --data-urlencode "nickname=$NICK" --data "password=abcd%00efghijk"
check "mot de passe avec un caractère nul => refusé proprement (plus d'erreur 500)" \
    bash -c '[ "$1" = 200 ] && grep -q "caractère non autorisé" <<<"$2"' _ "$CODE" "$BODY"
register r "$NEW" testeur motdepasse
check "pseudo déjà pris (casse différente) => refusé" has 'Ce pseudo est déjà pris.'
register r user@foodbattle.local "$NICK" motdepasse
check "champ caché trafiqué (email existant) => refusé" has 'Un compte existe déjà'
register r pas-un-email "$NICK" motdepasse
check "champ caché trafiqué (email invalide) => refusé" has 'Saisis une adresse email valide.'
register r "$NEW" "<b>$NICK</b>" motdepasse
check "pseudo avec balises HTML (<b>…</b>) => refusé par le format" has 'Pseudo invalide'
register r "$NEW" "$NICK" motdepasse
check "inscription valide => 302 vers /" [ "$CODE" = 302 -a "$LOC" = / ]
check "compte créé : rôle user, hash \$2y\$12\$" [ "$(sql "SELECT role || ' ' || left(password_hash,7) FROM users WHERE email='$NEW'")" = 'user $2y$12$' ]
req r GET /
check "inscription => connecté directement (bouton Déconnexion dans le header)" has 'action="/deconnexion"'
req r GET /dashboard
check "nouvel inscrit GET /dashboard => 403" [ "$CODE" = 403 ]

section "Le compte créé peut se reconnecter"
step1 r2 "$NEW"
check "email du nouveau compte => étape mot de passe" has 'Bon retour'
password r2 "$NEW" motdepasse
check "reconnexion => 302 vers /" [ "$CODE" = 302 -a "$LOC" = / ]

section "Inscriptions simultanées du même email (double-clic)"
DOUBLE="double-$RUN@test.fr"
for i in 1 2 3 4; do step1 "dbl$i" "$DOUBLE"; token > "$DIR/dbl$i.token"; done
codes=$(for i in 1 2 3 4; do
    curl -s -o "$DIR/dbl$i.body" -w '%{http_code}\n' -b "$DIR/dbl$i" -c "$DIR/dbl$i" -X POST "$BASE/connexion/inscription" \
        --data-urlencode "token=$(cat "$DIR/dbl$i.token")" --data-urlencode "email=$DOUBLE" \
        --data-urlencode "nickname=Double$i$RUN" --data-urlencode "password=motdepasse" &
done; wait)
check "4 envois en même temps => aucune erreur 500" bash -c '! grep -q 500 <<<"$1"' _ "$codes"
check "… un seul compte créé" [ "$(sql "SELECT count(*) FROM users WHERE email='$DOUBLE'")" = 1 ]
check "… les autres envois affichent « Un compte existe déjà »" \
    [ "$(grep -l 'Un compte existe déjà' "$DIR"/dbl?.body | wc -l)" -eq "$(grep -c 200 <<<"$codes")" ]
sql "DELETE FROM users WHERE email='$DOUBLE'" >/dev/null

section "Limitation par compte (niveau 2)"
for i in 1 2 3 4 5; do step1 b user@foodbattle.local; password b user@foodbattle.local "faux-$i"; done
check "5 mauvais mots de passe => toujours 'Mot de passe incorrect.'" has 'Mot de passe incorrect.'
step1 b user@foodbattle.local; password b user@foodbattle.local faux-6
check "6e tentative => 429 + message de blocage" bash -c "[ $CODE = 429 ] && grep -q 'Trop de mots de passe incorrects' <<<\"\$0\"" "$BODY"
check "message de blocage : délai en minutes (15)" has 'dans 15 minute(s)'
step1 b user@foodbattle.local; password b user@foodbattle.local foodbattle-user
check "BON mot de passe pendant le blocage => refusé (429, pas de connexion)" [ "$CODE" = 429 ]
check "tentatives bloquées non comptées (5 lignes login:user)" [ "$(sql "SELECT COUNT(*) FROM rate_limit_hits WHERE key='login:user@foodbattle.local'")" = 5 ]
sql "DELETE FROM rate_limit_hits WHERE key LIKE 'auth-ip:%'" >/dev/null
step1 c admin@foodbattle.local; password c admin@foodbattle.local foodbattle-admin
check "un autre compte (admin) n'est pas bloqué" [ "$CODE" = 302 -a "$LOC" = /dashboard ]

section "Connexion réussie => compteur remis à zéro"
for i in 1 2 3 4; do step1 d user@foodbattle.local; password d user@foodbattle.local "faux-$i"; done
step1 d user@foodbattle.local; password d user@foodbattle.local foodbattle-user
check "4 échecs puis bon mot de passe => connecté" [ "$CODE" = 302 -a "$LOC" = / ]
check "compteur du compte effacé après succès" [ "$(sql "SELECT COUNT(*) FROM rate_limit_hits WHERE key='login:user@foodbattle.local'")" = 0 ]

section "Email trafiqué à l'étape mot de passe"
req e GET /connexion; t=$(token)
req e POST /connexion/mot-de-passe --data-urlencode "token=$t" --data-urlencode "email=$(printf 'a%.0s' {1..400})@x.fr" --data-urlencode "password=x"
check "email de 400+ caractères => pas d'erreur 500, retour à l'étape 1" bash -c "[ $CODE = 200 ] && grep -q 'Saisis une adresse email valide.' <<<\"\$0\"" "$BODY"

section "Limitation par IP (niveau 1)"
for i in $(seq 1 20); do req f POST /connexion --data "email=x$i@test.fr"; done
check "20 POST (même invalides, sans CSRF) => pas encore bloqué" [ "$CODE" = 403 ]
req f POST /connexion --data "email=x21@test.fr"
check "21e POST => 429 texte" bash -c "[ $CODE = 429 ] && grep -q 'Trop de tentatives de connexion' <<<\"\$0\"" "$BODY"
check "en-tête Retry-After présent (secondes)" grep -qiE '^retry-after: [0-9]+' "$DIR/headers"
req f POST /connexion/mot-de-passe --data "email=user@foodbattle.local&password=foodbattle-user"
check "étape mot de passe aussi bloquée => 429" [ "$CODE" = 429 ]
req f POST /connexion/inscription --data "email=y@test.fr"
check "étape inscription aussi bloquée => 429" [ "$CODE" = 429 ]
req f GET /connexion
check "GET /connexion (afficher le formulaire) reste accessible" [ "$CODE" = 200 ]
check "requêtes bloquées non comptées (20 lignes pour l'IP)" [ "$(sql "SELECT COUNT(*) FROM rate_limit_hits WHERE key LIKE 'auth-ip:%'")" = 20 ]
req f GET /
check "le reste du site n'est pas bloqué" [ "$CODE" = 200 ]

section "Limitation par IP : les connexions réussies ne comptent pas"
ip_hits() { sql "SELECT COUNT(*) FROM rate_limit_hits WHERE key LIKE 'auth-ip:%'"; }
for i in $(seq 1 12); do step1 "ip-ok$i" user@foodbattle.local; password "ip-ok$i" user@foodbattle.local foodbattle-user; done
check "12 connexions réussies d'affilée (24 POST) => la dernière passe" [ "$CODE" = 302 -a "$LOC" = / ]
check "… et aucune tentative comptée pour l'IP" [ "$(ip_hits)" = 0 ]
step1 ip-p user@foodbattle.local; password ip-p user@foodbattle.local faux
check "mot de passe faux => étapes 1 et 2 comptées (2)" [ "$(ip_hits)" = 2 ]
sql "DELETE FROM rate_limit_hits" >/dev/null
step1 ip-q "autre-$RUN@test.fr"                       # étape 1 sur un autre email (énumération)…
password ip-q user@foodbattle.local foodbattle-user   # … puis connexion directe à son propre compte
check "étape 1 sur un autre email puis connexion à son compte => l'étape 1 reste comptée (1)" [ "$CODE" = 302 -a "$(ip_hits)" = 1 ]
sql "DELETE FROM rate_limit_hits" >/dev/null
step1 ip-r "ipfree-$RUN@test.fr"; register ip-r "ipfree-$RUN@test.fr" "Ipfree$RUN" motdepasse
check "inscription réussie => aucune tentative comptée pour l'IP" [ "$CODE" = 302 -a "$(ip_hits)" = 0 ]

section "Nettoyage des tentatives de plus de 24 h"
sql "INSERT INTO rate_limit_hits (key, hit_at) VALUES ('vieux', NOW() - INTERVAL '2 days')" >/dev/null
step1 g user@foodbattle.local
check "une nouvelle tentative purge les lignes de plus de 24 h" [ "$(sql "SELECT COUNT(*) FROM rate_limit_hits WHERE key='vieux'")" = 0 ]

section "Redirection vers la page demandée"
req i GET '/moderation?page=2'
check "visiteur GET /moderation?page=2 => 302 /connexion" [ "$CODE" = 302 -a "$LOC" = /connexion ]
step1 i admin@foodbattle.local; password i admin@foodbattle.local foodbattle-admin
check "après connexion admin => renvoyé vers /moderation?page=2" [ "$CODE" = 302 -a "$LOC" = '/moderation?page=2' ]
req i GET /; t=$(token); req i POST /deconnexion --data-urlencode "token=$t"
step1 i admin@foodbattle.local; password i admin@foodbattle.local foodbattle-admin
check "page mémorisée utilisée une seule fois (2e connexion => /dashboard)" [ "$LOC" = /dashboard ]
req j POST /dashboard/plats --data "name=x"
check "visiteur POST /dashboard/plats => 302 /connexion" [ "$CODE" = 302 -a "$LOC" = /connexion ]
step1 j admin@foodbattle.local; password j admin@foodbattle.local foodbattle-admin
check "un POST n'est pas mémorisé (connexion => /dashboard)" [ "$LOC" = /dashboard ]
req k GET /dashboard
step1 k "inscrit-$RUN@test.fr"; register k "inscrit-$RUN@test.fr" "Inscrit$RUN" motdepasse
check "inscription => renvoyé vers la page demandée (/dashboard)" [ "$CODE" = 302 -a "$LOC" = /dashboard ]
req k GET /dashboard
check "… où le nouveau user reçoit la 403 normale" [ "$CODE" = 403 ]

section "Chemins acceptés par rememberIntendedUrl (redirection ouverte)"
out=$(docker compose exec -T app php < tests/php/intended-url.php)
echo "$out" | sed 's/^/   /'
check "chemin local accepté" grep -q '^/moderation?page=2 .*=> accepté (/moderation?page=2)' <<<"$out"
check "//pirate.com refusé" grep -q '^//pirate.com .*=> refusé' <<<"$out"
check "/\pirate.com refusé" grep -qF '/\pirate.com           => refusé' <<<"$out"
check "https://pirate.com refusé" grep -q '^https://pirate.com .*=> refusé' <<<"$out"
check "javascript: refusé" grep -q '^javascript:alert(1) .*=> refusé' <<<"$out"
check "chaîne vide refusée" grep -q '^(vide) .*=> refusé' <<<"$out"
check "/<tabulation>/pirate.com refusé (le navigateur lirait //pirate.com)" grep -qF '/\t/pirate.com         => refusé' <<<"$out"
check "/<retour à la ligne>/pirate.com refusé" grep -qF '/\n/pirate.com         => refusé' <<<"$out"
req th GET /classement; t=$(token)
req th POST /theme --data-urlencode "token=$t" --data "back=%2F%09%2Fpirate.com"
check "bouton thème avec back=/<tabulation>/pirate.com => renvoyé vers /" [ "$LOC" = / ]

section "RequireGuest"
step1 l user@foodbattle.local; password l user@foodbattle.local foodbattle-user
req l GET /connexion
check "user connecté GET /connexion => 302 vers /" [ "$CODE" = 302 -a "$LOC" = / ]
step1 m admin@foodbattle.local; password m admin@foodbattle.local foodbattle-admin
req m GET /connexion
check "admin connecté GET /connexion => 302 vers /dashboard" [ "$CODE" = 302 -a "$LOC" = /dashboard ]
sql "DELETE FROM rate_limit_hits" >/dev/null
req l POST /connexion --data "email=x@test.fr"
check "user connecté POST /connexion => 302 vers /" [ "$CODE" = 302 -a "$LOC" = / ]
check "… sans être compté dans la limite par IP" [ "$(sql "SELECT COUNT(*) FROM rate_limit_hits")" = 0 ]
req l GET /; t=$(token); req l POST /deconnexion --data-urlencode "token=$t"
check "la déconnexion reste accessible" [ "$CODE" = 302 -a "$LOC" = / ]
req l GET /connexion
check "après déconnexion, /connexion s'affiche à nouveau" [ "$CODE" = 200 ]

finish
