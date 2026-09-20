# Envoi de cartes illustrées — contrat complet

Le principe et la règle « le dos s'envoie en image » sont dans le SKILL.md, section 3. Ce fichier donne le contrat exact et les contraintes de fichier, à vérifier **avant** l'appel : une carte hors gabarit part à l'impression et l'exemplaire est perdu.

## Le champ

```json
{
  "letter": "",
  "photo": "",
  "card": {
    "format": "postcard",
    "visuel": { "type": "base64", "value": "<jpeg en base64>" },
    "text":   { "type": "base64", "value": "<jpeg en base64>" },
    "coin": "carre",
    "papier": "classic"
  }
}
```

`visuel` est la face illustrée. `text` est la face imprimée du message — l'API l'appelle « le dos ».

Les clés sont en **français** : `visuel` (pas `visual`), `coin`, `papier`. Elles cohabitent avec `format` et `text` en anglais. C'est incohérent et c'est le contrat : ne l'harmonise pas.

`type` vaut `base64` (fichier encodé) ou `customimg` (URL publique). Même arbitrage que pour la lettre : **le base64 est le mode fiable**, une URL non téléchargeable par les serveurs de Merci Facteur échoue de leur côté, après coup.

## Le dos : une image, jamais du HTML

L'API accepte `text.type: "html"`. **Ne l'utilise pas.**

Le HTML est rendu sans que tu voies le résultat : un texte trop long déborde ou est coupé, une police manquante est substituée, une césure tombe où elle veut. Et l'échec ne se voit pas — la carte est imprimée, postée et facturée telle quelle. Le destinataire est le premier à découvrir la mise en page.

Compose le dos côté application, rends-le en image, envoie l'image. Tu contrôles alors exactement ce qui sera imprimé, et tu peux le montrer à l'utilisateur avant l'envoi.

Conséquence pratique : **les classes PHP publiées par Merci Facteur ne conviennent pas pour les cartes.** `php-class/apiMf.class.php` comme le `Content::carte()` du client v2 forcent `card.text.type = 'html'` et n'exposent qu'un paramètre de texte HTML — les variantes image sont inatteignables. Pour une carte, écris l'appel directement.

## Les formats

| `format` | Ce que c'est | Dimensions finies | Enveloppe auto |
|---|---|---|---|
| `postcard` | carte postale avec enveloppe | 10,5 × 15 cm | C6 |
| `classic` | carte non pliée | 14 × 19,5 cm | C5 |
| `folded` | carte pliée | 14 × 19,5 cm fermée, 28 × 19,5 ouverte | C5 |
| `square` | carte carrée | 14 × 14 cm | non documentée |
| `large` | carte géante pliée | 18,5 × 26 cm fermée, 37 × 26 ouverte | C4 |
| `large-a4` | carte géante A4 | 21 × 29,7 cm | C4 |

Vertical ou horizontal indifféremment, sauf la carte carrée. Impression sur papier épais 350 g/m², certifié contre la déforestation.

**`square` n'est pas dans la spec OpenAPI publiée.** Le format existe et l'API l'accepte, mais l'enum de `openapi.json` ne le contient pas. Un client généré depuis la spec le rejettera à la validation, et un agent qui ne lit que la spec ne le proposera jamais. Envoie la valeur `square` telle quelle.

**L'enum de l'API contient une septième valeur, `naked-postcard` — la carte postale sans enveloppe. Ne la propose pas et ne l'implémente pas :** sans enveloppe, la carte est manipulée à nu tout au long de l'acheminement et arrive abîmée. Si un utilisateur la demande explicitement, dis-lui pourquoi, et oriente-le vers `postcard`, qui est la même carte, protégée.

**`folded` et `large` sont pliées.** La face imprimée du message est l'intérieur. Avant une première série, fais valider ton gabarit de dos par le service client Merci Facteur : la géométrie exacte de la zone utile par format n'est pas publiée.

## Papier et coins

| Clé | Valeurs |
|---|---|
| `papier` | `classic`, `nacre`, `creation` |
| `coin` | `carre`, `arrondi` |

Ces valeurs ne s'inventent pas et ne se traduisent pas (`square`, `rounded`, `pearl` échouent — et `square` est un *format* de carte, pas une valeur de `coin`). Elles changent le prix : demande à l'utilisateur ce qu'il veut plutôt que de mettre un défaut en dur.

## Contraintes de fichier

Identiques pour le `visuel` et pour l'image de `text`.

| Contrainte | Valeur |
|---|---|
| Formats acceptés | `jpeg`, `jpg` — **pas de PDF, pas de PNG** |
| Poids maximum | 4 Mo par fichier |
| Espace colorimétrique | RVB conseillé |
| Dimension recommandée | 1200 × 1690 px |
| Dimension minimum | 800 × 1124 px |
| Dimension maximum | 4000 × 2840 px |
| Proportions | petit côté = 0,71 × grand côté |

Exemples de dimensions conformes : 800 × 1124, 1000 × 1408, 1500 × 2112.

La proportion 0,71 correspond aux formats rectangulaires — 10,5 × 15 donne 0,70, 14 × 19,5 donne 0,72, l'A4 donne 0,707. Un visuel conforme y passe sans recadrage visible. **Elle ne vaut pas pour `square` :** une carte carrée est au ratio 1, un visuel au ratio recommandé y perd près de 30 % de son grand côté, rogné sans validation de ta part. Pour une carte carrée, compose un visuel carré et garde le sujet centré ; le gabarit exact attendu par l'API pour ce format n'est pas publié, demande-le au service client avant une première série.

Dans tous les cas, prévois du fond perdu sur les bords, ne place ni texte ni logo à moins de quelques millimètres de la coupe, et si l'utilisateur téléverse son propre visuel, montre-lui le recadrage avant l'envoi plutôt que de le lui faire découvrir sur la carte.

## Le prix

La réponse de `sendCourrier` détaille le coût de la carte dans `price.detail.carteHt`, distinct de l'affranchissement. Utile pour afficher un récapitulatif après coup — mais c'est un retour, pas un devis : au moment où tu le lis, la carte est déjà partie à l'impression.
