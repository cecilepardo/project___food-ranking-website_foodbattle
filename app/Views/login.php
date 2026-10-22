<?php
/**
 * Formulaire de connexion.
 *
 * @var string      $email  Email saisi, réaffiché après une erreur
 * @var string|null $error
 */
?>
<h1>Connexion</h1>

<?php if ($error): ?>
    <p role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form class="form-panel" method="post" action="/connexion">
    <?= csrf_field() ?>

    <label>
        Email
        <input name="email" type="email" value="<?= e($email) ?>" maxlength="254" autocomplete="email" required autofocus>
    </label>

    <label>
        Mot de passe
        <input name="password" type="password" autocomplete="current-password" required>
    </label>

    <button class="btn">Se connecter</button>
</form>

<p>Pas encore de compte ? <a href="/inscription">Inscris-toi</a></p>
