<?php

declare(strict_types=1);

namespace MerciFacteur\Api;

use MerciFacteur\Api\Enum\CardCorner;
use MerciFacteur\Api\Enum\CardFormat;
use MerciFacteur\Api\Enum\CardPaper;
use MerciFacteur\Api\Enum\PrintSides;
use MerciFacteur\Api\Exception\ValidationException;

/**
 * Contenu d'un courrier : une lettre, une carte illustrée, ou des photos.
 *
 * L'API attend les trois clés `letter`, `photo` et `card` à chaque envoi, et le
 * vide s'écrit `""` — jamais `[]` ni `null`, sous peine de LETTER_INVALID_FILES.
 * Cette classe s'en charge : construisez le contenu voulu, les deux autres clés
 * sont remplies correctement.
 */
final class Content
{
    /** @param array<string, mixed> $letter */
    private function __construct(
        private readonly array $letter = [],
        private readonly array $photo = [],
        private readonly array $card = [],
    ) {
    }

    /**
     * Une lettre, à partir de PDF déjà encodés en base64.
     *
     * @param list<string> $base64Files  10 fichiers maximum, 50 Mo par fichier
     * @param string|null  $finalFilename Libellé sans extension, 50 caractères max.
     *                                    Visible par le destinataire sur un ERE.
     */
    public static function lettre(
        array $base64Files,
        PrintSides $printSides = PrintSides::RECTO,
        ?string $finalFilename = null,
    ): self {
        $base64Files = array_values(array_map(self::nettoieBase64(...), $base64Files));

        if ($base64Files === []) {
            throw new ValidationException('Lettre : au moins un PDF est nécessaire.');
        }
        if (count($base64Files) > 10) {
            throw new ValidationException('Lettre : 10 fichiers PDF maximum.');
        }

        return new self(letter: self::letterPayload('', $base64Files, $printSides, $finalFilename));
    }

    /**
     * Une lettre, à partir de PDF lus sur le disque local.
     *
     * @param list<string> $paths
     */
    public static function lettreDepuisFichiers(
        array $paths,
        PrintSides $printSides = PrintSides::RECTO,
        ?string $finalFilename = null,
    ): self {
        $base64 = [];
        foreach ($paths as $path) {
            $contenu = @file_get_contents($path);
            if ($contenu === false) {
                throw new ValidationException("Lettre : fichier illisible — $path");
            }
            $base64[] = base64_encode($contenu);
        }

        return self::lettre($base64, $printSides, $finalFilename);
    }

    /**
     * Une lettre, à partir d'URL publiques.
     *
     * Le base64 reste le mode fiable : une URL signée n'est pas toujours
     * téléchargeable par les serveurs de Merci Facteur, et l'échec survient
     * de leur côté, après l'appel. Réservez ce mode aux fichiers volumineux,
     * avec une URL publique et non signée.
     *
     * @param list<string> $urls
     */
    public static function lettreDepuisUrls(
        array $urls,
        PrintSides $printSides = PrintSides::RECTO,
        ?string $finalFilename = null,
    ): self {
        $urls = array_values(array_filter(array_map('trim', $urls), static fn (string $u): bool => $u !== ''));

        if ($urls === []) {
            throw new ValidationException('Lettre : au moins une URL de PDF est nécessaire.');
        }
        if (count($urls) > 10) {
            throw new ValidationException('Lettre : 10 fichiers PDF maximum.');
        }

        return new self(letter: self::letterPayload($urls, '', $printSides, $finalFilename));
    }

    /**
     * Une carte illustrée.
     *
     * @param string $visuelBase64OuUrl Le recto de la carte (JPEG)
     * @param string $texteHtml         Le texte imprimé au dos
     */
    public static function carte(
        CardFormat $format,
        string $visuelBase64OuUrl,
        string $texteHtml = '',
        CardPaper $papier = CardPaper::CLASSIQUE,
        CardCorner $coin = CardCorner::CARRE,
    ): self {
        $visuel = trim($visuelBase64OuUrl);
        if ($visuel === '') {
            throw new ValidationException('Carte : le visuel est obligatoire.');
        }

        $estUrl = str_starts_with($visuel, 'http://') || str_starts_with($visuel, 'https://');

        return new self(card: [
            'format' => $format->value,
            'visuel' => [
                'type' => $estUrl ? 'customimg' : 'base64',
                'value' => $estUrl ? $visuel : self::nettoieBase64($visuel),
            ],
            'text' => [
                'type' => 'html',
                'value' => $texteHtml,
            ],
            'coin' => $coin->value,
            'papier' => $papier->value,
        ]);
    }

    /**
     * Des photos (JPEG), imprimées sur papier brillant 250 g.
     *
     * @param list<string> $base64Files 50 photos maximum par courrier
     */
    public static function photos(array $base64Files): self
    {
        $base64Files = array_values(array_map(self::nettoieBase64(...), $base64Files));

        if ($base64Files === []) {
            throw new ValidationException('Photo : au moins un fichier est nécessaire.');
        }
        if (count($base64Files) > 50) {
            throw new ValidationException('Photo : 50 photos maximum par courrier.');
        }

        return new self(photo: [
            'files' => '',
            'base64files' => $base64Files,
        ]);
    }

    /**
     * Charge utile finale : les trois clés, le vide en chaîne vide.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'letter' => $this->letter === [] ? '' : $this->letter,
            'photo' => $this->photo === [] ? '' : $this->photo,
            'card' => $this->card === [] ? '' : $this->card,
        ];
    }

    /**
     * @param string|list<string> $files
     * @param string|list<string> $base64Files
     *
     * @return array<string, mixed>
     */
    private static function letterPayload(
        string|array $files,
        string|array $base64Files,
        PrintSides $printSides,
        ?string $finalFilename,
    ): array {
        $letter = [
            'files' => $files,
            'base64files' => $base64Files,
            'print_sides' => $printSides->value,
        ];

        if ($finalFilename !== null && trim($finalFilename) !== '') {
            $nom = pathinfo(trim($finalFilename), PATHINFO_FILENAME);
            if (mb_strlen($nom) > 50) {
                throw new ValidationException('final_filename : 50 caractères maximum, sans extension.');
            }
            $letter['final_filename'] = $nom;
        }

        return $letter;
    }

    /** Retire un éventuel préfixe data: et tous les blancs. */
    private static function nettoieBase64(string $valeur): string
    {
        $valeur = preg_replace('#^data:[^;]+;base64,#', '', trim($valeur)) ?? $valeur;

        return preg_replace('/\s+/', '', $valeur) ?? $valeur;
    }
}
