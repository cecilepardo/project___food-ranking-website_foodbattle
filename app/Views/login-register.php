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
    <p class="message message--error" role="alert"><?= e($error) ?></p>
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

    <label for="password">Mot de passe</label>
    <div class="password-field">
        <input id="password" class="password-field__input" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="password-hint" required>

        <?php /* Caché sans JavaScript : app.js l'affiche et le fait fonctionner. */ ?>
        <button class="password-field__toggle" type="button" aria-controls="password" aria-pressed="false" data-password-toggle hidden>
            <span class="icon icon--eye" aria-hidden="true"></span>
            <span class="icon icon--eye-closed" aria-hidden="true"></span>
            <span class="visually-hidden">Afficher le mot de passe</span>
        </button>
    </div>
    <small id="password-hint">8 caractères minimum.</small>

    <button class="btn">Créer mon compte</button>
</form>
