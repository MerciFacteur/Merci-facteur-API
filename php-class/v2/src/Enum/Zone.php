<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Enum;

/** Zone tarifaire, utilisée par getCountry. */
enum Zone: string
{
    /** France métropolitaine. */
    case FR = 'fr';

    /** Union européenne hors France. */
    case Z1 = 'z1';

    /** Reste du monde. */
    case Z2 = 'z2';

    /** Guadeloupe, Guyane, Martinique, Mayotte, Réunion, St-Barthélemy, St-Martin, St-Pierre-et-Miquelon. */
    case OM1 = 'om1';

    /** Clipperton, Nouvelle-Calédonie, Polynésie française, TAAF, Wallis-et-Futuna. */
    case OM2 = 'om2';
}
