<?php
/**
 * Squelette commun à toutes les pages.
 *
 * @var string $content  HTML de la page, produit par la vue
 * @var string $title    Titre de l'onglet
 */
use App\Core\Theme;
?>
<!doctype html>
<html lang="fr" data-theme="<?= e(Theme::current()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'FoodBattle') ?> · FoodBattle</title>

    <!-- Polices visibles dès le premier affichage : téléchargées en priorité -->
    <link rel="preload" href="/assets/fonts/nunito-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/bungee-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>

    <!-- Du plus général au plus particulier : l'ordre compte -->
    <link rel="stylesheet" href="<?= e(asset('/assets/css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/theme-dark.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/theme-light.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/pages.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/random.css')) ?>">
</head>
<body>
    <?php /* Premier arrêt du clavier : permet de sauter l'en-tête et d'aller directement au contenu */ ?>
    <a class="skip-link" href="#contenu">Aller au contenu</a>

    <?= \App\Core\View::partial('header') ?>

    <main class="page" id="contenu">
        <?= $content ?>
    </main>

    <?= \App\Core\View::partial('footer') ?>

    <script src="<?= e(asset('/assets/js/app.js')) ?>"></script>
    <script src="<?= e(asset('/assets/js/random.js')) ?>"></script>
</body>
</html>
