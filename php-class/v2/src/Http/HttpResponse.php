<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Http;

use JsonException;
use MerciFacteur\Api\Exception\TransportException;

/** Réponse HTTP brute. */
final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {
    }

    /**
     * @return array<string, mixed>
     *
     * @throws TransportException si le corps n'est pas du JSON exploitable
     */
    public function json(string $endpoint, bool $outcomeUnknown): array
    {
        try {
            $data = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new TransportException(
                sprintf(
                    '%s : réponse illisible (HTTP %d) — %s. Extrait : %s',
                    $endpoint,
                    $this->status,
                    $e->getMessage(),
                    mb_substr($this->body, 0, 200)
                ),
                $endpoint,
                $outcomeUnknown,
            );
        }

        if (!\is_array($data)) {
            throw new TransportException(
                sprintf('%s : réponse inattendue (HTTP %d).', $endpoint, $this->status),
                $endpoint,
                $outcomeUnknown,
            );
        }

        return $data;
    }
}
