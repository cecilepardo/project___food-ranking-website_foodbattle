<?php
/**
 * Grande photo d'un plat (fiche plat).
 *
 * @var array $dish
 */
?>
<img
    class="dish-detail__image"
    src="<?= e(image_for($dish)) ?>"
    alt="Photo d’illustration : <?= e($dish['category']) ?>"
    data-fallback="/assets/img/placeholder.svg"
>
