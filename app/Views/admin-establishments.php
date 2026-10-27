<?php
/**
 * Administration, page Établissements : liste des restaurants.
 *
 * @var array $establishments
 */

use App\Core\View;
?>
<?= View::partial('admin-nav') ?>

<h1>Établissements (<?= count($establishments) ?>)</h1>

<table class="table">
    <thead>
        <tr>
            <th>Nom</th>
            <th>Ville</th>
            <th>Adresse</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($establishments as $establishment): ?>
            <tr>
                <td><?= e($establishment['name']) ?></td>
                <td><?= e($establishment['city']) ?></td>
                <td><?= e($establishment['address']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
