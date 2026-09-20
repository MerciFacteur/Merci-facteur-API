# Intégrer l'envoi de cartes illustrées — API Merci Facteur

> Document de référence à donner à un assistant de développement (Claude, ChatGPT, Cursor, Copilot, Lovable, v0…).
> Il décrit **comment déclencher l'envoi d'une carte** avec l'API Merci Facteur. Il ne prescrit aucune architecture :
> formulaire, tâche planifiée, réaction à un événement — c'est le besoin de l'utilisateur qui décide.
> Aligné sur la spec OpenAPI `https://www.merci-facteur.com/api/1.2/openapi.json`, à une exception près, signalée en section 3.

L'API Merci Facteur permet d'imprimer et de poster une vraie carte — papier épais 350 g/m², mise sous enveloppe, postée par La Poste — depuis une application. Ce document couvre l'envoi de **cartes illustrées**. L'API sait aussi envoyer des lettres PDF, des tirages photo, des recommandés électroniques et des publipostages : voir la spec.

Un envoi tient en deux appels : `/getToken` une fois par 24 h (section 2), puis `/sendCourrier` (section 3). Le reste du document traite de ce qui fait échouer les intégrations : les pièges de nommage (section 1), l'idempotence (section 4) et le comportement en cas d'échec (section 5).

---

## 1. Règles d'implémentation

**La secret key ne doit jamais atteindre le navigateur, une application mobile ou un dépôt public.** Tout appel à l'API part d'un composant serveur : route d'API, fonction serverless, worker, tâche planifiée, nœud HTTP d'un outil d'automatisation. Le client de l'application appelle ce composant, jamais Merci Facteur directement. Les identifiants vivent dans des variables d'environnement ou un gestionnaire de secrets.

**Les deux faces de la carte s'envoient en image.** Le visuel comme le texte. L'API accepte aussi du HTML pour la face du message — ne l'utilise pas, voir section 3.

**Ne « corrige » aucun nom de champ.** Plusieurs sont contre-intuitifs et échouent silencieusement si on les normalise :

| Piège | Règle |
|---|---|
| `adress` | Un seul « d ». Ce n'est pas `address`. |
| `visuel` | En français, dans `content.card`. Ce n'est pas `visual`. |
| `coin`, `papier` | En français aussi, à côté de `format` et `text` en anglais. C'est le contrat, il n'est pas cohérent. |
| `adress` et `content` | Des **chaînes JSON**, dans un corps `application/x-www-form-urlencoded`. Pas des objets imbriqués. |
| Tableaux vides | Interdits. Le vide s'écrit `""` — `"letter": ""`, `"photo": ""` quand le courrier est une carte. Jamais `[]` ni `null`. |
| Adresses | Les 11 clés toujours présentes, les inutilisées à `""`. Sinon `INFO_ADDRESS_MISSING`. |
| `dateEnvoi` | Une date complète `AAAA-MM-JJ`, ou le champ totalement absent. Sinon `INVALID_DATE_ENVOI`. |
| Images | JPEG, en base64 de préférence, pas en URL. Ni PDF, ni PNG. |

**Chaque appel réussi coûte de l'argent** et produit un objet physique irrécupérable. Le comportement en cas d'échec est traité en section 5 — c'est la partie que la plupart des intégrations ratent.

---

## 2. Authentification

Base URL : `https://www.merci-facteur.com/api/1.2/prod/service`

Trois identifiants, à récupérer sur le compte Merci Facteur Pro :

| Valeur | Où la trouver |
|---|---|
| service ID | onglet « API » |
| secret key | onglet « API » |
| user ID | menu « Utilisateurs » — l'utilisateur au nom duquel partent les courriers |

### Obtenir un token

`GET /getToken` avec quatre en-têtes :

| En-tête | Valeur |
|---|---|
| `ww-service-signature` | la signature calculée ci-dessous |
| `ww-timestamp` | timestamp Unix en secondes, **exactement le même** que celui de la signature |
| `ww-service-id` | le service ID |
| `ww-authorized-ip` | IP autorisées séparées par `;`. Obligatoire même quand la restriction est levée : passer alors `111.111.111`. |

Réponse : `{"success":true,"token":"...","expire":<timestamp>}`

Le token est valable **24 h**. Stocke-le et réutilise-le jusqu'à `expire` — ne rappelle pas `/getToken` à chaque envoi.

Sur une infrastructure sans IP fixe (fonctions serverless, conteneurs éphémères, la plupart des PaaS), demande au support technique de Merci Facteur la **levée de la restriction d'IP**. Sans elle, `/getToken` échoue systématiquement.

### Calcul de la signature

La secret key ne transite jamais en clair : elle est la **clé** du HMAC, jamais le message.

1. Timestamp Unix courant en **secondes** — pas en millisecondes : `ts`.
2. Message = service ID et timestamp **concaténés**, sans séparateur : `serviceId + ts`. Exemple : `"abc123"` et `1757404800` donnent `"abc1231757404800"`.
3. HMAC-SHA256 de ce message, clé = la secret key.
4. Sortie en **hexadécimal minuscule** — pas en base64.
5. La même valeur de `ts` part dans `ww-timestamp`. Recalculer l'heure entre les deux fait échouer la signature au changement de seconde.

---

## 3. Envoyer une carte

`POST /sendCourrier`

En-têtes : `ww-access-token` et `ww-service-id`.
Corps : `application/x-www-form-urlencoded`.

| Champ | Contenu |
|---|---|
| `idUser` | entier |
| `adress` | chaîne JSON : `{"exp":{…},"dest":[{…}]}` |
| `content` | chaîne JSON, voir ci-dessous |
| `modeEnvoi` | `normal` (lettre verte), `suivi`, `lrar`, `lrare` |
| `dateEnvoi` | facultatif, `AAAA-MM-JJ` complet, date non passée |
| `designation` | facultatif, 50 caractères max, libellé de recherche dans l'interface Merci Facteur Pro |
| `antidoublon` | facultatif, 200 caractères max, voir section 4 |

Réponse : `{"success":true,"envoi_id":123,"price":{"total":{"ht":X,"ttc":Y}},"resume":{"nb_dest":N}}`
En cas d'échec : `{"success":false,"error":…}`

Une carte part normalement en `normal`. Les modes recommandés fonctionnent, mais ils transforment la réception en passage au guichet avec pièce d'identité — ce n'est presque jamais ce que veut l'expéditeur d'une carte. Ne propose un recommandé que si l'utilisateur le demande.

### Le contenu

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

`type` vaut `base64` (fichier encodé) ou `customimg` (URL publique). `letter` et `photo` valent `""` : le vide s'écrit `""` à **tous** les niveaux, jamais `[]` ni `null`.

### Le dos s'envoie en image, jamais en HTML

L'API accepte une troisième valeur pour `text.type` : `html`. **Ne l'utilise pas, et ne la propose pas à l'utilisateur.**

Le HTML est mis en page côté Merci Facteur, sans que personne ne voie le résultat avant impression : un texte trop long déborde ou est coupé, une police manquante est substituée, une césure tombe où elle veut. Et rien ne le signale — la carte est imprimée, postée et facturée telle quelle. Le destinataire est le premier à découvrir la mise en page.

Compose donc le dos côté application — rendu serveur, canvas, bibliothèque d'images, peu importe — et envoie le résultat en JPEG. Tu contrôles exactement ce qui sera imprimé, et tu peux le montrer à l'utilisateur avant l'envoi.

Conséquence pour les intégrations PHP : les classes publiées par Merci Facteur, `php-class/apiMf.class.php` comme le client v2, forcent `card.text.type = 'html'` et n'exposent qu'un paramètre de texte HTML. **Elles ne conviennent pas pour les cartes** — écris l'appel directement, comme en section 7.

### Les formats

| `format` | Ce que c'est | Dimensions finies | Enveloppe |
|---|---|---|---|
| `postcard` | carte postale | 10,5 × 15 cm | C6 |
| `classic` | carte non pliée | 14 × 19,5 cm | C5 |
| `folded` | carte pliée | 14 × 19,5 cm fermée, 28 × 19,5 ouverte | C5 |
| `square` | carte carrée | 14 × 14 cm | non documentée |
| `large` | carte géante pliée | 18,5 × 26 cm fermée, 37 × 26 ouverte | C4 |
| `large-a4` | carte géante A4 | 21 × 29,7 cm | C4 |

Vertical ou horizontal indifféremment, sauf la carte carrée. L'enveloppe est choisie automatiquement selon le format.

**`square` manque à la spec OpenAPI publiée.** Le format existe et l'API l'accepte, mais l'enum de `content.card.format` dans `openapi.json` ne le contient pas. Un client généré depuis la spec rejettera la valeur à la validation, et un assistant qui ne lit que la spec ne proposera jamais la carte carrée. Envoie `square` telle quelle — c'est la seule valeur de ce guide qui ne soit pas dans la spec.

**L'enum contient aussi `naked-postcard` — la carte postale sans enveloppe. Ne la propose pas et ne l'implémente pas :** sans enveloppe, la carte est manipulée à nu tout au long de l'acheminement et arrive abîmée. Si un utilisateur la demande, dis-lui pourquoi et oriente-le vers `postcard`, qui est la même carte, protégée.

`folded` et `large` sont pliées : la face du message est l'intérieur. La géométrie exacte de la zone utile n'est pas publiée — fais valider ton gabarit par le service client Merci Facteur avant une première série.

### Papier et coins

| Clé | Valeurs |
|---|---|
| `papier` | `classic`, `nacre`, `creation` |
| `coin` | `carre`, `arrondi` |

Ces valeurs ne se traduisent pas et ne s'inventent pas : `rounded` ou `pearl` font échouer l'envoi, et `square` est un *format* de carte, pas une valeur de `coin`. Elles changent le prix de la carte — demande à l'utilisateur ce qu'il veut plutôt que de figer un défaut.

### Les images

Mêmes contraintes pour le visuel et pour le dos.

| Contrainte | Valeur |
|---|---|
| Formats acceptés | `jpeg`, `jpg` — pas de PDF, pas de PNG |
| Poids maximum | 4 Mo par fichier |
| Espace colorimétrique | RVB conseillé |
| Dimension recommandée | 1200 × 1690 px |
| Dimension minimum | 800 × 1124 px |
| Dimension maximum | 4000 × 2840 px |
| Proportions | petit côté = 0,71 × grand côté |

Exemples de dimensions conformes : 800 × 1124, 1000 × 1408, 1500 × 2112.

Cette proportion correspond aux formats rectangulaires : 10,5 × 15 donne 0,70, 14 × 19,5 donne 0,72, l'A4 donne 0,707. Un visuel conforme y passe sans recadrage visible.

**Elle ne vaut pas pour `square`.** Une carte carrée est au ratio 1 : un visuel au ratio recommandé y perd près de 30 % de son grand côté, rogné sans validation de ta part. Compose un visuel carré, garde le sujet centré, et fais confirmer le gabarit attendu par le service client avant une première série — il n'est pas publié.

Dans tous les cas, prévois du fond perdu, ne place ni texte ni logo à quelques millimètres de la coupe, et si l'utilisateur téléverse son propre visuel, montre-lui le recadrage avant l'envoi plutôt que de le lui faire découvrir sur la carte.

**Base64 plutôt qu'URL.** Les deux modes existent, mais le base64 est le mode fiable. Une URL signée de stockage n'est pas toujours téléchargeable par les serveurs de Merci Facteur, et l'échec survient de leur côté, après coup — donc difficile à diagnostiquer depuis l'application. Le base64 ne contient ni retour à la ligne, ni préfixe `data:image/jpeg;base64,`, ni marqueur de fin. Sur la plupart des runtimes, convertir un tampon d'octets en base64 en une seule passe fait déborder la pile d'appels : encode par tranches, ou utilise la fonction native du langage qui gère les gros volumes.

### Les adresses

```json
{
  "civilite": "Monsieur",
  "nom": "Martin",
  "prenom": "Joël",
  "societe": "",
  "adresse1": "33 allée de la Pâquerette",
  "adresse2": "",
  "adresse3": "",
  "cp": "75015",
  "ville": "Paris",
  "pays": "FRANCE",
  "reference": "ma-ref-interne"
}
```

**Les 11 clés doivent toutes être présentes**, dans l'expéditeur comme dans chaque destinataire : `civilite`, `nom`, `prenom`, `societe`, `adresse1`, `adresse2`, `adresse3`, `cp`, `ville`, `pays`, `reference`. Celles qui ne servent pas valent `""`, elles ne sont jamais omises — une adresse partielle est rejetée avec `INFO_ADDRESS_MISSING`, même si les clés manquantes sont facultatives sur le papier.

Doivent en plus être **non vides** : `nom` ou `societe`, `cp`, `ville`, `pays`.

Le pays doit être **exactement** l'une des valeurs de l'annexe. N'invente aucun libellé, ne traduis rien.

Limites : société 90 caractères, nom et prénom 70, lignes d'adresse 90, code postal 12, ville 70, civilité 12. `reference` est une référence interne libre, 30 caractères maximum, limitée aux chiffres, lettres, espaces, `-` et `_`.

Plusieurs destinataires dans un même `dest` produisent **la même carte** envoyée à chacun : un envoi, plusieurs cartes, un seul `envoi_id`. C'est le cas classique des vœux ou d'une campagne de fidélisation — le visuel et le dos sont identiques pour tout le monde. Pour un dos personnalisé par destinataire, il faut un appel par destinataire, donc un `antidoublon` par destinataire.

---

## 4. `antidoublon` — protection optionnelle

`antidoublon` accepte une référence unique de ton choix, 200 caractères maximum. Si un second `sendCourrier` arrive avec la **même** valeur dans les 30 jours glissants, l'API retourne une erreur au lieu de produire un second courrier.

Le champ est facultatif, mais il détermine la stratégie de reprise de toute l'intégration :

- **sans** : aucune reprise automatique sûre n'est possible ;
- **avec** : un envoi dont l'issue est inconnue peut être rejoué à l'identique sans risque.

La valeur doit identifier **la carte**, pas la tentative : elle reste identique d'un essai à l'autre. L'identifiant du client plus celui de la campagne, l'identifiant d'une ligne en file d'attente conviennent. **Un UUID régénéré à chaque tentative ou un horodatage ne protègent de rien** — c'est l'erreur classique.

Si aucune référence stable n'existe côté métier, omets le champ. N'en fabrique pas une.

---

## 5. Que faire quand ça échoue

Chaque appel réussi imprime et poste une carte facturée. Un second appel identique produit une seconde carte. Trois situations, trois traitements :

| Ce qui s'est passé | La carte est-elle partie ? | Que faire |
|---|---|---|
| L'API répond `success: false` | Non, avec certitude | Corriger et réessayer sans risque |
| Échec **avant** `sendCourrier` (validation locale, `/getToken` en échec) | Non, avec certitude | Réessayer sans risque |
| Timeout, coupure réseau, réponse illisible **pendant** `sendCourrier` | **Indéterminé** | Dépend d'`antidoublon` |

La troisième ligne est la seule qui compte. Un timeout ne signifie pas que l'envoi a échoué : la requête a pu aboutir côté Merci Facteur et la réponse se perdre au retour.

- **Avec `antidoublon`** : rejoue à l'identique, même valeur. Le second appel est refusé si le premier était passé. Trois tentatives espacées suffisent.
- **Sans `antidoublon`** : ne réessaie jamais automatiquement. Marque l'envoi « à vérifier », sors-le de la file, laisse un humain trancher depuis l'interface Merci Facteur Pro.

Deux causes d'échec reviennent constamment, et aucune des deux ne vient de la carte elle-même : un `[]` là où l'API attend `""` (`"letter": []` au lieu de `"letter": ""`), et une clé d'adresse absente, même facultative, qui donne `INFO_ADDRESS_MISSING`. `INVALID_DATE_ENVOI` signale une `dateEnvoi` vide ou tronquée. La liste complète et à jour des codes est retournée par l'endpoint `listErrors`.

---

## 6. Contraintes à respecter, quel que soit le déclencheur

Ce que l'API impose à l'appelant, que l'envoi soit déclenché par un bouton, un cron, un événement en base ou un webhook.

**Le déclencheur n'appartient pas à l'appel.** Écris l'envoi comme une fonction ou un point d'entrée unique, sans hypothèse sur son appelant. C'est ce qui permet de brancher ensuite ce que l'utilisateur veut sans retoucher la partie API.

**Un envoi ne se rejoue pas librement.** Il n'existe aucun moyen de vérifier après coup si un envoi donné est passé. Une reprise automatique n'est sûre que dans les cas de la section 5 — et, pour le cas indéterminé, uniquement avec `antidoublon`. Toute forme de déclenchement répétable (retry, cron qui repasse, événement rejoué, double clic) doit donc soit porter un `antidoublon` stable, soit être empêchée en amont.

**Un envoi déclenché sans témoin doit laisser une trace.** Un envoi lancé par un système, contrairement à un envoi lancé par un humain, n'a personne pour constater son échec. Conserve au minimum l'`envoi_id` retourné, ou l'erreur, associés à ce qui a déclenché l'envoi — sans quoi un échec passe inaperçu.

**Le volume se plafonne.** Chaque appel réussi imprime et facture un objet physique. Une boucle accidentelle dans un déclencheur automatique produit de vraies cartes. Prévois une limite de volume par période et un refus au-delà.

**Le temps d'exécution compte.** Composer le dos, encoder deux images en base64 puis les transmettre prend du temps. Sur une plateforme à timeout court, traite un envoi par invocation plutôt qu'un lot — et prépare les images en amont plutôt que dans la requête qui envoie.

### Recevoir l'état des courriers

Merci Facteur pousse les événements — imprimé, distribué, non distribué (NPAI), AR signé — par **webhook**, sur une URL déclarée dans l'onglet « API » de l'interface Merci Facteur Pro.

Le webhook arrive en POST avec deux champs : `event` (type d'événement, id utilisateur, date) et `detail` (tableau des courriers concernés : adresse, `ref_courrier`, `ref_interne`, `mode_envoi`, `id_envoi`, statut). Un webhook ne porte qu'un seul type d'événement mais peut concerner plusieurs courriers.

L'endpoint doit répondre **200**, avec un corps de moins de 500 caractères. Il n'est pas appelé par un utilisateur connecté : ne lui applique pas l'authentification de l'application, vérifie l'origine autrement.

C'est la seule voie de retour. Une intégration qui envoie sans écouter les webhooks ne saura jamais qu'une carte est revenue en NPAI.

---

## 7. Implémentations de référence

### JavaScript / TypeScript

Compatible Node 18+, Deno, Bun, Cloudflare Workers, Vercel, Netlify — tout runtime disposant de `fetch` et de la Web Crypto API. Sur Node 16 ou antérieur, remplace le bloc `crypto.subtle` par `crypto.createHmac("sha256", secretKey).update(serviceId + ts).digest("hex")`.

```js
const MF_BASE = "https://www.merci-facteur.com/api/1.2/prod/service";

const SERVICE_ID = process.env.MF_SERVICE_ID;
const SECRET_KEY = process.env.MF_SECRET_KEY;
const USER_ID = process.env.MF_USER_ID;
const AUTHORIZED_IP = process.env.MF_AUTHORIZED_IP || "111.111.111";

const ADDRESS_KEYS = [
  "civilite", "nom", "prenom", "societe",
  "adresse1", "adresse2", "adresse3",
  "cp", "ville", "pays", "reference",
];

// square (carte carree) est accepte par l'API mais absent de la spec OpenAPI.
// naked-postcard existe dans l'API et n'est pas propose : sans enveloppe,
// la carte est manipulee a nu pendant l'acheminement et arrive abimee.
const FORMATS = ["postcard", "classic", "folded", "square", "large", "large-a4"];
const PAPIERS = ["classic", "nacre", "creation"];
const COINS = ["carre", "arrondi"];

let cachedToken = null; // { token, expire }

// Signature : HMAC-SHA256(serviceId + timestamp), cle = secret key, sortie hexa minuscule.
async function hashSecretKey(secretKey, serviceId) {
  const ts = Math.floor(Date.now() / 1000); // secondes, pas millisecondes
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secretKey),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const sig = await crypto.subtle.sign(
    "HMAC", key, new TextEncoder().encode(`${serviceId}${ts}`),
  );
  const hash = Array.from(new Uint8Array(sig))
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("");
  return { ts, hash };
}

async function getToken() {
  const now = Math.floor(Date.now() / 1000);
  if (cachedToken && cachedToken.expire > now + 60) return cachedToken.token;

  const { ts, hash } = await hashSecretKey(SECRET_KEY, SERVICE_ID);
  const res = await fetch(`${MF_BASE}/getToken`, {
    headers: {
      "ww-service-signature": hash,
      "ww-timestamp": String(ts),   // le meme ts que celui signe
      "ww-service-id": SERVICE_ID,
      "ww-authorized-ip": AUTHORIZED_IP,
    },
  });
  const data = await res.json();
  if (!data?.success || !data.token) {
    throw new Error(`getToken: ${JSON.stringify(data?.error ?? res.status)}`);
  }
  cachedToken = { token: data.token, expire: data.expire ?? now + 86400 };
  return data.token;
}

// Les 11 cles doivent etre presentes, les inutilisees a "".
function normalizeAddress(input = {}) {
  const out = {};
  for (const k of ADDRESS_KEYS) out[k] = String(input[k] ?? "").trim();
  if (!out.nom && !out.societe) throw new Error("Adresse : nom ou societe requis");
  for (const k of ["cp", "ville", "pays"]) {
    if (!out[k]) throw new Error(`Adresse : ${k} requis`);
  }
  return out;
}

// Encodage par tranches : une conversion en une passe deborde la pile.
function toBase64(bytes) {
  let binary = "";
  const CHUNK = 0x8000;
  for (let i = 0; i < bytes.length; i += CHUNK) {
    binary += String.fromCharCode(...bytes.subarray(i, i + CHUNK));
  }
  return btoa(binary);
}

async function fetchJpegAsBase64(url) {
  const res = await fetch(url);
  if (!res.ok) throw new Error(`Image inaccessible (${res.status}) : ${url}`);
  const bytes = new Uint8Array(await res.arrayBuffer());
  if (bytes.length > 4 * 1024 * 1024) throw new Error(`Image > 4 Mo : ${url}`);
  return toBase64(bytes);
}

// Une face = { base64 } ou { url }. JPEG uniquement.
// Le HTML est volontairement absent : le rendu echappe a l'application,
// et une carte mal mise en page part quand meme a l'impression.
async function buildFace(face, label) {
  if (face?.base64) {
    return {
      type: "base64",
      value: String(face.base64).replace(/^data:.*?;base64,/, "").replace(/\s+/g, ""),
    };
  }
  if (face?.url) {
    return { type: "base64", value: await fetchJpegAsBase64(face.url) };
  }
  throw new Error(`${label} : fournis { base64 } ou { url } (JPEG)`);
}

/**
 * @param {object} o
 * @param {object}   o.visuel        { base64 } ou { url } — la face illustree
 * @param {object}   o.texte         { base64 } ou { url } — la face du message
 * @param {string}   o.format        postcard | classic | folded | square | large | large-a4
 * @param {string}   [o.papier="classic"]
 * @param {string}   [o.coin="carre"]
 * @param {object}   o.expediteur
 * @param {object[]} o.destinataires
 * @param {string}   [o.modeEnvoi="normal"]
 * @param {string}   [o.dateEnvoi]   AAAA-MM-JJ complet, ou rien
 * @param {string}   [o.designation]
 * @param {string}   [o.antidoublon] reference STABLE entre deux tentatives
 */
async function sendCarte(o) {
  if (!o.expediteur) throw new Error("Expediteur manquant");
  if (!Array.isArray(o.destinataires) || o.destinataires.length === 0) {
    throw new Error("Au moins un destinataire requis");
  }
  if (!FORMATS.includes(o.format)) {
    throw new Error(`format invalide : ${o.format}. Valeurs : ${FORMATS.join(", ")}`);
  }
  const papier = o.papier ?? "classic";
  const coin = o.coin ?? "carre";
  if (!PAPIERS.includes(papier)) throw new Error(`papier invalide : ${papier}`);
  if (!COINS.includes(coin)) throw new Error(`coin invalide : ${coin}`);

  // Cles en francais : visuel, coin, papier. Ne pas les traduire.
  const card = {
    format: o.format,
    visuel: await buildFace(o.visuel, "visuel"),
    text: await buildFace(o.texte, "texte"),
    coin,
    papier,
  };

  const token = await getToken();

  const body = new URLSearchParams();
  body.set("idUser", String(USER_ID));
  body.set("adress", JSON.stringify({
    exp: normalizeAddress(o.expediteur),
    dest: o.destinataires.map((d) => normalizeAddress(d)),
  }));
  // letter et photo valent "" et jamais [] : un tableau vide fait echouer l'appel.
  body.set("content", JSON.stringify({ letter: "", photo: "", card }));
  body.set("modeEnvoi", o.modeEnvoi ?? "normal");
  // Date complete, ou champ absent : une valeur tronquee donne INVALID_DATE_ENVOI.
  if (typeof o.dateEnvoi === "string" && /^\d{4}-\d{2}-\d{2}$/.test(o.dateEnvoi)) {
    body.set("dateEnvoi", o.dateEnvoi);
  }
  if (o.designation) body.set("designation", String(o.designation).slice(0, 50));
  if (o.antidoublon) body.set("antidoublon", String(o.antidoublon).slice(0, 200));

  const res = await fetch(`${MF_BASE}/sendCourrier`, {
    method: "POST",
    headers: {
      "ww-access-token": token,
      "ww-service-id": SERVICE_ID,
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body,
  });

  const data = await res.json();
  if (!data?.success) {
    cachedToken = null; // le token a pu etre invalide cote serveur
    const err = new Error(`sendCourrier: ${JSON.stringify(data?.error ?? "UNKNOWN")}`);
    err.sent = false;   // l'API a repondu : rien n'est parti, rejouable
    throw err;
  }
  return data;
}
```

### Python

```python
import base64, hashlib, hmac, json, os, re, time
import requests

MF_BASE = "https://www.merci-facteur.com/api/1.2/prod/service"

SERVICE_ID = os.environ["MF_SERVICE_ID"]
SECRET_KEY = os.environ["MF_SECRET_KEY"]
USER_ID = os.environ["MF_USER_ID"]
AUTHORIZED_IP = os.environ.get("MF_AUTHORIZED_IP", "111.111.111")

ADDRESS_KEYS = [
    "civilite", "nom", "prenom", "societe",
    "adresse1", "adresse2", "adresse3",
    "cp", "ville", "pays", "reference",
]

# square (carte carree) est accepte par l'API mais absent de la spec OpenAPI.
# naked-postcard existe dans l'API et n'est pas propose : sans enveloppe,
# la carte est manipulee a nu pendant l'acheminement et arrive abimee.
FORMATS = ("postcard", "classic", "folded", "square", "large", "large-a4")
PAPIERS = ("classic", "nacre", "creation")
COINS = ("carre", "arrondi")

MAX_BYTES = 4 * 1024 * 1024

_token_cache = None  # {"token": str, "expire": int}


def hash_secret_key(secret_key: str, service_id: str):
    """HMAC-SHA256(service_id + timestamp), cle = secret key, sortie hexa minuscule."""
    ts = int(time.time())  # secondes
    digest = hmac.new(
        secret_key.encode("utf-8"),
        f"{service_id}{ts}".encode("utf-8"),
        hashlib.sha256,
    ).hexdigest()
    return ts, digest


def get_token() -> str:
    global _token_cache
    now = int(time.time())
    if _token_cache and _token_cache["expire"] > now + 60:
        return _token_cache["token"]

    ts, signature = hash_secret_key(SECRET_KEY, SERVICE_ID)
    res = requests.get(
        f"{MF_BASE}/getToken",
        headers={
            "ww-service-signature": signature,
            "ww-timestamp": str(ts),  # le meme ts que celui signe
            "ww-service-id": SERVICE_ID,
            "ww-authorized-ip": AUTHORIZED_IP,
        },
        timeout=30,
    )
    data = res.json()
    if not data.get("success") or not data.get("token"):
        raise RuntimeError(f"getToken: {data.get('error', res.status_code)}")

    _token_cache = {"token": data["token"], "expire": data.get("expire", now + 86400)}
    return data["token"]


def normalize_address(a: dict) -> dict:
    """Les 11 cles doivent etre presentes, les inutilisees a ""."""
    out = {k: str(a.get(k) or "").strip() for k in ADDRESS_KEYS}
    if not out["nom"] and not out["societe"]:
        raise ValueError("Adresse : nom ou societe requis")
    for k in ("cp", "ville", "pays"):
        if not out[k]:
            raise ValueError(f"Adresse : {k} requis")
    return out


def jpeg_url_to_base64(url: str) -> str:
    res = requests.get(url, timeout=60)
    res.raise_for_status()
    if len(res.content) > MAX_BYTES:
        raise ValueError(f"Image > 4 Mo : {url}")
    return base64.b64encode(res.content).decode("ascii")


def build_face(face: dict, label: str) -> dict:
    """Une face = {"base64": ...} ou {"url": ...}. JPEG uniquement.

    Le HTML est volontairement absent : le rendu echappe a l'application,
    et une carte mal mise en page part quand meme a l'impression.
    """
    if face.get("base64"):
        value = re.sub(r"\s+", "", re.sub(r"^data:.*?;base64,", "", face["base64"]))
        return {"type": "base64", "value": value}
    if face.get("url"):
        return {"type": "base64", "value": jpeg_url_to_base64(face["url"])}
    raise ValueError(f'{label} : fournis {{"base64": ...}} ou {{"url": ...}} (JPEG)')


def send_carte(
    expediteur: dict,
    destinataires: list,
    visuel: dict,          # {"base64": ...} ou {"url": ...} — la face illustree
    texte: dict,           # {"base64": ...} ou {"url": ...} — la face du message
    format_carte: str,     # postcard | classic | folded | square | large | large-a4
    papier: str = "classic",
    coin: str = "carre",
    mode_envoi: str = "normal",
    date_envoi: str = None,
    designation: str = None,
    antidoublon: str = None,   # reference STABLE entre deux tentatives
) -> dict:
    if not destinataires:
        raise ValueError("Au moins un destinataire requis")
    if format_carte not in FORMATS:
        raise ValueError(f"format invalide : {format_carte}. Valeurs : {', '.join(FORMATS)}")
    if papier not in PAPIERS:
        raise ValueError(f"papier invalide : {papier}")
    if coin not in COINS:
        raise ValueError(f"coin invalide : {coin}")

    # Cles en francais : visuel, coin, papier. Ne pas les traduire.
    card = {
        "format": format_carte,
        "visuel": build_face(visuel, "visuel"),
        "text": build_face(texte, "texte"),
        "coin": coin,
        "papier": papier,
    }

    payload = {
        "idUser": USER_ID,
        "adress": json.dumps({
            "exp": normalize_address(expediteur),
            "dest": [normalize_address(d) for d in destinataires],
        }),
        # letter et photo valent "" et jamais [] : un tableau vide fait echouer l'appel.
        "content": json.dumps({"letter": "", "photo": "", "card": card}),
        "modeEnvoi": mode_envoi,
    }
    # Date complete, ou cle absente : une valeur tronquee donne INVALID_DATE_ENVOI.
    if date_envoi and re.fullmatch(r"\d{4}-\d{2}-\d{2}", date_envoi):
        payload["dateEnvoi"] = date_envoi
    if designation:
        payload["designation"] = designation[:50]
    if antidoublon:
        payload["antidoublon"] = antidoublon[:200]

    res = requests.post(
        f"{MF_BASE}/sendCourrier",
        headers={"ww-access-token": get_token(), "ww-service-id": SERVICE_ID},
        data=payload,  # requests encode en application/x-www-form-urlencoded
        timeout=120,
    )
    data = res.json()
    if not data.get("success"):
        global _token_cache
        _token_cache = None
        raise RuntimeError(f"sendCourrier: {data.get('error', 'UNKNOWN')}")
    return data
```

### PHP

Merci Facteur publie deux classes PHP : `php-class/apiMf.class.php` et le client v2 du même dépôt.

**N'utilise ni l'une ni l'autre pour les cartes** : toutes deux forcent `card.text.type = 'html'` et n'exposent qu'un paramètre de texte HTML, donc le seul chemin accessible est celui que la section 3 déconseille. Écris l'appel en cURL en suivant les sections 2 et 3.

### Autres langages, outils no-code

Le contrat des sections 2 et 3 se transpose partout. Deux points d'attention avec un client HTTP générique (n8n, Make, Zapier, Postman) : le corps doit être `application/x-www-form-urlencoded` avec `adress` et `content` sérialisés en chaînes JSON, et la signature HMAC doit être calculée dans un nœud de code — la plupart des outils ne savent pas le faire dans un champ de formulaire. Le poids du base64 de deux images compte double dans ces outils, qui gardent souvent tout en mémoire : prépare les images à la bonne dimension avant de les encoder.

## Annexe — valeurs autorisées pour le champ `pays`

Le champ `pays` n'accepte que ces valeurs, à l'identique. Toute autre orthographe fait échouer l'envoi.

```
AFGHANISTAN
AFRIQUE DU SUD
ALBANIE
ALGERIE
ALLEMAGNE
ANDORRE
ANGLETERRE
ANGOLA
ANGUILLA
ANTIGUA-ET-BARBUDA
ANTILLES NEERL
ARABIE SAOUDITE
ARGENTINE
ARMENIE
AUSTRALIE
AUTRICHE
AZERBAIDJAN
BAHAMAS
BAHREIN
BANGLADESH
BARBADE
BELGIQUE
BENIN
BERMUDES
BHOUTAN
BIELORUSSIE
BIRMANIE
BOLIVIE
BOSNIE-HERZEGOVINE
BOTSWANA
BRESIL
BULGARIE
BURKINA FASO
BURUNDI
CAMBODGE
CAMEROUN
CANADA
CAP-VERT
CENTRAFRIQUE
CHILI
CHINE
CHYPRE
CLIPPERTON
COLOMBIE
COMORES
COREE DU SUD - REPUBLIQUE DE COREE
COSTA RICA
COTE D IVOIRE
CROATIE
CUBA
DANEMARK
DJIBOUTI
DOMINIQUE
EGYPTE
EMIRAT ARABE UNIS
EQUATEUR
ERYTHREE
ESPAGNE
ESTONIE
ETATS-UNIS
ETHIOPIE
FIDJI
FINLANDE
FRANCE
GABON
GAMBIE
GEORGIE
GHANA
GIBRALTAR
GRANDE BRETAGNE
GRECE
GRENADE
GUADELOUPE
GUATEMALA
GUERNESEY
GUINEE
GUYANA
GUYANE FRANCAISE
HAITI
HONDURAS
HONG KONG
HONG-KONG
HONGRIE
ILE DE MAN
ILES CAIMANES
ILES COOK
ILES FEROE
ILES SALOMON
ILES VIERGES BRIT
ILES VIERGES US
INDE
INDONESIE
IRAK
IRAN
IRLANDE
ISLANDE
ISRAEL
ITALIE
JAMAIQUE
JAPON
JERSEY
JORDANIE
KAZAKHSTAN
KENYA
KOSOVO
KOWEIT
LAOS
LESOTHO
LETTONIE
LIBAN
LIBERIA
LIECHTENSTEIN
LITUANIE
LUXEMBOURG
MACAO
MACEDOINE
MADAGASCAR
MALAISIE
MALAWI
MALDIVES
MALI
MALTE
MAROC
MARTINIQUE
MAURICE
MAURITANIE
MAYOTTE
MEXIQUE
MOLDAVIE
MONACO
MONGOLIE
MONTENEGRO
MOZAMBIQUE
NAMIBIE
NEPAL
NICARAGUA
NIGER
NIGERIA
NORVEGE
NOUVELLE CALEDONIE
NOUVELLE-ZELANDE
OMAN
OUGANDA
OUZBEKISTAN
PAKISTAN
PALAOS
PALESTINE
PANAMA
PAPOUASIE-NOUVELLE-GUINEE
PARAGUAY
PAYS-BAS
PEROU
PHILIPPINES
POLOGNE
POLYNESIE FRANCAISE
PORTO RICO
PORTUGAL
QATAR
REP DEMOCRATIQUE DU CONGO
REP DOMINICAINE
REP DU CONGO
REP POP DE COREE
REPUBLIQUE TCHEQUE
REUNION
ROUMANIE
ROYAUME-UNI
RUSSIE
RWANDA
SAHARA OCCIDENTAL
SAINT BARTHELEMY
SAINT MARTIN
SAINT-KITTS-ET-NEVIS
SAINT-MARIN
SAINTE-HELENE
SAINTE-LUCIE
SALVADOR
SAMOA
SAMOA AMERICAINES
SENEGAL
SERBIE
SEYCHELLES
SIERRA LEONE
SINGAPOUR
SLOVAQUIE
SLOVENIE
SOMALIE
SOUDAN
SRI LANKA
ST-PIERRE-MIQUELON
SUEDE
SUISSE
SURINAM
SWAZILAND
SYRIE
TADJIKISTAN
TAIWAN
TANZANIE
TCHAD
TERRES AUSTRALES FR
THAILANDE
TIMOR-LESTE
TOGO
TONGA
TRINITE-ET-TOBAGO
TUNISIE
TURKMENISTAN
TURQUIE
UKRAINE
URUGUAY
VANUATU
VATICAN
VENEZUELA
VIET NAM
VIETNAM
WALLIS ET FUTUNA
YEMEN
ZAMBIE
ZIMBABWE
```
