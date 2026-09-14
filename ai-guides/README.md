# Guides d'intégration pour assistants IA

Ce dossier contient des documents de référence conçus pour être **donnés tels quels à un assistant de développement** — Claude, ChatGPT, Cursor, Copilot, Lovable, v0 — afin qu'il sache intégrer l'API Merci Facteur sans se tromper.

Chaque fichier est autonome : il contient le contrat complet de la capacité qu'il décrit, authentification comprise. Inutile d'en coller plusieurs.

## Comment s'en servir

Colle le fichier correspondant à ton besoin dans ton assistant, puis précise ton langage, ton framework et ce qui doit déclencher l'envoi. Les guides décrivent **ce que l'API attend**, pas l'architecture de ton application : c'est toi qui décides s'il te faut un formulaire, une tâche planifiée ou une réaction à un événement.

Si tu préfères partir de la spec brute : `https://www.merci-facteur.com/api/1.2/openapi.json`

## Les guides

| Fichier | Pour |
|---|---|
| [`envoi-lettres.md`](besoin/envoi-lettres.md) | Envoyer une lettre à partir d'un ou plusieurs PDF — courrier simple, suivi ou recommandé avec avis de réception |
| [`recommande-electronique.md`](besoin/recommande-electronique.md) | Envoyer un recommandé électronique eIDAS, avec code de vérification par email ou par SMS |
| [`webhooks.md`](besoin/webhooks.md) | Recevoir les notifications de suivi : imprimé, distribué, retourné, accusé de réception signé |

D'autres guides suivront, par capacité : envoi de cartes illustrées, envoi de photos, publipostage. En attendant, ces capacités sont documentées dans le [README principal](../README.md) et dans la spec OpenAPI.

### Plateformes

Le dossier [`plateformes/`](plateformes/) contient des variantes adaptées à un environnement précis, quand celui-ci impose sa propre façon de tenir un secret côté serveur.

| Fichier | Pour |
|---|---|
| [`plateformes/lovable.md`](besoin/plateformes/lovable.md) | Envoyer une lettre depuis une application Lovable (Edge Function Lovable Cloud) |

## Ce que tous les guides ont en commun

Ces points reviennent dans chaque capacité de l'API. Ils sont répétés dans chaque guide, il n'y a rien à aller chercher ailleurs.

- **Authentification en deux temps** : la secret key est hashée en HMAC-SHA256 pour obtenir un token via `/getToken`, valable 24 h, réutilisé ensuite à chaque appel.
- **La secret key ne quitte jamais le serveur.** Aucun appel à l'API depuis un navigateur ou une application mobile.
- **Format des adresses** identique partout : 11 clés, toutes présentes, les inutilisées à la chaîne vide.
- **Le vide s'écrit `""`**, jamais `[]` ni `null`, à tous les niveaux du champ `content`.
- **Un envoi réussi est irréversible et facturé.** Chaque guide explique quoi faire quand un appel échoue — et quoi ne surtout pas faire.

## Un mot sur l'orthographe des champs

Plusieurs noms de champs sont contre-intuitifs et un assistant a tendance à les « corriger » spontanément, ce qui fait échouer les appels : `adress` prend un seul « d », `print_sides` prend un « s » final. Les guides le signalent explicitement — c'est aussi pour ça qu'ils existent.

## Signaler une erreur

Ces guides sont maintenus à jour avec l'API. Si l'un d'eux induit ton assistant en erreur, ouvre une issue en indiquant le fichier, le passage concerné et le code d'erreur obtenu.
