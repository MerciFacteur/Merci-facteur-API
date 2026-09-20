<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Exception;

/**
 * L'appel n'a pas abouti à une réponse exploitable : timeout, coupure réseau,
 * réponse illisible.
 *
 * ATTENTION, sur sendCourrier / sendPublipostage l'issue est INDÉTERMINÉE :
 * la requête a pu être traitée côté Merci Facteur et la réponse se perdre au
 * retour. Ne rejouez l'appel automatiquement que s'il portait un `antidoublon`
 * stable. Sinon, sortez l'envoi de la file et faites trancher un humain depuis
 * l'interface Merci Facteur Pro.
 */
final class TransportException extends MerciFacteurException
{
    public function __construct(
        string $message,
        private readonly string $endpoint,
        private readonly bool $outcomeUnknown,
    ) {
        parent::__construct($message);
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * true si l'appel peut avoir produit un courrier facturé malgré l'erreur.
     */
    public function isOutcomeUnknown(): bool
    {
        return $this->outcomeUnknown;
    }
}
