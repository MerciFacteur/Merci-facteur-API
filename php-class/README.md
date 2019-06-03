# Merci facteur API - Exemples de class PHP

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
$adress = array('exp'=>85,'dest'=>array(83,84,86,87));


//Les fichiers PDF à imprimer et à poster
$infosLetter = array('files'=>array('https://your-website.com/url-file-1.pdf', ''https://your-website.com/url-file-2.pdf',''https://your-website.com/url-file-3.pdf'));

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
