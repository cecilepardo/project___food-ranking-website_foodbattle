<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Validator;
use App\Models\Establishment;

/**
 * Liste des établissements et ajout d'un établissement (administration).
 */
class EstablishmentController extends Controller
{
    /**
     * GET /dashboard/etablissements
     */
    public function index(): void
    {
        $this->showEstablishments();
    }

    /**
     * POST /dashboard/etablissements
     */
    public function store(): void
    {
        $this->checkCsrf();

        $name    = trim($this->postString('name'));
        $city    = trim($this->postString('city'));
        $address = trim($this->postString('address'));

        if (!$this->isValidEstablishment($name, $city, $address)) {
            $this->showEstablishments('Vérifie les champs de l\'établissement.');
            return;
        }

        Establishment::create($name, $city, $address);

        $this->redirect('/dashboard/etablissements?added=1');
    }

    private function showEstablishments(?string $error = null): void
    {
        $this->render('admin-establishments', [
            'title'          => 'Établissements',
            'establishments' => Establishment::all(),
            'error'          => $error,
            'added'          => isset($_GET['added']),
        ]);
    }

    /**
     * Mêmes limites que les colonnes de la table (VARCHAR(120), VARCHAR(80)) ; 255 pour l'adresse.
     */
    private function isValidEstablishment(string $name, string $city, string $address): bool
    {
        $nameIsValid    = mb_strlen($name) <= 120 && Validator::isCleanText($name);
        $cityIsValid    = mb_strlen($city) <= 80 && Validator::isCleanText($city);
        $addressIsValid = mb_strlen($address) <= 255 && Validator::isCleanText($address);

        return $nameIsValid && $cityIsValid && $addressIsValid;
    }
}
