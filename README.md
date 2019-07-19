# API envoi de courrier par La Poste
## merci-facteur-api
## API de Merci facteur

Version : 1.2.7

L'API d'envoi de courrier proposé par Merci facteur vous permet d'intégrer à votre applicatif l'envoi de courriers par La Poste via API. 

La version actuelle de l'API d'envoi de courrier permet de :
- Créer et gérer des utilisateurs ;
- Créer et gérer des carnets d'adresses ;
- Créer et gérer des utilisateurs ;
- Envoyer des lettres via des fichiers PDF ;
- Envoyer des cartes illustrées (4 formats : cartes postales, cartes classiques, cartes géantes, et cartes postales) ;
- Envoyer des photos (format 10x15 cm, imprimées sur papier brillant) ;
- Envoyer des lettres en suivi, recommandé avec avis de réception et envoi normal ;
- Gérer les courriers envoyés ; 
- Suivre les courriers envoyés ; 
- générer et envoyer des publipostages ;

Les courriers envoyées via l'API d'envoi de courriers sont imprimées et postées le jour même, comme n'importe quel courrier envoyé via Merci facteur. Ils sont facturés au même prix qu'un courrier envoyé depuis l'interface Merci facteur PRO.

Documentation : https://www.merci-facteur.com/api/1.2/doc.php

En savoir plus : https://www.merci-facteur.com


## Sommaire : 

- [Processus de base d'un envoi](#processus_base)
- [Caractérisation d'un utilisateur](#caracterisation_utilisateur) 
- [Caractérisation d'un envoi](#caracterisation_envoi) 
- [Le mode d'envoi](#mode_envoi) 
- [API envoi de lettres](#envoi_lettre) 
- [API envoi de cartes](#envoi_cartes) 
- [API envoi de photos](#envoi_photo) 
- [Les enveloppes](#enveloppes) 
- [API de publipostage](#publipostage) 

<a id="processus_base"></a>
## Processus de base d'un envoi

Pour réaliser un envoi de courrier, quelques étapes sont nécessaires au préalable : 

Création d'un utilisateur (facultatif) -> Création/sélection d'une adresse d'expéditeur -> Création/sélection d'une adresse de destinataire -> Génération de l'envoi

Une fois qu'un utilisateur est créé, il n'est pas nécessaire de le re-créer pour les prochains envois. Idem pour les adresses qui sont enregistrées dans un carnet d'adresses.


<a id="caracterisation_utilisateur"></a>
## Caractérisation d'un utilisateur

Un utilisateur se caractérise par un email, un nom et un prénom. Ces éléments vous permettrons d'identifier les utilisateurs (savoir qui est quel utiliasteur. Nous vous conseillons d'enregistrer en local le user ID de chaque utilisateur.

Généralement il s'agira d'un client pour vous. 

Chaque utilisateur à un carnet d'adresses avec l'ensemble des adresses qui ont été créées pour ses envoi (les adresses sont réutilisables pour les envois suivants), ainsi qu'un historique de ses envois.

Vous n'êtes pas obligé de créer plusieurs utilisateurs. Suivant votre fonctionnement vosu pouvez tout à fait n'avoir qu'un seul uitilisateur.


<a id="caracterisation_envoi"></a>
## Caractérisation d'un envoi

Un envoi est un ou plusieurs courrier(s) identique(s) qui est/sont envoyé(s) à un ou plusieurs destinataires. Un envoi peut donc être composé d'un ou plusieurs courriers. Mais le contenu et le mode d'envoi de chaque courriers d'un envoi seront identiques.

### Exemples : 

Envoi 1 ->  destinataire 1, destinataire 2, destinataire 3

Envoi 2 ->  destinataire 1

Envoi 3 ->  destinataire 1, destinataire 3

L'envoi 1 est composé de 3 courriers, l'envoi 2 est composé de 1 courrier, l'envoi 3 est composé de 2 courriers.


<a id="mode_envoi"></a>
## Le mode d'envoi

Lorsque vous réalisez un envoi, vous devez choisi le mode d'envoi du/des courrier(s) : 

- lrar : le courrier sera envoyé en recommandé avec avis de réception (valeur légale, l'éxpéditeur recevra l'avis de réception signé par le destinataire).
- suivi : le courrier est envoyé avec un suivi simple (permet de connaître la date de réception, mais sans valeur légale).
- normal : le courrier est envoyé sans aucun suivi (lettre verte).


<a id="envoi_lettre"></a>
## API d'envoi de lettres

Les lettres sont imprimées sur papier premium blanc de 80Gr/m², certifié pour la lutte contre la déforestation.

Vous pouvez envoyer jusqu'à 10 fichiers pour une même lettre (des PDF via leur URL et/ou des PDF en base64).

Seul le format PDF est accepté. Et le poids maximum par fichier est de 4 Mo. Le fichier peut-être en couleur ou en noir et blanc.


<a id="envoi_cartes"></a>
## API d'envoi de cartes illustrées

Les cartes sont imprimées sur papier épais haute qualité de 350Gr/m², certifié pour la lutte contre la déforestation.

### 6 formats sont disponibles :
- Carte postale sans enveloppe (naked-postcard)
- Carte postale avec enveloppe (postcard)
- Carte pliée (folded)
- Carte non pliée (classic)
- Carte géante pliée (large)
- Carte géante A4 (large-a4)

### 3 papiers différents disponibles : 
- Papier classique
- Papier nacré
- Papier création

### 2 types de découpes : 
- Coins carrés
- Coins arrondis

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


<a id="enveloppes"></a>
## Spécifications relatives aux enveloppes

Le choix de l'enveloppe se fait automatiquement, selon le contenu du courrier. Les enveloppes sont blanches.

Vous avez la possibilité de mettre un logo dans le coin en haut à gauche de l'enveloppe.

4 formats sont possibles :
- C4 (lettres de plus de 10 pages, cartes géantes)
- C4 renforcée avec soufflet (lettres de plus de 50 pages)
- C5 (cartes classiques ou pliées, lettres de moins de 11 pages, plus de 10 photos)
- C6 (cartes postales, moins de 11 photos)


<a id="publipostage"></a>
## API de publipostage

L'API de Merci facteur vous permet également d'integrer dans vos applicatifs du publipostage.

La création de publipostage via l'API de publipostage se fait en 3 phases successives : 
- Envoi du template (fichier docx)
- Envoi de la source de données (fichier csv ou txt, ou json)
- Validation du publipostage

Entre chaque phase, vous avez la possibilité de contrôler les données pour vous assurer que les courriers générés par ce publipostage sont bien conformes. La 3ème phase, qui génère le publipostage, peut-être déclenchée via l'API mais aussi via l'interface Merci facteur Pro si vous souhaitez effectuer un contrôle manuel.


### Envoi du template (/templatePublipostage) :

Vous devez dans un premier temps envoyer le template (format docx obligatoirement), qui est la lettre contenant les variables qui pourront être remplacées.

Les variables doivent être de la forme ${ma_variable} . Elles ne peuvent contenir que les caractères a-z, A-Z, 0-9 et _.

Certaines variables, celles qui composent l'adresse postale, sont standardisées : 
${civilite}
${nom}
${prenom} 
${adresse1} 
${adresse2} 
${adresse3}
${cp}
${ville}
${pays}

Exemple de fichier conforme : https://www.merci-facteur.com/pro/exemples/fichier%20exemple.docx

Vous avez la possibilité d'envoyer le template via une URL distante, ou en base64.

En sortie, vous disposerez d'un json "templateValidation" contenant les informations à contrôler par vos soins (nombre de pages détectées, variables détectées, etc), ainsi qu'une clé de validation. Ce json devra ensuite être envoyé en même temps que la source.


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
- json de données, avec pour chaque adresse la variable en clé (type="json" et value=[{"civilite":"","societe":"","nom":"","prenom":"","adresse1":"","adresse2":"","adresse3":"","cp":"","ville":"","pays":""},{"civilite":"","societe":"","nom":"","prenom":"","adresse1":"","adresse2":"","adresse3":"","cp":"","ville":"","pays":""},etc.])

En plus de la source de données, vous devrez envoyer à cette phase le json "templateValidation" qui vous a été précédemment retourné, sans aucune modification.


### Envoi de la source (/sendPublipostage) :

La 3ème et dernière phase permet de valider le publipostage. Une fois cette phase executée, Merci facteur a le feu vert pour fusionner les données, générer les lettres, et pocéder à l'envoi de vos courriers.

Cette phase étant sensible, en plus de la validation par l'API vous avez aussi la possiblité, si vous le souhaitez, d'effectuer cette phase manuellement. Ce qui vous permettra de vérifier visuellement un échantillon de lettres une fois fusionnées.

Pour cela, connectez-vous à votre interface Merci facteur Pro, puis allez dans "Envoyer un publipostage". Vous trouverez alors les publipostages en attente de validation.
