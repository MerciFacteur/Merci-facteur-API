<?php

declare(strict_types=1);

namespace MerciFacteur\Api;

use MerciFacteur\Api\Enum\AddressType;
use MerciFacteur\Api\Enum\AnonymizeTarget;
use MerciFacteur\Api\Enum\CardCorner;
use MerciFacteur\Api\Enum\CardFormat;
use MerciFacteur\Api\Enum\CardPaper;
use MerciFacteur\Api\Enum\ModeEnvoi;
use MerciFacteur\Api\Enum\PrintSides;
use MerciFacteur\Api\Enum\ProofDocument;
use MerciFacteur\Api\Enum\UserRole;
use MerciFacteur\Api\Enum\Zone;
use MerciFacteur\Api\Exception\ApiException;
use MerciFacteur\Api\Exception\AuthenticationException;
use MerciFacteur\Api\Exception\TransportException;
use MerciFacteur\Api\Exception\ValidationException;
use MerciFacteur\Api\Http\CurlTransport;
use MerciFacteur\Api\Token\InMemoryTokenStore;
use MerciFacteur\Api\Token\TokenStore;

/**
 * Client de l'API Merci Facteur 1.2.
 *
 * La secret key ne doit jamais atteindre un navigateur ni une application
 * mobile : ce client s'utilise depuis un composant serveur, et les identifiants
 * vivent dans des variables d'environnement ou un gestionnaire de secrets.
 *
 * ```php
 * $mf = new Client(getenv('MF_SERVICE_ID'), getenv('MF_SECRET_KEY'));
 *
 * $reponse = $mf->sendCourrier(
 *     idUser: (int) getenv('MF_USER_ID'),
 *     expediteur: new Address(pays: 'FRANCE', ville: 'Versailles', cp: '78000', societe: 'Dupont Corp.'),
 *     destinataires: [new Address(pays: 'FRANCE', ville: 'Paris', cp: '75015', nom: 'Martin', reference: 'facture-4417')],
 *     content: Content::lettreDepuisFichiers(['/tmp/facture.pdf']),
 *     mode: ModeEnvoi::SUIVI,
 *     antidoublon: 'facture-4417-relance-1',
 * );
 * ```
 */
final class Client
{
    public const BASE_URL = 'https://www.merci-facteur.com/api/1.2/prod/service';

    private TokenStore $tokenStore;
    private CurlTransport $transport;

    /**
     * @param list<string> $authorizedIps IP autorisées à utiliser le token.
     *                                    Sur une infrastructure sans IP fixe,
     *                                    demandez la levée de la restriction au
     *                                    service technique et laissez la valeur
     *                                    par défaut.
     * @param int|null     $tokenTimeLimit Durée de vie du token en tranches de
     *                                     24 h (1 à 365). null = 24 h.
     */
    public function __construct(
        private readonly string $serviceId,
        private readonly string $secretKey,
        private readonly array $authorizedIps = ['111.111.111'],
        ?TokenStore $tokenStore = null,
        ?CurlTransport $transport = null,
        private readonly ?int $tokenTimeLimit = null,
        private readonly string $baseUrl = self::BASE_URL,
    ) {
        if (trim($this->serviceId) === '' || trim($this->secretKey) === '') {
            throw new ValidationException('Client : serviceId et secretKey sont obligatoires.');
        }
        if ($this->tokenTimeLimit !== null && ($this->tokenTimeLimit < 1 || $this->tokenTimeLimit > 365)) {
            throw new ValidationException('Client : tokenTimeLimit doit être compris entre 1 et 365.');
        }

        $this->tokenStore = $tokenStore ?? new InMemoryTokenStore();
        $this->transport = $transport ?? new CurlTransport();
    }

    // ---------------------------------------------------------------- Token

    /**
     * Retourne un access token valide, depuis le cache ou depuis l'API.
     */
    public function token(): string
    {
        $cache = $this->tokenStore->get();
        if ($cache !== null) {
            return $cache;
        }

        return $this->demandeToken();
    }

    /**
     * Force la création d'un nouveau token et invalide le précédent.
     *
     * À utiliser pour une rotation de sécurité, ou si le token a fuité.
     */
    public function renouvelleToken(?int $timeLimit = null): string
    {
        $this->tokenStore->forget();

        return $this->demandeToken(force: 'renewal', timeLimit: $timeLimit);
    }

    private function demandeToken(?string $force = null, ?int $timeLimit = null): string
    {
        $timeLimit ??= $this->tokenTimeLimit;

        // La signature n'est valable que 5 minutes : elle se calcule ici, juste
        // avant l'appel, et le timestamp signé est celui de l'en-tête.
        $ts = time();
        $signature = hash_hmac('sha256', $this->serviceId . $ts, $this->secretKey);

        $headers = [
            'ww-service-signature' => $signature,
            'ww-timestamp' => (string) $ts,
            'ww-service-id' => $this->serviceId,
            'ww-authorized-ip' => implode(';', $this->authorizedIps),
        ];

        $form = null;
        $methode = 'GET';
        if ($force !== null || $timeLimit !== null) {
            $methode = 'POST';
            $form = [];
            if ($timeLimit !== null) {
                $form['timeLimit'] = $timeLimit;
            }
            if ($force !== null) {
                $form['force'] = $force;
            }
        }

        $http = $this->transport->request($methode, $this->baseUrl . '/getToken', $headers, $form, 'getToken');
        $reponse = $http->json('getToken', false);

        $this->verifieSucces($reponse, 'getToken', $http->status);

        $token = isset($reponse['token']) ? (string) $reponse['token'] : '';
        if ($token === '') {
            throw new TransportException('getToken : réponse sans token.', 'getToken', false);
        }

        $expire = isset($reponse['expire']) ? (int) $reponse['expire'] : time() + 86400;
        $this->tokenStore->save($token, $expire);

        return $token;
    }

    // -------------------------------------------------------------- Utilisateurs

    /**
     * Crée un utilisateur et retourne son user ID.
     *
     * Un seul utilisateur suffit dans la plupart des intégrations. Plusieurs
     * utilisateurs servent à séparer les historiques d'envoi (une copropriété
     * par utilisateur chez un syndic, par exemple).
     *
     * @param string $email Doit être unique. Peut être fictif : ref-4417@monapp.fr
     */
    public function createUser(
        string $email,
        string $firstName = '',
        string $lastName = '',
        ?UserRole $role = null,
    ): int {
        $form = ['emailUser' => $email, 'first_name' => $firstName, 'last_name' => $lastName];
        if ($role !== null) {
            $form['role'] = $role->value;
        }

        $reponse = $this->call('POST', '/setUser', form: $form);

        return (int) ($reponse['user_id'] ?? 0);
    }

    /** Toutes les informations doivent être renvoyées, même celles qui ne changent pas. */
    public function updateUser(int $idUser, string $email, string $firstName = '', string $lastName = ''): void
    {
        $this->call('POST', '/updateUser', query: ['idUser' => $idUser], form: [
            'emailUser' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }

    public function getUserId(string $email): int
    {
        $reponse = $this->call('GET', '/getUserId', query: ['emailUser' => $email]);

        return (int) ($reponse['user_id'] ?? 0);
    }

    /** Opération irrémédiable. */
    public function deleteUser(int $idUser): void
    {
        $this->call('DELETE', '/deleteUser', query: ['idUser' => $idUser]);
    }

    // ------------------------------------------------------------------ Adresses

    /**
     * Liste des libellés de pays acceptés, pour les zones demandées.
     *
     * C'est la source qui fait autorité : le champ `pays` n'accepte aucune autre
     * orthographe, et cette liste ne périme pas.
     *
     * @return array<int|string, mixed>
     */
    public function getCountries(Zone ...$zones): array
    {
        $zones = $zones === [] ? [Zone::FR] : $zones;
        $query = ['zone' => array_map(static fn (Zone $z): string => $z->value, $zones)];

        $reponse = $this->call('GET', '/getCountry', query: $query);

        return \is_array($reponse['country'] ?? null) ? $reponse['country'] : [];
    }

    /** Crée une adresse dans le carnet d'adresses et retourne son ID. */
    public function createAddress(int $idUser, AddressType $type, Address $adresse): int
    {
        $reponse = $this->call(
            'POST',
            '/setNewAdress',
            query: ['idUser' => $idUser, 'type' => $type->value],
            form: ['adress' => $this->encodeJson($adresse->toBookArray())],
        );

        return (int) ($reponse['adress_id'] ?? 0);
    }

    public function updateAddress(int $idAdress, Address $adresse): void
    {
        $this->call(
            'POST',
            '/updateAdress',
            query: ['idAdress' => $idAdress],
            form: ['adress' => $this->encodeJson($adresse->toBookArray())],
        );
    }

    public function deleteAddress(int $idAdress): void
    {
        $this->call('DELETE', '/deleteAdress', query: ['idAdress' => $idAdress]);
    }

    /**
     * Adresses d'un utilisateur, limité à 500.
     *
     * @param array<string, string> $recherche ex. ['nom' => 'Dupont', 'ville' => 'paris']
     *
     * @return array<int, mixed>
     */
    public function listAddresses(int $idUser, AddressType $type, array $recherche = []): array
    {
        $query = ['idUser' => $idUser, 'type' => $type->value];
        if ($recherche !== []) {
            $query['search'] = $this->encodeJson($recherche);
        }

        $reponse = $this->call('GET', '/listAdress', query: $query);

        return \is_array($reponse['adress'] ?? null) ? $reponse['adress'] : [];
    }

    /**
     * Détail d'adresses par leurs ID, 50 maximum par appel.
     *
     * @param list<int> $idsAdresses
     *
     * @return array<int|string, mixed>
     */
    public function getAddressInfos(array $idsAdresses): array
    {
        if ($idsAdresses === []) {
            throw new ValidationException('getAddressInfos : au moins un ID est nécessaire.');
        }
        if (count($idsAdresses) > 50) {
            throw new ValidationException('getAddressInfos : 50 adresses maximum par appel.');
        }

        $reponse = $this->call('GET', '/getAdressInfos', query: ['idAdress' => array_values($idsAdresses)]);

        return \is_array($reponse['adress'] ?? null) ? $reponse['adress'] : [];
    }

    // -------------------------------------------------------------------- Envoi

    /**
     * Envoie un courrier.
     *
     * ATTENTION : un appel réussi imprime, poste et facture un objet physique
     * irrécupérable. En cas de timeout, l'issue est indéterminée — voir
     * TransportException et le paramètre $antidoublon.
     *
     * @param Address|int                      $expediteur    Une adresse, ou l'ID d'une adresse du carnet
     * @param list<Address|int|array{0:int,1:string}> $destinataires Adresses, ID, ou [ID, référence interne]
     * @param string|null                      $dateEnvoi     AAAA-MM-JJ, date non passée
     * @param string|null                      $designation   50 caractères max ; visible par le destinataire sur un ERE
     * @param string|null                      $antidoublon   Référence STABLE entre deux tentatives du MÊME courrier.
     *                                                        Un UUID régénéré à chaque essai ne protège de rien.
     * @param bool                             $gestionNpai   Modes normal et suivi : fait revenir et numériser les plis non distribués
     * @param array{delay:int,target:list<AnonymizeTarget>}|null $anonymize
     * @param string|null                      $enveloppeId   ID d'une enveloppe personnalisée créée dans « Votre branding »
     *
     * @return array<string, mixed> Contient notamment envoi_id, price et resume
     */
    public function sendCourrier(
        int $idUser,
        Address|int $expediteur,
        array $destinataires,
        Content $content,
        ModeEnvoi $mode = ModeEnvoi::NORMAL,
        ?string $dateEnvoi = null,
        ?string $designation = null,
        ?string $antidoublon = null,
        bool $gestionNpai = false,
        ?array $anonymize = null,
        ?string $enveloppeId = null,
    ): array {
        if ($destinataires === []) {
            throw new ValidationException('sendCourrier : au moins un destinataire est nécessaire.');
        }

        $form = [
            'idUser' => $idUser,
            'modeEnvoi' => $mode->value,
            'adress' => $this->encodeJson([
                'exp' => $this->adressePourEnvoi($expediteur, $mode, false),
                'dest' => array_map(
                    fn (Address|int|array $d): mixed => $this->adressePourEnvoi($d, $mode, true),
                    array_values($destinataires),
                ),
            ]),
            'content' => $this->encodeJson($content->toArray()),
        ];

        // Une date tronquée ou vide déclenche INVALID_DATE_ENVOI : le champ doit
        // être complet, ou totalement absent.
        if ($dateEnvoi !== null) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnvoi) !== 1) {
                throw new ValidationException('sendCourrier : dateEnvoi doit être au format AAAA-MM-JJ.');
            }
            if ($dateEnvoi < date('Y-m-d')) {
                throw new ValidationException('sendCourrier : dateEnvoi ne peut pas être une date passée.');
            }
            $form['dateEnvoi'] = $dateEnvoi;
        }

        if ($designation !== null && $designation !== '') {
            if (mb_strlen($designation) > 50) {
                throw new ValidationException('sendCourrier : designation est limitée à 50 caractères.');
            }
            $form['designation'] = $designation;
        }

        if ($antidoublon !== null && $antidoublon !== '') {
            if (mb_strlen($antidoublon) > 200) {
                throw new ValidationException('sendCourrier : antidoublon est limité à 200 caractères.');
            }
            $form['antidoublon'] = $antidoublon;
        }

        if ($gestionNpai) {
            if ($mode === ModeEnvoi::LRAR || $mode === ModeEnvoi::LRARE || $mode->isElectronique()) {
                throw new ValidationException(
                    'gestionNpai concerne les modes normal et suivi. Pour un recommandé, '
                    . 'la fonction équivalente est le mode lrare.'
                );
            }
            $form['gestionNpai'] = 1;
        }

        if ($anonymize !== null) {
            $form['anonymize'] = $this->encodeJson($this->normaliseAnonymize($anonymize));
        }

        if ($enveloppeId !== null && $enveloppeId !== '') {
            $form['enveloppe'] = $this->encodeJson(['type' => 'template', 'value' => $enveloppeId]);
        }

        return $this->call('POST', '/sendCourrier', form: $form, effetDeBord: true);
    }

    /**
     * Prix d'un envoi, avant de l'envoyer.
     *
     * @param list<string> $paysDestinataires Libellés conformes (cf. getCountries)
     * @param list<int>    $idsDestinataires  Alternative : les ID du carnet d'adresses
     *
     * @return array<string, mixed>
     */
    public function getPostagePrice(
        ModeEnvoi $mode,
        array $paysDestinataires = [],
        array $idsDestinataires = [],
        int $letterPageNumber = 0,
        int $photoNumber = 0,
        ?PrintSides $letterPrintSides = null,
        ?CardFormat $cardFormat = null,
        ?CardPaper $cardPapier = null,
        ?CardCorner $cardCoin = null,
    ): array {
        if ($paysDestinataires === [] && $idsDestinataires === []) {
            throw new ValidationException(
                'getPostagePrice : indiquez soit paysDestinataires, soit idsDestinataires.'
            );
        }

        $query = [
            'modeEnvoi' => [$mode->value],
            'letterPageNumber' => $letterPageNumber,
            'photoNumber' => $photoNumber,
            'cardFormat' => [$cardFormat?->value ?? ''],
            'cardPapier' => [$cardPapier?->value ?? ''],
            'cardCoin' => [$cardCoin?->value ?? ''],
        ];

        if ($paysDestinataires !== []) {
            $query['paysDestinataire'] = array_values($paysDestinataires);
        }
        if ($idsDestinataires !== []) {
            $query['idDestinataire'] = array_values($idsDestinataires);
        }
        if ($letterPrintSides !== null) {
            $query['letterPrintSides'] = $letterPrintSides->value;
        }

        return $this->call('GET', '/getPostagePrice', query: $query);
    }

    /**
     * Annule un envoi. Opération irrémédiable.
     *
     * `success: true` ne signifie pas « le courrier ne partira pas » : selon
     * l'avancement, l'annulation peut être rejetée, différée, ou partielle sur
     * un envoi multi-destinataires. Vérifiez l'état réel avec getSuiviEnvoi et
     * les webhooks plutôt que de le déduire de cet appel.
     *
     * La seule fenêtre d'annulation garantie consiste à retarder l'appel à
     * sendCourrier côté application.
     */
    public function deleteEnvoi(int $idEnvoi): void
    {
        $this->call('DELETE', '/deleteEnvoi', query: ['idEnvoi' => $idEnvoi], effetDeBord: true);
    }

    /** @return array<int, mixed> Les 50 derniers envois d'un utilisateur. */
    public function listEnvois(int $idUser): array
    {
        $reponse = $this->call('GET', '/listEnvois', query: ['idUser' => $idUser]);

        return \is_array($reponse['envois'] ?? null) ? $reponse['envois'] : [];
    }

    /** @return array<int|string, mixed> */
    public function getEnvoi(int $idEnvoi): array
    {
        $reponse = $this->call('GET', '/getEnvoi', query: ['idEnvoi' => $idEnvoi]);

        return \is_array($reponse['envoi'] ?? null) ? $reponse['envoi'] : [];
    }

    /**
     * Suivi des courriers d'un envoi.
     *
     * N'en faites pas une boucle de polling : les webhooks poussent chaque
     * étape, y compris les preuves. Cet appel sert au rattrapage et au debug.
     *
     * @return array<string, mixed>
     */
    public function getSuiviEnvoi(int $idEnvoi): array
    {
        return $this->call('GET', '/getSuiviEnvoi', query: ['idEnvoi' => $idEnvoi]);
    }

    /**
     * Récupère une preuve.
     *
     * $trackingNumber est le numéro de suivi La Poste (2C123456789), présent
     * dans les webhooks à partir de l'événement `printed` — pas l'envoi_id, ni
     * la ref_courrier. Il vaut null pour un envoi en mode normal, qui ne produit
     * aucune preuve.
     *
     * Les webhooks `pdd` et `are` transportent déjà la preuve de dépôt et l'AR
     * numérisé : cet appel sert à la preuve de téléchargement d'un ERE, au
     * rattrapage d'un webhook manqué et à l'historique.
     */
    public function getProof(string $trackingNumber, ProofDocument $document): Proof
    {
        if (trim($trackingNumber) === '') {
            throw new ValidationException('getProof : trackingNumber est obligatoire.');
        }

        $reponse = $this->call('GET', '/getProof', query: [
            'trackingNumber' => $trackingNumber,
            'document' => $document->value,
        ]);

        return new Proof(
            format: (string) ($reponse['format_return'] ?? ''),
            base64: (string) ($reponse['document'] ?? ''),
        );
    }

    /**
     * URL signée (5 minutes) du fichier final d'un courrier.
     *
     * @param string $referenceCourrier Référence Merci Facteur, de la forme 1234-5678
     *
     * @return array<int, mixed>
     */
    public function getLetterFinalFile(string $referenceCourrier): array
    {
        $reponse = $this->call('GET', '/getLetterFinalFile', query: ['referenceCourrier' => $referenceCourrier]);

        return \is_array($reponse['result'] ?? null) ? $reponse['result'] : [];
    }

    // -------------------------------------------------------------- Publipostage

    /**
     * Phase 1 : envoi du template docx.
     *
     * Contrôlez le `templateValidation` retourné (nombre de pages, champs de
     * fusion détectés) avant de passer à la phase 2, et transmettez-le ensuite
     * sans aucune modification.
     *
     * @param string $template   URL du docx, ou son contenu encodé en base64
     * @param bool   $estBase64  false = $template est une URL
     *
     * @return array<string, mixed> templateValidation
     */
    public function publipostageTemplate(string $template, bool $estBase64 = false): array
    {
        $reponse = $this->call('POST', '/templatePublipostage', form: [
            'typeTemplate' => $estBase64 ? 'base64' : 'file',
            'template' => $template,
        ]);

        return \is_array($reponse['templateValidation'] ?? null) ? $reponse['templateValidation'] : [];
    }

    /**
     * Phase 2 : envoi de la source de données.
     *
     * @param array<string, mixed>       $templateValidation Retourné par la phase 1, inchangé
     * @param 'file'|'base64'|'json'     $type
     * @param string|array<int, mixed>   $source             URL, base64, ou tableau de destinataires
     *
     * @return array{idEnvoi:int, sourceValidation:array<string,mixed>}
     */
    public function publipostageSource(
        int $idUser,
        array $templateValidation,
        string $type,
        string|array $source,
    ): array {
        if (!\in_array($type, ['file', 'base64', 'json'], true)) {
            throw new ValidationException('publipostageSource : type doit valoir file, base64 ou json.');
        }

        $reponse = $this->call('POST', '/sourcePublipostage', form: [
            'idUser' => $idUser,
            'templateValidation' => $this->encodeJson($templateValidation),
            'source' => $this->encodeJson([
                'type' => $type,
                'value' => $type === 'json' ? $source : (string) $source,
            ]),
        ]);

        return [
            'idEnvoi' => (int) ($reponse['idEnvoi'] ?? 0),
            'sourceValidation' => \is_array($reponse['sourceValidation'] ?? null)
                ? $reponse['sourceValidation']
                : [],
        ];
    }

    /**
     * Phase 3 : validation. Déclenche la fusion, l'impression et l'envoi.
     *
     * ATTENTION : à partir de cet appel, les courriers sont produits et
     * facturés. Cette phase peut aussi être faite à la main depuis l'interface
     * Merci Facteur Pro, ce qui permet de contrôler visuellement un échantillon.
     *
     * @param Address|int                                       $expediteur
     * @param array{delay:int,target:list<AnonymizeTarget>}|null $anonymize
     *
     * @return array<string, mixed>
     */
    public function publipostageSend(
        int $idEnvoi,
        Address|int $expediteur,
        ModeEnvoi $mode = ModeEnvoi::NORMAL,
        ?array $anonymize = null,
    ): array {
        if ($mode->isElectronique()) {
            throw new ValidationException('publipostageSend : le publipostage ne gère pas le recommandé électronique.');
        }

        $form = ['idEnvoi' => $idEnvoi, 'modeEnvoi' => $mode->value];

        if ($expediteur instanceof Address) {
            $expediteur->validate($mode, false);
            $form['jsonExp'] = $this->encodeJson($expediteur->toArray(false));
        } else {
            $form['idExp'] = $expediteur;
        }

        if ($anonymize !== null) {
            $form['anonymize'] = $this->encodeJson($this->normaliseAnonymize($anonymize));
        }

        return $this->call('POST', '/sendPublipostage', form: $form, effetDeBord: true);
    }

    // ----------------------------------------------------------------- SAV

    /**
     * Ouvre un ticket SAV sur un courrier.
     *
     * @return array<string, mixed> sav_id et sav_token
     */
    public function openSavTicket(
        string $nomDeVotreService,
        string $email,
        string $sujet,
        string $message,
        string $referenceCourrier = '',
    ): array {
        return $this->call('POST', '/openSavTicket', form: [
            'yourServiceName' => $nomDeVotreService,
            'email' => $email,
            'sujet' => $sujet,
            'messageTexte' => $message,
            'referenceCourrier' => $referenceCourrier,
        ]);
    }

    // --------------------------------------------------------------- Compte

    /** @return array<string, mixed> Plan, crédit et quota de pages du compte. */
    public function getQuota(): array
    {
        $reponse = $this->call('GET', '/getQuotaCompte');

        return \is_array($reponse['quotas'] ?? null) ? $reponse['quotas'] : [];
    }

    /** Définit l'URL de réception des webhooks. Une chaîne vide la supprime. */
    public function setWebhookEndpoint(string $url): void
    {
        $this->call('POST', '/setWebhookEndpoint', form: ['url' => $url]);
    }

    public function getWebhookEndpoint(): string
    {
        $reponse = $this->call('GET', '/getWebhookEndpoint');

        return (string) ($reponse['url'] ?? '');
    }

    /**
     * Catalogue des codes d'erreur et leur signification.
     *
     * @return array<string, mixed>
     */
    public function listErrors(): array
    {
        $reponse = $this->call('GET', '/listErrors');

        return \is_array($reponse['listErrors'] ?? null) ? $reponse['listErrors'] : [];
    }

    // -------------------------------------------------------------- Webhooks

    /**
     * Vérifie qu'un webhook vient bien de Merci Facteur.
     *
     * Les webhooks partent de plusieurs IP : le filtrage par IP ne fonctionne
     * pas. Merci Facteur envoie votre clé dans l'en-tête
     * X-Mf-Webhook-Secret-Key, à récupérer dans l'onglet « API » du compte.
     *
     * ```php
     * if (!Client::webhookEstAuthentique(getenv('MF_WEBHOOK_SECRET'), $_SERVER['HTTP_X_MF_WEBHOOK_SECRET_KEY'] ?? null)) {
     *     http_response_code(403);
     *     exit;
     * }
     * ```
     */
    public static function webhookEstAuthentique(string $cleAttendue, ?string $cleRecue): bool
    {
        if ($cleAttendue === '' || $cleRecue === null || $cleRecue === '') {
            return false;
        }

        return hash_equals($cleAttendue, $cleRecue);
    }

    // --------------------------------------------------------------- Interne

    /**
     * @param array<string, mixed>       $query
     * @param array<string, scalar>|null $form
     *
     * @return array<string, mixed>
     */
    private function call(
        string $method,
        string $endpoint,
        array $query = [],
        ?array $form = null,
        bool $effetDeBord = false,
        bool $reessaiAutorise = true,
    ): array {
        $url = $this->baseUrl . $endpoint;
        if ($query !== []) {
            $url .= '?' . $this->buildQuery($query);
        }

        $headers = [
            'ww-service-id' => $this->serviceId,
            'ww-access-token' => $this->token(),
        ];

        $http = $this->transport->request($method, $url, $headers, $form, $endpoint, $effetDeBord);
        $reponse = $http->json($endpoint, $effetDeBord);

        // Un token expiré se renouvelle et l'appel se rejoue — sauf sur une
        // opération à effet de bord, qu'on ne rejoue jamais automatiquement.
        if ($http->status === 401 && $reessaiAutorise && !$effetDeBord) {
            $this->tokenStore->forget();

            return $this->call($method, $endpoint, $query, $form, $effetDeBord, reessaiAutorise: false);
        }

        $this->verifieSucces($reponse, $endpoint, $http->status);

        return $reponse;
    }

    /**
     * @param array<string, mixed> $reponse
     *
     * @throws ApiException
     */
    private function verifieSucces(array $reponse, string $endpoint, int $status): void
    {
        if (($reponse['success'] ?? false) === true) {
            return;
        }

        $erreur = $reponse['error'] ?? null;
        $code = 'UNKNOWN_ERROR';
        $texte = '';

        if (\is_array($erreur)) {
            $code = (string) ($erreur['code'] ?? $code);
            $texte = (string) ($erreur['text'] ?? '');
        } elseif (\is_string($erreur) && $erreur !== '') {
            $code = $erreur;
        }

        if ($status === 401) {
            throw new AuthenticationException($code, $texte, $status, $endpoint, $reponse);
        }

        throw new ApiException($code, $texte, $status, $endpoint, $reponse);
    }

    /**
     * Une adresse d'envoi : objet validé, ID du carnet, ou [ID, référence].
     *
     * @param Address|int|array{0:int,1:string} $adresse
     *
     * @return array<string, mixed>|int|array{0:int,1:string}
     */
    private function adressePourEnvoi(Address|int|array $adresse, ModeEnvoi $mode, bool $estDestinataire): array|int
    {
        if ($adresse instanceof Address) {
            $adresse->validate($mode, $estDestinataire);

            return $adresse->toArray($estDestinataire);
        }

        if (\is_array($adresse)) {
            if (!$estDestinataire) {
                throw new ValidationException("L'expéditeur ne peut pas porter de référence interne.");
            }
            if (!isset($adresse[0]) || !\is_int($adresse[0])) {
                throw new ValidationException('Destinataire : la forme attendue est [idAdresse, "référence"].');
            }

            return [$adresse[0], (string) ($adresse[1] ?? '')];
        }

        return $adresse;
    }

    /**
     * @param array{delay?:int,target?:list<AnonymizeTarget|string>} $anonymize
     *
     * @return array{delay:int,target:list<string>}
     */
    private function normaliseAnonymize(array $anonymize): array
    {
        $delay = (int) ($anonymize['delay'] ?? 0);
        if ($delay < 1 || $delay > 40) {
            throw new ValidationException('anonymize : delay doit être compris entre 1 et 40 jours.');
        }

        $cibles = [];
        foreach ($anonymize['target'] ?? [] as $cible) {
            $cibles[] = $cible instanceof AnonymizeTarget ? $cible->value : (string) $cible;
        }

        if ($cibles === []) {
            throw new ValidationException('anonymize : indiquez au moins une cible (content, exp, dest).');
        }

        return ['delay' => $delay, 'target' => $cibles];
    }

    /**
     * Query string avec des tableaux en `champ[]=` plutôt qu'en `champ[0]=`,
     * qui est la forme attendue par l'API (zone[], idAdress[], paysDestinataire[]).
     *
     * @param array<string, mixed> $query
     */
    private function buildQuery(array $query): string
    {
        $qs = http_build_query($query);

        return preg_replace('/%5B\d+%5D=/', '%5B%5D=', $qs) ?? $qs;
    }

    /** @param array<int|string, mixed> $valeur */
    private function encodeJson(array $valeur): string
    {
        return json_encode($valeur, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
