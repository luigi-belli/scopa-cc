<?php

declare(strict_types=1);

namespace App\Service;

final class PlayerTokenService
{
    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function sanitizeName(string $name, int $maxLength = 30): string
    {
        $name = trim($name);
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        return mb_substr($name, 0, $maxLength);
    }
}
