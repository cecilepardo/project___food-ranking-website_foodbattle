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
    /** Valeurs acceptées pour le filtre ?statut=… */
    private const STATUSES = ['pending', 'published', 'rejected'];

    /**
     * GET /moderation, filtrable par statut : /moderation?statut=pending
     */
    public function index(): void
    {
        $status = $this->statusFilter($_GET['statut'] ?? null);

        $this->render('admin-moderation', [
            'title'   => 'Modération',
            'reviews' => Rating::allWithDish($status),
            'counts'  => Rating::countByStatus(),
            'status'  => $status,
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

        // On revient sur la liste filtrée d'où vient le clic.
        $filter = $this->statusFilter($this->postString('statut'));
        $this->redirect('/moderation?' . http_build_query(['statut' => $filter, 'fait' => $status]));
    }

    /**
     * Statut à filtrer, ou null (tous les avis) si la valeur est absente ou inconnue.
     */
    private function statusFilter(mixed $value): ?string
    {
        return in_array($value, self::STATUSES, true) ? $value : null;
    }
}
