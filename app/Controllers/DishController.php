<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Dish;
use App\Models\Rating;
use App\Models\Vote;

/**
 * Fiche d'un plat, vote et dépôt d'avis.
 */
class DishController extends Controller
{
    /**
     * GET /plat/{id}
     */
    public function show(int $id): void
    {
        $dish = Dish::findWithEstablishment($id);

        if ($dish === null) {
            $this->render('error-dish-not-found', ['title' => 'Le plat'], 404);
            return;
        }

        $this->render('dish', [
            'title'   => 'Le plat',
            'dish'    => $dish,
            'reviews' => Rating::publishedForDish($id),
            'saved'   => isset($_GET['ok']),
        ]);
    }

    /**
     * POST /plat/{id}/vote
     */
    public function vote(int $id): void
    {
        $this->checkCsrf();
        $this->ensureDishExists($id);

        Vote::create($id, session_id());

        $this->redirectToDish($id);
    }

    /**
     * POST /plat/{id}/avis
     */
    public function rate(int $id): void
    {
        $this->checkCsrf();
        $this->ensureDishExists($id);

        $score    = filter_input(INPUT_POST, 'score', FILTER_VALIDATE_INT);
        $nickname = trim($_POST['nickname'] ?? '');
        $comment  = trim($_POST['comment'] ?? '');

        if (!$this->isValidRating($score, $nickname, $comment)) {
            $this->abort(422, 'Vérifie les champs de ton avis.');
        }

        Rating::create($id, $nickname, $score, $comment);

        $this->redirectToDish($id);
    }

    private function isValidRating(mixed $score, string $nickname, string $comment): bool
    {
        // BUG connu : les limites (240 et 4000) ne correspondent pas à celles du formulaire (60 et 1000).
        $scoreIsValid    = is_int($score) && $score >= 1 && $score <= 5;
        $nicknameIsValid = $nickname !== '' && strlen($nickname) <= 240;
        $commentIsValid  = $comment !== '' && strlen($comment) <= 4000;

        return $scoreIsValid && $nicknameIsValid && $commentIsValid;
    }

    private function ensureDishExists(int $id): void
    {
        if (!Dish::exists($id)) {
            $this->abort(404, 'Plat introuvable.');
        }
    }

    private function redirectToDish(int $id): never
    {
        $this->redirect('/plat/' . $id . '?ok=1');
    }
}
