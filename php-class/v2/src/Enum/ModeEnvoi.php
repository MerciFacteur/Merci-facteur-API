<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Enum;

/**
 * Mode d'envoi d'un courrier.
 *
 * Deux arbitrages que l'appelant doit trancher :
 * - LRAR renvoie l'avis de réception EN PAPIER à l'expéditeur, LRARE le
 *   numérise et le pousse par webhook. Dès qu'un logiciel doit archiver l'AR,
 *   c'est LRARE.
 * - SUIVI n'a aucune valeur légale : il ne convient pas pour une mise en
 *   demeure, une résiliation ou un délai opposable.
 */
enum ModeEnvoi: string
{
    /** Lettre verte, aucun suivi, aucune preuve. */
    case NORMAL = 'normal';

    /** Suivi simple : date de réception connue, sans valeur légale. */
    case SUIVI = 'suivi';

    /** Recommandé avec AR papier retourné à l'expéditeur. */
    case LRAR = 'lrar';

    /** Recommandé avec AR numérisé, poussé par l'API + gestion des NPAI. */
    case LRARE = 'lrare';

    /** Recommandé électronique eIDAS, code de vérification par email. */
    case ERE_OTP_MAIL = 'ere_otp_mail';

    /** Recommandé électronique eIDAS, code de vérification par SMS. */
    case ERE_OTP_SMS = 'ere_otp_sms';

    /** true pour un recommandé électronique (contraintes d'adresse renforcées). */
    public function isElectronique(): bool
    {
        return $this === self::ERE_OTP_MAIL || $this === self::ERE_OTP_SMS;
    }

    /** true si ce mode produit des preuves récupérables par API. */
    public function produitDesPreuves(): bool
    {
        return $this !== self::NORMAL;
    }

    /**
     * Champ d'adresse exigé de part et d'autre par ce mode, ou null.
     *
     * @return 'email'|'phone'|null
     */
    public function champContactRequis(): ?string
    {
        return match ($this) {
            self::ERE_OTP_MAIL => 'email',
            self::ERE_OTP_SMS => 'phone',
            default => null,
        };
    }
}
