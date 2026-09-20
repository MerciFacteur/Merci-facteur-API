<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Enum;

/** Données à supprimer des serveurs Merci Facteur après impression. */
enum AnonymizeTarget: string
{
    case CONTENU = 'content';
    case EXPEDITEUR = 'exp';
    case DESTINATAIRE = 'dest';
}
