<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Dish;

/**
 * Modification d'un plat depuis le dashboard.
 */
class DishController extends Controller
{
    /**
     * GET /dashboard/plats/{id}/modifier
     */
    public function edit(int $id): void
    {
        $dish = $this->findOrFail($id);

        $this->showForm($dish);
    }

    /**
     * POST /dashboard/plats/{id}/modifier
     */
    public function update(int $id): void
    {
        $dish = $this->findOrFail($id);
        $this->checkCsrf();

        $name  = trim($this->postString('name'));
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $price = is_float($price) ? round($price, 2) : $price;

        $isValid = $name !== ''
            && strlen($name) <= 120
            && $price !== false
            && $price !== null
            && $price >= 0
            && $price <= Dish::MAX_PRICE;

        if (!$isValid) {
            $this->showForm($dish, error: 'Vérifie le nom et le prix.');
            return;
        }

        Dish::update($id, $name, $price);
        $dish['name']  = $name;
        $dish['price'] = $price;

        $this->showForm($dish, saved: true);
    }

    /**
     * POST /dashboard/plats/{id}/supprimer
     */
    public function delete(int $id): void
    {
        $this->checkCsrf();
        $this->findOrFail($id);

        Dish::delete($id);

        $this->redirect('/dashboard?deleted=1');
    }

    private function showForm(array $dish, ?string $error = null, bool $saved = false): void
    {
        $this->render('admin-edit', [
            'title' => 'Modifier un plat',
            'dish'  => $dish,
            'error' => $error,
            'saved' => $saved,
        ]);
    }

    private function findOrFail(int $id): array
    {
        $dish = Dish::find($id);

        if ($dish === null) {
            $this->render('error-dish-not-found', ['title' => 'Modifier un plat', 'backLink' => false], 404);
            exit;
        }

        return $dish;
    }
}
