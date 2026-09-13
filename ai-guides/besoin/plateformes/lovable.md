# Prompt « Envoyer un courrier papier depuis Lovable »

> À copier-coller tel quel dans le chat Lovable. Aligné sur la spec OpenAPI Merci Facteur 1.2.7
> (`https://www.merci-facteur.com/api/1.2/openapi.json`).

---

Voici comment envoyer un vrai courrier papier — imprimé et posté par La Poste — depuis mon application, via l'API Merci Facteur.

Ce document décrit **la capacité d'envoi**, pas l'interface. Mets en place l'Edge Function ci-dessous : c'est le point d'entrée, et il fonctionne à l'identique appelé depuis un formulaire, un trigger en base, une tâche planifiée ou un webhook. Ce qu'on branche dessus, je te le dirai — n'invente pas d'écran que je n'ai pas demandé.

Un envoi tient en deux appels : `getToken` une fois par 24 h, puis `sendCourrier`. Le reste du document traite de ce qui fait échouer les intégrations : les pièges de nommage, l'idempotence et le comportement en cas d'échec.

## Contraintes d'architecture — non négociables

- La clé secrète Merci Facteur ne doit JAMAIS toucher le front. Tout passe par une Edge Function Lovable Cloud (Supabase).
- Crée trois secrets : `MF_SERVICE_ID`, `MF_SECRET_KEY`, `MF_USER_ID`.
- Ne réécris pas la logique d'authentification, et ne « corrige » aucun nom de champ : utilise exactement le code ci-dessous.

## Pièges connus — à respecter à la lettre

- Le champ des adresses s'écrit **`adress`**, avec un seul « d ». Ne l'orthographie pas `address`.
- Le recto-verso s'écrit **`print_sides`**, avec un « s » final, et se place **dans `content.letter`**, pas à la racine.
- `adress` et `content` sont envoyés comme des **chaînes JSON** dans un corps `application/x-www-form-urlencoded`, pas comme des objets imbriqués.
- **Jamais de tableau vide.** Dans `content.letter`, `files` et `base64files` valent soit un tableau non vide, soit la chaîne vide `""`. Un `[]` déclenche `LETTER_INVALID_FILES` ou `LETTER_INVALID_BASE64_FILES`.
- **Une adresse s'envoie toujours complète.** Les 11 clés doivent être présentes, les inutilisées à `""`. Une adresse partielle déclenche `INFO_ADDRESS_MISSING`.
- **`dateEnvoi` : tout ou rien.** Soit une date complète `AAAA-MM-JJ`, soit le champ absent du corps. Une valeur vide ou tronquée déclenche `INVALID_DATE_ENVOI`.
- **Le PDF part en base64, pas en URL.** Les serveurs de Merci Facteur ne parviennent pas toujours à télécharger une URL signée de stockage.

## Comment fonctionne l'API Merci Facteur

Base URL : `https://www.merci-facteur.com/api/1.2/prod/service`

**Étape 1 — obtenir un token (valable 24 h).** `GET /getToken`, avec ces en-têtes :

| En-tête | Valeur |
|---|---|
| `ww-service-signature` | la signature calculée ci-dessous |
| `ww-timestamp` | timestamp Unix en secondes, **exactement le même** que celui utilisé pour calculer la signature |
| `ww-service-id` | le service ID |
| `ww-authorized-ip` | IP autorisées séparées par `;`. Une Edge Function n'ayant pas d'IP fixe, la restriction d'IP doit être levée sur le compte ; on passe alors la valeur factice `111.111.111`. Cet en-tête reste obligatoire. |

### Calcul de la signature

La secret key ne transite jamais en clair : elle sert de **clé** à un HMAC, elle n'est jamais le message.

1. Prends le timestamp Unix courant en **secondes** (pas en millisecondes) : `ts`.
2. Construis le message en **concaténant** le service ID et ce timestamp, sans séparateur, sans espace : `serviceId + ts` (par exemple `"abc123" + "1757404800"` donne `"abc1231757404800"`).
3. Calcule un HMAC-SHA256 de ce message, avec la secret key comme clé.
4. Encode le résultat en **hexadécimal minuscule** — pas en base64.
5. Envoie cette valeur dans `ww-service-signature` et le **même** `ts` dans `ww-timestamp`. Si les deux ne correspondent pas, la signature est rejetée.

Implémentation de référence, en PHP (fournie par Merci Facteur) :

```php
function hashSecretKey($secretKey, $serviceId){
    $ts = time();
    $hashed = hash_hmac('sha256', $serviceId . $ts, $secretKey, false);
    return array('timestamp' => $ts, 'hash' => $hashed);
}
```

Le même calcul en TypeScript / Deno, via l'API Web Crypto (c'est cette version qu'il faut utiliser dans l'Edge Function) :

```ts
async function hashSecretKey(secretKey: string, serviceId: string) {
  const ts = Math.floor(Date.now() / 1000); // secondes, pas millisecondes
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secretKey),   // la secret key est la CLÉ du HMAC
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const sig = await crypto.subtle.sign(
    "HMAC",
    key,
    new TextEncoder().encode(`${serviceId}${ts}`), // le message = serviceId + timestamp
  );
  const hash = Array.from(new Uint8Array(sig))
    .map((b) => b.toString(16).padStart(2, "0"))   // hexadécimal minuscule
    .join("");
  return { timestamp: ts, hash };
}
```

Réponse de `/getToken` : `{"success":true,"token":"...","expire":<timestamp>}`. Stocke le token et réutilise-le jusqu'à `expire` — ne rappelle pas `/getToken` à chaque envoi.

**Étape 2 — envoyer le courrier.** `POST /sendCourrier`, en-têtes `ww-access-token` et `ww-service-id`, corps `application/x-www-form-urlencoded` :

| Champ | Contenu |
|---|---|
| `idUser` | entier |
| `adress` | chaîne JSON : `{"exp":{...},"dest":[{...}]}` |
| `content` | chaîne JSON : `{"letter":{"files":"","base64files":["<base64>"],"print_sides":"recto"},"photo":"","card":""}` |
| `modeEnvoi` | `normal` (lettre verte), `suivi`, `lrar` (recommandé AR papier), `lrare` (recommandé AR numérisé), `ere_otp_mail`, `ere_otp_sms` |
| `dateEnvoi` | facultatif, `AAAA-MM-JJ` complet, date non passée — **ne pas envoyer le champ du tout** s'il n'y a pas de date |
| `designation` | facultatif, 50 caractères max, libellé visible dans l'interface Merci Facteur Pro |
| `antidoublon` | facultatif, 200 caractères max, référence unique de ton choix — voir plus bas |

### Le vide s'écrit `""`, jamais `[]` ni `null`

Cette règle vaut à tous les niveaux de `content`, pas seulement pour `photo` et `card` :

- Un courrier sans photo et sans carte : `"photo": ""` et `"card": ""`.
- Une lettre envoyée en base64 : `"files": ""` et `"base64files": ["<base64>"]`.
- Une lettre envoyée par URL : `"files": ["https://…"]` et `"base64files": ""`.

Un tableau vide `[]` dans `files` ou `base64files` fait échouer l'envoi avec `LETTER_INVALID_FILES` ou `LETTER_INVALID_BASE64_FILES`.

Sous `content.letter` : `files`, `base64files`, `final_filename` (50 caractères max, sans extension) et `print_sides` (`recto`, `rectoverso`, `distinctrectoverso`).

### PDF en base64 plutôt qu'en URL

Les deux modes existent, mais **le base64 est le mode fiable**. Une URL signée de stockage (Supabase Storage et équivalents) n'est pas toujours téléchargeable par les serveurs de Merci Facteur, et l'échec se produit côté Merci Facteur, après coup — donc difficile à diagnostiquer depuis l'application.

Le PDF est donc récupéré côté serveur dans l'Edge Function, converti en base64, et envoyé dans `base64files`. Le base64 ne contient ni retour à la ligne, ni préfixe `data:application/pdf;base64,`, ni marqueur de fin — uniquement les caractères de l'encodage.

Le base64 augmente le volume d'environ 33 % et transite dans le corps de la requête : au-delà de quelques mégaoctets, bascule sur `files` avec une URL publique et non signée.

### `antidoublon` — protection optionnelle contre le double envoi

`antidoublon` accepte une référence unique de ton choix, 200 caractères maximum. Si un second `sendCourrier` est appelé avec la **même** valeur dans les 30 jours glissants, l'API retourne une erreur au lieu de produire un second courrier.

C'est facultatif, et le choix appartient à l'utilisateur de l'application :

- **Sans `antidoublon`** : rien n'empêche un doublon. Il faut alors ne jamais réessayer un envoi dont l'issue est inconnue.
- **Avec `antidoublon`** : un envoi dont l'issue est inconnue peut être rejoué à l'identique sans risque — le second appel est refusé si le premier était passé. C'est ce qui rend une automatisation robuste.

La valeur doit identifier **le courrier**, pas l'instant de l'appel : elle doit rester identique d'une tentative à l'autre. Une référence métier convient (numéro de facture + numéro de relance, identifiant de contrat, id de la ligne en file d'attente). Un horodatage ou un UUID régénéré à chaque tentative ne protège de rien.

Si l'utilisateur n'a pas de référence métier à fournir, n'invente pas de valeur : omets le champ, et applique la règle de reprise stricte plus bas.

Format d'une adresse (expéditeur comme destinataire) :

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

**Les 11 clés doivent toutes être présentes**, dans l'expéditeur comme dans chaque destinataire : `civilite`, `nom`, `prenom`, `societe`, `adresse1`, `adresse2`, `adresse3`, `cp`, `ville`, `pays`, `reference`. Celles qui ne servent pas valent la chaîne vide `""`, elles ne sont jamais omises. Une adresse partielle est rejetée avec `INFO_ADDRESS_MISSING`, même si les champs manquants sont facultatifs sur le papier.

Doivent en plus être **non vides** : `nom` ou `societe`, `cp`, `ville`, `pays`. Le pays doit être **exactement** l'une des valeurs de la liste en annexe — n'invente aucun libellé et ne traduis rien. Limites : société 90 caractères, nom et prénom 70, lignes d'adresse 90, ville 70.

Contraintes sur le contenu : uniquement du PDF, jusqu'à 10 fichiers par lettre, 50 Mo max par fichier.

Réponse : `{"success":true,"envoi_id":123,"price":{"total":{"ht":X,"ttc":Y}},"resume":{"nb_dest":N,"nb_page":P}}`. En cas d'échec : `{"success":false,"error":...}`.

## Edge Function à créer — `send-letter`

```ts
// supabase/functions/send-letter/index.ts
const MF_BASE = "https://www.merci-facteur.com/api/1.2/prod/service";

const SERVICE_ID = Deno.env.get("MF_SERVICE_ID")!;
const SECRET_KEY = Deno.env.get("MF_SECRET_KEY")!;
const USER_ID = Deno.env.get("MF_USER_ID")!;
// La restriction d'IP est levée sur le compte : l'en-tête reste obligatoire,
// on y passe une valeur factice comme le prévoit la documentation.
const AUTHORIZED_IP = Deno.env.get("MF_AUTHORIZED_IP") ?? "111.111.111";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

// Cache mémoire du token, réutilisé tant que l'instance reste chaude.
let cachedToken: { token: string; expire: number } | null = null;

// Signature : HMAC-SHA256(message = serviceId + timestamp, cle = secret key), en hexa minuscule.
async function hashSecretKey(secretKey: string, serviceId: string) {
  const ts = Math.floor(Date.now() / 1000); // secondes, pas millisecondes
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secretKey),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const sig = await crypto.subtle.sign(
    "HMAC",
    key,
    new TextEncoder().encode(`${serviceId}${ts}`),
  );
  const hash = Array.from(new Uint8Array(sig))
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("");
  return { timestamp: ts, hash };
}

async function getToken(): Promise<string> {
  const now = Math.floor(Date.now() / 1000);
  if (cachedToken && cachedToken.expire > now + 60) return cachedToken.token;

  // Le timestamp envoye doit etre celui utilise pour la signature.
  const { timestamp: ts, hash: signature } = await hashSecretKey(SECRET_KEY, SERVICE_ID);

  const res = await fetch(`${MF_BASE}/getToken`, {
    method: "GET",
    headers: {
      "ww-service-signature": signature,
      "ww-timestamp": String(ts),
      "ww-service-id": SERVICE_ID,
      "ww-authorized-ip": AUTHORIZED_IP,
    },
  });

  const data = await res.json();
  if (!data?.success || !data.token) {
    throw new Error(`getToken a échoué : ${JSON.stringify(data?.error ?? res.status)}`);
  }

  cachedToken = { token: data.token, expire: data.expire ?? now + 86400 };
  return data.token;
}

// Les 11 cles d'une adresse doivent TOUTES etre presentes, les inutilisees a "".
// Une adresse partielle est rejetee avec INFO_ADDRESS_MISSING.
const ADDRESS_KEYS = [
  "civilite", "nom", "prenom", "societe",
  "adresse1", "adresse2", "adresse3",
  "cp", "ville", "pays", "reference",
] as const;

function normalizeAddress(input: Record<string, unknown> = {}) {
  const out: Record<string, string> = {};
  for (const k of ADDRESS_KEYS) out[k] = String(input[k] ?? "").trim();
  if (!out.nom && !out.societe) throw new Error("Adresse invalide : nom ou societe requis");
  for (const k of ["cp", "ville", "pays"]) {
    if (!out[k]) throw new Error(`Adresse invalide : ${k} requis`);
  }
  return out;
}

// Encodage base64 par tranches : un spread sur un gros Uint8Array fait deborder la pile.
function toBase64(bytes: Uint8Array): string {
  let binary = "";
  const CHUNK = 0x8000;
  for (let i = 0; i < bytes.length; i += CHUNK) {
    binary += String.fromCharCode(...bytes.subarray(i, i + CHUNK));
  }
  return btoa(binary); // pas de retour a la ligne, pas de prefixe data:
}

async function fetchPdfAsBase64(url: string): Promise<string> {
  const res = await fetch(url);
  if (!res.ok) throw new Error(`PDF inaccessible (${res.status}) : ${url}`);
  return toBase64(new Uint8Array(await res.arrayBuffer()));
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });

  try {
    const {
      pdfUrl, pdfUrls, pdfBase64, pdfBase64Files, expediteur, destinataires,
      modeEnvoi, printSides, dateEnvoi, designation, finalFilename, antidoublon,
    } = await req.json();

    if (!expediteur) throw new Error("Expéditeur manquant");
    if (!Array.isArray(destinataires) || destinataires.length === 0) {
      throw new Error("Au moins un destinataire est requis");
    }

    // On privilegie le base64 : une URL signee n'est pas toujours telechargeable
    // par les serveurs de Merci Facteur. Les URLs recues sont donc recuperees ici.
    const urls: string[] = pdfUrls ?? (pdfUrl ? [pdfUrl] : []);
    const provided: string[] = pdfBase64Files ?? (pdfBase64 ? [pdfBase64] : []);
    const base64files = [
      ...provided.map((b) => b.replace(/^data:.*?;base64,/, "").replace(/\s+/g, "")),
      ...(await Promise.all(urls.map(fetchPdfAsBase64))),
    ];

    if (base64files.length === 0) throw new Error("Aucun PDF fourni");
    if (base64files.length > 10) throw new Error("10 fichiers PDF maximum par lettre");

    // Le vide s'ecrit "" et jamais [] : un tableau vide declenche LETTER_INVALID_FILES.
    const letter: Record<string, unknown> = {
      files: "",
      base64files,
      print_sides: printSides ?? "recto",
    };
    if (finalFilename) letter.final_filename = finalFilename;

    const token = await getToken();

    // L'API attend un corps form-urlencoded ; "adress" et "content" sont des chaînes JSON.
    const body = new URLSearchParams();
    body.set("idUser", USER_ID);
    body.set(
      "adress",
      JSON.stringify({
        exp: normalizeAddress(expediteur),
        dest: destinataires.map(normalizeAddress),
      }),
    );
    body.set("content", JSON.stringify({ letter, photo: "", card: "" }));
    body.set("modeEnvoi", modeEnvoi ?? "normal");
    // dateEnvoi : soit une date complete AAAA-MM-JJ, soit le champ absent.
    // Une valeur vide ou tronquee declenche INVALID_DATE_ENVOI.
    if (typeof dateEnvoi === "string" && /^\d{4}-\d{2}-\d{2}$/.test(dateEnvoi)) {
      body.set("dateEnvoi", dateEnvoi);
    }
    if (designation) body.set("designation", designation);
    // antidoublon : facultatif. Fourni, il doit rester IDENTIQUE entre deux tentatives
    // du meme courrier, sinon il ne protege de rien.
    if (antidoublon) body.set("antidoublon", String(antidoublon).slice(0, 200));

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
      // Le token a pu être invalidé côté serveur : on purge le cache pour le prochain appel.
      cachedToken = null;
      return new Response(JSON.stringify({ success: false, error: data?.error ?? "UNKNOWN" }), {
        status: 400,
        headers: { ...corsHeaders, "Content-Type": "application/json" },
      });
    }

    return new Response(JSON.stringify(data), {
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  } catch (e) {
    return new Response(JSON.stringify({ success: false, error: String(e) }), {
      status: 500,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  }
});
```

## Appeler la fonction

Quel que soit l'appelant, le contrat est le même. On passe soit l'URL du PDF (l'Edge Function le télécharge et l'encode elle-même), soit directement le base64 — il n'envoie jamais de tableau vide ni de `dateEnvoi` incomplète, et n'a pas à compléter les adresses : l'Edge Function s'en charge.

```ts
const { data, error } = await supabase.functions.invoke("send-letter", {
  body: {
    // Au choix : pdfUrl (récupérée côté serveur) ou pdfBase64 (déjà encodée).
    pdfUrl: "https://.../ma-lettre.pdf",
    expediteur: {
      societe: "Ma Société", nom: "Dupont", prenom: "Sophie",
      adresse1: "9 allée de la Rose", cp: "78000", ville: "Versailles", pays: "FRANCE",
    },
    destinataires: [{
      civilite: "Monsieur", nom: "Martin", prenom: "Joël",
      adresse1: "33 allée de la Pâquerette", cp: "75015", ville: "Paris", pays: "FRANCE",
    }],
    modeEnvoi: "normal",
    printSides: "recto",
    designation: "Envoi depuis mon app",
    // dateEnvoi: "2026-10-15"     // à omettre entièrement s'il n'y a pas de date
    // antidoublon: "FACT-2026-0187-relance1",  // facultatif, stable entre deux tentatives
  },
});
```

La réponse contient l'`envoi_id` et le prix. Traite explicitement le cas `success: false` avec son code d'erreur — les codes les plus fréquents et leur cause réelle : `LETTER_INVALID_FILES` / `LETTER_INVALID_BASE64_FILES` (un `[]` là où il fallait `""`), `INFO_ADDRESS_MISSING` (une clé d'adresse absente), `INVALID_DATE_ENVOI` (une `dateEnvoi` vide ou tronquée).

## La règle de reprise — vraie dans tous les cas

Chaque appel réussi à `sendCourrier` imprime et poste un courrier facturé, et un second appel identique produit un second courrier. Trois situations, à traiter différemment :

| Ce qui s'est passé | Le courrier est-il parti ? | Que faire |
|---|---|---|
| L'API répond `success: false` | Non, avec certitude | Corriger l'appel et réessayer sans risque |
| L'appel échoue **avant** `sendCourrier` (validation locale, `getToken` en échec) | Non, avec certitude | Réessayer sans risque |
| Timeout, coupure réseau, réponse illisible **pendant** `sendCourrier` | **Indéterminé** | Dépend d'`antidoublon`, voir ci-dessous |

La troisième ligne est la seule qui compte vraiment : un timeout ne veut pas dire que l'envoi a échoué. La requête a pu aboutir côté Merci Facteur et la réponse se perdre au retour.

- **Avec `antidoublon`** : rejoue l'appel à l'identique, même valeur d'`antidoublon`. Si le premier était passé, le second est refusé par l'API. Trois tentatives espacées suffisent.
- **Sans `antidoublon`** : ne réessaie jamais automatiquement. Marque l'envoi « à vérifier » et laisse un humain trancher depuis l'interface Merci Facteur Pro.

## Contraintes à respecter, quel que soit le déclencheur

L'Edge Function est un point d'entrée HTTP : elle ne sait pas — et n'a pas à savoir — ce qui l'appelle. Ce qui suit découle du comportement de l'API, pas d'un choix d'architecture.

**Aucun déclenchement répétable sans garde-fou.** Retry, tâche planifiée qui repasse, événement rejoué, double clic : tout ce qui peut appeler deux fois doit soit porter un `antidoublon` stable, soit être empêché en amont. Un bouton se désactive au premier clic ; un déclencheur automatique n'a personne pour le retenir.

**Un envoi déclenché sans témoin doit laisser une trace.** Un envoi lancé par un système n'a personne pour constater son échec. Conserve au minimum l'`envoi_id` retourné, ou l'erreur, rattachés à ce qui a déclenché l'envoi.

**Un plafond de volume.** Chaque appel réussi imprime et facture un objet physique ; une boucle accidentelle produit de vrais courriers. Limite le nombre d'envois par période et refuse au-delà.

**L'authentification dépend de l'appelant.** Appelée depuis le front, la fonction reçoit le JWT de l'utilisateur. Appelée depuis un trigger ou une tâche planifiée, elle s'authentifie avec la clé de service. Si elle n'est jamais appelée depuis le front, désactive la vérification du JWT et contrôle l'appelant toi-même.

**Le temps d'exécution compte.** Encoder un PDF en base64 puis le transmettre prend du temps, et une Edge Function a un timeout court : traite un envoi par invocation plutôt qu'un lot.

### Recevoir l'état des courriers

Merci Facteur pousse les événements — imprimé, distribué, non distribué (NPAI), accusé de réception signé — par **webhook**, sur une URL déclarée dans l'onglet « API » de l'interface Merci Facteur Pro.

Le webhook arrive en POST avec deux champs : `event` (type d'événement, id utilisateur, date) et `detail` (tableau des courriers concernés : adresse, `ref_courrier`, `ref_interne`, `mode_envoi`, `id_envoi`, statut). Un webhook ne porte qu'un seul type d'événement, mais peut concerner plusieurs courriers. L'endpoint doit répondre **200**, avec un corps de moins de 500 caractères.

C'est la seule voie de retour : sans elle, l'application ne saura jamais qu'un courrier est revenu en NPAI. Reçois-les dans une seconde Edge Function, sans vérification de JWT — c'est Merci Facteur qui appelle, pas un utilisateur connecté.

## Avant de tester

1. Crée un compte sur merci-facteur.com/pro, puis récupère les trois valeurs à mettre dans les secrets :
   - le **service ID** et la **secret key**, dans l'onglet « API » ;
   - le **user ID** (`MF_USER_ID`), dans le menu « Utilisateurs ». C'est l'utilisateur au nom duquel les courriers seront envoyés — celui dont l'historique et la facturation porteront les envois. En général l'utilisateur `admin` créé par défaut suffit.
2. **Demande au support technique de Merci Facteur la levée de la restriction d'IP** : une Edge Function Lovable n'a pas d'IP fixe. Sans cette levée, `getToken` échouera systématiquement.
3. Demande aussi l'ouverture d'un compte Sandbox tant que tu développes — sinon chaque appel réussi imprime et poste un vrai courrier, facturé.

## Annexe — valeurs autorisées pour le champ `pays`

Le champ `pays` n'accepte que ces valeurs, à l'identique (majuscules, sans accents, espaces et tirets tels quels). Toute autre orthographe fait échouer l'envoi.

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
