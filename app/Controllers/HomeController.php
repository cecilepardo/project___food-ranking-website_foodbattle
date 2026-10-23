<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Dish;

/**
 * Page d'accueil : la carte avec recherche et filtre par catégorie.
 */
class HomeController extends Controller
{
    private const SEARCH_MAX_LENGTH = 100;

    public function index(): void
    {
        // Une recherche n'a pas besoin de plus de 100 caractères : au-delà, on coupe.
        $search   = mb_substr(trim($this->queryString('q')), 0, self::SEARCH_MAX_LENGTH);
        $category = $this->queryString('categorie');

        $dishes = Dish::search($search, $this->categoryFilter($category));

        $this->render('home', [
            'title'      => 'Découvrir',
            'search'     => $search,
            'category'   => $category,
            'categories' => Dish::CATEGORIES,
            'dishes'     => $dishes,
        ]);
    }

    /**
     * Catégorie réellement envoyée à la requête SQL ('' = pas de filtre).
     */
    private function categoryFilter(string $category): string
    {
        if (!array_key_exists($category, Dish::CATEGORIES)) {
            return '';
        }

        return $category;
    }
}
