<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Exception;

use RuntimeException;

/**
 * Classe de base de toutes les exceptions du client.
 *
 * Attraper MerciFacteurException permet de tout intercepter ;
 * attraper les sous-classes permet de distinguer les cas qui se rejouent
 * de ceux qui ne se rejouent pas.
 */
class MerciFacteurException extends RuntimeException
{
}
