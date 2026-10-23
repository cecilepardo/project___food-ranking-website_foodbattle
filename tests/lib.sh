# Fonctions communes aux tests HTTP de FoodBattle, à inclure avec `source tests/lib.sh`.
# Un test joue le rôle d'un navigateur avec curl : un "jar" (fichier de cookies) = un visiteur.

BASE=${BASE:-http://127.0.0.1:8081}
DIR=$(mktemp -d)
RUN=$(date +%s)   # suffixe unique : les scripts se relancent sans remettre la base à zéro
PASS=0; FAIL=0; NOTE=0

# check "description" commande… : ✅ si la commande réussit, ❌ sinon.
check() {
    local desc=$1; shift
    if "$@"; then echo "✅ $desc"; PASS=$((PASS + 1)); else echo "❌ $desc"; FAIL=$((FAIL + 1)); fi
}

# note "texte" : constat connu, ni réussite ni échec.
note() { echo "⚠️  $1"; NOTE=$((NOTE + 1)); }

# req JAR METHODE CHEMIN [options curl…] : remplit $CODE (statut), $LOC (redirection) et $BODY (page).
req() {
    local jar=$1 method=$2 path=$3; shift 3
    CODE=$(curl -s -o "$DIR/body" -D "$DIR/headers" -w '%{http_code}' \
        -b "$DIR/$jar" -c "$DIR/$jar" -X "$method" "$@" "$BASE$path")
    LOC=$(grep -i '^location:' "$DIR/headers" | tr -d '\r' | cut -d' ' -f2)
    BODY=$(cat "$DIR/body")
}

# Jeton CSRF de la dernière page reçue.
token() { grep -o 'name="token" value="[^"]*"' "$DIR/body" | head -1 | sed 's/.*value="//;s/"$//'; }

# Identifiant de session d'un visiteur.
sid() { grep PHPSESSID "$DIR/$1" | awk '{print $7}'; }

has()   { grep -q -- "$1" <<<"$BODY"; }
hasnt() { ! grep -q -- "$1" <<<"$BODY"; }

# Requête SQL dans le conteneur PostgreSQL (les erreurs sont gardées pour les tests de contraintes).
sql() { docker compose exec -T db psql -U foodbattle -tAc "$1" 2>&1; }

# Titre d'un groupe de tests. Remet les compteurs anti brute-force à zéro :
# en local, toutes les requêtes viennent de la même IP et finiraient par être bloquées.
section() { echo "== $1"; sql "DELETE FROM rate_limit_hits" >/dev/null; }

# Parcours de connexion en deux étapes.
step1() {     # step1 JAR EMAIL
    req "$1" GET /connexion; local t; t=$(token)
    req "$1" POST /connexion --data-urlencode "token=$t" --data-urlencode "email=$2"
}
password() {  # password JAR EMAIL MOT_DE_PASSE, depuis l'étape 2a affichée
    local t; t=$(token)
    req "$1" POST /connexion/mot-de-passe --data-urlencode "token=$t" \
        --data-urlencode "email=$2" --data-urlencode "password=$3"
}
register() {  # register JAR EMAIL PSEUDO MOT_DE_PASSE, depuis l'étape 2b affichée
    local t; t=$(token)
    req "$1" POST /connexion/inscription --data-urlencode "token=$t" \
        --data-urlencode "email=$2" --data-urlencode "nickname=$3" --data-urlencode "password=$4"
}
login() { step1 "$1" "$2"; password "$1" "$2" "$3"; }   # login JAR EMAIL MOT_DE_PASSE

# Fin de script : bilan, nettoyage, et code de sortie 1 s'il y a au moins un échec.
finish() {
    echo; echo "Résultat : $PASS OK, $FAIL échec(s), $NOTE constat(s)"
    sql "DELETE FROM rate_limit_hits" >/dev/null
    rm -rf "$DIR"
    [ "$FAIL" -eq 0 ]
}
