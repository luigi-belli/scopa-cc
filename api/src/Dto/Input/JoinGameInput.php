<?php

declare(strict_types=1);

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class JoinGameInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    public string $playerName = '';
}
