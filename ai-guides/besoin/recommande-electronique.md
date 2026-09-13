# Envoyer un recommandé électronique (ERE) — API Merci Facteur

> Document de référence à donner à un assistant de développement (Claude, ChatGPT, Cursor, Copilot, Lovable, v0…).
> Il décrit **comment déclencher un envoi de recommandé électronique**. Il ne prescrit aucune architecture :
> formulaire, tâche planifiée, réaction à un événement — c'est le besoin de l'utilisateur qui décide.
> Aligné sur la spec OpenAPI `https://www.merci-facteur.com/api/1.2/openapi.json`.

Le recommandé électronique (ERE) répond aux exigences de l'article 43 du règlement (UE) eIDAS n° 910/2014 du 23 juillet 2014 et de l'article 48 du décret n° 2020-834 du 2 juillet 2020. Il s'agit d'un envoi recommandé électronique **de niveau simple**.

Rien n'est imprimé ni posté : le destinataire reçoit une notification, s'identifie par un code à usage unique, puis accède au document. Merci Facteur relance automatiquement par email un destinataire qui n'a pas ouvert son recommandé.

**C'est le même endpoint que pour une lettre papier.** Tout ce qui suit est identique à l'envoi de lettres, à trois choses près : le mode d'envoi, les coordonnées obligatoires, et le consentement. Ces différences sont regroupées en section 4.

---

## 1. Règles d'implémentation

**La secret key ne doit jamais atteindre le navigateur, une application mobile ou un dépôt public.** Tout appel à l'API part d'un composant serveur : route d'API, fonction serverless, worker, tâche planifiée, nœud HTTP d'un outil d'automatisation. Les identifiants vivent dans des variables d'environnement ou un gestionnaire de secrets.

**Ne « corrige » aucun nom de champ.** Plusieurs sont contre-intuitifs et échouent silencieusement si on les normalise :

| Piège | Règle |
|---|---|
| `adress` | Un seul « d ». Ce n'est pas `address`. |
| `adress` et `content` | Des **chaînes JSON**, dans un corps `application/x-www-form-urlencoded`. Pas des objets imbriqués. |
| Tableaux vides | Interdits. Le vide s'écrit `""`. Un `[]` déclenche `LETTER_INVALID_FILES` ou `LETTER_INVALID_BASE64_FILES`. |
| Adresses | Toutes les clés toujours présentes, les inutilisées à `""`. Sinon `INFO_ADDRESS_MISSING`. |
| `consent` | Un entier `1`, pas un booléen, pas la chaîne `"true"`. |
| `dateEnvoi` | Une date complète `AAAA-MM-JJ`, ou le champ totalement absent. Sinon `INVALID_DATE_ENVOI`. |
| PDF | En base64 de préférence, pas en URL. |

**Chaque appel réussi coûte de l'argent** et produit un envoi juridiquement opposable qu'on ne rappelle pas. Le comportement en cas d'échec est traité en section 7.

---

## 2. Authentification

Base URL : `https://www.merci-facteur.com/api/1.2/prod/service`

Trois identifiants, à récupérer sur le compte Merci Facteur Pro :

| Valeur | Où la trouver |
|---|---|
| service ID | onglet « API » |
| secret key | onglet « API » |
| user ID | menu « Utilisateurs » — l'utilisateur au nom duquel partent les envois |

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

## 3. Envoyer le recommandé

`POST /sendCourrier`

En-têtes : `ww-access-token` et `ww-service-id`.
Corps : `application/x-www-form-urlencoded`.

| Champ | Contenu |
|---|---|
| `idUser` | entier |
| `adress` | chaîne JSON : `{"exp":{…},"dest":[{…}]}` |
| `content` | chaîne JSON, voir ci-dessous |
| `modeEnvoi` | `ere_otp_mail` ou `ere_otp_sms` |
| `designation` | **visible par le destinataire**, 50 caractères max — voir section 4 |
| `dateEnvoi` | facultatif, `AAAA-MM-JJ` complet, date non passée |
| `antidoublon` | facultatif, 200 caractères max, voir section 6 |

Réponse : `{"success":true,"envoi_id":123,"price":{"total":{"ht":X,"ttc":Y}},"resume":{"nb_dest":N,"nb_page":P}}`
En cas d'échec : `{"success":false,"error":…}`

### Le contenu

```json
{
  "letter": {
    "files": "",
    "base64files": ["<pdf en base64>"],
    "final_filename": "contrat-souscription-2026"
  },
  "photo": "",
  "card": ""
}
```

Le vide s'écrit `""` à **tous** les niveaux, jamais `[]` ni `null` :

- pas de photo, pas de carte → `"photo": ""`, `"card": ""` ;
- document en base64 → `"files": ""`, `"base64files": ["…"]` ;
- document par URL → `"files": ["https://…"]`, `"base64files": ""`.

Format : PDF uniquement, jusqu'à 10 fichiers, 50 Mo par fichier.

`print_sides` n'a pas de sens ici : rien n'est imprimé.

### Base64 plutôt qu'URL

Les deux modes existent, mais **le base64 est le mode fiable**. Une URL signée de stockage n'est pas toujours téléchargeable par les serveurs de Merci Facteur, et l'échec survient de leur côté, après coup — donc difficile à diagnostiquer depuis l'application.

Récupère le PDF depuis ton propre serveur, encode-le, et envoie-le dans `base64files`. Le base64 ne contient ni retour à la ligne, ni préfixe `data:application/pdf;base64,`, ni marqueur de fin.

Le base64 gonfle le volume d'environ 33 % : au-delà de quelques mégaoctets, bascule sur `files` avec une URL **publique et non signée**. Et encode par tranches : convertir un tampon d'octets en base64 en une seule passe fait déborder la pile d'appels dès quelques centaines de kilo-octets.

---

## 4. Ce qui change par rapport à une lettre papier

Trois différences, et elles sont toutes bloquantes.

### Le mode d'envoi

| Valeur | Canal du code de vérification |
|---|---|
| `ere_otp_mail` | Le destinataire reçoit son code par **email** |
| `ere_otp_sms` | Le destinataire reçoit son code par **SMS** |

### Les coordonnées, sur le destinataire **et** sur l'expéditeur

Le champ correspondant au canal choisi est **obligatoire des deux côtés** :

| Mode | Champ obligatoire | Sur |
|---|---|---|
| `ere_otp_mail` | `email` | expéditeur **et** chaque destinataire |
| `ere_otp_sms` | `phone` | expéditeur **et** chaque destinataire |

C'est la différence la plus souvent manquée : un formulaire d'envoi papier ne collecte ni email ni téléphone, et une adresse d'expéditeur réutilisée d'un envoi papier n'en contient pas. Un envoi sans ces champs est rejeté.

En pratique, renseigne `email` et `phone` des deux côtés dès que tu les as : cela rend l'adresse utilisable dans les deux modes sans retoucher le code.

### Le consentement

Chaque destinataire porte `consent`, à la valeur entière `1`, qui signifie que **tu déclares avoir le consentement du destinataire** pour lui adresser un recommandé électronique.

Le consentement n'est pas requis pour un destinataire professionnel. Il l'est pour un particulier.

Ce n'est pas une formalité technique : c'est une déclaration. Ne code pas `consent: 1` en dur dans un envoi à des particuliers sans que le consentement ait réellement été recueilli et conservé quelque part.

### Deux champs deviennent visibles par le destinataire

Sur un envoi papier, `designation` et `content.letter.final_filename` ne servent qu'à retrouver un courrier dans l'interface Merci Facteur Pro. Sur un recommandé électronique, **ils apparaissent dans l'email reçu par le destinataire** :

- `final_filename` est le nom du fichier qu'il voit — sans extension, `.pdf` est ajouté ;
- `designation` est la désignation du courrier, 50 caractères maximum.

Traite-les comme du contenu destiné à un tiers. Une valeur technique du type `doc_4417_v2_final` s'affichera telle quelle chez le destinataire.

---

## 5. Les adresses

```json
{
  "civilite": "Monsieur",
  "nom": "Martin",
  "prenom": "Joël",
  "societe": "Exemple Corp",
  "adresse1": "33 allée de la Pâquerette",
  "adresse2": "",
  "adresse3": "",
  "cp": "75015",
  "ville": "Paris",
  "pays": "FRANCE",
  "email": "joel.martin@exemple.fr",
  "phone": "+33612345678",
  "consent": 1,
  "reference": "ma-ref-interne"
}
```

**Toutes les clés doivent être présentes**, dans l'expéditeur comme dans chaque destinataire. Les onze clés communes à tous les envois — `civilite`, `nom`, `prenom`, `societe`, `adresse1`, `adresse2`, `adresse3`, `cp`, `ville`, `pays`, `reference` — plus `email`, `phone` et `consent` pour un recommandé électronique. Celles qui ne servent pas valent `""`, elles ne sont jamais omises : une adresse partielle est rejetée avec `INFO_ADDRESS_MISSING`, même si les clés manquantes sont facultatives sur le papier.

Doivent en plus être **non vides** : `nom` ou `societe`, `cp`, `ville`, `pays`, et le champ de contact correspondant au mode d'envoi (section 4).

L'adresse postale reste obligatoire même si rien n'est posté.

Le pays doit être **exactement** l'une des valeurs de l'annexe. N'invente aucun libellé, ne traduis rien.

Limites : société 90 caractères, nom et prénom 70, lignes d'adresse 90, code postal 12, ville 70, civilité 12. `reference` est ta référence interne, 30 caractères maximum, limitée aux chiffres, lettres, espaces, `-` et `_` — c'est elle que tu retrouveras dans `ref_interne` sur les webhooks de suivi.

Plusieurs destinataires dans un même `dest` produisent **le même document** adressé à chacun : un envoi, plusieurs recommandés, un seul `envoi_id`.

---

## 6. `antidoublon` — protection optionnelle

`antidoublon` accepte une référence unique de ton choix, 200 caractères maximum. Si un second `sendCourrier` arrive avec la **même** valeur dans les 30 jours glissants, l'API retourne une erreur au lieu de produire un second envoi.

Le champ est facultatif, mais il détermine la stratégie de reprise de toute l'intégration :

- **sans** : aucune reprise automatique sûre n'est possible ;
- **avec** : un envoi dont l'issue est inconnue peut être rejoué à l'identique sans risque.

La valeur doit identifier **l'envoi**, pas la tentative : elle reste identique d'un essai à l'autre. Un numéro de contrat, un identifiant de dossier, l'identifiant d'une ligne en file d'attente conviennent. **Un UUID régénéré à chaque tentative ou un horodatage ne protègent de rien.**

Si aucune référence stable n'existe côté métier, omets le champ. N'en fabrique pas une.

---

## 7. Que faire quand ça échoue

Chaque appel réussi déclenche un envoi facturé. Un second appel identique produit un second recommandé — et un destinataire qui reçoit deux fois le même recommandé électronique pose un problème bien pire qu'un doublon papier. Trois situations, trois traitements :

| Ce qui s'est passé | L'envoi est-il parti ? | Que faire |
|---|---|---|
| L'API répond `success: false` | Non, avec certitude | Corriger et réessayer sans risque |
| Échec **avant** `sendCourrier` (validation locale, `/getToken` en échec) | Non, avec certitude | Réessayer sans risque |
| Timeout, coupure réseau, réponse illisible **pendant** `sendCourrier` | **Indéterminé** | Dépend d'`antidoublon` |

La troisième ligne est la seule qui compte. Un timeout ne signifie pas que l'envoi a échoué : la requête a pu aboutir côté Merci Facteur et la réponse se perdre au retour.

- **Avec `antidoublon`** : rejoue à l'identique, même valeur. Le second appel est refusé si le premier était passé. Trois tentatives espacées suffisent.
- **Sans `antidoublon`** : ne réessaie jamais automatiquement. Marque l'envoi « à vérifier » et laisse un humain trancher depuis l'interface Merci Facteur Pro.

Codes d'erreur fréquents et leur cause réelle :

| Code | Cause |
|---|---|
| `INFO_ADDRESS_MISSING` | une clé d'adresse absente, même facultative — sur un ERE, souvent `email`, `phone` ou `consent` |
| `LETTER_INVALID_FILES`, `LETTER_INVALID_BASE64_FILES` | un `[]` là où il fallait `""` |
| `INVALID_DATE_ENVOI` | `dateEnvoi` vide ou tronquée |

---

## 8. Suivre le recommandé après l'envoi

Un recommandé électronique a son propre cycle de vie, différent du papier. Les étapes remontent par **webhook** — voir [`webhooks.md`](webhooks.md) pour la mise en place.

| Événement | `statut_courrier` | Signification |
|---|---|---|
| `new` | `wait` | En attente d'envoi |
| `sended` | `envoye` | Envoyé au destinataire par Merci Facteur |
| `new-state` | `accept_wait` | En attente d'acceptation par le destinataire |
| `error` | `accept_lock` | Bloqué 3 heures après 6 échecs d'identification OTP |
| `error` | `accept_notification_error` | Notification impossible : email erroné ou boîte pleine — **fin d'acheminement** |
| `delivered` | `accepted` | Le destinataire a accepté et signé l'accusé de réception |
| `delivered` | `accepted_downloaded` | Accepté, signé, et document téléchargé |
| `pnd` | `expired` | Non accepté dans le délai imparti — **fin d'acheminement** |
| `pnd` | `refused` | Refusé par le destinataire — **fin d'acheminement** |

Merci Facteur envoie de lui-même des emails de rappel à un destinataire qui n'a pas ouvert son recommandé. Inutile de programmer tes propres relances tant que le statut est `accept_wait`.

Les trois fins d'acheminement autres que l'acceptation — `expired`, `refused`, `accept_notification_error` — sont celles qui ont une portée juridique pour l'expéditeur, et celles que les intégrations oublient le plus souvent de traiter. Un recommandé expiré n'est pas un envoi raté : c'est un fait à conserver.

---

## 9. Implémentations de référence

### JavaScript / TypeScript

Compatible Node 18+, Deno, Bun, Cloudflare Workers, Vercel, Netlify — tout runtime disposant de `fetch` et de la Web Crypto API. Sur Node 16 ou antérieur, remplace le bloc `crypto.subtle` par `crypto.createHmac("sha256", secretKey).update(serviceId + ts).digest("hex")`.

```js
const MF_BASE = "https://www.merci-facteur.com/api/1.2/prod/service";

const SERVICE_ID = process.env.MF_SERVICE_ID;
const SECRET_KEY = process.env.MF_SECRET_KEY;
const USER_ID = process.env.MF_USER_ID;
const AUTHORIZED_IP = process.env.MF_AUTHORIZED_IP || "111.111.111";

// 11 cles communes + les 3 specifiques au recommande electronique.
const ADDRESS_KEYS = [
  "civilite", "nom", "prenom", "societe",
  "adresse1", "adresse2", "adresse3",
  "cp", "ville", "pays", "reference",
  "email", "phone",
];

let cachedToken = null; // { token, expire }

// Signature : HMAC-SHA256(serviceId + timestamp), cle = secret key, sortie hexa minuscule.
async function hashSecretKey(secretKey, serviceId) {
  const ts = Math.floor(Date.now() / 1000); // secondes, pas millisecondes
  const key = await crypto.subtle.importKey(
    "raw", new TextEncoder().encode(secretKey),
    { name: "HMAC", hash: "SHA-256" }, false, ["sign"],
  );
  const sig = await crypto.subtle.sign(
    "HMAC", key, new TextEncoder().encode(`${serviceId}${ts}`),
  );
  const hash = Array.from(new Uint8Array(sig))
    .map((b) => b.toString(16).padStart(2, "0")).join("");
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

/**
 * Normalise une adresse pour un recommande electronique.
 * @param {object} input
 * @param {"ere_otp_mail"|"ere_otp_sms"} mode
 * @param {boolean} isDest  true pour un destinataire (ajoute consent)
 */
function normalizeEreAddress(input = {}, mode, isDest) {
  const out = {};
  for (const k of ADDRESS_KEYS) out[k] = String(input[k] ?? "").trim();

  if (!out.nom && !out.societe) throw new Error("Adresse : nom ou societe requis");
  for (const k of ["cp", "ville", "pays"]) {
    if (!out[k]) throw new Error(`Adresse : ${k} requis`);
  }

  // Le canal OTP doit etre renseigne des DEUX cotes : expediteur et destinataire.
  const canal = mode === "ere_otp_sms" ? "phone" : "email";
  if (!out[canal]) {
    throw new Error(`Adresse : ${canal} requis pour ${mode}`);
  }

  if (isDest) {
    // consent = 1 declare que le consentement du destinataire a ete recueilli.
    // Non requis pour un destinataire professionnel. Entier, pas booleen.
    out.consent = input.consent === 1 || input.consent === true ? 1 : 0;
  }
  return out;
}

function toBase64(bytes) {
  let binary = "";
  const CHUNK = 0x8000; // par tranches : une passe unique deborde la pile
  for (let i = 0; i < bytes.length; i += CHUNK) {
    binary += String.fromCharCode(...bytes.subarray(i, i + CHUNK));
  }
  return btoa(binary);
}

async function fetchPdfAsBase64(url) {
  const res = await fetch(url);
  if (!res.ok) throw new Error(`PDF inaccessible (${res.status}) : ${url}`);
  return toBase64(new Uint8Array(await res.arrayBuffer()));
}

/**
 * @param {object} o
 * @param {"ere_otp_mail"|"ere_otp_sms"} o.mode
 * @param {object}   o.expediteur      doit porter email (mail) ou phone (sms)
 * @param {object[]} o.destinataires   idem, plus consent
 * @param {string[]} [o.pdfUrls]       recuperees et encodees ici meme
 * @param {string[]} [o.pdfBase64s]    deja encodees
 * @param {string}   [o.designation]   VISIBLE par le destinataire
 * @param {string}   [o.finalFilename] VISIBLE par le destinataire, sans extension
 * @param {string}   [o.dateEnvoi]     AAAA-MM-JJ complet, ou rien
 * @param {string}   [o.antidoublon]   reference STABLE entre deux tentatives
 */
async function sendRecommandeElectronique(o) {
  const mode = o.mode ?? "ere_otp_mail";
  if (mode !== "ere_otp_mail" && mode !== "ere_otp_sms") {
    throw new Error("mode doit valoir ere_otp_mail ou ere_otp_sms");
  }
  if (!o.expediteur) throw new Error("Expediteur manquant");
  if (!Array.isArray(o.destinataires) || o.destinataires.length === 0) {
    throw new Error("Au moins un destinataire requis");
  }

  const base64files = [
    ...(o.pdfBase64s ?? []).map((b) =>
      b.replace(/^data:.*?;base64,/, "").replace(/\s+/g, "")),
    ...(await Promise.all((o.pdfUrls ?? []).map((u) => fetchPdfAsBase64(u)))),
  ];
  if (base64files.length === 0) throw new Error("Aucun PDF fourni");
  if (base64files.length > 10) throw new Error("10 PDF maximum");

  // files vaut "" et jamais [] : un tableau vide declenche LETTER_INVALID_FILES.
  const letter = { files: "", base64files };
  if (o.finalFilename) letter.final_filename = String(o.finalFilename).slice(0, 50);

  const token = await getToken();

  const body = new URLSearchParams();
  body.set("idUser", String(USER_ID));
  body.set("adress", JSON.stringify({
    exp: normalizeEreAddress(o.expediteur, mode, false),
    dest: o.destinataires.map((d) => normalizeEreAddress(d, mode, true)),
  }));
  body.set("content", JSON.stringify({ letter, photo: "", card: "" }));
  body.set("modeEnvoi", mode);
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

Appel :

```js
await sendRecommandeElectronique({
  mode: "ere_otp_mail",
  expediteur: {
    societe: "Ma Société", nom: "Dupont", prenom: "Sophie",
    adresse1: "9 allée de la Rose", cp: "78000", ville: "Versailles", pays: "FRANCE",
    email: "contact@masociete.fr",          // obligatoire en ere_otp_mail
  },
  destinataires: [{
    civilite: "Monsieur", nom: "Martin", prenom: "Joël",
    adresse1: "33 allée de la Pâquerette", cp: "75015", ville: "Paris", pays: "FRANCE",
    email: "joel.martin@exemple.fr",        // obligatoire en ere_otp_mail
    consent: 1,                             // consentement recueilli
    reference: "dossier-4417",
  }],
  pdfUrls: ["https://…/mise-en-demeure.pdf"],
  designation: "Mise en demeure",           // le destinataire la verra
  finalFilename: "mise-en-demeure",         // le destinataire la verra
  antidoublon: "dossier-4417-med1",
});
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
    "email", "phone",
]

_token_cache = None


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


def normalize_ere_address(a: dict, mode: str, is_dest: bool) -> dict:
    out = {k: str(a.get(k) or "").strip() for k in ADDRESS_KEYS}

    if not out["nom"] and not out["societe"]:
        raise ValueError("Adresse : nom ou societe requis")
    for k in ("cp", "ville", "pays"):
        if not out[k]:
            raise ValueError(f"Adresse : {k} requis")

    # Le canal OTP doit etre renseigne des DEUX cotes.
    canal = "phone" if mode == "ere_otp_sms" else "email"
    if not out[canal]:
        raise ValueError(f"Adresse : {canal} requis pour {mode}")

    if is_dest:
        # Entier 1, pas booleen. Declare que le consentement a ete recueilli.
        out["consent"] = 1 if a.get("consent") in (1, True) else 0
    return out


def send_recommande_electronique(
    expediteur: dict,
    destinataires: list,
    mode: str = "ere_otp_mail",
    pdf_urls: list = None,
    pdf_base64s: list = None,
    designation: str = None,     # VISIBLE par le destinataire
    final_filename: str = None,  # VISIBLE par le destinataire
    date_envoi: str = None,
    antidoublon: str = None,     # reference STABLE entre deux tentatives
) -> dict:
    if mode not in ("ere_otp_mail", "ere_otp_sms"):
        raise ValueError("mode doit valoir ere_otp_mail ou ere_otp_sms")
    if not destinataires:
        raise ValueError("Au moins un destinataire requis")

    base64files = [
        re.sub(r"\s+", "", re.sub(r"^data:.*?;base64,", "", b))
        for b in (pdf_base64s or [])
    ] + [
        base64.b64encode(requests.get(u, timeout=60).content).decode("ascii")
        for u in (pdf_urls or [])
    ]
    if not base64files:
        raise ValueError("Aucun PDF fourni")
    if len(base64files) > 10:
        raise ValueError("10 PDF maximum")

    # files vaut "" et jamais [] : un tableau vide declenche LETTER_INVALID_FILES.
    letter = {"files": "", "base64files": base64files}
    if final_filename:
        letter["final_filename"] = final_filename[:50]

    payload = {
        "idUser": USER_ID,
        "adress": json.dumps({
            "exp": normalize_ere_address(expediteur, mode, False),
            "dest": [normalize_ere_address(d, mode, True) for d in destinataires],
        }),
        "content": json.dumps({"letter": letter, "photo": "", "card": ""}),
        "modeEnvoi": mode,
    }
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

Merci Facteur publie une classe PHP prête à l'emploi :
`https://github.com/MerciFacteur/Merci-facteur-API` (`php-class/apiMf.class.php`).

Elle ne couvre ni `designation`, ni `dateEnvoi`, ni `antidoublon` : ajoute-les au tableau passé à `CURLOPT_POSTFIELDS` en suivant la section 3. Les champs `email`, `phone` et `consent` se placent simplement dans les objets d'adresse.

### Autres langages, outils no-code

Le contrat des sections 2 à 5 se transpose partout. Deux points d'attention avec un client HTTP générique (n8n, Make, Zapier, Postman) : le corps doit être `application/x-www-form-urlencoded` avec `adress` et `content` sérialisés en chaînes JSON, et la signature HMAC doit être calculée dans un nœud de code — la plupart des outils ne savent pas le faire dans un champ de formulaire.

---

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
