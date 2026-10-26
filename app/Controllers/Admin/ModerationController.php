<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Rating;

/**
 * Modération des avis : un avis reste en attente tant qu'un admin ne l'a pas publié ou refusé.
 */
class ModerationController extends Controller
{
    public function index(): void
    {
        $this->render('admin-moderation', [
            'title'   => 'Modération',
            'reviews' => Rating::allWithDish(),
            'done'    => $_GET['fait'] ?? null,
        ]);
    }

    /**
     * POST /moderation/{id}/publier
     */
    public function publish(int $id): void
    {
        $this->moderate($id, 'published');
    }

    /**
     * POST /moderation/{id}/refuser
     */
    public function reject(int $id): void
    {
        $this->moderate($id, 'rejected');
    }

    private function moderate(int $id, string $status): never
    {
        $this->checkCsrf();

        if (!Rating::setStatus($id, $status)) {
            $this->abort(404, 'Avis introuvable.');
        }

        $this->redirect('/moderation?fait=' . $status);
    }
}
