<?php
/**
 * Connexion, étape 1 : saisie de l'email.
 *
 * @var string      $email  Email saisi, réaffiché après une erreur
 * @var string|null $error
 */
?>
<h1>Connexion</h1>

<p>Saisis ton email : on te connecte si tu as déjà un compte, sinon on t'aide à en créer un.</p>

<?php if ($error): ?>
    <p role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form class="form-panel" method="post" action="/connexion">
    <?= csrf_field() ?>

    <label>
        Email
        <input name="email" type="email" value="<?= e($email) ?>" maxlength="254" autocomplete="email" required autofocus>
    </label>

    <button class="btn">Continuer</button>
</form>
