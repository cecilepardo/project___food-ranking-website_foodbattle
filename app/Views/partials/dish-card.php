<?php
/**
 * Carte d'un plat sur la page d'accueil.
 *
 * @var array $dish
 */
$dishUrl = '/plat/' . (int) $dish['id'];
$votes   = (int) $dish['votes'];

// "4,6/5" ou "—" si personne n'a encore noté le plat
$rating = $dish['average'] ? score($dish['average']) . '/5' : '—';
?>
<article class="card">
    <img
        class="card__image"
        src="<?= e(image_for($dish['category'])) ?>"
        alt="Photo d’illustration : <?= e($dish['category']) ?>"
        data-fallback="/assets/img/placeholder.svg"
    >

    <div class="card__body">
        <span class="tag"><?= e($dish['category']) ?></span>

        <h3 class="card__title">
            <a href="<?= $dishUrl ?>"><?= e($dish['name']) ?></a>
        </h3>

        <p class="card__meta"><?= e($dish['restaurant']) ?> · <?= e($dish['city']) ?></p>
        <p class="card__text"><?= e($dish['description']) ?></p>

        <p class="card__footer">
            <strong class="card__price"><?= euro($dish['price']) ?></strong>
            <span class="card__rating">
                <!-- L'étoile est décorative : un lecteur d'écran lirait "étoile noire" -->
                <span aria-hidden="true">★</span>
                <?= e($rating) ?> · <?= $votes ?> <?= $votes > 1 ? 'votes' : 'vote' ?>
            </span>
        </p>

        <?php /* Même page que le titre : un seul arrêt au clavier et pour les lecteurs d'écran (le titre) */ ?>
        <a class="btn btn--block card__action" href="<?= $dishUrl ?>" tabindex="-1" aria-hidden="true">Découvrir et voter</a>
    </div>
</article>
