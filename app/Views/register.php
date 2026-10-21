<?php
/**
 * Formulaire d'inscription (crée toujours un compte 'user').
 *
 * @var string      $email     Valeurs saisies, réaffichées après une erreur
 * @var string      $nickname
 * @var string|null $error
 */
?>
<h1>Inscription</h1>

<?php if ($error): ?>
    <p role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form class="form-panel" method="post" action="/inscription">
    <?= csrf_field() ?>

    <label>
        Email
        <input name="email" type="email" value="<?= e($email) ?>" maxlength="254" autocomplete="email" required autofocus>
    </label>

    <label>
        Pseudo
        <input name="nickname" value="<?= e($nickname) ?>" maxlength="60" autocomplete="nickname" required>
        <small>Affiché publiquement à la place de ton email.</small>
    </label>

    <label>
        Mot de passe
        <input name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
        <small>8 caractères minimum.</small>
    </label>

    <button class="btn">Créer mon compte</button>
</form>

<p>Déjà inscrit ? <a href="/connexion">Connecte-toi</a></p>
