<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Exception;

/**
 * L'API a répondu et a refusé la requête (success = false).
 *
 * Dans le cas de sendCourrier, cela signifie qu'AUCUN courrier n'a été créé :
 * l'appel peut être corrigé puis rejoué sans risque de doublon.
 */
class ApiException extends MerciFacteurException
{
    public function __construct(
        private readonly string $errorCode,
        private readonly string $errorText,
        private readonly int $httpStatus,
        private readonly string $endpoint,
        private readonly array $response = [],
    ) {
        parent::__construct(
            sprintf('%s a échoué (HTTP %d) : %s — %s', $endpoint, $httpStatus, $errorCode, $errorText)
        );
    }

    /** Code d'erreur stable, sur lequel construire la logique applicative. */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** Message lisible, susceptible de changer : ne jamais le tester. */
    public function getErrorText(): string
    {
        return $this->errorText;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /** @return array<string, mixed> La réponse décodée complète. */
    public function getResponse(): array
    {
        return $this->response;
    }
}
