<?php
/**
 * Modération des avis : les avis en attente sont listés en premier.
 *
 * @var array       $reviews
 * @var string|null $done     'published' ou 'rejected' après une action
 */

use App\Core\View;

$statusLabels = [
    'pending'   => 'En attente',
    'published' => 'Publié',
    'rejected'  => 'Refusé',
];
?>
<?= View::partial('admin-nav') ?>

<h1>Modération des avis</h1>
<p>Un avis n'apparaît sur la fiche du plat qu'une fois publié.</p>

<?php if ($done === 'published'): ?>
    <p class="message" role="status">L'avis a été publié.</p>
<?php elseif ($done === 'rejected'): ?>
    <p class="message" role="status">L'avis ne sera pas affiché sur le site.</p>
<?php endif; ?>

<table class="table">
    <thead>
        <tr>
            <th>Plat</th>
            <th>Pseudo</th>
            <th>Avis</th>
            <th>Statut</th>
            <th class="table__cell--center">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($reviews as $review): ?>
            <?php
            // Un avis déjà publié est « dépublié » ; un avis en attente est « refusé » (même action)
            $rejectLabel = $review['status'] === 'published' ? 'Dépublier' : 'Refuser';
            ?>
            <tr>
                <td><?= e($review['dish']) ?></td>
                <td><?= e($review['nickname'] ?? 'Compte désactivé') ?></td>
                <td><?= e($review['comment']) ?></td>
                <td><?= e($statusLabels[$review['status']] ?? $review['status']) ?></td>
                <td>
                    <div class="table__actions">
                        <?php if ($review['status'] !== 'published'): ?>
                            <form class="inline-form" method="post" action="/moderation/<?= (int) $review['id'] ?>/publier">
                                <?= csrf_field() ?>
                                <button class="btn btn--small">Publier</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($review['status'] !== 'rejected'): ?>
                            <form class="inline-form" method="post" action="/moderation/<?= (int) $review['id'] ?>/refuser">
                                <?= csrf_field() ?>
                                <button class="btn btn--small btn--secondary"><?= e($rejectLabel) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
