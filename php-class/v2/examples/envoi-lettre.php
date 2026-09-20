<?php

declare(strict_types=1);

/**
 * Envoi d'une lettre en suivi, avec reprise sûre en cas de timeout.
 *
 * ATTENTION : cet exemple envoie un VRAI courrier, imprimé, posté et facturé.
 * Pour tester, demandez un compte Sandbox au service client Merci Facteur.
 *
 *   php examples/envoi-lettre.php /chemin/vers/facture.pdf
 */

require __DIR__ . '/../vendor/autoload.php';

use MerciFacteur\Api\Address;
use MerciFacteur\Api\Client;
use MerciFacteur\Api\Content;
use MerciFacteur\Api\Enum\ModeEnvoi;
use MerciFacteur\Api\Exception\ApiException;
use MerciFacteur\Api\Exception\TransportException;
use MerciFacteur\Api\Exception\ValidationException;
use MerciFacteur\Api\Token\FileTokenStore;

$pdf = $argv[1] ?? null;
if ($pdf === null) {
    fwrite(STDERR, "Usage : php envoi-lettre.php /chemin/vers/fichier.pdf\n");
    exit(1);
}

$mf = new Client(
    serviceId: (string) getenv('MF_SERVICE_ID'),
    secretKey: (string) getenv('MF_SECRET_KEY'),
    tokenStore: new FileTokenStore(sys_get_temp_dir() . '/mf-token.json'),
);

// La référence anti-doublon identifie LE COURRIER, pas la tentative : elle doit
// rester identique d'un essai à l'autre. Un uniqid() ne protégerait de rien.
$reference = 'facture-4417-relance-1';

try {
    $reponse = $mf->sendCourrier(
        idUser: (int) getenv('MF_USER_ID'),
        expediteur: new Address(
            pays: 'FRANCE',
            ville: 'Versailles',
            cp: '78000',
            societe: 'Dupont Corp.',
            adresse1: '9 allée de la Rose',
        ),
        destinataires: [
            new Address(
                pays: 'FRANCE',
                ville: 'Paris',
                cp: '75015',
                nom: 'Martin',
                prenom: 'Joël',
                adresse1: '33 allée de la Pâquerette',
                adresse2: 'Entrée B',
                reference: $reference,
            ),
        ],
        content: Content::lettreDepuisFichiers([$pdf], finalFilename: 'facture-4417'),
        mode: ModeEnvoi::SUIVI,
        antidoublon: $reference,
    );

    // À stocker : sans envoi_id, plus d'annulation ni de suivi possible.
    printf("Envoi créé : %s\n", json_encode($reponse['envoi_id'] ?? null));
    printf("Coût TTC : %s\n", $reponse['price']['total']['ttc'] ?? '?');
} catch (ValidationException $e) {
    fwrite(STDERR, "Refusé avant l'appel, rien n'est parti : {$e->getMessage()}\n");
    exit(1);
} catch (ApiException $e) {
    fwrite(STDERR, "L'API a refusé ({$e->getErrorCode()}) : {$e->getErrorText()}\n");
    exit(1);
} catch (TransportException $e) {
    if ($e->isOutcomeUnknown()) {
        // L'envoi a PEUT-ÊTRE abouti. Comme il porte un antidoublon stable,
        // le rejouer à l'identique est sûr : l'API refusera le doublon.
        fwrite(STDERR, "Issue indéterminée : rejouez avec le même antidoublon « $reference ».\n");
        exit(75); // EX_TEMPFAIL
    }

    fwrite(STDERR, "Échec réseau : {$e->getMessage()}\n");
    exit(1);
}
