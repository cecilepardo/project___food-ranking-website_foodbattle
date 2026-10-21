<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Transforme un fichier de app/Views en HTML.
 */
class View
{
    /**
     * Rend une vue, puis l'insère dans le layout principal.
     *
     * Exemple : View::render('dish', ['dish' => $dish])
     * => la vue app/Views/dish.php reçoit une variable $dish.
     */
    public static function render(string $view, array $data = []): string
    {
        $content = self::renderFile($view, $data);

        return self::renderFile('layout', $data + ['content' => $content]);
    }

    /**
     * Rend un petit morceau de page réutilisable (app/Views/partials).
     */
    public static function partial(string $name, array $data = []): string
    {
        return self::renderFile('partials/' . $name, $data);
    }

    private static function renderFile(string $view, array $data): string
    {
        $file = APP_ROOT . '/app/Views/' . $view . '.php';

        extract($data);
        ob_start();
        require $file;

        return ob_get_clean();
    }
}
