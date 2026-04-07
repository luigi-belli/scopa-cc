<?php

declare(strict_types=1);

namespace App\ValueObject;

final readonly class TurnResult implements \JsonSerializable
{
    /** @param list<list<Card>>|null $options */
    public function __construct(
        public TurnResultType $type,
        public Card $card,
        public int $playerIndex,
        public CardCollection $captured,
        public bool $scopa,
        public ?SweepData $sweep = null,
        public ?array $options = null,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $data = [
            'type' => $this->type->value,
            'card' => $this->card->jsonSerialize(),
            'playerIndex' => $this->playerIndex,
            'captured' => $this->captured->jsonSerialize(),
            'scopa' => $this->scopa,
            'sweep' => $this->sweep?->jsonSerialize(),
        ];

        if ($this->options !== null) {
            $data['options'] = array_map(
                static fn(array $cards): array => array_map(
                    static fn(Card $c): array => $c->jsonSerialize(),
                    $cards,
                ),
                $this->options,
            );
        }

        return $data;
    }
}
