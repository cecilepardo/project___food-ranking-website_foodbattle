<?php
/**
 * Classement des plats.
 *
 * @var array $dishes  Plats classés
 */
?>
<h1>Le top street food</h1>
<p>Les 10 plats les mieux notés. À note égale, le plus voté passe devant. À toi de faire bouger le classement !</p>

<table class="table">
    <thead>
        <tr>
            <th>Rang</th>
            <th>Plat</th>
            <th>Établissement</th>
            <th>Votes</th>
            <th>Note /5</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($dishes as $index => $dish): ?>
            <tr>
                <td>#<?= $index + 1 ?></td>
                <td><a href="/plat/<?= (int) $dish['id'] ?>"><?= e($dish['name']) ?></a></td>
                <td><?= e($dish['restaurant']) ?></td>
                <td><?= (int) $dish['votes'] ?></td>
                <td><?= $dish['average'] ? score($dish['average']) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
