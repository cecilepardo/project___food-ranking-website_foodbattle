<?php
/**
 * Plat inexistant.
 *
 * @var bool $backLink  Afficher le lien de retour (true par défaut)
 */
?>
<h1>Plat introuvable</h1>

<?php if ($backLink ?? true): ?>
    <a href="/">Retour à la carte</a>
<?php endif; ?>
