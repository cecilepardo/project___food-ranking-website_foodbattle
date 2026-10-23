<?php
/**
 * Page d'accueil : la carte.
 *
 * @var string $search      Texte recherché
 * @var string $category    Catégorie sélectionnée
 * @var array  $categories  ['burger' => 'Burgers', ...]
 * @var array  $dishes      Plats trouvés
 */
use App\Core\View;
?>
<section class="hero">
    <span class="hero__eyebrow">BURGERS · TACOS · PIZZAS</span>
    <h1 class="hero__title">Qui mérite la première place ?</h1>
    <p class="hero__text">Explore les spots du quartier, note les plats et vote pour ton favori.</p>
    <a class="btn" href="/classement">Voir le classement →</a>
</section>

<h2>À la carte</h2>

<form class="filters" method="get" action="/">
    <label>
        Rechercher
        <input name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Un plat ou un établissement">
    </label>

    <label>
        Catégorie
        <select name="categorie">
            <option value="">Tout</option>
            <?php foreach ($categories as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $category === $value ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <button class="btn filters__submit">Rechercher</button>
    <a href="/">Réinitialiser</a>
</form>

<p><?= count($dishes) ?> plat(s) trouvé(s)</p>

<div class="card-grid">
    <?php foreach ($dishes as $dish): ?>
        <?= View::partial('dish-card', ['dish' => $dish]) ?>
    <?php endforeach; ?>
</div>

<?php if (empty($dishes)): ?>
    <p>Aucun résultat. Essaie un autre terme.</p>
<?php endif; ?>
