<?php

declare(strict_types=1);

namespace App\Core;

final class Flash
{
    /** @param 'success'|'error'|'info' $type */
    public static function add(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type: string, message: string}> */
    public static function pull(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }
}
