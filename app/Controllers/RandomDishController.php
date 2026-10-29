<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DishPhoto;
use App\Core\Response;
use App\Models\Dish;

/**
 * Roue du destin : un plat tiré au sort.
 * Le tirage se fait côté serveur, sur les plats en base : la page ne peut pas choisir le gagnant.
 * Filtrable par catégorie, comme la carte : /hasard?categorie=pizza
 */
class RandomDishController extends Controller
{
    /** Nombre de plats envoyés pour l'animation de la roue (gagnant compris). */
    private const WHEEL_SIZE = 12;

    private const NO_DISH = 'Aucun plat à tirer au sort pour le moment.';

    /**
     * GET /hasard
     * Redirige vers la fiche du plat tiré : fonctionne sans JavaScript.
     */
    public function pick(): void
    {
        $dish = Dish::random($this->categoryFilter());

        if ($dish === null) {
            $this->abort(404, self::NO_DISH);
        }

        $this->redirect('/plat/' . (int) $dish['id']);
    }

    /**
     * GET /hasard.json
     * Le gagnant et les plats à faire défiler sur la roue.
     */
    public function pickJson(): void
    {
        // Plats dans un ordre tiré au sort : le premier est le gagnant, puis on les mélange
        // pour que sa place sur la roue ne le trahisse pas.
        $dishes = array_map(fn (array $dish): array => $this->forWheel($dish), Dish::randomMany(self::WHEEL_SIZE, $this->categoryFilter()));

        if ($dishes === []) {
            Response::json(['error' => self::NO_DISH], 404);
        }

        $winner = $dishes[0];
        shuffle($dishes);

        Response::json(['winner' => $winner, 'dishes' => $dishes]);
    }

    /**
     * Catégorie demandée (?categorie=…), ou null (tous les plats) si elle est absente ou inconnue.
     */
    private function categoryFilter(): ?string
    {
        $category = $this->queryString('categorie');

        return array_key_exists($category, Dish::CATEGORIES) ? $category : null;
    }

    /**
     * Ce que la roue et la fenêtre du résultat affichent d'un plat (textes déjà formatés).
     */
    private function forWheel(array $dish): array
    {
        return [
            'id'          => (int) $dish['id'],
            'name'        => $dish['name'],
            'restaurant'  => $dish['restaurant'],
            'city'        => $dish['city'],
            'categoryKey' => $dish['category'],                                         // case où la roue s'arrête
            'category'    => Dish::CATEGORIES[$dish['category']] ?? $dish['category'],  // "Burgers"
            'price'       => euro($dish['price']),                                      // "12,50 €"
            'rating'      => rating_summary($dish),                                     // "4,0/5 · 9 votes"
            'photo'       => DishPhoto::url($dish),
            'url'         => '/plat/' . (int) $dish['id'],
        ];
    }
}
