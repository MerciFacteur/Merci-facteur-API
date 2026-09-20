<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Enum;

/** Format d'une carte illustrée. */
enum CardFormat: string
{
    /** Carte postale sans enveloppe, 11 x 16 cm. */
    case CARTE_POSTALE_NUE = 'naked-postcard';

    /** Carte postale avec enveloppe, 11 x 16 cm. */
    case CARTE_POSTALE = 'postcard';

    /** Carte pliée, 15 x 21 cm fermée. */
    case PLIEE = 'folded';

    /** Carte non pliée, 15 x 21 cm. */
    case CLASSIQUE = 'classic';

    /** Carte géante pliée, 19 x 21 cm fermée. */
    case GEANTE = 'large';

    /** Carte géante A4, 21 x 29,7 cm. */
    case GEANTE_A4 = 'large-a4';
}
