<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Page 500 : toute erreur non prévue (exception, erreur fatale) affiche la page d'erreur du site
 * au lieu d'une page blanche ou d'une trace PHP. Le détail part dans les logs : docker compose logs app
 */
class ErrorHandler
{
    /** Erreurs fatales : elles ne passent pas par set_exception_handler. */
    private const FATAL_ERRORS = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    /**
     * @param bool $showDetails true en dev : le détail technique s'affiche aussi sur la page
     */
    public static function register(bool $showDetails): void
    {
        set_exception_handler(function (Throwable $exception) use ($showDetails): void {
            self::handle($exception, $showDetails);
        });

        register_shutdown_function(function () use ($showDetails): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], self::FATAL_ERRORS, true)) {
                self::handle(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']), $showDetails);
            }
        });
    }

    private static function handle(Throwable $exception, bool $showDetails): void
    {
        error_log((string) $exception);

        // On jette le HTML à moitié construit (vues en cours de rendu).
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
        }

        $details = $showDetails
            ? get_class($exception) . ' : ' . $exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine()
            : null;

        try {
            echo Response::errorPage(500, 'Une erreur est survenue de notre côté. Réessaie dans quelques instants.', $details);
        } catch (Throwable) {
            // La page d'erreur elle-même a planté (ex. vue cassée) : texte brut en dernier recours.
            echo 'Une erreur est survenue. Réessaie dans quelques instants.';
        }
    }
}
