<?php

declare(strict_types=1);

namespace App\ValueObject;

final readonly class PendingPlay implements \JsonSerializable
{
    /** @param list<list<int>> $options */
    public function __construct(
        public Card $card,
        public int $playerIndex,
        public array $options,
        public ?int $cardIndex = null,
    ) {}

    /** @param array{card: array{suit: string, value: int}, playerIndex: int, options: list<list<int>>, cardIndex?: int} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Card::fromArray($data['card']),
            $data['playerIndex'],
            $data['options'],
            $data['cardIndex'] ?? null,
        );
    }

    /** @return array{card: array{suit: string, value: int}, playerIndex: int, options: list<list<int>>, cardIndex?: int} */
    #[\Override]
    public function jsonSerialize(): array
    {
        $data = [
            'card' => $this->card->jsonSerialize(),
            'playerIndex' => $this->playerIndex,
            'options' => $this->options,
        ];

        if ($this->cardIndex !== null) {
            $data['cardIndex'] = $this->cardIndex;
        }

        return $data;
    }
}
