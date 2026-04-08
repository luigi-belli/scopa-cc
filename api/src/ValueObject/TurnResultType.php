<?php

declare(strict_types=1);

namespace App\ValueObject;

enum TurnResultType: string
{
    case Place = 'place';
    case Capture = 'capture';
    case Choosing = 'choosing';   // Scopa: multiple capture options
    case Trick = 'trick';         // Briscola: trick resolved
}
