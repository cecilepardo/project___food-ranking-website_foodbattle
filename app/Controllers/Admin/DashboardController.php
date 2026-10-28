<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Dish;
use App\Models\Establishment;

/**
 * Dashboard : liste des plats et des établissements, ajout d'un plat.
 */
class DashboardController extends Controller
{
    /**
     * GET /dashboard
     */
    public function index(): void
    {
        $this->showDashboard();
    }

    /**
     * POST /dashboard/plats
     */
    public function store(): void
    {
        $this->checkCsrf();

        $name            = trim($this->postString('name'));
        $price           = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $price           = is_float($price) ? round($price, 2) : $price;
        $establishmentId = filter_input(INPUT_POST, 'establishment_id', FILTER_VALIDATE_INT);
        $category        = $this->postString('category');
        $description     = trim($this->postString('description'));

        if (!$this->isValidDish($name, $price, $category, $establishmentId)) {
            $this->showDashboard('Vérifie les champs du plat.');
            return;
        }

        Dish::create($establishmentId, $name, $category, $description, $price);

        $this->redirect('/dashboard?added=1');
    }

    private function showDashboard(?string $error = null): void
    {
        // Tri du tableau : une valeur inconnue revient à l'ordre par défaut.
        $sort       = $this->queryString('tri');
        $sort       = in_array($sort, Dish::SORTS, true) ? $sort : '';
        $descending = $this->queryString('ordre') === 'desc';

        $this->render('admin-dashboard', [
            'title'          => 'Dashboard',
            'dishes'         => Dish::allWithEstablishment($sort, $descending),
            'sort'           => $sort,
            'descending'     => $descending,
            'establishments' => Establishment::all(),
            'categories'     => Dish::CATEGORIES,
            'error'          => $error,
            'added'          => isset($_GET['added']),
            'deleted'        => isset($_GET['deleted']),
        ]);
    }

    private function isValidDish(string $name, mixed $price, string $category, mixed $establishmentId): bool
    {
        $establishmentIds = array_column(Establishment::all(), 'id');

        $nameIsValid          = $name !== '' && mb_strlen($name) <= 120;
        $priceIsValid         = $price !== false && $price !== null && $price >= 0 && $price <= Dish::MAX_PRICE;
        $categoryIsValid      = array_key_exists($category, Dish::CATEGORIES);
        $establishmentIsValid = in_array($establishmentId, $establishmentIds);

        return $nameIsValid && $priceIsValid && $categoryIsValid && $establishmentIsValid;
    }
}
