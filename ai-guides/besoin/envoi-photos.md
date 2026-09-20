# Intégrer l'envoi de photos — API Merci Facteur

> Document de référence à donner à un assistant de développement (Claude, ChatGPT, Cursor, Copilot, Lovable, v0…).
> Il décrit **comment déclencher un envoi de photos** avec l'API Merci Facteur. Il ne prescrit aucune architecture :
> formulaire, tâche planifiée, réaction à un événement — c'est le besoin de l'utilisateur qui décide.
> Aligné sur la spec OpenAPI `https://www.merci-facteur.com/api/1.2/openapi.json`.

L'API Merci Facteur permet d'envoyer de vrais tirages photo — imprimés et postés par La Poste — depuis une application. Ce document couvre l'envoi de **tirages photo**. L'API sait aussi envoyer des lettres PDF, des cartes illustrées, des recommandés électroniques et des publipostages : voir la spec.

Un envoi tient en deux appels : `/getToken` une fois par 24 h (section 2), puis `/sendCourrier` (section 3). Le reste du document traite de ce qui fait échouer les intégrations : les pièges de nommage (section 1), l'idempotence (section 4) et le comportement en cas d'échec (section 5).

---

## 1. Règles d'implémentation

**La secret key ne doit jamais atteindre le navigateur, une application mobile ou un dépôt public.** Tout appel à l'API part d'un composant serveur : route d'API, fonction serverless, worker, tâche planifiée, nœud HTTP d'un outil d'automatisation. Le client de l'application appelle ce composant, jamais Merci Facteur directement. Les identifiants vivent dans des variables d'environnement ou un gestionnaire de secrets.

**Ne « corrige » aucun nom de champ.** Plusieurs sont contre-intuitifs et échouent silencieusement si on les normalise :

| Piège | Règle |
|---|---|
| `adress` | Un seul « d ». Ce n'est pas `address`. |
| `photo` | Les tirages vont **dans `content.photo`**, pas à la racine. |
| `hd` | Réservé aux planches de photos d'identité. Ce n'est pas une option de qualité — voir section 3. |
| `adress` et `content` | Des **chaînes JSON**, dans un corps `application/x-www-form-urlencoded`. Pas des objets imbriqués. |
| Tableaux vides | Interdits. Le vide s'écrit `""`. |
| Adresses | Les 11 clés toujours présentes, les inutilisées à `""`. Sinon `INFO_ADDRESS_MISSING`. |
| `dateEnvoi` | Une date complète `AAAA-MM-JJ`, ou le champ totalement absent. Sinon `INVALID_DATE_ENVOI`. |
| Images | En base64 de préférence, pas en URL. |

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

## 3. Envoyer des photos

`POST /sendCourrier`

En-têtes : `ww-access-token` et `ww-service-id`.
Corps : `application/x-www-form-urlencoded`.

| Champ | Contenu |
|---|---|
| `idUser` | entier |
| `adress` | chaîne JSON : `{"exp":{…},"dest":[{…}]}` |
| `content` | chaîne JSON, voir ci-dessous |
| `modeEnvoi` | `normal` (lettre verte), `suivi`, `lrar` (recommandé AR papier), `lrare` (recommandé AR numérisé), `ere_otp_mail`, `ere_otp_sms` |
| `dateEnvoi` | facultatif, `AAAA-MM-JJ` complet, date non passée |
| `designation` | facultatif, 50 caractères max, libellé de recherche dans l'interface Merci Facteur Pro |
| `antidoublon` | facultatif, 200 caractères max, voir section 4 |

Réponse : `{"success":true,"envoi_id":123,"price":{"total":{"ht":X,"ttc":Y}},"resume":{"nb_dest":N,"nb_page":P}}`
En cas d'échec : `{"success":false,"error":…}`

### Le contenu

```json
{
  "letter": "",
  "photo": {
    "files": "",
    "base64files": ["<jpeg en base64>"]
  },
  "card": ""
}
```

Le vide s'écrit `""` à **tous** les niveaux, jamais `[]` ni `null` :

- pas de lettre, pas de carte → `"letter": ""`, `"card": ""` ;
- photos en base64 → `"files": ""`, `"base64files": ["…"]` ;
- photos par URL → `"files": ["https://…"]`, `"base64files": ""`.

`files` attend des URL publiques, `base64files` des fichiers encodés : l'un **ou** l'autre, jamais les deux. Celui qui ne sert pas vaut `""`.

### Le format des images

jpeg ou jpg **uniquement** : ni PDF, ni PNG. 4 Mo maximum par fichier. Espace colorimétrique RVB conseillé.

Dimension recommandée **1200 × 1690 px**, minimum 800 × 1124, maximum 4000 × 2840.

Les proportions sont contraintes : petit côté = 0,71 × grand côté. Exemples conformes : 800 × 1124, 1000 × 1408, 1500 × 2112.

Hors proportion, l'image est recadrée ou complétée **sans validation**. Recadre côté application avant l'envoi, ou montre le rendu à l'utilisateur avant de déclencher l'envoi.

### L'impression

Papier brillant supergloss 250 g/m², format fini 10,5 × 14,85 cm. **50 tirages maximum par courrier.**

L'enveloppe est choisie automatiquement : C6 jusqu'à 10 tirages, C5 au-delà.

### `photo.hd` — planches de photos d'identité

`content.photo.hd = true` bascule l'envoi en planche de photos d'identité, sur papier homologué.

**Ce n'est pas une option de qualité.** Ne la propose jamais pour « imprimer en meilleure qualité ».

Le gabarit est imposé et les spécifications complètes ne sont pas publiques. Contacte le service client Merci Facteur avant de coder ce cas. Une planche non conforme est imprimée et facturée telle quelle, et la photo sera refusée en préfecture ou en mairie.

Calibrations de référence :

- verticale : `https://dk2wbmtb9x9n8.cloudfront.net/assets/v2017/img/calibration_photo_hd_v.jpg`
- horizontale : `https://dk2wbmtb9x9n8.cloudfront.net/assets/v2017/img/calibration_photo_hd_h.jpg`

Le code de la section 7 ne pose jamais `hd`.

### Base64 plutôt qu'URL

Les deux modes existent, mais **le base64 est le mode fiable**. Une URL signée de stockage n'est pas toujours téléchargeable par les serveurs de Merci Facteur, et l'échec survient de leur côté, après coup — donc difficile à diagnostiquer depuis l'application.

Récupère donc l'image depuis ton propre serveur, encode-la, et envoie-la dans `base64files`. Le base64 ne contient ni retour à la ligne, ni préfixe `data:image/jpeg;base64,`, ni marqueur de fin.

Le base64 gonfle le volume d'environ 33 % et transite dans le corps de la requête : au-delà de quelques mégaoctets, bascule sur `files` avec des URL **publiques et non signées**.

Attention aussi à l'encodage lui-même : sur la plupart des runtimes, convertir un tampon d'octets en base64 en une seule passe fait déborder la pile d'appels dès quelques centaines de kilo-octets. Encode par tranches, ou utilise la fonction native du langage qui gère les gros volumes.

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

Plusieurs destinataires dans un même `dest` produisent **le même courrier** envoyé à chacun : un envoi, plusieurs courriers, un seul `envoi_id`.

Un recommandé électronique (`ere_otp_mail`, `ere_otp_sms`) exige en plus `email` ou `phone` selon le canal **sur l'expéditeur comme sur chaque destinataire**, et `consent: 1` sur chaque destinataire. Le code de ce guide ne couvre pas ces champs : pour un envoi électronique, utilise `recommande-electronique.md`.

---

## 4. `antidoublon` — protection optionnelle

`antidoublon` accepte une référence unique de ton choix, 200 caractères maximum. Si un second `sendCourrier` arrive avec la **même** valeur dans les 30 jours glissants, l'API retourne une erreur au lieu de produire un second courrier.

Le champ est facultatif, mais il détermine la stratégie de reprise de toute l'intégration :

- **sans** : aucune reprise automatique sûre n'est possible ;
- **avec** : un envoi dont l'issue est inconnue peut être rejoué à l'identique sans risque.

La valeur doit identifier **le courrier**, pas la tentative : elle reste identique d'un essai à l'autre. Un numéro de facture plus un numéro de relance, un identifiant de contrat, l'identifiant d'une ligne en file d'attente conviennent. **Un UUID régénéré à chaque tentative ou un horodatage ne protègent de rien** — c'est l'erreur classique.

Si aucune référence stable n'existe côté métier, omets le champ. N'en fabrique pas une.

---

## 5. Que faire quand ça échoue

Chaque appel réussi imprime et poste un courrier facturé. Un second appel identique produit un second courrier. Trois situations, trois traitements :

| Ce qui s'est passé | Le courrier est-il parti ? | Que faire |
|---|---|---|
| L'API répond `success: false` | Non, avec certitude | Corriger et réessayer sans risque |
| Échec **avant** `sendCourrier` (validation locale, `/getToken` en échec) | Non, avec certitude | Réessayer sans risque |
| Timeout, coupure réseau, réponse illisible **pendant** `sendCourrier` | **Indéterminé** | Dépend d'`antidoublon` |

La troisième ligne est la seule qui compte. Un timeout ne signifie pas que l'envoi a échoué : la requête a pu aboutir côté Merci Facteur et la réponse se perdre au retour.

- **Avec `antidoublon`** : rejoue à l'identique, même valeur. Le second appel est refusé si le premier était passé. Trois tentatives espacées suffisent.
- **Sans `antidoublon`** : ne réessaie jamais automatiquement. Marque l'envoi « à vérifier », sors-le de la file, laisse un humain trancher depuis l'interface Merci Facteur Pro.

Un `[]` envoyé là où l'API attend `""` est rejeté : c'est la cause la plus fréquente des erreurs sur `content`. Vérifie `files` et `base64files` avant de chercher ailleurs.

Codes d'erreur les plus fréquents et leur cause réelle :

| Code | Cause |
|---|---|
| `INFO_ADDRESS_MISSING` | une clé d'adresse absente, même facultative |
| `INVALID_DATE_ENVOI` | `dateEnvoi` vide ou tronquée |

---

## 6. Contraintes à respecter, quel que soit le déclencheur

Ce que l'API impose à l'appelant, que l'envoi soit déclenché par un bouton, un cron, un événement en base ou un webhook.

**Le déclencheur n'appartient pas à l'appel.** Écris l'envoi comme une fonction ou un point d'entrée unique, sans hypothèse sur son appelant. C'est ce qui permet de brancher ensuite ce que l'utilisateur veut sans retoucher la partie API.

**Un envoi ne se rejoue pas librement.** Il n'existe aucun moyen de vérifier après coup si un envoi donné est passé. Une reprise automatique n'est sûre que dans les cas de la section 5 — et, pour le cas indéterminé, uniquement avec `antidoublon`. Toute forme de déclenchement répétable (retry, cron qui repasse, événement rejoué, double clic) doit donc soit porter un `antidoublon` stable, soit être empêchée en amont.

**Un envoi déclenché sans témoin doit laisser une trace.** Un envoi lancé par un système, contrairement à un envoi lancé par un humain, n'a personne pour constater son échec. Conserve au minimum l'`envoi_id` retourné, ou l'erreur, associés à ce qui a déclenché l'envoi — sans quoi un échec passe inaperçu.

**Le volume se plafonne.** Chaque appel réussi imprime et facture un objet physique. Une boucle accidentelle dans un déclencheur automatique produit de vrais courriers. Prévois une limite de volume par période et un refus au-delà.

**Le temps d'exécution compte.** Encoder jusqu'à 50 images en base64 puis les transmettre prend du temps. Sur une plateforme à timeout court, traite un envoi par invocation plutôt qu'un lot.

### Recevoir l'état des courriers

Merci Facteur pousse les événements — imprimé, distribué, non distribué (NPAI), AR signé — par **webhook**, sur une URL déclarée dans l'onglet « API » de l'interface Merci Facteur Pro.

Le webhook arrive en POST avec deux champs : `event` (type d'événement, id utilisateur, date) et `detail` (tableau des courriers concernés : adresse, `ref_courrier`, `ref_interne`, `mode_envoi`, `id_envoi`, statut). Un webhook ne porte qu'un seul type d'événement mais peut concerner plusieurs courriers.

L'endpoint doit répondre **200**, avec un corps de moins de 500 caractères. Il n'est pas appelé par un utilisateur connecté : ne lui applique pas l'authentification de l'application, vérifie l'origine autrement.

C'est la seule voie de retour. Une intégration qui envoie sans écouter les webhooks ne saura jamais qu'un courrier est revenu en NPAI.

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

async function fetchImageAsBase64(url) {
  const res = await fetch(url);
  if (!res.ok) throw new Error(`Image inaccessible (${res.status}) : ${url}`);
  const bytes = new Uint8Array(await res.arrayBuffer());
  // 4 Mo maximum par fichier, jpeg ou jpg uniquement.
  if (bytes.length > 4 * 1024 * 1024) throw new Error(`Image > 4 Mo : ${url}`);
  return toBase64(bytes);
}

/**
 * @param {object} o
 * @param {string[]} [o.imageUrls]     URLs recuperees et encodees ici meme
 * @param {string[]} [o.imageBase64s]  JPEG deja encodes
 * @param {object}   o.expediteur
 * @param {object[]} o.destinataires
 * @param {string}   [o.modeEnvoi="normal"]
 * @param {string}   [o.dateEnvoi]     AAAA-MM-JJ complet, ou rien
 * @param {string}   [o.designation]
 * @param {string}   [o.antidoublon]   reference STABLE entre deux tentatives
 */
async function sendPhotos(o) {
  if (!o.expediteur) throw new Error("Expediteur manquant");
  if (!Array.isArray(o.destinataires) || o.destinataires.length === 0) {
    throw new Error("Au moins un destinataire requis");
  }

  const base64files = [
    ...(o.imageBase64s ?? []).map((b) =>
      b.replace(/^data:.*?;base64,/, "").replace(/\s+/g, "")),
    ...(await Promise.all((o.imageUrls ?? []).map((u) => fetchImageAsBase64(u)))),
  ];
  if (base64files.length === 0) throw new Error("Aucune image fournie");
  if (base64files.length > 50) throw new Error("50 tirages maximum par courrier");

  // files vaut "" et jamais [] : un tableau vide est refuse par l'API.
  const photo = { files: "", base64files };

  const token = await getToken();

  const body = new URLSearchParams();
  body.set("idUser", String(USER_ID));
  body.set("adress", JSON.stringify({
    exp: normalizeAddress(o.expediteur),
    dest: o.destinataires.map((d) => normalizeAddress(d)),
  }));
  body.set("content", JSON.stringify({ letter: "", photo, card: "" }));
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

MAX_FILE_BYTES = 4 * 1024 * 1024  # 4 Mo par fichier

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


def image_url_to_base64(url: str) -> str:
    res = requests.get(url, timeout=60)
    res.raise_for_status()
    # 4 Mo maximum par fichier, jpeg ou jpg uniquement.
    if len(res.content) > MAX_FILE_BYTES:
        raise ValueError(f"Image > 4 Mo : {url}")
    return base64.b64encode(res.content).decode("ascii")


def send_photos(
    expediteur: dict,
    destinataires: list,
    image_urls: list = None,
    image_base64s: list = None,
    mode_envoi: str = "normal",
    date_envoi: str = None,
    designation: str = None,
    antidoublon: str = None,   # reference STABLE entre deux tentatives
) -> dict:
    if not destinataires:
        raise ValueError("Au moins un destinataire requis")

    base64files = [
        re.sub(r"\s+", "", re.sub(r"^data:.*?;base64,", "", b))
        for b in (image_base64s or [])
    ] + [image_url_to_base64(u) for u in (image_urls or [])]

    if not base64files:
        raise ValueError("Aucune image fournie")
    if len(base64files) > 50:
        raise ValueError("50 tirages maximum par courrier")

    # files vaut "" et jamais [] : un tableau vide est refuse par l'API.
    photo = {"files": "", "base64files": base64files}

    payload = {
        "idUser": USER_ID,
        "adress": json.dumps({
            "exp": normalize_address(expediteur),
            "dest": [normalize_address(d) for d in destinataires],
        }),
        "content": json.dumps({"letter": "", "photo": photo, "card": ""}),
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

Merci Facteur publie un client PHP officiel : `https://github.com/MerciFacteur/Merci-facteur-API`.

- **PHP 8.1+ : `php-class/v2/`** — namespace `MerciFacteur\Api`, Composer, exceptions typées, cache de token, validation des adresses avant l'appel. Le contrat de ce guide y est déjà appliqué.
- **PHP plus ancien : `php-class/apiMf.class.php`** — la classe historique. `sendCourrier()` accepte un 8ᵉ paramètre `$options` pour `dateEnvoi`, `designation`, `antidoublon`, `gestionNpai`, `anonymize` et `enveloppe`.

### Autres langages, outils no-code

Le contrat des sections 2 et 3 se transpose partout. Deux points d'attention avec un client HTTP générique (n8n, Make, Zapier, Postman) : le corps doit être `application/x-www-form-urlencoded` avec `adress` et `content` sérialisés en chaînes JSON, et la signature HMAC doit être calculée dans un nœud de code — la plupart des outils ne savent pas le faire dans un champ de formulaire.

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
