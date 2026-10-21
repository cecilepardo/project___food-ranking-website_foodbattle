<?php
/**
 * Fiche d'un plat : informations, vote, formulaire d'avis et avis publiés.
 *
 * @var array $dish
 * @var array $reviews  Avis publiés
 * @var bool  $saved    true après un vote ou un avis
 */
use App\Core\View;

$dishId = (int) $dish['id'];
?>
<p><a href="/">← Tous les plats</a></p>

<section class="dish-detail">
    <?= View::partial('dish-photo', ['dish' => $dish]) ?>

    <div>
        <span class="tag"><?= e($dish['category']) ?></span>
        <h1><?= e($dish['name']) ?></h1>
        <h2><?= e($dish['restaurant']) ?></h2>
        <p><?= e($dish['address']) ?>, <?= e($dish['city']) ?></p>
        <p><?= e($dish['description']) ?></p>
        <h3><?= euro($dish['price']) ?></h3>

        <form method="post" action="/plat/<?= $dishId ?>/vote">
            <?= csrf_field() ?>
            <button class="btn">♥ Voter pour ce plat</button>
        </form>

        <small>Le vote désigne ton favori ; la note exprime ton appréciation sur 5.</small>
    </div>
</section>

<?php if ($saved): ?>
    <p class="message" role="status">Merci, ta contribution a été enregistrée.</p>
<?php endif; ?>

<h2>Donner mon avis</h2>

<form class="form-panel" method="post" action="/plat/<?= $dishId ?>/avis">
    <?= csrf_field() ?>

    <label>
        Pseudo
        <input name="nickname" maxlength="60" required>
    </label>

    <label>
        Note
        <select name="score" required>
            <?php for ($score = 5; $score >= 1; $score--): ?>
                <option value="<?= $score ?>"><?= $score ?>/5</option>
            <?php endfor; ?>
        </select>
    </label>

    <label>
        Commentaire
        <textarea name="comment" maxlength="1000" required></textarea>
    </label>

    <button class="btn">Publier mon avis</button>
</form>

<h2>Les avis</h2>

<?php foreach ($reviews as $review): ?>
    <article class="review">
        <b><?= e($review['nickname']) ?> · <?= (int) $review['score'] ?>/5</b>
        <?php /* BUG connu (faille XSS) : le commentaire est affiché sans e(). */ ?>
        <p><?= $review['comment'] ?></p>
    </article>
<?php endforeach; ?>

<?php if (empty($reviews)): ?>
    <p>Pas encore d’avis publié.</p>
<?php endif; ?>
