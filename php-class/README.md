# Merci facteur API - Exemples de class PHP

> **Nouveau projet ?** Utilisez plutôt le [client PHP 8.1+](v2/) : namespace, Composer, exceptions typées, cache de token, validation des adresses avant l'appel, et les 27 endpoints de l'API.
> Cette classe-ci reste maintenue pour les intégrations existantes : aucune de ses signatures n'a changé.

Exemples d'utilisations de l'API Merci facteur en PHP :

### APPEL DE LA LIBRAIRIE
```php
require '/your/path/apiMf.class.php';

$serviceId = 'public-yourServiceID';

$apiMF = new apiMerciFacteur($serviceId);
```


### DEMANDER UN TOKEN POUR UNE OU PLUSIEURS ADRESSES IP

```php
//Votre clé secret, disponible dans votre compte Merci facteur Pro (à ne JAMAIS rendre publique)
$secret = 'secret-yourSecretKey';

//Liste des IP des serveurs à autoriser avec ce token
$ipArray = array('111.222.333','111.444.555');

//On demande un Access Token
$resGetAccessToken = $apiMF->getAccessToken($secret,$ipArray);

if($resGetAccessToken['success'])
{
    $accessToken = $resGetAccessToken['token'];
    $expire = $resGetAccessToken['expire'];
    
    //IMPORTANT :
    //Stocker le token et son expiration en local chez vous pour le réutiliser sans re-soliciter getAccessToken()
}
else
{
    var_dump($resGetAccessToken['error']); die();
}
```


### CREER UN NOUVEL UTILISATEUR

```php
//Informations de l'utilisateur à créer
//L'email vous permettra de reconnaître l'utilisateur, il doit être unique.
//Nous vous recommandons un email fictif de la forme : référence_client@votre_société.com
$userInfos = array('email'=>'123456@yourcompagny.com','firstName'=>'Roger','lastName'=>'Moore');

//On créé un nouvel utilisateur
$resSetNewUser = $apiMF->setNewUser($accessToken,$userInfos);

if($resSetNewUser['success'])
{
    $userId = $resSetNewUser['user_id'];
    
    //Stocker en local le userID en l'associant à cet utilisateur pour ne pas avoir besoin de le redemander à chaque opération sur cet utilisateur.
}
else
{
    var_dump($resSetNewUser['error']);
}
```



### RECUPERER L'ID D'UN UTILISATEUR A PARTIR DE SON ADRESSE EMAIL

```php
//Adresse email de l'utilisateur : 
$emailUser = '123456@yourcompagny.com';

//On récupère le user ID à partir d'un email
$resGetUserId = $apiMF->getUserId($accessToken,$emailUser);

if($resGetUserId['success'])
{
    $userId = $resGetUserId['user_id'];
    
    //Stocker en local le userID en l'associant à cet utilisateur pour ne pas avoir besoin de le redemander à chaque opération sur cet utilisateur.
}
else
{
    var_dump($resGetUserId['error']);
}
```



### MODIFIER LES INFORMATIONS D'UN UTILISATEUR

```php
//Informations de l'utilisateur à créer
//L'email vous permettra de reconnaître l'utilisateur, il doit être unique.
//Nous vous recommandons un email fictif de la forme : référence_client@votre_société.com
$userInfos = array('email'=>'789d101112@yourcompagny.com','firstName'=>'Michael','lastName'=>'Jordan');

//Id de l'user à modifier (stocké en local ou récupéré avec getUserId())
$userId = $userId;

//On met à jour un utilisateur
$resUpdateUser = $apiMF->updateUser($accessToken, $userId, $userInfos);

if(!$resUpdateUser['success'])
{
    var_dump($resUpdateUser['error']);
}
```




### SUPPRIMER UN UTILISATEUR

```php
//Id de l'user à supprimer (stocké en local ou récupéré avec getUserId())
$userId = 36;

//On supprime un utilisateur
$resDeleteUser = $apiMF->deleteUser($accessToken, $userId);

if(!$resDeleteUser['success'])
{
    var_dump($resDeleteUser['error']);
}
```



### RECUPERER LA LISTE DES PAYS, AVEC LEUR ORTHOGRAPHE CONFORME


```php
//Les zones géorgaphiques à récupérer : 
$zone = array('fr','om1','om2','z1','z2');

//On liste les pays disponibles
$resGetCountry = $apiMF->getCountry($accessToken,$zone);

if($resGetCountry['success'])
{
    $arrayCountry = $resGetCountry['country'];
    
    //Stocker en local le userID en l'associant à cet utilisateur pour ne pas avoir besoin de le redemander à chaque opération sur cet utilisateur.
}
else
{
    var_dump($resGetCountry['error']);
}
```
 




        
### CREER UNE NOUVELLE ADRESSE

```php
//User ID de l'utilisateur
$idUser = 37;

$type = 'dest';
        

//Informations de l'utilisateur à créer
//L'email vous permettra de reconnaître l'utilisateur, il doit être unique.
//Nous vous recommandons un email fictif de la forme : référence_client@votre_société.com
$arrayInfosAdress = array('logo'=>'','civilite'=>'','nom'=>'dfgfdfgsdfgdfdfg','prenom'=>'','societe'=>'sdfsdf','adresse1'=>'','adresse2'=>'','adresse3'=>'','cp'=>'sdfsdf','ville'=>'dfgdfgdsdffg','pays'=>'FRANCE');

//On créé une nouvelle adresse
$resSetNewAdress = $apiMF->setNewAdress($accessToken, $idUser, $type, $arrayInfosAdress);

if($resSetNewAdress['success'])
{
    $adressId = $resSetNewAdress['user_id'];
}
else
{
    var_dump($resSetNewAdress['error']);
}
```



        
### MODIFIER UNE ADRESSE EXISTANTE

```php
//Adress ID de l'adresse à modifier
$idAdress = 81;


//Informations de l'utilisateur à créer
//L'email vous permettra de reconnaître l'utilisateur, il doit être unique.
//Nous vous recommandons un email fictif de la forme : référence_client@votre_société.com
$arrayInfosAdress = array('logo'=>'','civilite'=>'Socié','nom'=>'dfgdfgdfg','prenom'=>'','societe'=>'','adresse1'=>'','adresse2'=>'','adresse3'=>'','cp'=>'sdfsdf','ville'=>'dfgdfgdfg','pays'=>'ESPAGNE');

//On met à jour une adresse
$resUpdateAdress = $apiMF->updateAdress($accessToken, $idAdress, $arrayInfosAdress);

if(!$resUpdateAdress['success'])
{
    var_dump($resUpdateAdress['error']);
}
```



### Lister les adresses d'un utilisateur

```php
//Adress ID de l'adresse à modifier
$idUser = 37;

// Type d'adresse à extraire : dest ou exp
$type = 'dest';

//On liste les adresses
$resListAdress = $apiMF->listAdress($accessToken, $idUser, $type);

if($resListAdress['success'])
{
    $arrayListAdress = $resListAdress['adress'];
    var_dump($arrayListAdress);
}
else
{
    var_dump($resListAdress['error']);
}
```



### Valider l'envoi d'un courrier composé d'une lettre

```php
//user ID de l'utilisateur qui envoi ce courrier
$idUser = 37;

// Les adresses du courrier - expéditeur et destinataire(s)
// Dans l'exemple ci-dessous, nous passons des ID d'adresses auparavant créées dans votre carnet d'adresses. Si vous ne souhaitez pas gérer un carnet d'adresse, vous pouvez ici envoyer directement des JSON avec les infos des adresses. Plus d'explications ici : https://github.com/MerciFacteur/Merci-facteur-API/blob/master/README.md#infos_adresses
$adress = array('exp'=>85,'dest'=>array(83,84,86,87));


//Les fichiers PDF à imprimer et à poster
$infosLetter = array('files'=>array('https://your-website.com/url-file-1.pdf', 'https://your-website.com/url-file-2.pdf','https://your-website.com/url-file-3.pdf'));

//pas de carte dans ce courrier : 
$infosCard = null;

//pas de photo dans ce courrier : 
$infosPhoto = null;

//Le mode d'envoi du ou des courriers (lrar|suivi|normal)
$modeEnvoi = 'normal';

//On créé un nouvel utilisateur
$sendCourrier = $apiMF->sendCourrier($accessToken, $idUser, $adress, $infosLetter, $infosCard, $infosPhoto, $modeEnvoi);

if($sendCourrier['success'])
{
    // Id de l'envoi (un envoi peut être composé de plusieurs courriers)
    $envoi_id = $sendCourrier['envoi_id'];
    
    //array du résumé du prix facturé par Merci facteur
    $resume_prix = $sendCourrier['price'];
    
    // résumé du contenu du/des courrier(s)
    $resume_contenu = $sendCourrier['resume'];
}
else
{ 
    echo '<pre>';var_dump($sendCourrier['error']);echo '<pre>';
}
```

#### Les options facultatives de sendCourrier

Un 8ème paramètre facultatif permet de passer les options de l'envoi. Les appels existants à 7 paramètres restent valides.

```php
$options = array(
    'print_sides' => 'rectoverso',          // recto|rectoverso|distinctrectoverso
    'final_filename' => 'contrat-2026',     // 50 caractères max, sans extension
    'dateEnvoi' => '2026-10-15',            // date complète et non passée, ou clé absente
    'designation' => 'Contrat client 4417', // 50 car. ; visible par le destinataire sur un ERE
    'antidoublon' => 'facture-4417-relance-1',
    'gestionNpai' => 1,                     // modes normal et suivi uniquement
    'anonymize' => array('delay'=>15, 'target'=>array('content','exp','dest')),
    'enveloppe' => array('type'=>'template', 'value'=>'123456'),
);

$sendCourrier = $apiMF->sendCourrier($accessToken, $idUser, $adress, $infosLetter, $infosCard, $infosPhoto, $modeEnvoi, $options);
```

**`antidoublon` est l'option qui décide de votre stratégie de reprise.** Elle doit identifier le courrier, pas la tentative : la même valeur d'un essai à l'autre. Si un `sendCourrier` échoue par timeout, l'issue est indéterminée — le courrier a pu être créé côté Merci Facteur et la réponse se perdre au retour. Avec un `antidoublon` stable, vous pouvez rejouer l'appel à l'identique : le second sera refusé si le premier était passé. Sans lui, ne réessayez jamais automatiquement.

### Annuler un envoi

```php
$delete = $apiMF->deleteEnvoi($accessToken, $envoi_id);
```

Opération irrémédiable, et un `success` ne garantit pas que le courrier ne partira pas : selon l'avancement, l'annulation peut être rejetée, différée, ou partielle sur un envoi multi-destinataires. Contrôlez l'état réel avec `getSuiviEnvoi()` et les webhooks.

### Récupérer une preuve (preuve de dépôt, avis de réception, preuve de téléchargement)

```php
// $trackingNumber est le numéro de suivi La Poste (ex. 2C123456789), transmis par les
// webhooks à partir de l'événement "printed". Ce n'est ni l'envoi_id, ni la ref_courrier.
$proof = $apiMF->getProof($accessToken, $trackingNumber, 'reception'); // depot|reception|telechargement

if($proof['success'])
{
    // Le format n'est pas toujours PDF : un AR numérisé revient en JPEG.
    $extension = $proof['format_return'];
    file_put_contents('preuve-'.$trackingNumber.'.'.$extension, base64_decode($proof['document']));
}
```

Les webhooks `pdd` et `are` transportent déjà ces documents en base64 : `getProof()` sert surtout à la preuve de téléchargement d'un recommandé électronique, au rattrapage d'un webhook manqué et à l'historique.

### Vérifier l'origine d'un webhook

```php
if(!$apiMF->checkWebhookSecretKey(getenv('MF_WEBHOOK_SECRET'), $_SERVER['HTTP_X_MF_WEBHOOK_SECRET_KEY']))
{
    http_response_code(403); exit;
}
```

Le filtrage par IP ne fonctionne pas : les webhooks partent de plusieurs adresses.

### Publipostage

```php
// Phase 1 : le template docx
$template = $apiMF->templatePublipostage($accessToken, 'https://your-website.com/modele.docx', 'file');

// À contrôler avant d'aller plus loin : nombre de pages et champs de fusion détectés
$nbPages = $template['templateValidation']['nbPage'];
$champs  = $template['templateValidation']['inputs'];

// Phase 2 : la source de données (file, base64 ou json)
$source = $apiMF->sourcePublipostage($accessToken, $idUser, $template['templateValidation'], 'json', array(
    array('civilite'=>'M.','nom'=>'Dupont','prenom'=>'Michel','societe'=>'','adresse1'=>'3 rue des fleurs','adresse2'=>'','adresse3'=>'','cp'=>'75015','ville'=>'Paris','pays'=>'FRANCE'),
));

// Phase 3 : validation — à partir d'ici, les courriers sont produits et facturés
$envoi = $apiMF->sendPublipostage($accessToken, $source['idEnvoi'], 85, 'suivi');
```

Cette phase 3 peut aussi être déclenchée à la main depuis l'interface Merci facteur Pro, ce qui permet de vérifier visuellement un échantillon de lettres fusionnées.

### Les autres méthodes disponibles

```php
$apiMF->deleteAdress($accessToken, $idAdress);
$apiMF->getAdressInfos($accessToken, array(123, 456));            // 50 adresses max par appel
$apiMF->getPostagePrice($accessToken, 'suivi', array('paysDestinataire'=>array('FRANCE'), 'letterPageNumber'=>2));
$apiMF->getLetterFinalFile($accessToken, '1234-5678');            // URL signée, valable 5 minutes
$apiMF->openSavTicket($accessToken, array('yourServiceName'=>'Monsite.com', 'email'=>'client@exemple.fr', 'sujet'=>'...', 'messageTexte'=>'...', 'referenceCourrier'=>'1234-5678'));
$apiMF->getQuotaCompte($accessToken);
$apiMF->setWebhookEndpoint($accessToken, 'https://votre-site.fr/webhook-mf');
$apiMF->getWebhookEndpoint($accessToken);
$apiMF->listErrors($accessToken);                                 // catalogue des codes d'erreur
```

### Timeouts

Les appels sont bornés par défaut à 10 secondes de connexion et 120 secondes au total. Ajustez si nécessaire :

```php
$apiMF->connectTimeout = 5;
$apiMF->timeout = 60;
```

### Valider l'envoi d'un courrier composé d'une carte illustrée (format carte postale, sans enveloppe)

```php
//user ID de l'utilisateur qui envoi ce courrier
$idUser = 37;

// Les adresses du courrier - expéditeur et destinataire(s)
$adress = array('exp'=>85,'dest'=>array(83,84,86,87));

//Pas de lettre dans ce courrier
$infosLetter = null;

//pas de carte dans ce courrier : 
$infosCard = array('format'=>'naked-postcard', 
                    'imgUrl'=>'https://mysite/doc/img.jpeg', 
                    'htmlText'=>'<div align="center">Bonjour !</div>');

//Le mode d'envoi du ou des courriers (lrar|suivi|normal)
$modeEnvoi = 'normal';

//On créé un nouvel utilisateur
$sendCourrier = $apiMF->sendCourrier($accessToken, $idUser, $adress, $infosLetter, $infosCard, $modeEnvoi);

if($sendCourrier['success'])
{
    // Id de l'envoi (un envoi peut être composé de plusieurs courriers)
    $envoi_id = $sendCourrier['envoi_id'];
    
    //array du résumé du prix facturé par Merci facteur
    $resume_prix = $sendCourrier['price'];
    
    // résumé du contenu du/des courrier(s)
    $resume_contenu = $sendCourrier['resume'];
}
else
{ 
    echo '<pre>';var_dump($sendCourrier['error']);echo '<pre>';
}
```








### Lister les 50 derniers envois d'un utilisateur

```php
//user ID de l'utilisateur
$idUser = 37;

$listEnvois = $apiMF->listEnvois($accessToken, $idUser);
        
if($listEnvois['success'])
{
     $envois = $listEnvois['envois'];
}
else
{ 
    echo '<pre>';var_dump($listEnvois['error']);echo '<pre>';
}
 ```



### Lister les courriers et les infos d'un envoi en particulier

```php
//ID de l'envoi
$idEnvoi = 128;

$getEnvoi = $apiMF->getEnvoi($accessToken, $idEnvoi);
        
if($getEnvoi['success'])
{
    echo '<pre>';var_dump($getEnvoi['envoi']);echo '<pre>';
    
     $getEnvoi = $getEnvoi['envoi'];
}
else
{ 
    echo '<pre>';var_dump($getEnvoi['error']);echo '<pre>';
}
```


### Obtenir le suivi des courriers d'un envoi en particulier

```php
//ID de l'envoi
$idEnvoi = 128;

$getSuiviEnvoi = $apiMF->getSuiviEnvoi($accessToken, $idEnvoi);
        
if($getSuiviEnvoi['success'])
{
    echo '<pre>';var_dump($getSuiviEnvoi['envoi']);echo '<pre>';
}
else
{ 
    echo '<pre>';var_dump($getEnvoi['error']);echo '<pre>';
}
```
