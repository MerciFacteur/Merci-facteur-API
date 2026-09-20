<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Exception;

/**
 * Erreur détectée côté client, avant tout appel réseau.
 *
 * Aucun courrier n'a été créé : la correction puis le rappel sont sans risque.
 */
final class ValidationException extends MerciFacteurException
{
}
