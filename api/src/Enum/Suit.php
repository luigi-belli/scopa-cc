<?php

declare(strict_types=1);

namespace App\Enum;

enum Suit: string
{
    case Denari = 'Denari';
    case Coppe = 'Coppe';
    case Bastoni = 'Bastoni';
    case Spade = 'Spade';

    public function letter(): string
    {
        return match ($this) {
            self::Denari => 'd',
            self::Coppe => 'c',
            self::Bastoni => 'b',
            self::Spade => 's',
        };
    }
}
