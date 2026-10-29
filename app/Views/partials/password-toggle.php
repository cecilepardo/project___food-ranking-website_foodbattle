<?php
/**
 * Bouton œil d'un champ mot de passe (id="password").
 * Caché sans JavaScript : app.js l'affiche et le fait fonctionner.
 */
?>
<button class="password-field__toggle" type="button" aria-controls="password" aria-pressed="false" data-password-toggle hidden>
    <span class="icon icon--eye" aria-hidden="true"></span>
    <span class="icon icon--eye-closed" aria-hidden="true"></span>
    <span class="visually-hidden">Afficher le mot de passe</span>
</button>
