<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Enum;

/**
 * Impression d'une lettre. Le recto-verso réduit le poids, donc l'affranchissement.
 */
enum PrintSides: string
{
    case RECTO = 'recto';

    /** Recto-verso, tous les fichiers à la suite. */
    case RECTO_VERSO = 'rectoverso';

    /** Recto-verso en gardant chaque fichier distinct (page blanche intercalaire). */
    case RECTO_VERSO_DISTINCT = 'distinctrectoverso';
}
