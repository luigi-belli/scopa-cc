<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Enum\DeckStyle;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateGameInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    public string $playerName = '';

    #[Assert\Length(max: 60)]
    public ?string $gameName = null;

    public bool $singlePlayer = false;

    public DeckStyle $deckStyle = DeckStyle::Piacentine;
}
