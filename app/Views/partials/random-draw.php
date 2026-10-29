<?php
/**
 * Fenêtre « Je ne sais pas quoi manger ! » : la roue tourne, puis affiche le plat tiré par le serveur.
 * Ouverte par le bouton [data-random-draw] du hero (voir random.js) ; sans JavaScript, ce bouton
 * est un simple lien vers /hasard.
 * data-state : spinning (roue) | result (plat gagnant) | empty (aucun plat) | error
 *
 * @var array $categories  ['burger' => 'Burgers', ...]
 */

// Parts de la roue, dans le sens des aiguilles d'une montre depuis le haut :
// deux parts voisines n'ont jamais la même catégorie.
$segments = [['burger', '🍔'], ['tacos', '🌮'], ['pizza', '🍕'], ['burger', '🍔'], ['tacos', '🌮'], ['pizza', '🍕']];
?>
<dialog class="random-draw" aria-labelledby="random-draw-title" data-state="spinning">
    <h2 class="random-draw__title" id="random-draw-title">Je ne sais pas quoi manger !</h2>

    <?php /* Choisir une catégorie relance un tirage parmi ses plats (random.js), même pendant une rotation */ ?>
    <fieldset class="random-draw__filter">
        <legend class="visually-hidden">Catégorie du tirage</legend>
        <label class="random-draw__chip">
            <input class="visually-hidden" type="radio" name="random-category" value="" checked>
            Tout
        </label>
        <?php foreach ($categories as $value => $label): ?>
            <label class="random-draw__chip">
                <input class="visually-hidden" type="radio" name="random-category" value="<?= e($value) ?>">
                <?= e($label) ?>
            </label>
        <?php endforeach; ?>
    </fieldset>

    <?php /* Décorative pour les lecteurs d'écran : le résultat est annoncé par le message (role="status") */ ?>
    <div class="wheel" aria-hidden="true">
        <span class="wheel__pointer"></span>
        <div class="wheel__disc">
            <?php foreach ($segments as [$category, $emoji]): ?>
                <span class="wheel__icon" data-category="<?= e($category) ?>"><?= $emoji ?></span>
            <?php endforeach; ?>
        </div>
        <span class="wheel__hub"></span>
    </div>

    <p class="random-draw__status" role="status"></p>

    <?php /* Plat gagnant, rempli par random.js (textContent : aucun HTML venu du serveur n'est injecté) */ ?>
    <article class="random-draw__result">
        <img class="random-draw__photo" src="/assets/img/placeholder.svg" alt="">
        <span class="tag" data-field="category"></span>
        <h3 class="random-draw__name" data-field="name"></h3>
        <p class="random-draw__meta" data-field="place"></p>
        <p class="random-draw__price" data-field="price"></p>
        <p><span aria-hidden="true">★</span> <span data-field="rating"></span></p>
        <a class="btn" href="/" data-field="url">Voir le plat</a>
    </article>

    <?php /* Secours en cas d'erreur : le tirage sans animation */ ?>
    <a class="random-draw__fallback" href="/hasard">Tirer un plat sans animation</a>

    <div class="random-draw__actions">
        <button class="btn btn--secondary" type="button" data-random-retry>Retenter ma chance</button>
        <form method="dialog"><button class="btn btn--secondary">Fermer</button></form>
    </div>
</dialog>
