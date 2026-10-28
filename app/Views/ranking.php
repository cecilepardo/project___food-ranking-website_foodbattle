<?php
/**
 * Classement des plats.
 *
 * @var array $dishes  Plats classés
 */
?>
<h1>Le top street food</h1>
<p>Les 10 plats les mieux notés. À note égale, le plus voté passe devant. À toi de faire bouger le classement !</p>

<table class="table table--cards">
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
                <td data-label="Rang">#<?= $index + 1 ?></td>
                <td data-label="Plat"><a href="/plat/<?= (int) $dish['id'] ?>"><?= e($dish['name']) ?></a></td>
                <td data-label="Établissement"><?= e($dish['restaurant']) ?></td>
                <td data-label="Votes"><?= (int) $dish['votes'] ?></td>
                <td data-label="Note /5"><?= $dish['average'] ? score($dish['average']) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
