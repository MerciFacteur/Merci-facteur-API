<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Token;

/**
 * Stockage en mémoire : le token ne survit pas à la requête PHP.
 *
 * Suffisant pour un script ou un worker de longue durée. Sur un site web qui
 * envoie du courrier à chaque requête, préférez FileTokenStore ou une
 * implémentation sur votre cache : sinon chaque page paie un getToken.
 */
final class InMemoryTokenStore implements TokenStore
{
    private ?string $token = null;
    private int $expire = 0;

    /** @param int $marge Secondes de sécurité avant l'expiration réelle. */
    public function __construct(private readonly int $marge = 60)
    {
    }

    public function get(): ?string
    {
        if ($this->token === null || $this->expire <= time() + $this->marge) {
            return null;
        }

        return $this->token;
    }

    public function save(string $token, int $expire): void
    {
        $this->token = $token;
        $this->expire = $expire;
    }

    public function forget(): void
    {
        $this->token = null;
        $this->expire = 0;
    }
}
