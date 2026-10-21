<?php
/**
 * Squelette commun à toutes les pages.
 *
 * @var string $content  HTML de la page, produit par la vue
 * @var string $title    Titre de l'onglet
 */
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'FoodBattle') ?> · FoodBattle</title>

    <link rel="stylesheet" href="/assets/css/variables.css">
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/layout.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <link rel="stylesheet" href="/assets/css/pages.css">
</head>
<body>

    <?= \App\Core\View::partial('header') ?>

    <main class="page">
        <?= $content ?>
    </main>

    <?= \App\Core\View::partial('footer') ?>

    <script src="/assets/js/app.js"></script>
</body>
</html>
