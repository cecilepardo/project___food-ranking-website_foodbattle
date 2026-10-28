<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/**
 * Mentions légales et données personnelles (RGPD).
 */
class LegalController extends Controller
{
    /**
     * GET /mentions-legales
     */
    public function index(): void
    {
        $this->render('legal', ['title' => 'Mentions légales et données personnelles']);
    }
}
