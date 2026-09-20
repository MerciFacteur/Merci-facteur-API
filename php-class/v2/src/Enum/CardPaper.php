<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Enum;

/** Papier d'une carte illustrée (350 g/m²). */
enum CardPaper: string
{
    case CLASSIQUE = 'classic';
    case NACRE = 'nacre';
    case CREATION = 'creation';
}
