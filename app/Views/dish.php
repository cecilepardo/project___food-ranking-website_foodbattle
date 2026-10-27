<?php
/**
 * Fiche d'un plat : informations, vote, formulaire d'avis et avis publiés.
 *
 * @var array      $dish
 * @var array      $reviews       Avis publiés
 * @var array|null $currentUser   Utilisateur connecté (id, nickname, role), null pour un visiteur
 * @var bool       $saved         true après un vote
 * @var bool       $alreadyVoted  true si l'utilisateur avait déjà voté
 * @var bool       $alreadyRated  true si l'utilisateur avait déjà donné son avis
 * @var bool       $ratingSent    true après un avis, en attente de modération
 */
use App\Core\View;

$dishId = (int) $dish['id'];
?>
<p><a href="/">← Tous les plats</a></p>

<section class="dish-detail">
    <?= View::partial('dish-photo', ['dish' => $dish]) ?>

    <div class="dish-detail__body">
        <span class="tag"><?= e($dish['category']) ?></span>
        <h1><?= e($dish['name']) ?></h1>
        <h2><?= e($dish['restaurant']) ?></h2>
        <p><?= e($dish['address']) ?>, <?= e($dish['city']) ?></p>
        <p><?= e($dish['description']) ?></p>
        <h3><?= euro($dish['price']) ?></h3>

        <?php if ($currentUser): ?>
            <form method="post" action="/plat/<?= $dishId ?>/vote">
                <?= csrf_field() ?>
                <button class="btn">♥ Voter pour ce plat</button>
            </form>
        <?php else: ?>
            <a class="btn" href="/plat/<?= $dishId ?>/participer">♥ Connecte-toi pour voter</a>
        <?php endif; ?>

        <small>Le vote désigne ton favori ; la note exprime ton appréciation sur 5.</small>
    </div>
</section>

<?php if ($saved): ?>
    <p class="message" role="status">Merci, ta contribution a été enregistrée.</p>
<?php endif; ?>

<?php if ($alreadyVoted): ?>
    <p class="message" role="status">Tu as déjà voté pour ce plat.</p>
<?php endif; ?>

<?php if ($alreadyRated): ?>
    <p class="message" role="status">Tu as déjà donné ton avis sur ce plat.</p>
<?php endif; ?>

<?php if ($ratingSent): ?>
    <p class="message" role="status">Merci ! Ton avis est en attente de modération, il sera publié après validation.</p>
<?php endif; ?>

<h2>Donner mon avis</h2>

<?php if ($currentUser): ?>
    <form class="form-panel" method="post" action="/plat/<?= $dishId ?>/avis">
        <?= csrf_field() ?>

        <p>Tu publies en tant que <b><?= e($currentUser['nickname']) ?></b>.</p>

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
<?php else: ?>
    <p><a href="/plat/<?= $dishId ?>/participer">Connecte-toi</a> pour donner ton avis.</p>
<?php endif; ?>

<h2>Les avis</h2>

<?php foreach ($reviews as $review): ?>
    <article class="review">
        <b><?= e($review['nickname'] ?? 'Compte désactivé') ?> · <?= (int) $review['score'] ?>/5</b>
        <p><?= e($review['comment']) ?></p>
    </article>
<?php endforeach; ?>

<?php if (empty($reviews)): ?>
    <p>Pas encore d’avis publié.</p>
<?php endif; ?>
