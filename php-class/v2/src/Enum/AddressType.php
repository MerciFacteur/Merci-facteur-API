<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Enum;

/** Type d'adresse dans le carnet d'adresses. */
enum AddressType: string
{
    case EXPEDITEUR = 'exp';
    case DESTINATAIRE = 'dest';
}
