<?php

declare(strict_types=1);

namespace MerciFacteur\Api;

use MerciFacteur\Api\Enum\ModeEnvoi;
use MerciFacteur\Api\Exception\ValidationException;

/**
 * Une adresse d'expéditeur ou de destinataire.
 *
 * Deux pièges que cette classe supprime :
 * - l'API rejette une adresse dont une clé est absente, même facultative
 *   (INFO_ADDRESS_MISSING) : toArray() les émet toutes, les inutilisées à "" ;
 * - un recommandé électronique exige email (OTP mail) ou phone (OTP SMS) sur
 *   l'expéditeur COMME sur chaque destinataire, plus consent = 1 sur chaque
 *   destinataire : validate() le vérifie avant l'appel réseau.
 */
final class Address
{
    /** Longueurs maximales acceptées par l'API, en caractères. */
    private const LIMITES = [
        'civilite' => 12,
        'societe' => 90,
        'nom' => 70,
        'prenom' => 70,
        'adresse1' => 90,
        'adresse2' => 90,
        'adresse3' => 90,
        'cp' => 12,
        'ville' => 70,
        'pays' => 70,
        'reference' => 30,
    ];

    public function __construct(
        public readonly string $pays,
        public readonly string $ville,
        public readonly string $cp,
        public readonly string $nom = '',
        public readonly string $societe = '',
        public readonly string $prenom = '',
        public readonly string $civilite = '',
        public readonly string $adresse1 = '',
        public readonly string $adresse2 = '',
        public readonly string $adresse3 = '',
        public readonly string $email = '',
        public readonly string $phone = '',
        /** Consentement du destinataire pour un recommandé électronique. */
        public readonly bool $consent = false,
        /** Votre référence interne : revient dans ref_interne sur les webhooks. */
        public readonly string $reference = '',
        /** URL du logo — expéditeur uniquement, et carnet d'adresses uniquement. */
        public readonly string $logo = '',
    ) {
    }

    /**
     * Construit une adresse depuis un tableau associatif (clés de l'API).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $get = static fn (string $k): string => isset($data[$k]) ? trim((string) $data[$k]) : '';

        return new self(
            pays: $get('pays'),
            ville: $get('ville'),
            cp: $get('cp'),
            nom: $get('nom'),
            societe: $get('societe'),
            prenom: $get('prenom'),
            civilite: $get('civilite'),
            adresse1: $get('adresse1'),
            adresse2: $get('adresse2'),
            adresse3: $get('adresse3'),
            email: $get('email'),
            phone: $get('phone'),
            consent: isset($data['consent']) && (int) $data['consent'] === 1,
            reference: $get('reference'),
            logo: $get('logo'),
        );
    }

    /**
     * Représentation attendue par sendCourrier.
     *
     * @return array<string, string|int>
     */
    public function toArray(bool $isDestinataire = false): array
    {
        $out = [
            'civilite' => $this->civilite,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'societe' => $this->societe,
            'adresse1' => $this->adresse1,
            'adresse2' => $this->adresse2,
            'adresse3' => $this->adresse3,
            'cp' => $this->cp,
            'ville' => $this->ville,
            'pays' => $this->pays,
            'email' => $this->email,
            'phone' => $this->phone,
        ];

        if ($isDestinataire) {
            $out['consent'] = $this->consent ? 1 : 0;
            $out['reference'] = $this->reference;
        }

        return $out;
    }

    /**
     * Représentation attendue par setNewAdress / updateAdress (carnet d'adresses).
     *
     * @return array<string, string|int>
     */
    public function toBookArray(): array
    {
        $out = $this->toArray(isDestinataire: true);
        unset($out['reference']);
        $out['logo'] = $this->logo;

        return $out;
    }

    /**
     * Contrôles exécutés avant tout appel réseau.
     *
     * @throws ValidationException
     */
    public function validate(ModeEnvoi $mode, bool $isDestinataire): void
    {
        $role = $isDestinataire ? 'destinataire' : 'expéditeur';

        if ($this->nom === '' && $this->societe === '') {
            throw new ValidationException("Adresse $role : nom ou societe est obligatoire.");
        }

        foreach (['cp', 'ville', 'pays'] as $champ) {
            if ($this->{$champ} === '') {
                throw new ValidationException("Adresse $role : $champ est obligatoire.");
            }
        }

        foreach (self::LIMITES as $champ => $max) {
            $valeur = (string) $this->{$champ};
            if (mb_strlen($valeur) > $max) {
                throw new ValidationException(
                    "Adresse $role : $champ dépasse $max caractères (" . mb_strlen($valeur) . ')."
                );
            }
        }

        if ($this->reference !== '' && preg_match('/^[A-Za-z0-9 _-]+$/', $this->reference) !== 1) {
            throw new ValidationException(
                "Adresse $role : reference n'accepte que chiffres, lettres, espaces, - et _."
            );
        }

        $champContact = $mode->champContactRequis();
        if ($champContact !== null && $this->{$champContact} === '') {
            throw new ValidationException(
                "Recommandé électronique ({$mode->value}) : $champContact est obligatoire sur l'$role. "
                . 'Une adresse reprise d\'un envoi papier ne le contient pas.'
            );
        }

        if ($mode->isElectronique() && $isDestinataire && !$this->consent) {
            throw new ValidationException(
                'Recommandé électronique : consent doit valoir 1 sur le destinataire. '
                . 'Le consentement n\'est pas requis pour un destinataire professionnel, '
                . 'mais vous devez déclarer l\'avoir recueilli pour un particulier.'
            );
        }
    }
}
