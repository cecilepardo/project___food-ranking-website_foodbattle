<?php
/**
 * Connexion, étape 2b : l'email n'a pas de compte, on propose d'en créer un (rôle 'user').
 *
 * @var string      $email
 * @var string      $nickname  Pseudo saisi, réaffiché après une erreur
 * @var string|null $error
 */
?>
<h1>Créer un compte</h1>

<p>
    Aucun compte pour <strong><?= e($email) ?></strong>
    · <a href="/connexion">Ce n'est pas mon email</a>
</p>

<?php if ($error): ?>
    <p role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form class="form-panel" method="post" action="/connexion/inscription">
    <?= csrf_field() ?>

    <?php /* Email de l'étape 1, renvoyé avec l'inscription (revalidé par le controller). */ ?>
    <input name="email" type="email" value="<?= e($email) ?>" autocomplete="username" readonly hidden>

    <label>
        Pseudo
        <input name="nickname" value="<?= e($nickname) ?>" minlength="3" maxlength="30" autocomplete="nickname" required autofocus>
        <small>Affiché publiquement à la place de ton email. 3 à 30 caractères : lettres, chiffres, espace, « . », « - », « _ ».</small>
    </label>

    <label>
        Mot de passe
        <input name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
        <small>8 caractères minimum.</small>
    </label>

    <button class="btn">Créer mon compte</button>
</form>
