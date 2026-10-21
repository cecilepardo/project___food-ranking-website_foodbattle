<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Dish;

/**
 * Classement des plats.
 */
class RankingController extends Controller
{
    public function index(): void
    {
        $this->render('ranking/index', [
            'title'  => 'Classement',
            'dishes' => Dish::ranking(10),
        ]);
    }
}
