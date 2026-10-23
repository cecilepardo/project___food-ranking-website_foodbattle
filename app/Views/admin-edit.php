<?php
/**
 * Formulaire de modification d'un plat.
 *
 * @var array       $dish
 * @var string|null $error
 * @var bool        $saved
 */
?>
<p><a href="/dashboard">← Dashboard</a></p>

<h1>Modifier un plat</h1>

<?php if ($saved): ?>
    <p class="message">Modifications enregistrées.</p>
<?php endif; ?>

<?php if ($error): ?>
    <p role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form class="form-panel" method="post" action="/dashboard/plats/<?= (int) $dish['id'] ?>/modifier">
    <?= csrf_field() ?>

    <label>
        Nom
        <input name="name" value="<?= e($dish['name']) ?>" maxlength="120" required>
    </label>

    <label>
        Prix
        <input name="price" type="number" step="0.5" data-arrow-step="0.5" min="0" value="<?= e($dish['price']) ?>" required>
    </label>

    <button class="btn">Enregistrer</button>
</form>
