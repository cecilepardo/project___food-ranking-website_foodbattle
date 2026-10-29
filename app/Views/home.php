<?php
/**
 * Page d'accueil : la carte.
 *
 * @var string $search      Texte recherché
 * @var string $category    Catégorie sélectionnée
 * @var array  $categories  ['burger' => 'Burgers', ...]
 * @var array  $dishes      Plats trouvés
 */
use App\Core\Auth;
use App\Core\View;

$count = count($dishes);
?>
<section class="hero">
    <div class="hero__content">
        <span class="hero__eyebrow">BURGERS · TACOS · PIZZAS</span>
        <h1 class="hero__title">Qui mérite la première place&nbsp;?</h1>
        <p class="hero__text">Explore les spots du quartier, note les plats et vote pour ton favori.</p>

        <div class="hero__actions">
            <a class="btn" href="/classement">Voir le classement <span aria-hidden="true">→</span></a>
            <?php if (!Auth::check()): ?>
                <a class="btn btn--secondary" href="/connexion">Créer un compte</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Décoratif : le logo de l'en-tête porte déjà le nom du site (alt vide) -->
    <img class="hero__logo" src="<?= e(logo_url()) ?>" alt="" width="400" height="160">
</section>

<div class="section-header">
    <h2 class="section-header__title">À la carte</h2>
    <!-- En français, 0 et 1 sont au singulier -->
    <span class="section-header__count"><?= $count ?> <?= $count > 1 ? 'plats trouvés' : 'plat trouvé' ?></span>
</div>

<form class="filters" method="get" action="/">
    <label class="search-field filters__search">
        <span class="visually-hidden">Rechercher un plat ou un établissement</span>
        <span class="icon icon--search search-field__icon" aria-hidden="true"></span>
        <input class="search-field__input" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Un plat ou un établissement">
    </label>

    <label class="filters__field">
        <span class="visually-hidden">Catégorie</span>
        <span class="select-field filters__select">
            <select class="select-field__select" name="categorie">
                <option value="">Tout</option>
                <?php foreach ($categories as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $category === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="icon icon--chevron-down select-field__icon" aria-hidden="true"></span>
        </span>
    </label>

    <button class="btn">Rechercher</button>
    <a href="/">Réinitialiser</a>
</form>

<div class="card-grid">
    <?php foreach ($dishes as $dish): ?>
        <?= View::partial('dish-card', ['dish' => $dish]) ?>
    <?php endforeach; ?>
</div>

<?php if (empty($dishes)): ?>
    <p>Aucun résultat. Essaie un autre terme.</p>
<?php endif; ?>
