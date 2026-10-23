<?php
/**
 * Historique des avis.
 *
 * @var array $reviews
 */
?>
<h1>Modération des avis</h1>
<p>Historique des contributions reçues.</p>

<table class="table">
    <thead>
        <tr>
            <th>Plat</th>
            <th>Pseudo</th>
            <th>Avis</th>
            <th>Statut</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($reviews as $review): ?>
            <tr>
                <td><?= e($review['dish']) ?></td>
                <td><?= e($review['nickname'] ?? 'Compte désactivé') ?></td>
                <td><?= e($review['comment']) ?></td>
                <td><?= e($review['status']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p><a href="/dashboard">Retour au dashboard</a></p>
