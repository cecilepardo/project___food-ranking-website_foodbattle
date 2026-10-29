<?php
/**
 * Connexion, étape 2a : l'email a un compte, on demande le mot de passe.
 *
 * @var string      $email
 * @var string|null $error
 * @var bool        $invalid  true si le mot de passe saisi est faux (pas après un blocage 429)
 */
?>
<h1>Bon retour !</h1>

<p>
    Connexion avec <strong><?= e($email) ?></strong>
    · <a href="/connexion">Ce n'est pas mon email</a>
</p>

<?php if ($error): ?>
    <p class="message message--error" id="form-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form class="form-panel" method="post" action="/connexion/mot-de-passe">
    <?= csrf_field() ?>

    <?php /* Email de l'étape 1, renvoyé avec le mot de passe (revalidé par le controller). */ ?>
    <input name="email" type="email" value="<?= e($email) ?>" autocomplete="username" readonly hidden>

    <div class="form-field">
        <label for="password">Mot de passe</label>
        <div class="password-field">
            <input id="password" class="password-field__input" name="password" type="password" autocomplete="current-password" required autofocus<?= field_attrs($invalid) ?>>
            <?= \App\Core\View::partial('password-toggle') ?>
        </div>
    </div>

    <button class="btn">Se connecter</button>
</form>
