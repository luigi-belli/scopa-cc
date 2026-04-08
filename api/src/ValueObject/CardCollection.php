<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\Suit;

/** @implements \IteratorAggregate<int, Card> */
final readonly class CardCollection implements \JsonSerializable, \Countable, \IteratorAggregate
{
    /** @var list<Card> */
    private array $cards;

    /** @param list<Card> $cards */
    public function __construct(array $cards = [])
    {
        $this->cards = $cards;
    }

    /** @param list<array{suit: string, value: int}> $data */
    public static function fromArray(array $data): self
    {
        return new self(array_map(Card::fromArray(...), $data));
    }

    public static function fill(int $count, Card $card): self
    {
        return new self(array_fill(0, $count, $card));
    }

    /** @return list<array{suit: string, value: int}> */
    public function jsonSerialize(): array
    {
        return array_map(static fn(Card $c): array => $c->jsonSerialize(), $this->cards);
    }

    public function count(): int
    {
        return count($this->cards);
    }

    public function isEmpty(): bool
    {
        return $this->cards === [];
    }

    public function get(int $index): Card
    {
        return $this->cards[$index];
    }

    /** @return list<Card> */
    public function toArray(): array
    {
        return $this->cards;
    }

    /** @return \ArrayIterator<int, Card> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->cards);
    }

    public function withAppended(Card ...$cards): self
    {
        return new self(array_values([...$this->cards, ...$cards]));
    }

    /**
     * Remove card at given index, return the new collection.
     *
     * @return array{card: Card, remaining: self}
     */
    public function removeAt(int $index): array
    {
        $card = $this->cards[$index];
        $remaining = $this->cards;
        array_splice($remaining, $index, 1);
        return ['card' => $card, 'remaining' => new self($remaining)];
    }

    /**
     * Take first $count cards, return taken + remaining.
     *
     * @return array{taken: self, remaining: self}
     */
    public function take(int $count): array
    {
        $taken = array_slice($this->cards, 0, $count);
        $remaining = array_slice($this->cards, $count);
        return ['taken' => new self($taken), 'remaining' => new self($remaining)];
    }

    /**
     * Remove cards at given indices (sorted desc internally to preserve indices).
     *
     * @param list<int> $indices
     * @return array{removed: self, remaining: self}
     */
    public function removeIndices(array $indices): array
    {
        $removed = [];
        foreach ($indices as $idx) {
            $removed[] = $this->cards[$idx];
        }
        $sortedDesc = $indices;
        rsort($sortedDesc);
        $remaining = $this->cards;
        foreach ($sortedDesc as $idx) {
            array_splice($remaining, $idx, 1);
        }
        return ['removed' => new self($removed), 'remaining' => new self($remaining)];
    }

    public function filterBySuit(Suit $suit): self
    {
        return new self(array_values(array_filter(
            $this->cards,
            static fn(Card $card): bool => $card->suit === $suit,
        )));
    }

    public function countBySuit(Suit $suit): int
    {
        $count = 0;
        foreach ($this->cards as $card) {
            if ($card->suit === $suit) {
                $count++;
            }
        }
        return $count;
    }

    public function hasCard(Suit $suit, int $value): bool
    {
        foreach ($this->cards as $card) {
            if ($card->suit === $suit && $card->value === $value) {
                return true;
            }
        }
        return false;
    }

    public function shuffle(): self
    {
        $cards = $this->cards;
        $n = count($cards);
        for ($i = $n - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$cards[$i], $cards[$j]] = [$cards[$j], $cards[$i]];
        }
        return new self(array_values($cards));
    }
}
