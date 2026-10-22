<?php

declare(strict_types=1);

namespace App\Middlewares;

/**
 * Contrat de tous les middlewares : le Router appelle handle() avant le controller.
 * - Si handle() se termine normalement, la requête continue (équivalent de next() en Express).
 * - Pour bloquer la requête, handle() appelle Response::redirect() ou Response::abort().
 */
interface Middleware
{
    public function handle(): void;
}
