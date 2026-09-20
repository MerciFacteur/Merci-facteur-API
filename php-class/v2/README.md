# Client PHP — API Merci Facteur 1.2

Client PHP 8.1+ de l'API Merci Facteur : envoi de lettres, cartes et photos, recommandé papier et électronique, publipostage, preuves, suivi, annulation, carnet d'adresses, quotas et webhooks.

Il remplace la classe historique [`../apiMf.class.php`](../apiMf.class.php), qui reste en place pour les intégrations existantes.

## Installation

Avec Composer, depuis ce dépôt :

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/MerciFacteur/Merci-facteur-API" }
  ],
  "require": {
    "merci-facteur/api-client": "dev-master"
  }
}
```

Sans Composer, l'autoload PSR-4 tient en quelques lignes :

```php
spl_autoload_register(function (string $classe): void {
    $prefixe = 'MerciFacteur\\Api\\';
    if (!str_starts_with($classe, $prefixe)) {
        return;
    }
    $chemin = __DIR__ . '/src/' . str_replace('\\', '/', substr($classe, strlen($prefixe))) . '.php';
    if (is_file($chemin)) {
        require $chemin;
    }
});
```

Prérequis : PHP 8.1 ou supérieur, extensions `curl` et `json`.

## Envoyer une lettre

```php
use MerciFacteur\Api\{Client, Address, Content};
use MerciFacteur\Api\Enum\ModeEnvoi;

$mf = new Client(
    serviceId: getenv('MF_SERVICE_ID'),
    secretKey: getenv('MF_SECRET_KEY'),
);

$reponse = $mf->sendCourrier(
    idUser: (int) getenv('MF_USER_ID'),
    expediteur: new Address(
        pays: 'FRANCE', ville: 'Versailles', cp: '78000',
        societe: 'Dupont Corp.', adresse1: '9 allée de la Rose',
    ),
    destinataires: [
        new Address(
            pays: 'FRANCE', ville: 'Paris', cp: '75015',
            nom: 'Martin', prenom: 'Joël', adresse1: '33 allée de la Pâquerette',
            reference: 'facture-4417',
        ),
    ],
    content: Content::lettreDepuisFichiers(['/var/app/pdf/facture-4417.pdf']),
    mode: ModeEnvoi::SUIVI,
    antidoublon: 'facture-4417-relance-1',
);

$idEnvoi = $reponse['envoi_id'];
```

**Conservez `envoi_id`** : sans lui, l'envoi ne peut plus être ni annulé ni retrouvé.

## Ce que le client vous évite

| Piège | Ce que fait le client |
|---|---|
| `adress` avec un seul « d », `print_sides` dans `content.letter` | Les noms de champs sont construits par le client, jamais par vous |
| Une adresse incomplète rejetée en `INFO_ADDRESS_MISSING` | `Address::toArray()` émet toutes les clés, les inutilisées à `""` |
| `[]` au lieu de `""` → `LETTER_INVALID_FILES` | `Content` remplit les trois clés `letter`, `photo`, `card` correctement |
| ERE sans `email` / `phone` sur **l'expéditeur** | `Address::validate()` le refuse avant l'appel réseau |
| `consent` en booléen au lieu de l'entier `1` | Converti à l'encodage |
| `dateEnvoi` tronquée → `INVALID_DATE_ENVOI` | Format et date non passée vérifiés localement |
| Un `getToken` à chaque envoi | Token mis en cache et renouvelé à l'expiration (`TokenStore`) |
| Un incident réseau qui fige le processus | Timeouts de connexion et de requête explicites |
| `format_return` ignoré, preuve écrite en `.pdf` alors qu'elle est en JPEG | `Proof::enregistrer()` choisit l'extension et le type MIME |

## Recommandé électronique

```php
use MerciFacteur\Api\Enum\ModeEnvoi;

$mf->sendCourrier(
    idUser: $idUser,
    expediteur: new Address(
        pays: 'FRANCE', ville: 'Versailles', cp: '78000', societe: 'Dupont Corp.',
        email: 'contact@dupont-corp.fr',       // obligatoire sur l'expéditeur aussi
    ),
    destinataires: [
        new Address(
            pays: 'FRANCE', ville: 'Paris', cp: '75015', nom: 'Martin',
            email: 'joel.martin@exemple.fr',
            consent: true,                      // consentement réellement recueilli
            reference: 'dossier-4417',
        ),
    ],
    content: Content::lettreDepuisFichiers(['/var/app/pdf/mise-en-demeure.pdf'], finalFilename: 'mise-en-demeure'),
    mode: ModeEnvoi::ERE_OTP_MAIL,
    designation: 'Mise en demeure',             // visible par le destinataire sur un ERE
);
```

`consent: true` est une **déclaration** : vous affirmez avoir recueilli le consentement du destinataire. Il n'est pas requis pour un destinataire professionnel.

## Token longue durée

Par défaut le token vit 24 h et le client le renouvelle tout seul. Sur une infrastructure sans état partagé — fonction serverless, conteneur éphémère — un token long évite un `getToken` par invocation :

```php
$mf = new Client(
    serviceId: getenv('MF_SERVICE_ID'),
    secretKey: getenv('MF_SECRET_KEY'),
    tokenStore: new FileTokenStore('/var/lib/monapp/mf-token.json'),
    tokenTimeLimit: 365, // jusqu'à un an
);
```

Un token de longue durée est un secret de longue durée : gestionnaire de secrets, jamais le dépôt de code. `renouvelleToken()` le révoque et en crée un nouveau.

## Gérer les échecs

Trois exceptions, trois conduites à tenir :

```php
use MerciFacteur\Api\Exception\{ValidationException, ApiException, TransportException};

try {
    $reponse = $mf->sendCourrier(...);
} catch (ValidationException $e) {
    // Refusé avant l'appel réseau. Rien n'est parti. Corrigez et rappelez.
} catch (ApiException $e) {
    // L'API a répondu et a refusé : aucun courrier créé, rejouable.
    // $e->getErrorCode() est stable, $e->getErrorText() ne l'est pas.
} catch (TransportException $e) {
    if ($e->isOutcomeUnknown()) {
        // Timeout PENDANT sendCourrier : le courrier a peut-être été créé.
        // Avec un antidoublon stable : rejouez à l'identique, l'API refusera le doublon.
        // Sans antidoublon : sortez l'envoi de la file et faites trancher un humain.
    }
}
```

Le client ne rejoue jamais tout seul un appel qui coûte de l'argent. Il renouvelle le token et rejoue une fois sur les opérations de lecture uniquement.

## Récupérer une preuve

Les webhooks `pdd` et `are` transportent déjà la preuve de dépôt et l'accusé de réception numérisé en base64 : `getProof` sert à la preuve de téléchargement d'un recommandé électronique, au rattrapage d'un webhook manqué, et à l'historique.

```php
use MerciFacteur\Api\Enum\ProofDocument;

$preuve = $mf->getProof($trackingNumber, ProofDocument::RECEPTION);
$chemin = $preuve->enregistrer('/var/app/preuves/dossier-4417-ar'); // .pdf ou .jpeg selon le retour
```

`$trackingNumber` est le numéro de suivi La Poste (`2C123456789`), présent dans les webhooks à partir de l'événement `printed` — ni l'`envoi_id`, ni la `ref_courrier`. Il vaut `null` en mode `normal`, qui ne produit aucune preuve.

## Vérifier un webhook

```php
use MerciFacteur\Api\Client;

if (!Client::webhookEstAuthentique(getenv('MF_WEBHOOK_SECRET'), $_SERVER['HTTP_X_MF_WEBHOOK_SECRET_KEY'] ?? null)) {
    http_response_code(403);
    exit;
}

$event = json_decode($_POST['event'], true);
$detail = json_decode($_POST['detail'], true);

// Répondez 200 tout de suite, traitez ensuite : un traitement long provoque
// un timeout, donc une relance, donc un doublon.
http_response_code(200);
```

Les webhooks partent de plusieurs IP : le filtrage par IP ne fonctionne pas. Merci Facteur réessaie deux fois en cas d'échec (à 60 minutes, puis 24 h), donc le traitement doit être idempotent — déduplication sur le couple (`name_event`, `ref_courrier`).

## Annuler

```php
$mf->deleteEnvoi($idEnvoi);
```

Un succès ne garantit pas que le courrier ne partira pas : l'annulation peut être rejetée, différée, ou partielle sur un envoi multi-destinataires. La seule fenêtre d'annulation réellement garantie consiste à **retarder l'appel à `sendCourrier`** côté application.

## Couverture

Les 27 endpoints de l'API 1.2 : `getToken`, `setUser`, `updateUser`, `getUserId`, `deleteUser`, `getCountry`, `setNewAdress`, `updateAdress`, `deleteAdress`, `listAdress`, `getAdressInfos`, `sendCourrier`, `getPostagePrice`, `deleteEnvoi`, `listEnvois`, `getEnvoi`, `getSuiviEnvoi`, `getProof`, `getLetterFinalFile`, `templatePublipostage`, `sourcePublipostage`, `sendPublipostage`, `openSavTicket`, `getQuotaCompte`, `setWebhookEndpoint`, `getWebhookEndpoint`, `listErrors`.

## Tester sans facturer

Il n'existe pas de mode test : un `sendCourrier` réussi imprime et facture. Demandez l'ouverture d'un compte **Sandbox** au service client pour vos tests d'intégration.

## Licence

MIT.
