<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function render(string $template, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        echo View::render($template, $data);
    }

    protected function redirect(string $to): never
    {
        header('Location: ' . $to, true, 303);
        exit;
    }

    /** Refuse toute requête POST sans jeton CSRF valide. */
    protected function verifyCsrf(): void
    {
        if (!Csrf::isValid($_POST['_token'] ?? null)) {
            $this->render('error/error', [
                'title' => 'Session expirée',
                'message' => 'Ta session a expiré. Recharge la page et réessaie.',
            ], 419);
            exit;
        }
    }

    protected function requireAdmin(): void
    {
        if (!Auth::check()) {
            Flash::add('error', 'Connecte-toi pour accéder au dashboard.');
            $this->redirect('/admin/login');
        }
    }

    protected function notFound(string $message = 'Ce plat n’existe pas ou a été retiré de la carte.'): never
    {
        $this->render('error/error', ['title' => 'Introuvable', 'message' => $message], 404);
        exit;
    }
}
