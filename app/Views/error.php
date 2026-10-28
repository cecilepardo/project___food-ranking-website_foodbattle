<?php
/**
 * Page d'erreur commune : 403, 404, 429, 500…
 *
 * @var int         $code     Code HTTP affiché en grand
 * @var string      $title    Titre de la page, ex. « Page introuvable »
 * @var string      $message  Explication pour le visiteur
 * @var string|null $details  Détail technique de l'erreur, seulement en dev (APP_ENV=dev)
 */
?>
<section class="error-page">
    <p class="error-page__code" aria-hidden="true"><?= (int) $code ?></p>
    <h1><?= e($title) ?></h1>
    <p class="error-page__message"><?= e($message) ?></p>
    <a class="btn" href="/">Retour à la carte</a>

    <?php if (!empty($details)): ?>
        <pre class="error-page__details"><?= e($details) ?></pre>
    <?php endif; ?>
</section>
