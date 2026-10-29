<?php
/**
 * Connexion, étape 2b : l'email n'a pas de compte, on propose d'en créer un (rôle 'user').
 *
 * @var string      $email
 * @var string      $nickname  Pseudo saisi, réaffiché après une erreur
 * @var string|null $error
 * @var string|null $errorField  Champ concerné par l'erreur (nickname, password), null sinon
 */
?>
<h1>Créer un compte</h1>

<p>
    Aucun compte pour <strong><?= e($email) ?></strong>
    · <a href="/connexion">Ce n'est pas mon email</a>
</p>

<?php if ($error): ?>
    <p class="message message--error" id="form-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form class="form-panel" method="post" action="/connexion/inscription">
    <?= csrf_field() ?>

    <?php /* Email de l'étape 1, renvoyé avec l'inscription (revalidé par le controller). */ ?>
    <input name="email" type="email" value="<?= e($email) ?>" autocomplete="username" readonly hidden>

    <div class="form-field">
        <label for="nickname">Pseudo</label>
        <input id="nickname" name="nickname" value="<?= e($nickname) ?>" minlength="3" maxlength="30" autocomplete="nickname" required autofocus<?= field_attrs($errorField === 'nickname', 'nickname-hint') ?>>
        <small id="nickname-hint">Affiché publiquement à la place de ton email. 3 à 30 caractères : lettres, chiffres, espace, « . », « - », « _ ».</small>
    </div>

    <div class="form-field">
        <label for="password">Mot de passe</label>
        <div class="password-field">
            <input id="password" class="password-field__input" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required<?= field_attrs($errorField === 'password', 'password-hint') ?>>
            <?= \App\Core\View::partial('password-toggle') ?>
        </div>
        <small id="password-hint">8 caractères minimum.</small>
    </div>

    <button class="btn">Créer mon compte</button>
</form>
