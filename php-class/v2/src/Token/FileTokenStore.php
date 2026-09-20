<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Token;

use MerciFacteur\Api\Exception\ValidationException;

/**
 * Stockage du token dans un fichier local.
 *
 * Le fichier contient un secret : placez-le hors de la racine web, avec des
 * droits restreints (0600). Sur plusieurs serveurs sans disque partagé, chaque
 * machine aura son propre fichier — ce n'est pas un problème, getToken retourne
 * le token existant tant qu'il est valide.
 */
final class FileTokenStore implements TokenStore
{
    public function __construct(
        private readonly string $path,
        private readonly int $marge = 60,
    ) {
        $dossier = \dirname($this->path);
        if (!is_dir($dossier) || !is_writable($dossier)) {
            throw new ValidationException("FileTokenStore : dossier inaccessible en écriture — $dossier");
        }
    }

    public function get(): ?string
    {
        if (!is_readable($this->path)) {
            return null;
        }

        $brut = @file_get_contents($this->path);
        if ($brut === false || $brut === '') {
            return null;
        }

        $data = json_decode($brut, true);
        if (!\is_array($data) || !isset($data['token'], $data['expire'])) {
            return null;
        }

        if ((int) $data['expire'] <= time() + $this->marge) {
            return null;
        }

        return (string) $data['token'];
    }

    public function save(string $token, int $expire): void
    {
        $json = json_encode(['token' => $token, 'expire' => $expire], JSON_THROW_ON_ERROR);
        file_put_contents($this->path, $json, LOCK_EX);
        @chmod($this->path, 0600);
    }

    public function forget(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
