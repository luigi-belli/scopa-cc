<?php

declare(strict_types=1);

namespace App\ValueObject;

/**
 * @phpstan-import-type ScoreRowArray from ScoreRow
 */
final readonly class RoundScores implements \JsonSerializable
{
    public function __construct(
        public ScoreRow $player1,
        public ScoreRow $player2,
    ) {}

    /** @param array{0: ScoreRowArray, 1: ScoreRowArray} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            ScoreRow::fromArray($data[0]),
            ScoreRow::fromArray($data[1]),
        );
    }

    public function get(int $index): ScoreRow
    {
        return $index === 0 ? $this->player1 : $this->player2;
    }

    /** @return array{0: ScoreRowArray, 1: ScoreRowArray} */
    public function jsonSerialize(): array
    {
        return [
            $this->player1->jsonSerialize(),
            $this->player2->jsonSerialize(),
        ];
    }
}
