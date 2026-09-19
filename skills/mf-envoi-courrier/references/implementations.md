# Implémentations de référence — envoi de courrier

Code complet et testable. Le contrat qu'il applique est dans le SKILL.md, sections 2 et 3.

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

async function fetchPdfAsBase64(url) {
  const res = await fetch(url);
  if (!res.ok) throw new Error(`PDF inaccessible (${res.status}) : ${url}`);
  return toBase64(new Uint8Array(await res.arrayBuffer()));
}

/**
 * @param {object} o
 * @param {string[]} [o.pdfUrls]      URLs recuperees et encodees ici meme
 * @param {string[]} [o.pdfBase64s]   PDF deja encodes
 * @param {object}   o.expediteur
 * @param {object[]} o.destinataires
 * @param {string}   [o.modeEnvoi="normal"]
 * @param {string}   [o.printSides="recto"]
 * @param {string}   [o.dateEnvoi]    AAAA-MM-JJ complet, ou rien
 * @param {string}   [o.designation]
 * @param {string}   [o.antidoublon]  reference STABLE entre deux tentatives
 */
async function sendCourrier(o) {
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
  if (base64files.length > 10) throw new Error("10 PDF maximum par lettre");

  // files vaut "" et jamais [] : un tableau vide declenche LETTER_INVALID_FILES.
  const letter = { files: "", base64files, print_sides: o.printSides ?? "recto" };
  if (o.finalFilename) letter.final_filename = o.finalFilename;

  const token = await getToken();

  const body = new URLSearchParams();
  body.set("idUser", String(USER_ID));
  body.set("adress", JSON.stringify({
    exp: normalizeAddress(o.expediteur),
    dest: o.destinataires.map((d) => normalizeAddress(d)),
  }));
  body.set("content", JSON.stringify({ letter, photo: "", card: "" }));
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


def pdf_url_to_base64(url: str) -> str:
    res = requests.get(url, timeout=60)
    res.raise_for_status()
    return base64.b64encode(res.content).decode("ascii")


def send_courrier(
    expediteur: dict,
    destinataires: list,
    pdf_urls: list = None,
    pdf_base64s: list = None,
    mode_envoi: str = "normal",
    print_sides: str = "recto",
    date_envoi: str = None,
    designation: str = None,
    final_filename: str = None,
    antidoublon: str = None,   # reference STABLE entre deux tentatives
) -> dict:
    if not destinataires:
        raise ValueError("Au moins un destinataire requis")

    base64files = [
        re.sub(r"\s+", "", re.sub(r"^data:.*?;base64,", "", b))
        for b in (pdf_base64s or [])
    ] + [pdf_url_to_base64(u) for u in (pdf_urls or [])]

    if not base64files:
        raise ValueError("Aucun PDF fourni")
    if len(base64files) > 10:
        raise ValueError("10 PDF maximum par lettre")

    # files vaut "" et jamais [] : un tableau vide declenche LETTER_INVALID_FILES.
    letter = {"files": "", "base64files": base64files, "print_sides": print_sides}
    if final_filename:
        letter["final_filename"] = final_filename[:50]

    payload = {
        "idUser": USER_ID,
        "adress": json.dumps({
            "exp": normalize_address(expediteur),
            "dest": [normalize_address(d) for d in destinataires],
        }),
        "content": json.dumps({"letter": letter, "photo": "", "card": ""}),
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

Merci Facteur publie une classe PHP prête à l'emploi :
`https://github.com/MerciFacteur/Merci-facteur-API` (`php-class/apiMf.class.php`).

Elle ne couvre ni `print_sides`, ni `dateEnvoi`, ni `designation`, ni `antidoublon` : ajoute-les au tableau passé à `CURLOPT_POSTFIELDS` en suivant la section 3.

### Autres langages, outils no-code

Le contrat des sections 2 et 3 se transpose partout. Deux points d'attention avec un client HTTP générique (n8n, Make, Zapier, Postman) : le corps doit être `application/x-www-form-urlencoded` avec `adress` et `content` sérialisés en chaînes JSON, et la signature HMAC doit être calculée dans un nœud de code — la plupart des outils ne savent pas le faire dans un champ de formulaire.

