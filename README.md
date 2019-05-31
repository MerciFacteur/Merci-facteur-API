# API envoi de courrier par La Poste
## merci-facteur-api
### API de Merci facteur

Version : 1.2.0

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

Les courriers envoyées via l'API d'envoi de courriers sont imprimées et postées le jour même, comme n'importe quel courrier envoyé via Merci facteur. Ils sont facturés au même prix qu'un courrier envoyé depuis l'interface Merci facteur PRO.

Documentation : https://www.merci-facteur.com/api/1.2/doc.php

En savoir plus : https://www.merci-facteur.com


### Processus de base d'un envoi

Pour réaliser un envoi de courrier, quelques étapes sont nécessaires au préalable : 

Création d'un utilisateur (facultatif) -> Création/sélection d'une adresse d'expéditeur -> Création/sélection d'une adresse de destinataire -> Génération de l'envoi

Une fois qu'un utilisateur est créé, il n'est pas nécessaire de le re-créer pour les prochains envois. Idem pour les adresses qui sont enregistrées dans un carnet d'adresses.


### Caractérisation d'un utilisateur

Un utilisateur se caractérise par un email, un nom et un prénom. Ces éléments vous permettrons d'identifier les utilisateurs (savoir qui est quel utiliasteur. Nous vous conseillons d'enregistrer en local le user ID de chaque utilisateur.

Généralement il s'agira d'un client pour vous. 

Chaque utilisateur à un carnet d'adresses avec l'ensemble des adresses qui ont été créées pour ses envoi (les adresses sont réutilisables pour les envois suivants), ainsi qu'un historique de ses envois.

Vous n'êtes pas obligé de créer plusieurs utilisateurs. Suivant votre fonctionnement vosu pouvez tout à fait n'avoir qu'un seul uitilisateur.


### Caractérisation d'un envoi

Un envoi est un ou plusieurs courrier(s) identique(s) qui est/sont envoyé(s) à un ou plusieurs destinataires. Un envoi peut donc être composé d'un ou plusieurs courriers. Mais le contenu et le mode d'envoi de chaque courriers d'un envoi seront identiques.

#### Exemples : 

Envoi 1 ->  destinataire 1
            destinataire 2
            destinataire 3

Envoi 2 ->  destinataire 1

Envoi 3 ->  destinataire 1
            destinataire 3

L'envoi 1 est composé de 3 courriers, l'envoi 2 est composé de 1 courrier, l'envoi 3 est composé de 2 courriers.


### Caractérisation du mode d'envoi

Lorsque vous réalisez un envoi, vous devez choisi le mode d'envoi du/des courrier(s) : 

- lrar : le courrier sera envoyé en recommandé avec avis de réception (valeur légale, l'éxpéditeur recevra l'avis de réception signé par le destinataire).
- suivi : le courrier est envoyé avec un suivi simple (permet de connaître la date de réception, mais sans valeur légale).
- normal : le courrier est envoyé sans aucun suivi (lettre verte).


### Spécifications relatives aux envois de lettres

Les lettres sont imprimées sur papier premium blanc de 80Gr/m², certifié pour la lutte contre la déforestation.

Vous pouvez envoyer jusqu'à 10 fichiers pour une même lettre (des PDF via leur URL et/ou des PDF en base64).

Seul le format PDF est accepté. Et le poids maximum par fichier est de 4 Mo. Le fichier peut-être en couleur ou en noir et blanc.


### Spécifications relatives aux envois de cartes illustrées

Les cartes sont imprimées sur papier épais haute qualité de 350Gr/m², certifié pour la lutte contre la déforestation.

#### 5 formats sont disponibles :
- Carte postale sans enveloppe (naked-postcard)
- Carte postale avec enveloppe (postcard)
- Carte pliée (folded)
- Carte non pliée (classic)
- Carte géante (large)

#### Dimensions (cm) des cartes après impression et coupe :
- Carte postale sans enveloppe : 11 x 16 cm (vertical ou horizontal)
- Carte postale avec enveloppe : 11 x 16 cm (vertical ou horizontal)
- Carte pliée : 15 x 21 cm fermée et 30 x 21 ouverte (vertical ou horizontal)
- Carte non pliée : 15 x 21 cm (vertical ou horizontal)
- Carte géante : 19 x 27 cm fermée et 38 x 21 ouverte (vertical ou horizontal)

#### Dimension (pixels) des fichiers acceptés :
- Dimension recommandée : 1200 x 1690 pixels (vertical ou horizontal)
- Dimension minimum : 800 x 1124 pixels (vertical ou horizontal)
- Dimension maximum : 4000 x 2840 pixels (vertical ou horizontal)
- Proportions à respecter : Petit côté = 0,71 x grand côté
- Exemples de dimensions : 800 x 1124 pixels, 1000 x 1408 pixels, 1500 x 2112 pixels

#### Types de fichiers acceptés :
Le visuel doit être envoyé soit via son URL, soit en base64.
- jpeg
- jpg
- Poids maximum de 4 Mo
- RVB conseillé


### Spécifications relatives aux envois de photos

Les photos sont imprimées sur du papier brillant supergloss de 250Gr/m², au format 10,5 x 14,85 cm.

#### Dimension (pixels) des fichiers acceptés :
- Dimension recommandée : 1200 x 1690 pixels (vertical ou horizontal)
- Dimension minimum : 800 x 1124 pixels (vertical ou horizontal)
- Dimension maximum : 4000 x 2840 pixels (vertical ou horizontal)
- Proportions à respecter : Petit côté = 0,71 x grand côté
- Exemples de dimensions : 800 x 1124 pixels, 1000 x 1408 pixels, 1500 x 2112 pixels

#### Types de fichiers acceptés :
Le visuel doit être envoyé soit via son URL, soit en base64.
- jpeg
- jpg
- Poids maximum de 4 Mo
- RVB conseillé


### Spécifications relatives aux enveloppes

Le choix de l'enveloppe se fait automatiquement, selon le contenu du courrier. Les enveloppes sont blanches.

4 formats sont possibles :
- C4 (lettres de plus de 10 pages, cartes géantes)
- C4 renforcée avec soufflet (lettres de plus de 50 pages)
- C5 (cartes classiques ou pliées, lettres de moins de 11 pages, plus de 10 photos)
- C6 (cartes postales, moins de 11 photos)
