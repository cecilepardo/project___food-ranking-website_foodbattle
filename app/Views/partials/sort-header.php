<?php
/**
 * En-tête de colonne triable : un lien qui trie le tableau par cette colonne,
 * puis inverse l'ordre au clic suivant.
 *
 * @var string $key         Valeur de ?tri=…
 * @var string $label
 * @var string $sort        Tri actuel ('' = par défaut)
 * @var bool   $descending
 */

$isActive = $sort === $key;
// Colonne déjà triée : on inverse l'ordre. Autre colonne : on commence par l'ordre croissant.
$nextOrder = $isActive && !$descending ? 'desc' : 'asc';
$url       = '/dashboard?' . http_build_query(['tri' => $key, 'ordre' => $nextOrder]);
?>
<th class="table__sortable"<?= $isActive ? ' aria-sort="' . ($descending ? 'descending' : 'ascending') . '"' : '' ?>>
    <a class="table__sort" href="<?= e($url) ?>">
        <?= e($label) ?>
        <?php if ($isActive): ?>
            <span class="icon icon--chevron-<?= $descending ? 'down' : 'up' ?>" aria-hidden="true"></span>
        <?php endif; ?>
    </a>
</th>
