<?php

declare(strict_types=1);

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class PlayCardInput
{
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(0)]
    public int $cardIndex = 0;
}
