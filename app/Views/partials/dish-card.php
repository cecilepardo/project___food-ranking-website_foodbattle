<?php
/**
 * Carte d'un plat sur la page d'accueil.
 *
 * @var array $dish
 */
$dishUrl = '/plat/' . (int) $dish['id'];
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

        <p class="card__text"><?= e($dish['restaurant']) ?> · <?= e($dish['city']) ?></p>
        <p class="card__text"><?= e($dish['description']) ?></p>

        <p class="card__text">
            <strong><?= euro($dish['price']) ?></strong>
            <span class="card__rating">★ <?= $dish['average'] ? score($dish['average']) : '—' ?>/5</span>
        </p>

        <p class="card__text"><?= (int) $dish['votes'] ?> vote(s)</p>

        <a class="btn" href="<?= $dishUrl ?>">Découvrir et voter</a>
    </div>
</article>
