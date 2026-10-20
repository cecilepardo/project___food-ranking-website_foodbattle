<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    /** Rend un template dans le layout. Chaque template reçoit ses données en variables locales. */
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, ['content' => $content] + $data);
    }

    public static function partial(string $template, array $data = []): string
    {
        return self::capture('partials/' . $template, $data);
    }

    private static function capture(string $template, array $data): string
    {
        $file = ROOT_PATH . '/templates/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template introuvable : $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
