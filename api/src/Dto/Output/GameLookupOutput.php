<?php

declare(strict_types=1);

namespace App\Dto\Output;

final readonly class GameLookupOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $state,
    ) {}
}
