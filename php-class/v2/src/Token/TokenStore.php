<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Token;

/**
 * Stockage de l'access token entre deux requêtes.
 *
 * Le token vit 24 h par défaut, et jusqu'à 365 jours avec `timeLimit`.
 * Le redemander à chaque envoi est un aller-retour inutile ; le perdre à chaque
 * invocation (fonction serverless, worker éphémère) revient au même.
 * Implémentez cette interface sur votre cache (Redis, base, APCu) ou utilisez
 * FileTokenStore.
 */
interface TokenStore
{
    /** Retourne le token s'il est encore valide, null sinon. */
    public function get(): ?string;

    /** @param int $expire Timestamp Unix d'expiration retourné par getToken. */
    public function save(string $token, int $expire): void;

    /** Oublie le token courant (401, rotation). */
    public function forget(): void;
}
