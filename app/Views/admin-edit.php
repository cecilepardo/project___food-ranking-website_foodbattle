<?php
/**
 * Formulaire de modification d'un plat.
 *
 * @var array       $dish
 * @var string|null $error
 */

use App\Core\View;
?>
<?= View::partial('admin-nav') ?>

<h1>Modifier <?= e($dish['name']) ?></h1>

<?php if ($error): ?>
    <p class="message message--error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form class="form-panel" method="post" action="/dashboard/plats/<?= (int) $dish['id'] ?>/modifier">
    <?= csrf_field() ?>

    <label>
        Nom
        <input name="name" value="<?= e($dish['name']) ?>" maxlength="120" required>
    </label>

    <label>
        Prix
        <input name="price" type="number" step="0.5" data-arrow-step="0.5" min="0" max="999.99" value="<?= e($dish['price']) ?>" required>
    </label>

    <button class="btn">Enregistrer</button>
</form>
