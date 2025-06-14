# API envoi de courrier par La Poste
## merci-facteur-api
## API de Merci facteur

Version : 1.2.7

L'API d'envoi de courrier proposé par Merci facteur vous permet d'intégrer à votre applicatif l'envoi de courriers par La Poste via API. 

La version actuelle de l'API d'envoi de courrier permet de :
- Créer et gérer des utilisateurs ;
- Gérer les adresses de destinataire et d'expéditeur ;
- Créer un carnet d'adresses réutilisables ;
- Envoyer des lettres via des fichiers PDF (impression recto ou recto-verso) ;
- Envoyer des cartes illustrées (4 formats : cartes postales, cartes classiques, cartes géantes, etc.) ;
- Envoyer des photos (format 10x15 cm, imprimées sur papier brillant) ;
- Envoyer des lettres en suivi, recommandé avec avis de réception et envoi normal ;
- Envoyer des recommandés électroniques ;
- Gérer les courriers envoyés ; 
- Suivre les courriers envoyés ; 
- Générer et envoyer des publipostages ;
- Ouvrir un ticket SAV au sujet d'un courrier envoyé ;
- Envoyer des documents agrafés ou reliés ;
- Anonymiser un courrier après son envoi ;
- Envoyer des courriers dans des enveloppes personnalisées ;
- Confier la gestion des NPAI à Merci Facteur ;


Les courriers envoyés via l'API d'envoi de courriers sont imprimés et postés le jour même, comme n'importe quel courrier envoyé via Merci facteur. Ils sont facturés au même prix qu'un courrier envoyé depuis l'interface Merci facteur PRO.

Documentation : https://www.merci-facteur.com/api/1.2/doc.php

En savoir plus : https://www.merci-facteur.com


## Sommaire : 

- [Processus de base d'un envoi](#processus_base)
- [Caractérisation d'un utilisateur](#caracterisation_utilisateur) 
- [Caractérisation d'un envoi](#caracterisation_envoi)  
- [Création du token](#creation_token) 
- [Date d'envoi des courriers](#date_envoi) 
- [Gestion des NPAI (ou PND / plis non distribués)](#npai) 
- [Enveloppes et branding (personnalisation d'enveloppe](#branding) 
- [Les adresses de destinataires et d'expéditeur](#infos_adresses) 
- [Le mode d'envoi](#mode_envoi) 
- [Anonymisation de courrier](#anonymisation) 
- [Anti-doublon](#antidoublon) 
- [Ajouter des références internes sur les courriers](#ref_interne) 
- [Envoyer une lettre recto-verso](#rectoverso) 
- [API envoi de recommandé électronique](#envoi_ere) 
- [API envoi de lettres](#envoi_lettre) 
- [API envoi de cartes](#envoi_cartes) 
- [API envoi de photos](#envoi_photo) 
- [API de publipostage](#publipostage) 
- [Webhooks](#webhooks) 

<a id="processus_base"></a>
## Processus de base d'un envoi (hors publipostage)

Pour réaliser un envoi de courrier, quelques étapes sont nécessaires au préalable : 

Création d'un utilisateur (facultatif) -> Création/sélection d'une adresse d'expéditeur -> Création/sélection d'une adresse de destinataire -> Génération de l'envoi

Une fois qu'un utilisateur est créé, il n'est pas nécessaire de le re-créer pour les prochains envois. Idem pour les adresses qui sont enregistrées dans un carnet d'adresses.


<a id="caracterisation_utilisateur"></a>
## Caractérisation d'un utilisateur

Dans la plupart des cas d'usages, il est recommandé d'envoyer tous vos courriers et de créer toutes vos adresses dans le même utilisateur (par exemple l'utilisateur admin créé par défaut dans votre compte Merci Facteur Pro).

Un utilisateur se caractérise par un email, un nom et un prénom. Ces éléments vous permettrons d'identifier les utilisateurs (savoir qui est quel utilisateur). Nous vous conseillons d'enregistrer en local le user ID de chaque utilisateur.

Chaque utilisateur aura son propre historique de ses envois.

Vous n'êtes pas obligé de créer plusieurs utilisateurs. Suivant votre fonctionnement vous pouvez tout à fait n'avoir qu'un seul utilisateur.

Exemple de cas où la création de plusieurs utilisateurs est utile : Cas d'un syndic qui pourra créer un utilisateur par copropriété, de manière à séparer les envois de chaque copropriété pour des raisons comptables et de gestion.

Vous pouvez créer des utilisateurs via l'API ou via l'interface (menu "Utilisateurs").

Vous pouvez également définir un role pour chaque utilisateur créé, définissant des droits différents : 
- admin : l'utilisateur aura à accès à l'interface Merci Facteur et aura accès à tous les courriers de tous les utilisateurs
- user : l'utilisateur aura à accès à l'interface Merci Facteur mais n'aura accès qu'à ses propres courriers
- api : l'utilisateur ne disposera pas d'un accès à l'interface Merci Facteur


<a id="caracterisation_envoi"></a>
## Caractérisation d'un envoi

Un envoi est un ou plusieurs courrier(s) identique(s) qui est/sont envoyé(s) à un ou plusieurs destinataires. Un envoi peut donc être composé d'un ou plusieurs courriers. Mais le contenu et le mode d'envoi de chaque courrier d'un envoi seront identiques.

### Exemples : 

Envoi 1 ->  destinataire 1, destinataire 2, destinataire 3

Envoi 2 ->  destinataire 1

Envoi 3 ->  destinataire 1, destinataire 3

Envoi 4 -> publipostage de 350 destinataires

L'envoi 1 est composé de 3 courriers, l'envoi 2 est composé de 1 courrier, l'envoi 3 est composé de 2 courriers, l'envoi 4 est composé de 350 courriers.

<a id="creation_token"></a>
## Création du token

Vous devez envoyer un token dans chaque requète à l'API Merci Facteur. Ce token est généré via getToken (https://www.merci-facteur.com/api/1.2/doc.php#/getToken/getToken) à partir notamment de votre secret Key que vous devrez hasher (en savoir plus sur la hashage de la secret key : https://github.com/MerciFacteur/Merci-facteur-API/tree/master/hash-secret-key).

Par défaut, vous devez autoriser des IP qui pourront utiliser le token, si votre infrastructure n'a pas d'IP fixe, vous pouvez soliciter auprès du service technique de Merci Facteur une levée de la restriction d'IP (https://www.merci-facteur.com/pro/contact.php).

Chaque token généré est valable 24h par défaut, il est recommandé de stocker la date d'expiration du token et de rappeler getToken lorsque votre token est expiré.

### Paramètres facultatifs à passer en POST dans getToken : 

Il existe 2 paramètres facultatifs, dont vous pourriez avoir besoin dans certains cas d'usages "exotiques".

- timeLimit = 1 à 365
- force = "extend" ou "renewal"

"timeLimit" correspond à la durée d'expiration souhaitée, par période de 24h (1 = 24h, 10 = 240h, etc.). Doit être compris entre 1 et 365.

Si "force" == "extend" : cela prolonge la durée du token (s'il existe) de la valeur de "timeLimit" (si "timeLimit" n'est pas défini, cela prolonge de 24h), à compter de l'heure de la requête. Si aucun token n'existe, cela en créera un nouveau avec comme durée de vie la valeur de "timeLimit" (s'il existe un token expiré, cela le réactivera en prolongeant sa durée sans en créer un nouveau).

Si "force" == "renewal" : cela supprime le token existant et force la création d'un nouveau token (avec la durée d'expiration de "timeLimit" si le paramètre est envoyé).

"timeLimit" peut être utilisé indépendamment de "force" (alors le comportement reste identique à celui par défaut : si un token existe, getToken vous le retourne, et si aucun token valide n'existe, alors getToken va créer un nouveau token avec "timeLimit" comme durée d'expiration).

<a id="date_envoi"></a>
## Date d'envoi des courriers

Nous imprimerons et nous posterons vos courriers validés via l'API si vous les validez avant 17h00 du lundi au vendredi.

Les courriers validés le samedi, le dimanche, ou les jours fériés, seront imprimés et postés le jour ouvrable suivant.

Vous pouvez également programmer un envoi de courrier pour une date précise ultérieure. Pour cela, indiquez-la dans dans la clé "dateEnvoi", lorsque vous executez un /sendCourrier. Cette date doit être de la forme AAAA-MM-JJ et doit être une date non passée.

<a id="npai"></a>
## Gestion des NPAI (ou PND / plis non distribués)

Le NPAI (ou PND) est un courrier qui n'a pas été remis au destinataire et qui est retourné à l'expéditeur (adresse incorrecte, boite aux lettres cassée ou inaccessible, recommandé non réclamé ou refusé, etc.).

Vous pouvez confier à Merci Facteur la gestion des NPAI quel que soit le mode d'envoi (recommandé, lettre verte, suivi). Les courriers NPAI reviennent alors chez Merci Facteur, nous les numérisons, et vous faisons remonter l'information par l'API via les [webhooks](#webhooks). Les courriers en NPAI sont ensuite archivés durant 3 ans.

### Gestion des NPAI pour les envois Suivi simple et Lettre Verte sans suivi :
Ajoutez dans "sendCourrier" (ou dans le "sendPublipostage" pour les publipostages) :
```json
{"gestionNpai":1}
```

### Gestion des NPAI pour les envois Recommandés :
Utilisez directement le mode d'envoi "lrare". Qui permet la numérisation des AR et la gestion des NPAI pour les recommandés. [En savoir plus](#mode_envoi)


<a id="branding"></a>
## Branding et enveloppes personnalisées

Le choix de l'enveloppe se fait automatiquement, selon le contenu du courrier. Les enveloppes sont blanches.

4 formats sont possibles :
- C4 (lettres de plus de 10 pages, cartes géantes)
- C4 renforcée avec soufflet (lettres de plus de 50 pages)
- C5 (cartes classiques ou pliées, lettres de moins de 11 pages, plus de 10 photos)
- C6 (cartes postales, moins de 11 photos)

Avec Merci Facteur Pro, vous pouvez envoyer vos courriers dans des enveloppes personnalisées :
![Enveloppe personnalisée avec Merci Facteur Pro](https://www.merci-facteur.com/pro/img/enveloppe-personnalisee.png)

Pour créer vos enveloppes personnalisées, rendez-vous dans "Votre branding" via le menu en haut à droite de votre compte Merci Facteur Pro.

Vous retrouverez ensuite l'ID de chaque enveloppe personnalisée, dans la liste de vos enveloppes personnalisées.

Pour utiliser une enveloppe personnalisée lors d'un envoi de courrier via l'API Merci Facteur Pro, ajoutez dans "sendCourrier" :
```json
{"enveloppe":{"type":"template","value":"123456"}}
```
(en remplaçant "123456" par l'ID de l'enveloppe personnalisée souhaitée.

<a id="infos_adresses"></a>
## Les adresses de destinataires et d'expéditeur

Un envoi est composé d'un expéditeur et d'un ou plusieurs destinataire(s). 

Vous pouvez soit intérer les adresses dans un carnet d'adresses (adresses étant créées au préalable avec /setNewAdress), afin de réutiliser ces adresses ensuite. 
Ou vous pouvez envoyer des courriers sans créer auparavant les adresses si vous ne souhaitez pas gérer un carnet d'adresses.

Dans une adresse les informations obligatoires sont : 
- nom ou société
- code postal
- ville
- pays

Si vous envoyez un recommandé électronique, les informations suivantes sont également obligatoires :
- "phone" (dans le cas d'un recommandé électronique OTP SMS)
- "email" (dans le cas d'un recommandé électronique OTP EMAIL)
- "consent" (doit être =1 pour signifier que vous avez le consentement du destinataire - obligatoire pour les destinataire non professionnels)

Les informations possibles mais facultatives sont :
- logo (pour l'expéditeur, imprimé en haut à gauche de l'enveloppe, l'image doit au maximum avoir une dimension de L400px * H250px)
- prénom
- civilité
- adresse ligne 1
- adresse ligne 2
- adresse ligne 3

Le pays doit être avec une orthographe conforme. La liste des pays disponibles est disponible avec /getCountry.

### Format des adresses lors de l'envoi d'un courrier :

Lorsque vous envoyez un courrier via /sendCourrier, vous pouvez intégrer les adresses d'expéditeur et de destinataire de 3 manières différentes.

**Expéditeur : depuis une adresse préalablement créée avec /setNewAdress**

Si vous gérer un carnet d'adresses, intégrez dans adress.exp uniquement l'id de l'adresse.
Exemple : 
```json
{"exp" : 123456}
```
<a id="infos_adresses_expediteur_sans_setNewAdress"></a>
**Expéditeur : sans avoir créé auparavant l'adresse avec /setNewAdress**

Si vous ne gérez pas de carnet d'adresses, envoyez dans adress.exp un objet contenant les informations de l'adresse (attention, dans ce cas vous ne pourrez pas intégrer de logo pour l'expéditeur).
Exemple : 
```json
{"exp" : {
    "civilite": "Mme",
    "nom": "Dupont",
    "prenom": "Sophie",
    "societe": "Dupont Corp.",
    "adresse1": "9 allée de la rose",
    "adresse2": "",
    "adresse3": "",
    "cp": "78000",
    "ville": "Versailles",
    "pays": "france",
    "email": ""
  }}
```

**Destinataire : depuis une adresse préalablement créée avec /setNewAdress**

Si vous gérer un carnet d'adresses, intégrez dans adress.dest un tableau contenant les ID des adresses de destinataire.
Exemple : 
```json
{"dest" : [1595,456,951,2368]}
```

**Destinataire : depuis une adresse préalablement créée avec /setNewAdress, et avec une référence**

Vous pouvez lier à chaque courrier une référence.
Si vous gérer un carnet d'adresses, intégrez dans adress.dest un tableau contenant un/des tableaux avec l'ID et la référence.
Exemple : 
```json
{"dest" : [[1231,"ref-client-1"],[4567,"ref-client-2"]]}
```

**Destinataire : sans avoir créé auparavant l'adresse avec /setNewAdress**

Si vous ne gérez pas de carnet d'adresses, envoyez dans adress.dest un tableau d'objets contenant directement les informations des adresses (si vous souhaitez lier au courrier une référence, utilisez la clé "reference".

Exemple : 
```json
{"dest" : [{
    "civilite": "Monsieur",
    "nom": "Martin",
    "prenom": "Joël",
    "societe": "",
    "adresse1": "33 allée de la paquerette",
    "adresse2": "Entrée B",
    "adresse3": "",
    "cp": "75015",
    "ville": "Paris",
    "pays": "france",
    "phone": "",
    "email": "",
    "consent": 0,
    "reference":"ref-client-1"
  },
  {
    "civilite": "Mme",
    "nom": "Alonzo",
    "prenom": "Charlène",
    "societe": "",
    "adresse1": "41 rue des hérissons",
    "adresse2": "",
    "adresse3": "",
    "cp": "13015",
    "ville": "Marseille",
    "pays": "france",
    "phone": "",
    "email": "",
    "consent": 0,
    "reference":"ref-client-2"
  }]}
```

**Destinataire : Combinaison d'adresse issues du carnet d'adresses et d'adresses non enregistrées**

Bien entendu, pour un même courrier vous pouvez combiner les 3 variantes décrites ci-dessus.

Exemple : 
```json
{"dest" : [{
    "civilite": "Monsieur",
    "nom": "Martin",
    "prenom": "Joël",
    "societe": "",
    "adresse1": "33 allée de la paquerette",
    "adresse2": "Entrée B",
    "adresse3": "",
    "cp": "75015",
    "ville": "Paris",
    "pays": "france",
    "phone": "",
    "email": "",
    "consent": 0,
    "reference":""
  },
  123456,
  [789456,"ref-client-2"]]}
```

### Limites du nombre de caractères :
<table>
<tr><th>Information</th><th>Nb. caractère max.</th></tr>
<tr><td>Civilité</td><td>12</td></tr>
<tr><td>Société</td><td>90</td></tr>
<tr><td>Nom</td><td>70</td></tr>
<tr><td>Prénom</td><td>70</td></tr>
<tr><td>Adresse ligne 1</td><td>90</td></tr>
<tr><td>Adresse ligne 2</td><td>90</td></tr>
<tr><td>Adresse ligne 3</td><td>90</td></tr>
<tr><td>Code postal</td><td>12</td></tr>
<tr><td>Ville</td><td>70</td></tr>
<tr><td>Pays</td><td>70</td></tr>
</table>



<a id="mode_envoi"></a>
## Le mode d'envoi

Lorsque vous réalisez un envoi, vous devez choisir le mode d'envoi du/des courrier(s) : 

### Courriers papier : 
- lrar : le courrier sera envoyé en recommandé avec avis de réception (valeur légale, l'expéditeur recevra l'avis de réception signé par le destinataire).
- lrare : le courrier sera envoyé en recommandé avec avis de réception (valeur légale). L'avis de réception reviendra chez Merci facteur, nous le numériserons, et vous le mettrons à disposition en version numérique.
- suivi : le courrier est envoyé avec un suivi simple (permet de connaître la date de réception, mais sans valeur légale).
- normal : le courrier est envoyé sans aucun suivi (lettre verte).

[En savoir plus sur les NPAI (ou PND, retour à l'expéditeur)](#npai)

### Courriers eléctroniques : 
- ere_otp_mail : le courrier électronique sera envoyé en Envoi Recommandé Electronique de niveau simple qui répond aux exigences de l'article 43 du règlement (UE) eIDAS n°910/2014 du 23 juillet 2014 et de l'article 48 du décret n°2020-834 du 2 juillet 2020 - Code de vérification envoyé au destinataire par email
- ere_otp_sms : le courrier électronique sera envoyé en Envoi Recommandé Electronique de niveau simple qui répond aux exigences de l'article 43 du règlement (UE) eIDAS n°910/2014 du 23 juillet 2014 et de l'article 48 du décret n°2020-834 du 2 juillet 2020 - Code de vérification envoyé au destinataire par SMS

<a id="anonymisation"></a>
## Anonymisation de courrier

Si vous le souhaitez, vous pouvez demander à ce que les courriers soient "anonymisés" après leur envoi. Cela aura pour conséquence de supprimer les données et fichiers de nos serveurs.

Ainsi, vous pourrez :
- Supprimer l'adresse d'expéditeur d'un courrier
- Supprimer l'adresse de destinataire d'un courrier
- Supprimer le contenu d'un courrier

Important : Lorsqu'un courrier est anonymisé, il en reste une trace dans votre interface (date d'envoi, suivi, etc.) mais toutes les données personnelles auront été supprimées.

Pour cela, lors de l'envoi du courrier (/sendCourrier ou /sendPublipostage), envoyez la donnée suivante :
```json
{"anonymize":{"delay":15,"target":["content","exp","dest"]}}
```

Où :

"delay":15 s'ignifie "anonymiser 15 jours après l'impression du courrier" (minimum 1 et maximum 40).

"target":["content","exp","dest"] s'ignifie "anonymiser le contenu, l'expéditeur et le destinataire".

Ainsi :

```json
{"delay":10,"target":["content","dest"]}
```
s'ignifira par exemple "Anonymiser le contenu et le destinataire 10 jours après l'impression".

```json
{"delay":1,"target":["dest"]}
```
s'ignifira par exemple "Anonymiser le destinataire 1 jour après l'impression".

<a id="antidoublon"></a>
## Anti-doublon de courrier

L'API Merci Facteur vous propose une fonctionnalité pour éviter le risque d'envoyer deux fois le même courrier sans le faire exprès.

Pour cela, envoyez dans le "/sendCourrier" le paramètre suivant : 
```json
{"antidoublon":"votre référence unique"}
```

En indiquant une référence de votre choix (maximum 200 caractères) correspondant au courrier (exemple : référence client+référence facture+numéro de relance).
Si vous essayez d'envoyer deux fois un courrier avec cette référence identique sur 30 jours glissant, l'API vous retournera une erreur.

<a id="ref_interne"></a>
## Ajouter des références internes sur les courriers

Pour des besoins d'organisation interne, vous pouvez ajouter des références internes sur vos courriers. Si il s'agit d'un envoi en recommandé avec avis de réception, la référence interne sera imprimée sur l'accusé de réception qui vous est retourné.

Cette référence interne se retrouvera également dans les exports CSV de vos courriers.

Pour ajouter une référence interne, envoyez un tableau plutôt qu'un "integer" au niveau du tableau des destinataires. La première clé sera l'ID du destinataire, et la seconde clé votre référence interne : [IDdestinataire,"reference interne"].

Si vous n'utilisez pas de carnet d'adresses et que vous envoyez dans /sendCourrier directement le json contenant les infos d'adresse, utilisez la clé "reference" dans l'objet contenant les informations de l'adresse (plus d'informations en [cliquant ici](#infos_adresses) ).

Exemple de bloc adresse avec des références internes (dans cet exemple, les deux derniers destinataires n'ont pas de référence interne) : 

"adress": {
    "exp": 145840,
    "dest": [
      [195652,"RELANCE 29854"],
      [185444,"RELANCE 29855"],
      [185444,"RELANCE 29856"],
      198562,
      185476
    ]
  }

La référence courrier ne peut être composée que de chiffres, lettres, espaces, et caractères -_ , et doit faire maximum 30 caractères.



<a id="rectoverso"></a>
## Envoyer une lettre recto-verso

Via l'API, vous pouvez choisir que votre lettre soit imprimée et recto, ou en recto verso. Pour cela, 3 variantes existent : 
- recto : votre lettre sera imprimée uniquement en recto.
- rectoverso : votre lettre sera imprimée en recto-verso, tous les fichiers à la suite.
- distinctrectoverso : votre lettre sera imprimée en recto-verso, mais en gardant distinct chaque fichier de la lettre (une page blanche sera ajoutée entre les fichiers si le fichier précédent à un nombre de pages impair).

Exemple avec "distinctrectoverso" : 
Vous envoyez une lettre composée d'un fichier de 3 pages, suivi d'un autre fichier de 2 pages, en recto-verso.
Pour éviter que la première page du second fichier ne soit imprimée au verso de la 3ème page du premier fichier, nous insérons une page blanche entre les deux fichiers.

En imprimant recto-verso, vous réduisez le poids de votre lettre, et faites donc potentiellement une économie sur l'affranchissement.


<a id="envoi_ere"></a>
## API d'envoi de recommandés électroniques

Avec l'API de Merci facteur, vous pouvez également envoyer des recommandés électroniques qui répondent aux exigences de l'article 43 du règlement (UE) eIDAS n°910/2014 du 23 juillet 2014 et de l'article 48 du décret n°2020-834 du 2 juillet 2020.

Comme pour tous les autres courriers, l'envoi se fait via /sendCourrier en spécifiant le mode d'envoi "ERE_OTP_MAIL" ou "ERE_OTP_SMS".

A la création des destinataires (/setNewAdress) ou dans le json envoyé pour l'adresse lors de l'envoi du courrier, veillez à bien remplir l'email, et à mettre consent = 1
Ce second paramètre sert à signifier que vous avez le consentement du destinataire (consentement non nécessaire dans le cas de destinataires professionnels).

Lors de l'execution du /sendCourrier pour envoyer le recommandé électronique, vous pouvez spécifier un nom de fichier qui sera visible par le destinataire, dans l'email qui lui sera envoyé. Pour cela, remplissez la clé "content.letter.final_filename".

Vous pouvez également ajouter une désignation au courrier, également visible par le destinataire dans l'email qui lui sera envoyé, en remplissant la clé "designation".

Une fois le recommandé électronique envoyé à votre destinataire, nous nous chargerons d'envoyer des emails de rappel si nous constatons que celui-ci n'a pas accepté et ouvert son recommandé électronique.


<a id="envoi_lettre"></a>
## API d'envoi de lettres

Les lettres sont imprimées sur papier premium blanc de 80Gr/m², certifié pour la lutte contre la déforestation.

Vous pouvez envoyer jusqu'à 10 fichiers pour une même lettre (des PDF via leur URL et/ou des PDF en base64).

Seul le format PDF est accepté. Et le poids maximum par fichier est de 50 Mo. Le fichier peut-être en couleur ou en noir et blanc.

Vous pouvez demander à ce que votre lettre soit imprimée en recto, ou en recto-verso (afin de diminuer le poid, et donc le montant de l'affranchissement).

Vous pouvez envoyer une lettre imprimée en recto, ou en recto-verso. Une option est également disponible pour imprimer en recto-verso tout en gardant des fichiers distincts.

Vous pouvez indiquer un nom de fichier (clé content.letter.final_filename) dans /sendCourrier.
Ce nom de fichier sera affiché dans votre interface Merci facteur Pro, et sera visible dans l'email envoyé au destinataire dans le cas d'un recommandé électronique. Ce nom de fichier est fictif et uniquement utile à des fins de lisibilité.



<a id="envoi_cartes"></a>
## API d'envoi de cartes illustrées

Les cartes sont imprimées sur papier épais haute qualité de 350Gr/m², certifié pour la lutte contre la déforestation.

### 6 formats sont disponibles :
- Carte postale sans enveloppe (envoyez "naked-postcard" dans l'API)
- Carte postale avec enveloppe (envoyez "postcard" dans l'API)
- Carte pliée (envoyez "folded" dans l'API)
- Carte non pliée (envoyez "classic" dans l'API)
- Carte géante pliée (envoyez "large" dans l'API)
- Carte géante A4 (envoyez "large-a4" dans l'API)

### 3 papiers différents disponibles : 
- Papier classique (envoyez "classic" dans l'API)
- Papier nacré (envoyez "nacre" dans l'API)
- Papier création (envoyez "creation" dans l'API)

### 2 types de découpes : 
- Coins carrés (envoyez "carre" dans l'API)
- Coins arrondis (envoyez "arrondi" dans l'API)

### Dimensions (cm) des cartes après impression et coupe :
- Carte postale sans enveloppe : 11 x 16 cm (vertical ou horizontal)
- Carte postale avec enveloppe : 11 x 16 cm (vertical ou horizontal)
- Carte pliée : 15 x 21 cm fermée et 30 x 21 ouverte (vertical ou horizontal)
- Carte non pliée : 15 x 21 cm (vertical ou horizontal)
- Carte géante pliée : 19 x 27 cm fermée et 38 x 21 ouverte (vertical ou horizontal)
- Carte géante A4 : 21 x 29,7 cm (vertical ou horizontal)

### Dimension (pixels) des fichiers acceptés :
- Dimension recommandée : 1200 x 1690 pixels (vertical ou horizontal)
- Dimension minimum : 800 x 1124 pixels (vertical ou horizontal)
- Dimension maximum : 4000 x 2840 pixels (vertical ou horizontal)
- Proportions à respecter : Petit côté = 0,71 x grand côté
- Exemples de dimensions : 800 x 1124 pixels, 1000 x 1408 pixels, 1500 x 2112 pixels

### Types de fichiers acceptés :
Le visuel doit être envoyé soit via son URL, soit en base64.
- jpeg
- jpg
- Poids maximum de 4 Mo
- RVB conseillé


<a id="envoi_photo"></a>
## API d'envoi de photos

Les photos sont imprimées sur du papier brillant supergloss de 250Gr/m², au format 10,5 x 14,85 cm.

### Dimension (pixels) des fichiers acceptés :
- Dimension recommandée : 1200 x 1690 pixels (vertical ou horizontal)
- Dimension minimum : 800 x 1124 pixels (vertical ou horizontal)
- Dimension maximum : 4000 x 2840 pixels (vertical ou horizontal)
- Proportions à respecter : Petit côté = 0,71 x grand côté
- Exemples de dimensions : 800 x 1124 pixels, 1000 x 1408 pixels, 1500 x 2112 pixels

### Types de fichiers acceptés :
Le visuel doit être envoyé soit via son URL, soit en base64.
- jpeg
- jpg
- Poids maximum de 4 Mo
- RVB conseillé

### Autres limitations :
- Maximum 50 photos par courrier.

<a id="publipostage"></a>
## API de publipostage

L'API de Merci facteur vous permet également d'intégrer dans vos applicatifs du publipostage.

La création de publipostage via l'API de publipostage se fait en 3 phases successives : 
- Envoi du template (fichier docx)
- Envoi de la source de données (fichier csv ou txt, ou json)
- Validation du publipostage

Entre chaque phase, vous avez la possibilité de contrôler les données pour vous assurer que les courriers générés par ce publipostage sont bien conformes. La 3ème phase, qui génère le publipostage, peut-être déclenchée via l'API mais aussi via l'interface Merci facteur Pro si vous souhaitez effectuer un contrôle manuel.


### Envoi du template (/templatePublipostage) :

Vous devez dans un premier temps envoyer le template (format docx obligatoirement), qui est la lettre contenant les variables qui pourront être remplacées.

Les variables doivent être de la forme ${ma_variable} . Elles ne peuvent contenir que les caractères a-z, A-Z, 0-9 et _.

Certaines variables, celles qui composent l'adresse postale, sont standardisées : 
```html
${civilite}
${nom}
${prenom} 
${adresse1} 
${adresse2} 
${adresse3}
${cp}
${ville}
${pays}
```

Exemple de fichier conforme : https://www.merci-facteur.com/pro/exemples/fichier%20exemple.docx
(Le fichier .docx est le format Word 2007, et les images doivent être encodées en .jpeg).

Vous avez la possibilité d'envoyer le template via une URL distante, ou en base64 (sans retours chariot, ni EOF).

En sortie, vous disposerez d'un json "templateValidation" contenant les informations à contrôler par vos soins (nombre de pages détectées, variables détectées, etc), ainsi qu'une clé de validation. Ce json devra ensuite être envoyé en même temps que la source.

Attention, les variables sont sensibles à la casse.


### Envoi de la source (/sourcePublipostage) :

Toutes les variables présentes dans le template doivent être présentes (même avec une valeur vide) dans la source de données, et cela pour tous les destinataires. Dans le cas contraire une erreur vous sera retournée, spécifiant l'information manquante.

Dans la source de données, 4 informations sont obligatoires : 
- "nom" ou "societe"
- "cp"
- "ville"
- "pays"

Le pays devant être avec une orthographe conforme (cf. /getCountry).

Vous pouvez envoyer la source de données sous 3 formats différents : 
- fichier CSV ou TXT distant via une url (type="file" et value="url du fichier")
- base64 d'un fichier CSV ou TXT (type="base64" et value="fichier en base64")
- json de données, avec pour chaque adresse la variable en clé (type="json" et value=[{"civilite":"","societe":"","nom":"","prenom":"","adresse1":"","adresse2":"","adresse3":"","cp":"","ville":"","pays":""},{"civilite":"","societe":"","nom":"","prenom":"","adresse1":"","adresse2":"","adresse3":"","cp":"","ville":"","pays":""},{etc.}])

Dans le cas de l'envoi d'un fichier CSV ou TXT, les données doivent être séparées par des points-viurgules (;) et les adresses par des sauts de lignes. La première ligne (titres de colonnes) doit correspondre exactement aux variables, sans le $ et les {}.

Exemple de fichier conforme : https://www.merci-facteur.com/pro/exemples/fichier%20exemple.csv

En plus de la source de données, vous devrez envoyer à cette phase le json "templateValidation" qui vous a été précédemment retourné, sans aucune modification.

Attention, les variables sont sensibles à la casse.


### Validation de l'envoi du publipostage (/sendPublipostage) :

La 3ème et dernière phase permet de valider le publipostage. Une fois cette phase exécutée, Merci facteur a le feu vert pour fusionner les données, générer les lettres, et procéder à l'envoi de vos courriers.

Envoyez ici soit l'id de votre adresse d'expéditeur (dans "idExp"), soit un json contenant directement les information de l'adresse d'expéditeur souhaitée (dans "jsonExp"). (plus d'informations en [cliquant ici](#infos_adresses_expediteur_sans_setNewAdress) )

Cette phase étant sensible, en plus de la validation par l'API vous avez aussi la possibilité, si vous le souhaitez, d'effectuer cette phase manuellement. Ce qui vous permettra de vérifier visuellement un échantillon de lettres une fois fusionnées.

Pour cela, connectez-vous à votre interface Merci facteur Pro, puis allez dans "Envoyer un publipostage". Vous trouverez alors les publipostages en attente de validation.


<a id="webhooks"></a>
## Les webhooks

Vous avez la possibilité de paramétrer sur votre compte une url de notification (endpoint) sur laquelle l'API de Merci facteur enverra des webhooks) lors de divers événements.

Pour paramétrer votre url de endpoint, rendez-vous dans l'onglet "API" de votre interface Merci facteur Pro.

Un webhook peut contenir des notifications de plusieurs courriers différents pour lesquels il s'agit du même événement (vous ne pouvez pas avoir plusieurs événements différents dans un même webhook).

Votre url de endpoint devra retourner en entête un code 200 en cas de succès. La réponse ne doit pas dépasser les 500 caractères (nous vous conseillons de n'envoyer une réponse que dans le cadre du debug).

Vous recevrez sur votre endpoint en post les informations suivantes :
- $_POST['event'] : json contenant les informations sur l'événement (type d'événement, et id user)
- $_POST['detail'] : tableau de json contenant les informations du (ou des) courrier(s) (adresse de destinataire, références de courrier/tracking, statut, etc.).

Vous pouvez, via l'interface Merci facteur Pro, visualiser l'historique de tous les webhooks envoyés sur votre endpoint url.


### Les événements disponibles :

Voici les événements pour lesquels vous pouvez recevoir des webhooks : 

<table>
    <tr>
        <th>Nom événement</th>
        <th>Description</th>
        <th>Informations envoyées</th>
    </tr>
    <tr>
        <td>new</td>
        <td>Nouveau(x) courrier(s) créé(s)</td>
        <td>&bull; event.name_event<br>
            &bull; event.id_user<br>
            &bull; event.date_event<br>
        &bull; detail[].civilite<br>
        &bull; detail[].nom<br>
        &bull; detail[].prenom<br>
        &bull; detail[].societe<br>
        &bull; detail[].adresse1<br>
        &bull; detail[].adresse2<br>
        &bull; detail[].adresse3<br>
        &bull; detail[].cp<br>
        &bull; detail[].ville<br>
        &bull; detail[].pays<br>
        &bull; detail[].ref_courrier<br>
        &bull; detail[].mode_envoi<br>
        &bull; detail[].id_envoi<br>
        &bull; detail[].statut_courrier<br>
        &bull; detail[].statut_description<br>
        &bull; detail[].ref_interne</td>
    </tr>
    <tr>
        <td>printed</td>
        <td>Courrier(s) papier imprimé(s)</td>
        <td>&bull; event.name_event<br>
            &bull; event.id_user<br>
            &bull; event.date_event<br>
        &bull; detail[].civilite<br>
        &bull; detail[].nom<br>
        &bull; detail[].prenom<br>
        &bull; detail[].societe<br>
        &bull; detail[].adresse1<br>
        &bull; detail[].adresse2<br>
        &bull; detail[].adresse3<br>
        &bull; detail[].cp<br>
        &bull; detail[].ville<br>
        &bull; detail[].pays<br>
        &bull; detail[].ref_courrier<br>
        &bull; detail[].mode_envoi<br>
        &bull; detail[].id_envoi<br>
        &bull; detail[].statut_courrier<br>
        &bull; detail[].statut_description<br>
        &bull; detail[].tracking_number<br>
        &bull; detail[].ref_interne</td>
    </tr>
    <tr>
        <td>sended</td>
        <td>Courrier(s) Electronique(s) envoyé(s)</td>
        <td>&bull; event.name_event<br>
            &bull; event.id_user<br>
            &bull; event.date_event<br>
        &bull; detail[].civilite<br>
        &bull; detail[].nom<br>
        &bull; detail[].prenom<br>
        &bull; detail[].societe<br>
        &bull; detail[].adresse1<br>
        &bull; detail[].adresse2<br>
        &bull; detail[].adresse3<br>
        &bull; detail[].cp<br>
        &bull; detail[].ville<br>
        &bull; detail[].pays<br>
        &bull; detail[].email<br>
        &bull; detail[].telephone<br>
        &bull; detail[].ref_courrier<br>
        &bull; detail[].mode_envoi<br>
        &bull; detail[].id_envoi<br>
        &bull; detail[].statut_courrier<br>
        &bull; detail[].statut_description<br>
        &bull; detail[].tracking_number<br>
        &bull; detail[].ref_interne</td>
    </tr>
    <tr>
        <td>new-state</td>
        <td>Nouveau(x) statut(s) de(s) courrier(s)</td>
        <td>&bull; event.name_event<br>
            &bull; event.id_user<br>
            &bull; event.date_event<br>
        &bull; detail[].civilite<br>
        &bull; detail[].nom<br>
        &bull; detail[].prenom<br>
        &bull; detail[].societe<br>
        &bull; detail[].adresse1<br>
        &bull; detail[].adresse2<br>
        &bull; detail[].adresse3<br>
        &bull; detail[].cp<br>
        &bull; detail[].ville<br>
        &bull; detail[].pays<br>
        &bull; detail[].ref_courrier<br>
        &bull; detail[].mode_envoi<br>
        &bull; detail[].id_envoi<br>
        &bull; detail[].statut_courrier<br>
        &bull; detail[].statut_description<br>
        &bull; detail[].tracking_number<br>
        &bull; detail[].ref_interne</td>
    </tr>
    <tr>
        <td>delivered</td>
        <td>Courrier(s) arrivé(s) chez le(s) destinataire(s)</td>
        <td>&bull; event.name_event<br>
            &bull; event.id_user<br>
            &bull; event.date_event<br>
        &bull; detail[].civilite<br>
        &bull; detail[].nom<br>
        &bull; detail[].prenom<br>
        &bull; detail[].societe<br>
        &bull; detail[].adresse1<br>
        &bull; detail[].adresse2<br>
        &bull; detail[].adresse3<br>
        &bull; detail[].cp<br>
        &bull; detail[].ville<br>
        &bull; detail[].pays<br>
        &bull; detail[].ref_courrier<br>
        &bull; detail[].mode_envoi<br>
        &bull; detail[].id_envoi<br>
        &bull; detail[].statut_courrier<br>
        &bull; detail[].statut_description<br>
        &bull; detail[].tracking_number<br>
        &bull; detail[].ref_interne</td>
    </tr>
    <tr>
        <td>error</td>
        <td>Erreur(s) de distribution(s) de(s) courrier(s)</td>
        <td>&bull; event.name_event<br>
            &bull; event.id_user<br>
            &bull; event.date_event<br>
        &bull; detail[].civilite<br>
        &bull; detail[].nom<br>
        &bull; detail[].prenom<br>
        &bull; detail[].societe<br>
        &bull; detail[].adresse1<br>
        &bull; detail[].adresse2<br>
        &bull; detail[].adresse3<br>
        &bull; detail[].cp<br>
        &bull; detail[].ville<br>
        &bull; detail[].pays<br>
        &bull; detail[].ref_courrier<br>
        &bull; detail[].mode_envoi<br>
        &bull; detail[].id_envoi<br>
        &bull; detail[].statut_courrier<br>
        &bull; detail[].statut_description<br>
        &bull; detail[].tracking_number<br>
        &bull; detail[].ref_interne</td>
    </tr>
    <tr>
        <td>pnd</td>
        <td>Pli(s) non distribuable(s)</td>
        <td>&bull; event.name_event<br>
            &bull; event.id_user<br>
            &bull; event.date_event<br>
        &bull; detail[].civilite<br>
        &bull; detail[].nom<br>
        &bull; detail[].prenom<br>
        &bull; detail[].societe<br>
        &bull; detail[].adresse1<br>
        &bull; detail[].adresse2<br>
        &bull; detail[].adresse3<br>
        &bull; detail[].cp<br>
        &bull; detail[].ville<br>
        &bull; detail[].pays<br>
        &bull; detail[].ref_courrier<br>
        &bull; detail[].mode_envoi<br>
        &bull; detail[].id_envoi<br>
        &bull; detail[].statut_courrier<br>
        &bull; detail[].statut_description<br>
        &bull; detail[].tracking_number<br>
        &bull; detail[].ref_interne</td>
    </tr>
    <tr>
        <td>are</td>
        <td>Avis de réception électronique disponible</td>
        <td>&bull; event.name_event<br>
            &bull; event.id_user<br>
            &bull; event.date_event<br>
        &bull; detail[].civilite<br>
        &bull; detail[].nom<br>
        &bull; detail[].prenom<br>
        &bull; detail[].societe<br>
        &bull; detail[].adresse1<br>
        &bull; detail[].adresse2<br>
        &bull; detail[].adresse3<br>
        &bull; detail[].cp<br>
        &bull; detail[].ville<br>
        &bull; detail[].pays<br>
        &bull; detail[].ref_courrier<br>
        &bull; detail[].mode_envoi<br>
        &bull; detail[].id_envoi<br>
        &bull; detail[].statut_courrier<br>
        &bull; detail[].statut_description<br>
        &bull; detail[].tracking_number<br>
        &bull; detail[].are_base64_jpeg<br>
        &bull; detail[].ref_interne</td>
    </tr>
    <tr>
        <td>pdd</td>
        <td>Preuve de dépôt disponible</td>
        <td>&bull; event.name_event<br>
            &bull; event.id_user<br>
            &bull; event.date_event<br>
        &bull; detail[].civilite<br>
        &bull; detail[].nom<br>
        &bull; detail[].prenom<br>
        &bull; detail[].societe<br>
        &bull; detail[].adresse1<br>
        &bull; detail[].adresse2<br>
        &bull; detail[].adresse3<br>
        &bull; detail[].cp<br>
        &bull; detail[].ville<br>
        &bull; detail[].pays<br>
        &bull; detail[].ref_courrier<br>
        &bull; detail[].mode_envoi<br>
        &bull; detail[].id_envoi<br>
        &bull; detail[].statut_courrier<br>
        &bull; detail[].statut_description<br>
        &bull; detail[].tracking_number<br>
        &bull; detail[].pdd_base64_pdf<br>
        &bull; detail[].ref_interne</td>
    </tr>
    
</table>

### Exemples d'événements :

Vous pouvez, via votre interface Merci facteur Pro, tester votre url de endpoint sur les différents événement.

Voici des exemples pour chaque événement :

#### Evénement new (Nouveau(x) courrier(s) créé(s))

```json
{
    "event": {
        "name_event": "new",
        "id_user": 17460,
        "date_event": 1581674000
    },
    "detail": [{
        "civilite": "M.",
        "nom": "Dupont",
        "prenom": "Michel",
        "societe": "Green Flower Corp",
        "adresse1": "3 rue des fleurs",
        "adresse2": "",
        "adresse3": "",
        "cp": "75015",
        "ville": "Paris",
        "pays": "FRANCE",
        "ref_courrier": "123456-789123456",
        "mode_envoi": "lrar",
        "id_envoi": "123",
        "statut_courrier": "wait",
        "statut_description": "14\/02\/2020 : Courrier en attente d'impression",
        "ref_interne":"client_123"
    }, {
        "civilite": "",
        "nom": "",
        "prenom": "",
        "societe": "Blue Water Inc",
        "adresse1": "9 allee de la poiscaille",
        "adresse2": "",
        "adresse3": "",
        "cp": "13012",
        "ville": "Marseille",
        "pays": "FRANCE",
        "ref_courrier": "987456-456123789",
        "mode_envoi": "normal",
        "id_envoi": "987",
        "statut_courrier": "wait",
        "statut_description": "14\/02\/2020 : Courrier en attente d'impression",
        "ref_interne":"client_123"
    }, {
        "civilite": "",
        "nom": "",
        "prenom": "",
        "societe": "Exemple Corp Inc",
        "adresse1": "12 rue montagnarde",
        "adresse2": "",
        "adresse3": "",
        "cp": "75010",
        "ville": "Paris",
        "pays": "FRANCE",
        "email": "monmail@gmail.com",
        "ref_courrier": "112233-4455669955",
        "mode_envoi": "ere_otp_mail",
        "id_envoi": "1095",
        "statut_courrier": "wait",
        "statut_description": "14\/02\/2020 : Courrier Electronique en attente d'envoi",
        "ref_interne":"client_123"
    }]
}
```

#### Evénement printed (Courrier(s) papier imprimé(s))

```json
{
    "event": {
        "name_event": "printed",
        "id_user": 17460,
        "date_event": 1581674000
    },
    "detail": [{
        "civilite": "M.",
        "nom": "Dupont",
        "prenom": "Michel",
        "societe": "Green Flower Corp",
        "adresse1": "3 rue des fleurs",
        "adresse2": "",
        "adresse3": "",
        "cp": "75015",
        "ville": "Paris",
        "pays": "FRANCE",
        "ref_courrier": "123456-789123456",
        "mode_envoi": "lrar",
        "tracking_number": "2C123456789",
        "id_envoi": "123",
        "statut_courrier": "imprime",
        "statut_description": "14\/02\/2020 : Courrier imprim\u00e9 par Merci facteur",
        "ref_interne":"client_123"
    }, {
        "civilite": "",
        "nom": "",
        "prenom": "",
        "societe": "Blue Water Inc",
        "adresse1": "9 allee de la poiscaille",
        "adresse2": "",
        "adresse3": "",
        "cp": "13012",
        "ville": "Marseille",
        "pays": "FRANCE",
        "ref_courrier": "987456-456123789",
        "mode_envoi": "normal",
        "tracking_number": null,
        "id_envoi": "987",
        "statut_courrier": "imprime",
        "statut_description": "14\/02\/2020 : Courrier imprim\u00e9 par Merci facteur",
        "ref_interne":"client_123"
    }]
}
```

#### Evénement sended (Courrier(s) Electronique(s) envoyé(s))

```json
{
    "event": {
        "name_event": "sended",
        "id_user": 17460,
        "date_event": 1581674000
    },
    "detail": [{
        "civilite": "M.",
        "nom": "Dupont",
        "prenom": "Michel",
        "societe": "Green Flower Corp",
        "adresse1": "3 rue des fleurs",
        "adresse2": "",
        "adresse3": "",
        "cp": "75015",
        "ville": "Paris",
        "pays": "FRANCE",
        "email": "monmail@gmail.com",
        "ref_courrier": "123456-789123456",
        "mode_envoi": "ere_otp_mail",
        "tracking_number": "485TGDSU5",
        "id_envoi": "123",
        "statut_courrier": "envoye",
        "statut_description": "14\/02\/2020 : Courrier \u00e9lectronique envoy\u00e9 par Merci facteur",
        "ref_interne":"client_123"
    }, {
        "civilite": "",
        "nom": "",
        "prenom": "",
        "societe": "Blue Water Inc",
        "adresse1": "9 allee de la poiscaille",
        "adresse2": "",
        "adresse3": "",
        "cp": "13012",
        "ville": "Marseille",
        "pays": "FRANCE",
        "ref_courrier": "987456-456123789",
        "mode_envoi": "ere_otp_sms",
        "email": "monmail2@gmail.com",
        "telephone": "+33628495176",
        "tracking_number": "4D49YHU",
        "id_envoi": "987",
        "statut_courrier": "envoye",
        "statut_description": "14\/02\/2020 : Courrier \u00e9lectronique envoy\u00e9 par Merci facteur",
        "ref_interne":"client_123"
    }]
}
```

#### Evénement new-state (Nouveau(x) statut(s) de(s) courrier(s))

```json
{
    "event": {
        "name_event": "new-state",
        "id_user": 17460,
        "date_event": 1581674000
    },
    "detail": [{
        "civilite": "M.",
        "nom": "Dupont",
        "prenom": "Michel",
        "societe": "Green Flower Corp",
        "adresse1": "3 rue des fleurs",
        "adresse2": "",
        "adresse3": "",
        "cp": "75015",
        "ville": "Paris",
        "pays": "FRANCE",
        "ref_courrier": "123456-789123456",
        "mode_envoi": "lrar",
        "tracking_number": "2C123456789",
        "id_envoi": "123",
        "statut_courrier": "pris_en_charge",
        "statut_description": "14\/02\/2020 : Courrier pris en charge par La Poste",
        "ref_interne":"client_123"
    }]
}
```

#### Evénement delivered (Courrier(s) arrivé(s) chez le(s) destinataire(s))

```json
{
    "event": {
        "name_event": "delivered",
        "id_user": 17460,
        "date_event": 1581674000
    },
    "detail": [{
        "civilite": "M.",
        "nom": "Dupont",
        "prenom": "Michel",
        "societe": "Green Flower Corp",
        "adresse1": "3 rue des fleurs",
        "adresse2": "",
        "adresse3": "",
        "cp": "75015",
        "ville": "Paris",
        "pays": "FRANCE",
        "ref_courrier": "123456-789123456",
        "mode_envoi": "lrar",
        "tracking_number": "2C123456789",
        "id_envoi": "123",
        "statut_courrier": "distribue",
        "statut_description": "14\/02\/2020 : Courrier distribu\u00e9 au destinataire",
        "ref_interne":"client_123"
    }]
}
```

#### Evénement error (Erreur(s) de distribution(s) de(s) courrier(s))

```json
{
    "event": {
        "name_event": "error",
        "id_user": 17460,
        "date_event": 1581674000
    },
    "detail": [{
        "civilite": "M.",
        "nom": "Dupont",
        "prenom": "Michel",
        "societe": "Green Flower Corp",
        "adresse1": "3 rue des fleurs",
        "adresse2": "",
        "adresse3": "",
        "cp": "75015",
        "ville": "Paris",
        "pays": "FRANCE",
        "ref_courrier": "123456-789123456",
        "mode_envoi": "lrar",
        "tracking_number": "2C123456789",
        "id_envoi": "123",
        "statut_courrier": "retour_expediteur",
        "statut_description": "14\/02\/2020 : Courrier retourn\u00e9 \u00e0 l'exp\u00e9diteur (adresse incompl\u00e8te)",
        "ref_interne":"client_123"
    }]
}
```

#### Evénement pnd (pli(s) non distribuable(s))

```json
{
    "event": {
        "name_event": "pnd",
        "id_user": 17460,
        "date_event": 1581674000
    },
    "detail": [{
        "civilite": "M.",
        "nom": "Dupont",
        "prenom": "Michel",
        "societe": "Green Flower Corp",
        "adresse1": "3 rue des fleurs",
        "adresse2": "",
        "adresse3": "",
        "cp": "75015",
        "ville": "Paris",
        "pays": "FRANCE",
        "ref_courrier": "123456-789123456",
        "mode_envoi": "lrar",
        "tracking_number": "2C123456789",
        "id_envoi": "123",
        "statut_courrier": "distribue_expediteur",
        "statut_description": "14\/02\/2020 : Distribu\u00e9 \u00e0 l'exp\u00e9diteur",
        "ref_interne":"client_123"
    }]
}
```

#### Evénement are (Avis de réception éléctronique disponible)

```json
{
    "event": {
        "name_event": "are",
        "id_user": 17460,
        "date_event": 1581674000
    },
    "detail": [{
        "civilite": "M.",
        "nom": "Dupont",
        "prenom": "Michel",
        "societe": "Green Flower Corp",
        "adresse1": "3 rue des fleurs",
        "adresse2": "",
        "adresse3": "",
        "cp": "75015",
        "ville": "Paris",
        "pays": "FRANCE",
        "ref_courrier": "123456-789123456",
        "mode_envoi": "lrare",
        "tracking_number": "2C123456789",
        "id_envoi": "123",
        "are_base64_jpeg": "JVBERi0xLjQj4+ [...] g0Kc3RhcUlRU9GDQo=",
        "statut_courrier": "retour_are",
        "statut_description": "14\/02\/2020 : Retour de l'accus\u00e9 de r\u00e9ception sign\u00e9",
        "ref_interne":"client_123"
    }]
}
```

#### Evénement pdd (Preuve de dépôt disponible)

```json
{
    "event": {
        "name_event": "pdd",
        "id_user": 17460,
        "date_event": 1581674000
    },
    "detail": [{
        "civilite": "M.",
        "nom": "Dupont",
        "prenom": "Michel",
        "societe": "Green Flower Corp",
        "adresse1": "3 rue des fleurs",
        "adresse2": "",
        "adresse3": "",
        "cp": "75015",
        "ville": "Paris",
        "pays": "FRANCE",
        "ref_courrier": "123456-789123456",
        "mode_envoi": "lrar",
        "tracking_number": "2C123456789",
        "id_envoi": "123",
        "pdd_base64_pdf": "JVBERi0xLjQj4+ [...] g0Kc3RhcUlRU9GDQo=",
        "statut_courrier": "scan_pdd",
        "statut_description": "14\/02\/2020 : Num\u00e9risation de la preuve de d\u00e9pot",
        "ref_interne":"client_123"
    }]
}
```
 
#### Les différents statuts de courrier (courriers papier)

Voici les différents statuts qu'un courrier "papier" va pouvoir prendre, dans l'ordre chronologique.

Vous retrouverez le code en question dans detail[].statut_courrier. Vous disposerez aussi d'une explication plus précise du statut dans detail[].statut_description.

<table>
<tr><th>événement</th><th>code statut</th><th>Explication</th></tr>
<tr><td>new</td><td>wait</td><td>Courrier en attente d'impression</td></tr>
<tr><td>printed</td><td>imprime</td><td>Courrier papier uniquement : Courrier imprimé par Merci facteur (et sera posté dans quelques instants)</td></tr>
<tr><td>new-state</td><td>pris_en_charge</td><td>Courrier pris en charge par La Poste</td></tr>
<tr><td>new-state</td><td>prix_en_charge_pays_destinataire</td><td>Courrier pris en charge par le service postal du pays destinataire (envois internationaux)</td></tr>
<tr><td>new-state</td><td>traitement</td><td>En cours de traitement chez La Poste</td></tr>
<tr><td>new-state</td><td>traitement_pays_destination</td><td>En cours de traitement par le service postal du pays destinataire (envois internationaux)</td></tr>
<tr><td>new-state</td><td>transit_pays_intermediaire</td><td>En cours de traitement par le service postal d'un pays de transit (envois internationaux)</td></tr>
<tr><td>new-state</td><td>attente_presentation</td><td>Courrier en attente de distribution</td></tr>
<tr><td>new-state</td><td>entree_douane</td><td>Courrier entre en douane (envois internationaux)</td></tr>
<tr><td>new-state</td><td>sortie_douane</td><td>Courrier sort de douane (envois internationaux)</td></tr>
<tr><td>new-state</td><td>retenu_douane</td><td>Courrier retenu en douane (envois internationaux)</td></tr>
<tr><td>new-state</td><td>probleme_resolu</td><td>La Poste a résolu un problème rencontré avec le courrier</td></tr>
<tr><td>new-state</td><td>distribution_en_cours</td><td>Courrier en cours de distribution</td></tr>
<tr><td>new-state</td><td>attente_au_guichet</td><td>L'expéditeur doit aller chercher le courrier au bureau de poste spécifié sur son avis de passage</td></tr>
<tr><td>error</td><td>probleme_en_cours</td><td>La Poste rencontre un problème avec le courrier</td></tr>
<tr><td>error</td><td>non_distribuable</td><td>Courrier non distribuable</td></tr>
<tr><td>error</td><td>retour_expediteur</td><td>Le courrier a été retourné à l'expéditeur</td></tr>
<tr><td>delivered</td><td>distribue</td><td>Courrier papier uniquement : Le courrier a été distribué au destinataire (fin d'acheminement)</td></tr>
<tr><td>pnd</td><td>distribue_expediteur</td><td>Le courrier a été distribué à l'expéditeur en retour (fin d'acheminement) = PND</td></tr>
<tr><td>pnd</td><td>archivage_pnd</td><td>Si vous avez envoyé un recommandé en LRARE, les PND reviennent chez Merci facteur, et sont archivés pendant 3 ans à compter de cet évènement.</td></tr>
<tr><td>are</td><td>retour_are</td><td>Si vous avez l'envoi en LRARE, l'accusé de réception numérisé est disponible.</td></tr>
<tr><td>pdd</td><td>scan_pdd</td><td>Pour les courriers suivis et recommandés, la preuve de dépôt format PDF est disponible.</td></tr></table> 


#### Les différents statuts de courrier (courriers électroniques)

Voici les différents statuts qu'un courrier électronique va pouvoir prendre, dans l'ordre chronologique.

Vous retrouverez le code en question dans detail[].statut_courrier. Vous disposerez aussi d'une explication plus précise du statut dans detail[].statut_description.

<table>
<tr><th>événement</th><th>code statut</th><th>Explication</th></tr>
<tr><td>new</td><td>wait</td><td>Courrier recommandé électronique en attente d'envoi</td></tr>
<tr><td>sended</td><td>envoye</td><td>Courrier Recommandé Electronique envoyé par Merci facteur à son destinataire</td></tr>
<tr><td>new-state</td><td>accept_wait</td><td>Courrier électronique en attente d'acceptation dans la boite de réception du destinataire.</td></tr>
<tr><td>error</td><td>accept_lock</td><td>Courrier recommandé électronique bloqué pendant 3 heures suite à 6 tentatives infructueuses d'identification OTP.</td></tr>
<tr><td>error</td><td>accept_notification_error</td><td>Erreur de notification du destinataire : email erroné ou boite email pleine (fin d'acheminement)</td></tr>
<tr><td>delivered</td><td>accepted</td><td>Courrier Electronique uniquement : Le destinataire a accepté la lettre et signé l'accusé de réception.</td></tr>
<tr><td>delivered</td><td>accepted_downloaded</td><td>Courrier Electronique uniquement : Le destinataire a accepté la lettre, signé l'accusé de réception et télechargé le document.</td></tr>
<tr><td>pnd</td><td>expired</td><td>Le courrier électronique est expiré car son destinataire ne l'a pas accepté dans le délai imparti.</td></tr>
<tr><td>pnd</td><td>refused</td><td>Le destinataire a refusé le recommandé électronique.</td></tr></table> 


#### Relance des webhooks en cas d'echec

Si votre endpoint ne retourne pas un statut 200, deux tentatives supplémentaires seront faites.:
- Une seconde tentative 60 minutes après la première tentative.
- Une troisième tentative 24h après la seconde tentative.

<a id="secret_webhook"></a>
#### Contrôle de l'origine du webhook

Nos webhooks sont susceptibles d'être postés depuis différentes IP, vous ne pouvez donc pas vérifier l'IP du referer pour contrôler que le webhook envoyé sur votre endpoint vient bien de Merci Facteur. Pour faire ce contrôle, vérifiez que l'entête du POST contient votre webhook-secret-key, dans "X-Mf-Webhook-Secret-Key" : 

-H "X-Mf-Webhook-Secret-Key: secret-webhook-123456789azerty"

Vous retrouverez votre webhook-secret-key dans l'onglet "API" via votre compte Merci Facteur.

Si vous avez besoin que la clé soit différente de "X-Mf-Webhook-Secret-Key", contactez-nous !
