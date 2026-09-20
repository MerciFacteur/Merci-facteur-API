<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Enum;

/**
 * Document de preuve demandé à getProof.
 *
 * Avant d'appeler getProof : les webhooks `pdd` et `are` transportent déjà
 * la preuve de dépôt et l'avis de réception numérisé en base64. getProof sert
 * à la preuve de téléchargement d'un ERE, au rattrapage d'un webhook manqué,
 * et à l'historique.
 */
enum ProofDocument: string
{
    /** Preuve de dépôt — suivi, lrar, lrare, ERE. */
    case DEPOT = 'depot';

    /** Avis de réception — lrar, lrare. */
    case RECEPTION = 'reception';

    /** Preuve de téléchargement — ERE uniquement. */
    case TELECHARGEMENT = 'telechargement';
}
