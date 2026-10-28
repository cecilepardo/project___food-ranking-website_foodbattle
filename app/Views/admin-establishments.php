<?php
/**
 * Administration, page Établissements : liste des restaurants et ajout d'un restaurant.
 *
 * @var array       $establishments
 * @var string|null $error  Message si le formulaire d'ajout est invalide
 * @var bool        $added  true après l'ajout d'un établissement
 */

use App\Core\View;
?>
<?= View::partial('admin-nav') ?>

<h1>Établissements (<?= count($establishments) ?>)</h1>

<?php if ($error): ?>
    <p class="message message--error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<?php if ($added): ?>
    <p class="message">L'établissement a été ajouté.</p>
<?php endif; ?>

<table class="table table--cards">
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
                <td data-label="Nom"><?= e($establishment['name']) ?></td>
                <td data-label="Ville"><?= e($establishment['city']) ?></td>
                <td data-label="Adresse"><?= e($establishment['address']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>


<details class="panel">
    <summary class="panel__summary">
        Ajouter un établissement
        <span class="icon icon--chevron-down panel__chevron" aria-hidden="true"></span>
    </summary>

    <form class="form-panel" method="post" action="/dashboard/etablissements">
        <?= csrf_field() ?>

        <label>
            Nom
            <input name="name" maxlength="120" required>
        </label>

        <label>
            Ville
            <input name="city" maxlength="80" required>
        </label>

        <label>
            Adresse
            <input name="address" maxlength="255" required>
        </label>

        <button class="btn">Ajouter</button>
    </form>
</details>
