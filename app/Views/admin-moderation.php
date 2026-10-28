<?php
/**
 * Modération des avis : les avis en attente sont listés en premier, filtrables par statut.
 *
 * @var array       $reviews
 * @var array       $counts   Nombre d'avis par statut, ex. ['pending' => 6]
 * @var string|null $status   Statut filtré, ou null pour tous les avis
 * @var string|null $done     'published' ou 'rejected' après une action
 */

use App\Core\View;

$statusLabels = [
    'pending'   => 'En attente',
    'published' => 'Publié',
    'rejected'  => 'Refusé',
];

// Pilules de filtre : statut ('' = tous les avis) => libellé
$filters = [
    ''          => 'Tous',
    'pending'   => 'En attente',
    'published' => 'Publiés',
    'rejected'  => 'Refusés',
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

<nav class="admin-nav" aria-label="Filtrer les avis par statut">
    <?php foreach ($filters as $value => $label): ?>
        <?php
        $count = $value === '' ? array_sum($counts) : ($counts[$value] ?? 0);
        $url   = $value === '' ? '/moderation' : '/moderation?statut=' . $value;
        ?>
        <a class="admin-nav__link" href="<?= e($url) ?>"<?= ($status ?? '') === $value ? ' aria-current="true"' : '' ?>>
            <?= e($label) ?> (<?= (int) $count ?>)
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($reviews === []): ?>
    <p>Aucun avis avec ce statut.</p>
<?php else: ?>
<table class="table table--cards">
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
                <td data-label="Plat"><?= e($review['dish']) ?></td>
                <td data-label="Pseudo"><?= e($review['nickname'] ?? 'Compte désactivé') ?></td>
                <td data-label="Avis"><?= e($review['comment']) ?></td>
                <td data-label="Statut"><?= e($statusLabels[$review['status']] ?? $review['status']) ?></td>
                <td>
                    <div class="table__actions">
                        <?php if ($review['status'] !== 'published'): ?>
                            <form class="inline-form" method="post" action="/moderation/<?= (int) $review['id'] ?>/publier">
                                <?= csrf_field() ?>
                                <input type="hidden" name="statut" value="<?= e($status ?? '') ?>">
                                <button class="btn btn--small">Publier</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($review['status'] !== 'rejected'): ?>
                            <form class="inline-form" method="post" action="/moderation/<?= (int) $review['id'] ?>/refuser">
                                <?= csrf_field() ?>
                                <input type="hidden" name="statut" value="<?= e($status ?? '') ?>">
                                <button class="btn btn--small btn--secondary"><?= e($rejectLabel) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
