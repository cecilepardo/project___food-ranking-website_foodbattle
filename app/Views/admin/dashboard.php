<?php
/**
 * Dashboard : plats, ajout d'un plat, établissements.
 *
 * @var array       $dishes
 * @var array       $establishments
 * @var array       $categories
 * @var string|null $error  Message si le formulaire d'ajout est invalide
 * @var bool        $added  true après l'ajout d'un plat
 */
?>
<h1>Dashboard</h1>
<p>Espace de gestion du prototype local FoodBattle.</p>

<nav class="subnav">
    <a href="#plats">Plats</a> ·
    <a href="#etablissements">Établissements</a> ·
    <a href="/moderation">Modération</a>
</nav>

<?php if ($error): ?>
    <p role="alert"><?= e($error) ?></p>
<?php endif; ?>

<?php if ($added): ?>
    <p class="message">Le plat a été ajouté.</p>
<?php endif; ?>


<h2 id="plats">Les plats (<?= count($dishes) ?>)</h2>

<table class="table">
    <thead>
        <tr>
            <th>Plat</th>
            <th>Établissement</th>
            <th>Prix</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($dishes as $dish): ?>
            <tr>
                <td><?= e($dish['name']) ?></td>
                <td><?= e($dish['restaurant']) ?></td>
                <td><?= euro($dish['price']) ?></td>
                <td>
                    <a href="/dashboard/plats/<?= (int) $dish['id'] ?>/modifier">Modifier</a>
                    <button type="button" class="btn btn--muted" data-delete="<?= (int) $dish['id'] ?>">Supprimer</button>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>


<details class="panel">
    <summary>Ajouter un plat</summary>

    <form class="form-panel" method="post" action="/dashboard/plats">
        <?= csrf_field() ?>

        <label>
            Nom
            <input name="name" maxlength="120" required>
        </label>

        <label>
            Établissement
            <select name="establishment_id">
                <?php foreach ($establishments as $establishment): ?>
                    <option value="<?= (int) $establishment['id'] ?>"><?= e($establishment['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Catégorie
            <select name="category">
                <option value="burger">Burger</option>
                <option value="tacos">Tacos</option>
                <option value="pizza">Pizza</option>
            </select>
        </label>

        <label>
            Prix
            <input type="number" name="price" min="0" step="0.01" required>
        </label>

        <label>
            Description
            <textarea name="description"></textarea>
        </label>

        <button class="btn">Ajouter</button>
    </form>
</details>


<h2 id="etablissements">Les établissements</h2>

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
