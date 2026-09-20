<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Enum;

/**
 * Rôle d'un utilisateur créé via setUser.
 *
 * Pour un utilisateur créé par programme, c'est API qu'il faut : il n'a pas
 * d'accès à l'interface et ne reçoit aucune notification par email.
 */
enum UserRole: string
{
    /** Accès à l'interface et à tous les courriers de tous les utilisateurs. */
    case ADMIN = 'admin';

    /** Accès à l'interface, limité à ses propres courriers. */
    case USER = 'user';

    /** Aucun accès à l'interface, aucune notification par email. */
    case API = 'api';
}
