<?php
/**
 * En-tête du site : les liens affichés dépendent de l'utilisateur connecté.
 * Masquer un lien n'est pas une protection : l'accès est vérifié par les middlewares.
 */
use App\Core\Auth;
use App\Core\Theme;

$user = Auth::user();

// Le bouton propose toujours l'autre thème : soleil en sombre, lune en clair.
$themeLabel = Theme::isLight() ? 'Passer au thème sombre' : 'Passer au thème clair';
$themeIcon  = Theme::isLight() ? 'icon--moon' : 'icon--sun';
?>
<header class="site-header">
    <a href="/">
        <img class="site-header__logo" src="<?= e(logo_url()) ?>" alt="FoodBattle, accueil" width="140" height="56">
    </a>

    <nav class="site-header__nav" aria-label="Navigation principale">
        <a class="site-header__link" href="/"<?= current_page_attr('/') ?>>Découvrir</a>
        <a class="site-header__link" href="/classement"<?= current_page_attr('/classement') ?>>Classement</a>

        <?php if (Auth::isAdmin()): ?>
            <a class="site-header__link" href="/dashboard"<?= current_page_attr('/dashboard') ?>>Dashboard</a>
        <?php endif; ?>

        <form method="post" action="/theme">
            <?= csrf_field() ?>
            <!-- Page actuelle (avec sa recherche éventuelle) : on y revient après le changement -->
            <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
            <button class="theme-toggle">
                <span class="icon <?= e($themeIcon) ?>" aria-hidden="true"></span>
                <span class="visually-hidden"><?= e($themeLabel) ?></span>
            </button>
        </form>

        <?php if ($user === null): ?>
            <a class="btn btn--secondary" href="/connexion">Connexion</a>
        <?php else: ?>
            <span class="site-header__user"><?= e($user['nickname']) ?></span>
            <form class="site-header__logout" method="post" action="/deconnexion">
                <?= csrf_field() ?>
                <button class="site-header__logout-button">Déconnexion</button>
            </form>
        <?php endif; ?>
    </nav>
</header>
