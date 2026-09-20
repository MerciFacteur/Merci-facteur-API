<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Exception;

/**
 * HTTP 401 : token invalide ou expiré, service ID incorrect, ou IP non autorisée.
 *
 * Le client renouvelle le token et rejoue l'appel une fois de lui-même sur les
 * opérations sans effet de bord. Cette exception signale que le renouvellement
 * n'a pas suffi : la cause est ailleurs (restriction d'IP, identifiants).
 */
final class AuthenticationException extends ApiException
{
}
