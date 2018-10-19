# merci-facteur-api
API de Merci facteur

Version : 1.2.0

L'API de Merci facteur vous permet d'intégrer à votre applicatif l'envoi de courriers par La Poste. 

La version actuelle de l'API permet de :
- Créer et gérer des utilisateurs ;
- Créer et gérer des carnets d'adresses ;
- Créer et gérer des utilisateurs ;
- Envoyer des lettres via des fichiers PDF ;
- Envoyer des lettres en suivi, recommandé avec avis de réception et envoi normal ;
- Gérer les lettres envoyées ; 

Les lettres envoyées via l'API sont imprimées et postées le jour même, comme n'importe quel courrier envoyé via Merci facteur. Ils sont facturés au même prix qu'un courrier envoyé depuis l'interface Merci facteur PRO.

Documentation : https://www.merci-facteur.com/api/1.2/doc.php

En savoir plus : https://www.merci-facteur.com


### Processus de base d'un envoi

Pour réaliser un envoi de courrier, quelques étapes sont nécessaires au préalable : 

Création d'un utilisateur -> Création d'une adresse d'expéditeur -> Création d'une adresse de destinataire -> Génération de l'envoi

Une fois qu'un utilisateur est créé, il n'est pas nécessaire de le re-créer pour les prochains envois 


### Caractérisation d'un utilisateur

Un utilisateur se caractérise par un email, un nom et un prénom. Ces éléments vous permettrons d'identifier les utilisateurs (savoir qui est quel utiliasteur. Nous vous conseillons d'enregistrer en local le user ID de chaque utilisateur.

Généralement il s'agira d'un client pour vous. 

Chaque utilisateur à un carnet d'adresses avec l'ensemble des adresses qui ont été créées pour ses envoi (les adresses sont réutilisables pour les envois suivants), ainsi qu'un historique de ses envois.


### Caractérisation d'un envoi

Un envoi est une lettre qui est envoyée à un ou plusieurs destinataires. Un envoi peut donc être composé d'un ou plusieurs courriers. Mais le contenu et le mode d'envoi de chaque courriers d'un envoi seront identiques.


### Caractérisation du mode d'envoi

Lorsque vous réalisez un envoi, vous devez choisi le mode d'envoi du/des courrier(s) : 

- lrar : le courrier sera envoyé en recommandé avec avis de réception (valeur légale, l'éxpéditeur recevra l'avis de réception signé par le destinataire).
- suivi : le courrier est envoyé avec un suivi simple (permet de connaître la date de réception, mais sans valeur légale).
- normal : le courrier est envoyé sans aucun suivi.
