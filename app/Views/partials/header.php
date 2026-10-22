<?php
/**
 * En-tête du site : les liens affichés dépendent de l'utilisateur connecté.
 * Masquer un lien n'est pas une protection : l'accès est vérifié par les middlewares.
 */
$user = \App\Core\Auth::user();
?>
<header class="site-header">
    <a class="site-header__logo" href="/">FoodBattle 🍔</a>

    <nav class="site-header__nav">
        <a href="/">Découvrir</a>
        <a href="/classement">Classement</a>

        <?php if (\App\Core\Auth::isAdmin()): ?>
            <a href="/dashboard">Dashboard</a>
        <?php endif; ?>

        <?php if ($user === null): ?>
            <a href="/connexion">Connexion</a>
            <a href="/inscription">Inscription</a>
        <?php else: ?>
            <span class="site-header__user"><?= e($user['nickname']) ?></span>
            <form class="site-header__logout" method="post" action="/deconnexion">
                <?= csrf_field() ?>
                <button class="site-header__logout-button">Déconnexion</button>
            </form>
        <?php endif; ?>
    </nav>
</header>
