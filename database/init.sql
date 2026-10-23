-- pgcrypto fournit crypt() et gen_salt(), utilisés pour hacher les mots de passe des comptes de démo.
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Comptes du site. L'inscription publique crée toujours un 'user' ;
-- un 'admin' ne peut être créé que directement en base.
CREATE TABLE users (
    id            SERIAL PRIMARY KEY,
    -- Identifiant de connexion, stocké en minuscules (normalisé par le PHP, garanti par le CHECK).
    email         VARCHAR(254) NOT NULL UNIQUE CHECK (email = lower(email)),
    -- Nom public, affiché à la place de l'email pour anonymiser votes et avis.
    nickname      VARCHAR(60)  NOT NULL CHECK (length(trim(nickname)) > 0),
    -- Hash bcrypt : le mot de passe en clair n'est jamais stocké.
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(10)  NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Pseudo unique sans tenir compte de la casse ("Toto" et "toto" ne peuvent pas coexister),
-- tout en gardant à l'affichage la casse choisie par l'utilisateur.
CREATE UNIQUE INDEX users_nickname_unique ON users (lower(nickname));

CREATE TABLE establishments (id SERIAL PRIMARY KEY, name VARCHAR(120) NOT NULL, city VARCHAR(80) NOT NULL, address TEXT NOT NULL);
CREATE TABLE dishes (id SERIAL PRIMARY KEY, establishment_id INTEGER NOT NULL REFERENCES establishments(id), name VARCHAR(120) NOT NULL, category VARCHAR(20) NOT NULL CHECK (category IN ('burger','tacos','pizza')), description TEXT NOT NULL DEFAULT '', price NUMERIC(8,2) NOT NULL CHECK (price >= 0));

-- Avis sur un plat. Un seul avis par compte et par plat.
CREATE TABLE ratings (
    id         SERIAL PRIMARY KEY,
    dish_id    INTEGER     NOT NULL REFERENCES dishes(id) ON DELETE CASCADE,
    -- Auteur. NULL = compte supprimé : l'avis reste, affiché « Compte désactivé ».
    user_id    INTEGER     REFERENCES users(id) ON DELETE SET NULL,
    score      INTEGER     NOT NULL CHECK (score BETWEEN 1 AND 5),
    comment    TEXT        NOT NULL,
    status     VARCHAR(20) NOT NULL DEFAULT 'published' CHECK (status IN ('pending', 'published', 'rejected')),
    created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (dish_id, user_id)
);

-- Votes pour un plat. Un seul vote par compte et par plat.
CREATE TABLE votes (
    id         SERIAL PRIMARY KEY,
    dish_id    INTEGER   NOT NULL REFERENCES dishes(id) ON DELETE CASCADE,
    -- Votant. NULL = compte supprimé : le vote continue de compter.
    user_id    INTEGER   REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (dish_id, user_id)
);

-- Tentatives horodatées, pour limiter le brute-force (voir App\Core\RateLimiter).
-- "key" désigne ce qu'on compte : 'auth-ip:<adresse IP>' ou 'login:<email>'.
CREATE TABLE rate_limit_hits (
    id     SERIAL PRIMARY KEY,
    key    VARCHAR(300) NOT NULL,
    hit_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Chaque requête filtre sur une clé et une période : cet index les rend rapides.
CREATE INDEX rate_limit_hits_key_hit_at ON rate_limit_hits (key, hit_at);

INSERT INTO establishments(name,city,address) VALUES
('Le Comptoir Smash','Lille','12 rue des Ateliers'),('Taco Tempo','Lille','8 place du Marché'),('Forno Club','Roubaix','24 rue des Tisserands'),('La Planque du Burger','Roubaix','5 rue du Canal'),('Tacos & Co','Tourcoing','17 avenue des Érables'),('Pizza Minuit','Lille','3 rue des Halles');
INSERT INTO dishes(establishment_id,name,category,description,price) VALUES(1,'Le Double Smash','burger','Deux steaks, cheddar, pickles et sauce maison.',12.5);
INSERT INTO dishes(establishment_id,name,category,description,price) VALUES(1,'Le Jardin Fumé','burger','Galette de pois chiches, oignons et sauce fumée.',11);
INSERT INTO dishes(establishment_id,name,category,description,price) VALUES(2,'Le Poulet Crunch','tacos','Poulet croustillant, crudités et sauce citron.',9.5);
INSERT INTO dishes(establishment_id,name,category,description,price) VALUES(2,'Le Veggie Tempo','tacos','Haricots rouges, maïs et légumes grillés.',8.9);
INSERT INTO dishes(establishment_id,name,category,description,price) VALUES(3,'La Margherita Club','pizza','Tomate, mozzarella et basilic.',10);
INSERT INTO dishes(establishment_id,name,category,description,price) VALUES(3,'La Champi Party','pizza','Champignons, mozzarella et persillade.',12);
INSERT INTO dishes(establishment_id,name,category,description,price) VALUES(4,'Le Nordiste','burger','Steak, fromage du Nord et oignons confits.',13);
INSERT INTO dishes(establishment_id,name,category,description,price) VALUES(4,'Le Classique','burger','Steak, salade, tomate et cheddar.',10.5);
INSERT INTO dishes(establishment_id,name,category,description,price) VALUES(5,'Le Trois Sauces','tacos','Poulet, pommes de terre et trio de sauces.',11);
INSERT INTO dishes(establishment_id,name,category,description,price) VALUES(5,'Le Piment Doux','tacos','Légumes, bœuf et sauce légèrement épicée.',10);
INSERT INTO dishes(establishment_id,name,category,description,price) VALUES(6,'La Minuit','pizza','Tomate, poivrons, olives et mozzarella.',13);
INSERT INTO dishes(establishment_id,name,category,description,price) VALUES(6,'La Quatre Fromages','pizza','Quatre fromages et une pâte fine.',14);

-- Comptes de démonstration, un par rôle (local uniquement : à changer avant toute mise en ligne).
-- admin@foodbattle.local / foodbattle-admin
-- user@foodbattle.local  / foodbattle-user
INSERT INTO users (email, nickname, password_hash, role) VALUES
('admin@foodbattle.local', 'Admin',   crypt('foodbattle-admin', gen_salt('bf', 12)), 'admin'),
('user@foodbattle.local',  'Testeur', crypt('foodbattle-user',  gen_salt('bf', 12)), 'user');

-- 38 gourmands de démo, auteurs des avis et des votes ci-dessous (mot de passe : foodbattle-demo).
-- Un seul hash pour tous : hacher 38 fois en bcrypt coût 12 ralentirait l'initialisation d'environ 10 s.
INSERT INTO users (email, nickname, password_hash)
SELECT 'gourmand' || n || '@foodbattle.local', 'Gourmand' || n, demo.hash
FROM generate_series(1, 38) AS n,
     (SELECT crypt('foodbattle-demo', gen_salt('bf', 12)) AS hash) AS demo;

-- Avis de démonstration, chacun rattaché au compte du gourmand qui l'a écrit.
INSERT INTO ratings (dish_id, user_id, score, comment, status)
SELECT r.dish_id, u.id, r.score, r.comment, r.status
FROM (VALUES
    (1, 'Gourmand3', 3, 'Belle découverte dans le quartier.', 'published'),
    (1, 'Gourmand4', 4, 'La sauce fait la différence.', 'published'),
    (1, 'Gourmand5', 5, 'Bon rapport qualité-prix.', 'published'),
    (2, 'Gourmand6', 4, 'La sauce fait la différence.', 'published'),
    (2, 'Gourmand7', 5, 'Bon rapport qualité-prix.', 'published'),
    (2, 'Gourmand8', 2, 'Une option sympa pour le midi.', 'published'),
    (3, 'Gourmand9', 5, 'Bon rapport qualité-prix.', 'published'),
    (3, 'Gourmand10', 2, 'Une option sympa pour le midi.', 'published'),
    (3, 'Gourmand11', 3, 'Très bon, je reviendrai.', 'pending'),
    (4, 'Gourmand12', 2, 'Une option sympa pour le midi.', 'published'),
    (4, 'Gourmand13', 3, 'Très bon, je reviendrai.', 'pending'),
    (4, 'Gourmand14', 4, 'Belle découverte dans le quartier.', 'published'),
    (5, 'Gourmand15', 3, 'Très bon, je reviendrai.', 'pending'),
    (5, 'Gourmand16', 4, 'Belle découverte dans le quartier.', 'published'),
    (5, 'Gourmand17', 5, 'La sauce fait la différence.', 'published'),
    (6, 'Gourmand18', 4, 'Belle découverte dans le quartier.', 'published'),
    (6, 'Gourmand19', 5, 'La sauce fait la différence.', 'published'),
    (6, 'Gourmand20', 2, 'Bon rapport qualité-prix.', 'published'),
    (7, 'Gourmand21', 5, 'La sauce fait la différence.', 'published'),
    (7, 'Gourmand22', 2, 'Bon rapport qualité-prix.', 'published'),
    (7, 'Gourmand23', 3, 'Une option sympa pour le midi.', 'published'),
    (8, 'Gourmand24', 2, 'Bon rapport qualité-prix.', 'published'),
    (8, 'Gourmand25', 3, 'Une option sympa pour le midi.', 'published'),
    (8, 'Gourmand26', 4, 'Très bon, je reviendrai.', 'pending'),
    (9, 'Gourmand27', 3, 'Une option sympa pour le midi.', 'published'),
    (9, 'Gourmand28', 4, 'Très bon, je reviendrai.', 'pending'),
    (9, 'Gourmand29', 5, 'Belle découverte dans le quartier.', 'published'),
    (10, 'Gourmand30', 4, 'Très bon, je reviendrai.', 'pending'),
    (10, 'Gourmand31', 5, 'Belle découverte dans le quartier.', 'published'),
    (10, 'Gourmand32', 2, 'La sauce fait la différence.', 'published'),
    (11, 'Gourmand33', 5, 'Belle découverte dans le quartier.', 'published'),
    (11, 'Gourmand34', 2, 'La sauce fait la différence.', 'published'),
    (11, 'Gourmand35', 3, 'Bon rapport qualité-prix.', 'published'),
    (12, 'Gourmand36', 2, 'La sauce fait la différence.', 'published'),
    (12, 'Gourmand37', 3, 'Bon rapport qualité-prix.', 'published'),
    (12, 'Gourmand38', 4, 'Une option sympa pour le midi.', 'published')
) AS r(dish_id, nickname, score, comment, status)
JOIN users u ON u.nickname = r.nickname;

-- Votes de démonstration : pour chaque plat, les N premiers gourmands votent pour lui.
INSERT INTO votes (dish_id, user_id)
SELECT v.dish_id, u.id
FROM (VALUES (1, 9), (2, 16), (3, 4), (4, 11), (5, 18), (6, 6),
             (7, 13), (8, 20), (9, 8), (10, 15), (11, 3), (12, 10)) AS v(dish_id, total)
CROSS JOIN LATERAL generate_series(1, v.total) AS n
JOIN users u ON u.nickname = 'Gourmand' || n;
