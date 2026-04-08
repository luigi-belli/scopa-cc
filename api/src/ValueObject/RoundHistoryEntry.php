<?php

declare(strict_types=1);

namespace App\ValueObject;

/**
 * @phpstan-import-type ScoreRowArray from ScoreRow
 */
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
        /** @var array{0: ScoreRowArray, 1: ScoreRowArray} $scores */
        $scores = $data['scores'];
        /** @var array{0: int, 1: int} $totals */
        $totals = $data['totals'];
        return new self(
            RoundScores::fromArray($scores),
            $totals,
        );
    }

    /** @return array{scores: array{0: ScoreRowArray, 1: ScoreRowArray}, totals: array{0: int, 1: int}} */
    public function jsonSerialize(): array
    {
        return [
            'scores' => $this->scores->jsonSerialize(),
            'totals' => $this->totals,
        ];
    }
}
