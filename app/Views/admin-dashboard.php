<?php
/**
 * Administration, page Plats : liste des plats et ajout d'un plat.
 *
 * @var array       $dishes
 * @var array       $establishments  Pour le menu déroulant du formulaire d'ajout
 * @var array       $categories
 * @var string      $sort        Tri du tableau ('' = ordre d'ajout, voir Dish::SORTS)
 * @var bool        $descending  true = ordre décroissant
 * @var string|null $error    Message si le formulaire d'ajout est invalide
 * @var bool        $added    true après l'ajout d'un plat
 * @var bool        $deleted  true après la suppression d'un plat
 */

use App\Core\View;
?>
<?= View::partial('admin-nav') ?>

<h1>Plats (<?= count($dishes) ?>)</h1>
<p>Espace de gestion du prototype local FoodBattle.</p>

<?php if ($error): ?>
    <p class="message message--error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<?php if ($added): ?>
    <p class="message">Le plat a été ajouté.</p>
<?php endif; ?>

<?php if ($deleted): ?>
    <p class="message">Le plat a été supprimé.</p>
<?php endif; ?>


<table class="table table--cards table--sortable">
    <thead>
        <tr>
            <th>Plat</th>
            <?= View::partial('sort-header', ['key' => 'lieu', 'label' => 'Ville', 'sort' => $sort, 'descending' => $descending]) ?>
            <th>Établissement</th>
            <?= View::partial('sort-header', ['key' => 'categorie', 'label' => 'Catégorie', 'sort' => $sort, 'descending' => $descending]) ?>
            <?= View::partial('sort-header', ['key' => 'prix', 'label' => 'Prix', 'sort' => $sort, 'descending' => $descending]) ?>
            <th class="table__cell--center">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($dishes as $dish): ?>
            <tr>
                <td data-label="Plat"><?= e($dish['name']) ?></td>
                <td data-label="Ville"><?= e($dish['city']) ?></td>
                <td data-label="Établissement"><?= e($dish['restaurant']) ?></td>
                <td data-label="Catégorie"><?= e($categories[$dish['category']] ?? $dish['category']) ?></td>
                <td data-label="Prix"><?= euro($dish['price']) ?></td>
                <td>
                    <div class="table__actions">
                        <a class="btn btn--small btn--secondary" href="/dashboard/plats/<?= (int) $dish['id'] ?>/modifier">Modifier</a>
                        <form class="inline-form" method="post" action="/dashboard/plats/<?= (int) $dish['id'] ?>/supprimer" data-delete>
                            <?= csrf_field() ?>
                            <button class="btn btn--small btn--danger">Supprimer</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>


<details class="panel">
    <summary class="panel__summary">
        Ajouter un plat
        <span class="icon icon--chevron-down panel__chevron" aria-hidden="true"></span>
    </summary>

    <form class="form-panel" method="post" action="/dashboard/plats">
        <?= csrf_field() ?>

        <label>
            Nom
            <input name="name" maxlength="120" required>
        </label>

        <label>
            Établissement
            <span class="select-field">
                <select class="select-field__select" name="establishment_id">
                    <?php foreach ($establishments as $establishment): ?>
                        <option value="<?= (int) $establishment['id'] ?>"><?= e($establishment['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="icon icon--chevron-down select-field__icon" aria-hidden="true"></span>
            </span>
        </label>

        <label>
            Catégorie
            <span class="select-field">
                <select class="select-field__select" name="category">
                    <option value="burger">Burger</option>
                    <option value="tacos">Tacos</option>
                    <option value="pizza">Pizza</option>
                </select>
                <span class="icon icon--chevron-down select-field__icon" aria-hidden="true"></span>
            </span>
        </label>

        <label>
            Prix
            <input type="number" name="price" min="0" max="999.99" step="0.5" data-arrow-step="0.5" required>
        </label>

        <label>
            Description
            <textarea name="description"></textarea>
        </label>

        <button class="btn">Ajouter</button>
    </form>
</details>
