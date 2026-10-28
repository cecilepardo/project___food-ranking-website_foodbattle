<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Validator;
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
        $this->renderDish($id);
    }

    /**
     * Affiche la fiche du plat. Après un avis refusé, $rejectedRating garde la saisie
     * (note, commentaire) : le formulaire est réaffiché rempli, rien n'est à retaper.
     */
    private function renderDish(int $id, ?array $rejectedRating = null, int $statusCode = 200): void
    {
        $dish = Dish::findWithEstablishment($id);

        if ($dish === null) {
            $this->abort(404, "Ce plat n'existe pas ou a été supprimé.");
        }

        $this->render('dish', [
            'title'         => $dish['name'],
            'dish'          => $dish,
            'reviews'       => Rating::publishedForDish($id),
            'currentUser'   => Auth::user(),
            'saved'         => isset($_GET['ok']),
            'alreadyVoted'  => isset($_GET['deja']),
            'alreadyRated'  => isset($_GET['deja-avis']),
            'ratingSent'    => isset($_GET['avis']),
            'ratingError'   => $rejectedRating !== null,
            'ratingScore'   => $rejectedRating['score'] ?? null,
            'ratingComment' => $rejectedRating['comment'] ?? '',
        ], $statusCode);
    }

    /**
     * GET /plat/{id}/participer
     * Lien « Connecte-toi pour voter » : protégé par RequireLogin, qui mémorise cette page GET.
     * Après la connexion, l'utilisateur arrive ici puis revient sur la fiche du plat.
     */
    public function participate(int $id): void
    {
        $this->ensureDishExists($id);

        $this->redirect('/plat/' . $id);
    }

    /**
     * POST /plat/{id}/vote
     */
    public function vote(int $id): void
    {
        $this->checkCsrf();
        $this->ensureDishExists($id);

        if (!Vote::create($id, $this->currentUserId())) {
            $this->redirect('/plat/' . $id . '?deja=1');
        }

        $this->redirectToDish($id);
    }

    /**
     * POST /plat/{id}/avis
     */
    public function rate(int $id): void
    {
        $this->checkCsrf();
        $this->ensureDishExists($id);

        $score   = filter_input(INPUT_POST, 'score', FILTER_VALIDATE_INT);
        $comment = trim($this->postString('comment'));

        if (!$this->isValidRating($score, $comment)) {
            $this->renderDish($id, ['score' => $score, 'comment' => $comment], 422);
            return;
        }

        if (!Rating::create($id, $this->currentUserId(), $score, $comment)) {
            $this->redirect('/plat/' . $id . '?deja-avis=1');
        }

        $this->redirect('/plat/' . $id . '?avis=1');
    }

    private function isValidRating(mixed $score, string $comment): bool
    {
        $scoreIsValid   = is_int($score) && $score >= 1 && $score <= 5;
        $commentIsValid = mb_strlen($comment) <= 1000 && Validator::isCleanText($comment);

        return $scoreIsValid && $commentIsValid;
    }

    /**
     * Id de l'utilisateur connecté. Les routes de vote et d'avis sont protégées par RequireLogin :
     * le cas « personne n'est connecté » ne devrait pas arriver, mais on ne laisse jamais passer un id manquant.
     */
    private function currentUserId(): int
    {
        $userId = Auth::id();

        if ($userId === null) {
            $this->redirect('/connexion');
        }

        return $userId;
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
