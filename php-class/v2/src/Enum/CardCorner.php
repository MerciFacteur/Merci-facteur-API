<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Enum;

/** Découpe des coins d'une carte illustrée. */
enum CardCorner: string
{
    case CARRE = 'carre';
    case ARRONDI = 'arrondi';
}
