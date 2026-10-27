<?php
/**
 * Menu de l'administration, affiché en haut de chaque page admin.
 * La pilule de la page en cours reçoit aria-current="page" (voir current_page_attr()).
 */
?>
<nav class="admin-nav" aria-label="Administration">
    <a class="admin-nav__link" href="/dashboard"<?= current_page_attr('/dashboard') ?>>Plats</a>
    <a class="admin-nav__link" href="/dashboard/etablissements"<?= current_page_attr('/dashboard/etablissements') ?>>Établissements</a>
    <a class="admin-nav__link" href="/moderation"<?= current_page_attr('/moderation') ?>>Modération</a>
</nav>
