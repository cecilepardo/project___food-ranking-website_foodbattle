<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Establishment;

/**
 * Liste des établissements (administration).
 */
class EstablishmentController extends Controller
{
    /**
     * GET /dashboard/etablissements
     */
    public function index(): void
    {
        $this->render('admin-establishments', [
            'title'          => 'Établissements',
            'establishments' => Establishment::all(),
        ]);
    }
}
