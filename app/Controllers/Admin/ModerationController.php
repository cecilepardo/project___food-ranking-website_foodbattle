<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Rating;

/**
 * Historique des avis reçus.
 *
 * BUG connu : la page liste les avis mais ne permet pas de les publier ou de les refuser.
 */
class ModerationController extends Controller
{
    public function index(): void
    {
        $this->render('admin-moderation', [
            'title'   => 'Modération',
            'reviews' => Rating::allWithDish(),
        ]);
    }
}
