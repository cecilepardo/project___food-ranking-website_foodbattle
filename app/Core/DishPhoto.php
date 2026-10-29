<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Dish;

/**
 * Photo d'un plat.
 * - Photo envoyée par l'admin à l'ajout du plat : public/uploads/plats/{id}.jpg|png|webp
 * - Photos des plats de démonstration : public/assets/img/plats/{id}.webp
 * - Sinon, la photo de la catégorie : public/assets/img/plats/burger.webp…
 */
class DishPhoto
{
    public const MAX_BYTES = 2 * 1024 * 1024; // 2 Mo, comme upload_max_filesize de PHP

    /** Types acceptés (lus dans le contenu du fichier, jamais dans son nom) => extension enregistrée. */
    private const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const UPLOAD_DIR = '/uploads/plats';

    /**
     * Problème avec la photo envoyée ($_FILES['photo']), ou null si elle est valide ou absente (champ facultatif).
     */
    public static function uploadError(mixed $file): ?string
    {
        if (!is_array($file) || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > self::MAX_BYTES) {
            return 'La photo dépasse 2 Mo.';
        }
        if ($file['error'] !== UPLOAD_ERR_OK || self::extensionOf($file['tmp_name']) === null) {
            return 'La photo doit être une image JPEG, PNG ou WebP.';
        }

        return null;
    }

    /**
     * Enregistre la photo envoyée pour ce plat (à appeler après uploadError()).
     * Renvoie false si l'enregistrement a échoué (ex. dossier non accessible en écriture) ; true s'il a réussi
     * ou si aucune photo n'a été envoyée.
     */
    public static function save(int $dishId, mixed $file): bool
    {
        if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK) {
            return true;
        }

        $directory = APP_ROOT . '/public' . self::UPLOAD_DIR;
        if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
            return false;
        }

        // Nom choisi par le site (id + extension de la liste) : le nom envoyé par le navigateur n'est jamais utilisé.
        return @move_uploaded_file($file['tmp_name'], $directory . '/' . $dishId . '.' . self::extensionOf($file['tmp_name']));
    }

    /**
     * Supprime la photo envoyée pour ce plat, s'il y en a une.
     */
    public static function delete(int $dishId): void
    {
        foreach (self::TYPES as $extension) {
            $file = APP_ROOT . '/public' . self::UPLOAD_DIR . '/' . $dishId . '.' . $extension;
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Adresse de la photo à afficher pour ce plat.
     */
    public static function url(array $dish): string
    {
        $id = (int) $dish['id'];

        foreach (self::TYPES as $extension) {
            $uploaded = self::UPLOAD_DIR . '/' . $id . '.' . $extension;
            if (is_file(APP_ROOT . '/public' . $uploaded)) {
                return asset($uploaded); // ?v=… : une photo remplacée s'affiche tout de suite
            }
        }

        $demoPhoto = '/assets/img/plats/' . $id . '.webp';
        if (is_file(APP_ROOT . '/public' . $demoPhoto)) {
            return $demoPhoto;
        }

        $category = isset(Dish::CATEGORIES[$dish['category']]) ? $dish['category'] : 'burger';

        return '/assets/img/plats/' . $category . '.webp';
    }

    /**
     * Extension correspondant au vrai contenu du fichier, ou null si ce n'est pas une image acceptée.
     * getimagesize() vérifie en plus que l'image se lit (un fichier texte renommé en .jpg est refusé).
     */
    private static function extensionOf(string $path): ?string
    {
        $type = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (!isset(self::TYPES[$type]) || @getimagesize($path) === false) {
            return null;
        }

        return self::TYPES[$type];
    }
}
