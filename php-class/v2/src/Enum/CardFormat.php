<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Enum;

/**
 * Format d'une carte illustrée (papier 350 g/m²).
 *
 * Les dimensions sont celles de la carte après impression et coupe.
 */
enum CardFormat: string
{
    /** Carte postale sans enveloppe, 10,5 x 15 cm. */
    case CARTE_POSTALE_NUE = 'naked-postcard';

    /** Carte postale avec enveloppe, 10,5 x 15 cm. */
    case CARTE_POSTALE = 'postcard';

    /** Carte pliée, 14 x 19,5 cm fermée (28 x 19,5 ouverte). */
    case PLIEE = 'folded';

    /** Carte non pliée, 14 x 19,5 cm. */
    case CLASSIQUE = 'classic';

    /** Carte carrée, 14 x 14 cm. */
    case CARREE = 'square';

    /** Carte géante pliée, 18,5 x 26 cm fermée (37 x 26 ouverte). */
    case GEANTE = 'large';

    /** Carte géante A4, 21 x 29,7 cm. */
    case GEANTE_A4 = 'large-a4';
}
