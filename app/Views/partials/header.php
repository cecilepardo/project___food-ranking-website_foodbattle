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
    <a class="site-header__home" href="/">
        <img class="site-header__logo" src="<?= e(logo_url()) ?>" alt="FoodBattle, accueil" width="140" height="56">
    </a>

    <nav class="site-header__nav" aria-label="Navigation principale">
        <a class="site-header__link" href="/"<?= current_page_attr('/') ?>>Découvrir</a>
        <a class="site-header__link" href="/classement"<?= current_page_attr('/classement') ?>>Classement</a>

        <?php if (Auth::isAdmin()): ?>
            <a class="site-header__link" href="/dashboard"<?= current_section_attr('/dashboard', ['/dashboard', '/moderation']) ?>>Dashboard</a>
        <?php endif; ?>
    </nav>

    <?php /* Compte et thème : restent sur la première ligne, à côté du logo, même sur mobile */ ?>
    <div class="site-header__actions">
        <?php if ($user === null): ?>
            <a class="btn btn--secondary" href="/connexion">Connexion</a>
        <?php else: ?>
            <?php /* Déconnexion en POST (voir AuthController::logout), à la place du bouton Connexion */ ?>
            <form class="inline-form" method="post" action="/deconnexion">
                <?= csrf_field() ?>
                <button class="btn btn--secondary">Déconnexion</button>
            </form>
        <?php endif; ?>

        <form method="post" action="/theme">
            <?= csrf_field() ?>
            <!-- Page actuelle (avec sa recherche éventuelle) : on y revient après le changement.
                 Sauf après un POST (formulaire en erreur) : l'URL n'a souvent pas de page GET, donc l'accueil. -->
            <input type="hidden" name="back" value="<?= e(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? ($_SERVER['REQUEST_URI'] ?? '/') : '/') ?>">
            <button class="theme-toggle">
                <span class="icon <?= e($themeIcon) ?>" aria-hidden="true"></span>
                <span class="visually-hidden"><?= e($themeLabel) ?></span>
            </button>
        </form>
    </div>
</header>
