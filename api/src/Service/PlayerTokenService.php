<?php

declare(strict_types=1);

namespace App\Service;

final readonly class PlayerTokenService
{
    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function sanitizeName(string $name, int $maxLength = 30): string
    {
        return $name
            |> trim(...)
            |> (static fn(string $n): string => preg_replace('/[\x00-\x1F\x7F]/u', '', $n))
            |> (static fn(string $n): string => mb_substr($n, 0, $maxLength));
    }
}
