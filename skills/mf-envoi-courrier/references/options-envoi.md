# Options facultatives de sendCourrier

Quatre paramètres facultatifs de `sendCourrier` qui résolvent des problèmes réels. Ne les ajoute pas d'office — propose celui qui correspond au besoin exprimé.

## `gestionNpai` — faire revenir les plis non distribués

```json
{ "gestionNpai": 1 }
```

Un courrier non distribué (adresse fausse, boîte inaccessible, recommandé non réclamé) repart normalement vers l'expéditeur, en papier. Avec `gestionNpai`, il revient chez Merci Facteur, y est numérisé, et l'information remonte par webhook — puis il est archivé 3 ans.

S'applique aux modes `normal` et `suivi`. Pour un recommandé, la fonction équivalente est le mode `lrare`, pas ce paramètre.

C'est l'option à proposer dès que l'application doit **réagir** à un NPAI : suspendre une relance, marquer une adresse comme invalide, alerter un gestionnaire. Sans elle, le pli revient dans une boîte aux lettres physique et le logiciel n'en sait rien.

## `anonymize` — effacer les données après l'envoi

```json
{ "anonymize": { "delay": 15, "target": ["content", "exp", "dest"] } }
```

Supprime des serveurs Merci Facteur le contenu du courrier et/ou les adresses, `delay` jours après l'impression. `delay` va de 1 à 40. `target` accepte `content`, `exp`, `dest`, dans n'importe quelle combinaison.

La trace de l'envoi subsiste dans l'interface (date, suivi), mais les données personnelles sont supprimées.

À proposer dès que le courrier transporte des données sensibles ou que l'utilisateur évoque le RGPD, une politique de rétention, ou un DPO. `{"delay":1,"target":["dest"]}` suffit souvent.

## `enveloppe` — enveloppe personnalisée, et logo de l'expéditeur

```json
{ "enveloppe": { "type": "template", "value": "123456" } }
```

`value` est l'ID d'une enveloppe créée dans « Votre branding » sur le compte Merci Facteur Pro. Sans ce paramètre, l'enveloppe est blanche et son format choisi automatiquement selon le contenu (C6, C5, C4, ou C4 renforcée).

**C'est aussi la réponse au logo de l'expéditeur.** Le logo se place sur l'enveloppe personnalisée, au moment où elle est créée dans « Votre branding ». Il n'a donc rien à faire dans le JSON d'adresse envoyé à `sendCourrier` : côté API, tout le branding tient dans cet ID d'enveloppe.

Si l'utilisateur demande « comment je mets mon logo sur l'enveloppe », la réponse est : créer l'enveloppe personnalisée dans l'interface, puis passer son ID ici. Rien à coder de plus.

L'ID ne s'invente pas, il se lit dans l'interface. Demande-le plutôt que de mettre un exemple en dur — et une fois obtenu, c'est une constante de configuration, pas une valeur à recalculer à chaque envoi.

## `designation` — retrouver le courrier plus tard

50 caractères, visible dans l'interface Merci Facteur Pro comme libellé de recherche. Sur un recommandé électronique, elle est **aussi visible par le destinataire** dans l'email qu'il reçoit — comme `content.letter.final_filename`. Ne mets donc pas de référence technique interne dans ces deux champs sur un envoi ERE.
