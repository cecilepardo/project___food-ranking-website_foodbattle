/*
 * « Je ne sais pas quoi manger ! » : le serveur tire un plat (/hasard.json), la roue s'arrête
 * sur une part de sa catégorie, puis la fenêtre affiche le plat.
 * Sans JavaScript ou sans <dialog>, le bouton reste un lien vers /hasard.
 */

var SPIN_TURNS = 5;         // tours complets avant de s'arrêter
var SPIN_DURATION = 4500;   // ms, un peu plus que la transition CSS (secours si transitionend ne vient pas)
var FETCH_TIMEOUT = 5000;   // ms

function setupRandomDraw() {
    var opener = document.querySelector('[data-random-draw]');
    var dialog = document.querySelector('dialog.random-draw');

    if (!opener || !dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    var disc = dialog.querySelector('.wheel__disc');
    var status = dialog.querySelector('.random-draw__status');
    var retry = dialog.querySelector('[data-random-retry]');
    var filter = dialog.querySelector('.random-draw__filter');
    var fallback = dialog.querySelector('.random-draw__fallback');
    var categories = Array.prototype.map.call(dialog.querySelectorAll('.wheel__icon'), function (icon) {
        return icon.dataset.category;
    });
    var segmentAngle = 360 / categories.length;
    var rotation = 0;
    var isDrawing = false;
    var isRedrawWanted = false;   // catégorie changée pendant une rotation : on relance à la fin

    // Catégorie choisie dans les pastilles ('' = tous les plats), au format attendu par le serveur
    function categoryQuery() {
        var checked = filter.querySelector('input:checked');

        return checked && checked.value !== '' ? '?categorie=' + encodeURIComponent(checked.value) : '';
    }

    function setState(state, message) {
        dialog.dataset.state = state;
        status.textContent = message;
    }

    // Tourne jusqu'à une part de la catégorie, choisie au hasard parmi les deux, avec un léger décalage
    function spinTo(category) {
        var indexes = categories.reduce(function (found, item, index) {
            return item === category ? found.concat(index) : found;
        }, []);
        var index = indexes[Math.floor(Math.random() * indexes.length)];
        var offset = (Math.random() - 0.5) * segmentAngle * 0.6;
        var target = 360 - index * segmentAngle + offset;   // amène la part sous le pointeur, en haut

        rotation += SPIN_TURNS * 360 + ((target - rotation) % 360 + 360) % 360;
        disc.style.setProperty('--wheel-rotation', rotation + 'deg');

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return Promise.resolve();
        }
        return new Promise(function (resolve) {
            disc.addEventListener('transitionend', resolve, { once: true });
            setTimeout(resolve, SPIN_DURATION);
        });
    }

    function showWinner(dish) {
        dialog.querySelector('.random-draw__photo').src = dish.photo;
        dialog.querySelector('[data-field="category"]').textContent = dish.category;
        dialog.querySelector('[data-field="name"]').textContent = dish.name;
        dialog.querySelector('[data-field="place"]').textContent = dish.restaurant + ' · ' + dish.city;
        dialog.querySelector('[data-field="price"]').textContent = dish.price;
        dialog.querySelector('[data-field="rating"]').textContent = dish.rating;
        dialog.querySelector('[data-field="url"]').href = dish.url;
        setState('result', 'Le destin a choisi : ' + dish.name);
    }

    function draw() {
        var controller = new AbortController();
        var query = categoryQuery();
        setTimeout(function () { controller.abort(); }, FETCH_TIMEOUT);

        isDrawing = true;
        retry.disabled = true;
        fallback.href = '/hasard' + query;   // le tirage de secours garde la catégorie choisie
        setState('spinning', 'Le destin hésite…');

        fetch('/hasard.json' + query, { signal: controller.signal, headers: { Accept: 'application/json' } })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (response.status === 404) {
                        return setState('empty', data.error);
                    }
                    if (!response.ok || !data.winner) {
                        throw new Error('Réponse inattendue');
                    }
                    return spinTo(data.winner.categoryKey).then(function () {
                        // Une autre catégorie a été choisie entre-temps : ce résultat n'est plus celui attendu
                        if (!isRedrawWanted) {
                            showWinner(data.winner);
                        }
                    });
                });
            })
            .catch(function () {
                setState('error', 'Le tirage n\'a pas pu se faire.');
            })
            .finally(function () {
                isDrawing = false;
                retry.disabled = false;

                if (isRedrawWanted) {
                    isRedrawWanted = false;
                    draw();
                }
            });
    }

    opener.addEventListener('click', function (event) {
        event.preventDefault();
        dialog.showModal();
        draw();
    });
    retry.addEventListener('click', draw);
    // Le filtre reste actif pendant la rotation (le focus clavier n'est jamais perdu) :
    // un changement de catégorie relance le tirage dès que la roue s'arrête.
    filter.addEventListener('change', function () {
        if (isDrawing) {
            isRedrawWanted = true;
            return;
        }
        draw();
    });
}

setupRandomDraw();
