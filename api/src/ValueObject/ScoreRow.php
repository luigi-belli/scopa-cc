<?php

declare(strict_types=1);

namespace App\ValueObject;

final readonly class ScoreRow implements \JsonSerializable
{
    public function __construct(
        public int $carte = 0,
        public int $denari = 0,
        public int $setteBello = 0,
        public int $primiera = 0,
        public int $scope = 0,
        public int $carteCount = 0,
        public int $denariCount = 0,
        public ?int $primieraValue = null,
        public bool $hasSetteBello = false,
    ) {}

    /** @param array{carte: int, denari: int, setteBello: int, primiera: int, scope: int, carteCount: int, denariCount: int, primieraValue: int|null, hasSetteBello: bool} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            carte: $data['carte'],
            denari: $data['denari'],
            setteBello: $data['setteBello'],
            primiera: $data['primiera'],
            scope: $data['scope'],
            carteCount: $data['carteCount'],
            denariCount: $data['denariCount'],
            primieraValue: $data['primieraValue'],
            hasSetteBello: $data['hasSetteBello'],
        );
    }

    public function total(): int
    {
        return $this->carte + $this->denari + $this->setteBello + $this->primiera + $this->scope;
    }

    /** @return array{carte: int, denari: int, setteBello: int, primiera: int, scope: int, carteCount: int, denariCount: int, primieraValue: int|null, hasSetteBello: bool} */
    public function jsonSerialize(): array
    {
        return [
            'carte' => $this->carte,
            'denari' => $this->denari,
            'setteBello' => $this->setteBello,
            'primiera' => $this->primiera,
            'scope' => $this->scope,
            'carteCount' => $this->carteCount,
            'denariCount' => $this->denariCount,
            'primieraValue' => $this->primieraValue,
            'hasSetteBello' => $this->hasSetteBello,
        ];
    }
}
