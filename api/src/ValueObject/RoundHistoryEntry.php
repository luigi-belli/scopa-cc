<?php

declare(strict_types=1);

namespace App\ValueObject;

final readonly class RoundHistoryEntry implements \JsonSerializable
{
    /** @param array{0: int, 1: int} $totals */
    public function __construct(
        public RoundScores $scores,
        public array $totals,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array{0: array{carte: int, denari: int, setteBello: int, primiera: int, scope: int, carteCount: int, denariCount: int, primieraValue: int|null, hasSetteBello: bool}, 1: array{carte: int, denari: int, setteBello: int, primiera: int, scope: int, carteCount: int, denariCount: int, primieraValue: int|null, hasSetteBello: bool}} $scores */
        $scores = $data['scores'];
        /** @var array{0: int, 1: int} $totals */
        $totals = $data['totals'];
        return new self(
            RoundScores::fromArray($scores),
            $totals,
        );
    }

    /** @return array{scores: mixed, totals: array{0: int, 1: int}} */
    public function jsonSerialize(): array
    {
        return [
            'scores' => $this->scores->jsonSerialize(),
            'totals' => $this->totals,
        ];
    }
}
