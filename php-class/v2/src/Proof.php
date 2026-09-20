<?php

declare(strict_types=1);

namespace MerciFacteur\Api;

use MerciFacteur\Api\Exception\ValidationException;

/**
 * Un document de preuve retourné par getProof.
 *
 * Le format n'est pas toujours PDF : un avis de réception numérisé revient en
 * JPEG. C'est `format` qui fait foi, pas une hypothèse.
 */
final class Proof
{
    public function __construct(
        /** "pdf" ou "jpeg" */
        public readonly string $format,
        /** Le document, encodé en base64 tel que l'API l'a retourné. */
        public readonly string $base64,
    ) {
    }

    public function mimeType(): string
    {
        return match (strtolower($this->format)) {
            'pdf' => 'application/pdf',
            'jpeg', 'jpg' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }

    /** Le document décodé, prêt à être écrit ou stocké. */
    public function contenu(): string
    {
        $binaire = base64_decode($this->base64, true);
        if ($binaire === false) {
            throw new ValidationException('Preuve : base64 invalide.');
        }

        return $binaire;
    }

    /**
     * Écrit la preuve sur le disque et retourne le chemin final.
     *
     * Une preuve a une valeur juridique : stockez le fichier, pas un lien, et
     * conservez à côté le numéro de suivi et la date de récupération.
     */
    public function enregistrer(string $cheminSansExtension): string
    {
        $chemin = $cheminSansExtension . '.' . strtolower($this->format);
        if (file_put_contents($chemin, $this->contenu()) === false) {
            throw new ValidationException("Preuve : écriture impossible — $chemin");
        }

        return $chemin;
    }

    /** URL de données, pour un affichage direct dans un navigateur. */
    public function dataUri(): string
    {
        return 'data:' . $this->mimeType() . ';base64,' . $this->base64;
    }
}
