# Recevoir les webhooks de suivi — API Merci Facteur

> Document de référence à donner à un assistant de développement (Claude, ChatGPT, Cursor, Copilot, Lovable, v0…).
> Il décrit **comment recevoir et traiter les notifications de Merci Facteur**. Il ne prescrit aucune architecture.
> Voir aussi la spec OpenAPI : `https://www.merci-facteur.com/api/1.2/openapi.json`

Merci Facteur notifie une URL de ton choix à chaque étape de la vie d'un courrier : créé, imprimé, pris en charge par La Poste, distribué, retourné, accusé de réception disponible. C'est **la seule voie de retour** : sans webhooks, une application qui envoie du courrier ne saura jamais qu'un pli est revenu en NPAI ni qu'un recommandé a été signé.

Déclare ton URL dans l'onglet « API » de l'interface Merci Facteur Pro. La même page permet de déclencher chaque événement en test, et d'y consulter l'historique de tous les webhooks envoyés.

---

## 1. Le format de la requête

Merci Facteur poste sur ton URL en **`POST`**, avec un corps **`application/x-www-form-urlencoded`** contenant deux champs, chacun étant une **chaîne JSON** :

| Champ | Contenu |
|---|---|
| `event` | objet JSON : type d'événement, id utilisateur, date |
| `detail` | tableau JSON : un élément par courrier concerné |

**Ce n'est pas un corps JSON.** Une implémentation qui fait `await req.json()` ou `request.get_json()` échouera. Il faut lire le corps comme un formulaire, puis désérialiser chacun des deux champs.

Un webhook ne porte **qu'un seul type d'événement**, mais peut concerner plusieurs courriers — par exemple tous les courriers d'un même envoi imprimés au même moment.

### La réponse attendue

Ton endpoint doit répondre **HTTP 200**, avec un corps de **moins de 500 caractères**. En pratique, réponds un corps vide ou un `ok` : ne renvoie du contenu que pour déboguer.

Ce n'est pas un utilisateur connecté qui appelle : **n'applique pas l'authentification de ton application** à cette route. Pas de JWT, pas de session, pas de CSRF. Le contrôle se fait par la clé secrète, section 2.

### Les relances

Si ton endpoint ne répond pas 200, Merci Facteur retente **deux fois** :

1. une seconde tentative 60 minutes après la première ;
2. une troisième tentative 24 h après la seconde.

Passé la troisième, l'événement est perdu.

Conséquence directe : **ton traitement doit être idempotent.** Un même événement peut arriver jusqu'à trois fois — par exemple si ton serveur a bien traité le webhook mais a mis trop de temps à répondre. Vérifie que tu n'as pas déjà enregistré cette combinaison d'événement et de courrier avant d'agir.

---

## 2. Vérifier que le webhook vient bien de Merci Facteur

Les webhooks partent d'adresses IP variables : **filtrer par IP ne fonctionne pas**.

Le contrôle se fait sur un en-tête HTTP :

```
X-Mf-Webhook-Secret-Key: secret-webhook-123456789azerty
```

La valeur attendue est ta **webhook secret key**, disponible dans l'onglet « API » de l'interface Merci Facteur Pro. Elle doit vivre dans une variable d'environnement, jamais dans le code.

Compare la valeur reçue à la tienne et **rejette tout ce qui ne correspond pas**, avant même de lire le corps de la requête. Une comparaison à temps constant est préférable. Une requête rejetée ne renvoie pas 200 — ce n'est pas Merci Facteur, il n'y a rien à relancer.

Si tu as besoin d'un autre nom d'en-tête que `X-Mf-Webhook-Secret-Key`, contacte Merci Facteur.

---

## 3. Rattacher un webhook à tes propres données

Trois identifiants arrivent dans chaque élément de `detail[]`. Ils ne servent pas à la même chose :

| Champ | Ce que c'est | Usage |
|---|---|---|
| `ref_interne` | **Ta** référence, telle que tu l'as fournie à l'envoi | La clé de rapprochement avec ta base |
| `ref_courrier` | La référence Merci Facteur **du courrier** | Identifie un destinataire précis |
| `id_envoi` | L'id **de l'envoi** | Commun à tous les courriers d'un même envoi |

Un envoi à trois destinataires produit **un** `id_envoi` et **trois** `ref_courrier`. Les événements arrivent par courrier : c'est donc `ref_courrier`, pas `id_envoi`, qui identifie ce dont parle une ligne de `detail[]`.

`ref_interne` reprend la valeur que tu as placée dans la clé `reference` de l'adresse destinataire au moment du `sendCourrier`. **Renseigne-la systématiquement** : sans elle, rattacher un webhook à la facture, au contrat ou au dossier qui a déclenché l'envoi suppose de stocker la correspondance `ref_courrier` toi-même, ce qui n'est possible que si tu as déjà reçu l'événement `new`.

`tracking_number` est la référence de suivi postal, utile pour un lien vers le suivi La Poste. Elle vaut `null` sur les envois sans suivi (lettre verte).

---

## 4. Les événements

| Événement | Quand |
|---|---|
| `new` | Un ou plusieurs courriers viennent d'être créés |
| `printed` | Courriers papier imprimés — l'envoi part sous peu |
| `sended` | Courriers électroniques envoyés au destinataire |
| `new-state` | Nouvelle étape d'acheminement |
| `delivered` | Courriers arrivés chez le destinataire |
| `error` | Problème ou erreur de distribution |
| `pnd` | Pli non distribuable, fin d'acheminement |
| `are` | Avis de réception électronique disponible |
| `pdd` | Preuve de dépôt disponible |

Tous les événements portent la même enveloppe :

```json
{
  "event": {
    "name_event": "printed",
    "id_user": 17460,
    "date_event": 1581674000
  },
  "detail": [ { "…": "un objet par courrier" } ]
}
```

`date_event` est un timestamp Unix en secondes.

### Les champs de `detail[]`

Présents sur **tous** les événements : `civilite`, `nom`, `prenom`, `societe`, `adresse1`, `adresse2`, `adresse3`, `cp`, `ville`, `pays`, `ref_courrier`, `mode_envoi`, `id_envoi`, `statut_courrier`, `statut_description`, `ref_interne`.

S'y ajoutent selon l'événement :

| Champ | Sur quels événements | Contenu |
|---|---|---|
| `tracking_number` | tous sauf `new` | Référence de suivi postal, `null` si envoi sans suivi |
| `email`, `telephone` | `sended` (et `new` pour les envois électroniques) | Coordonnées du destinataire d'un recommandé électronique |
| `are_base64_jpeg` | `are` | L'accusé de réception signé, image JPEG encodée en base64 |
| `pdd_base64_pdf` | `pdd` | La preuve de dépôt, PDF encodé en base64 |

`statut_courrier` est un code stable, destiné à ton code. `statut_description` est une phrase en français destinée à être affichée — **ne construis aucune logique dessus**, son libellé peut changer.

Les deux champs en base64 peuvent peser lourd : écris-les dans un stockage de fichiers, pas dans une colonne de log ni dans la sortie console.

### Exemple complet — événement `printed`

```json
{
    "event": {
        "name_event": "printed",
        "id_user": 17460,
        "date_event": 1581674000
    },
    "detail": [{
        "civilite": "M.",
        "nom": "Dupont",
        "prenom": "Michel",
        "societe": "Green Flower Corp",
        "adresse1": "3 rue des fleurs",
        "adresse2": "",
        "adresse3": "",
        "cp": "75015",
        "ville": "Paris",
        "pays": "FRANCE",
        "ref_courrier": "123456-789123456",
        "mode_envoi": "lrar",
        "tracking_number": "2C123456789",
        "id_envoi": "123",
        "statut_courrier": "imprime",
        "statut_description": "14/02/2020 : Courrier imprimé par Merci facteur",
        "ref_interne": "client_123"
    }, {
        "civilite": "",
        "nom": "",
        "prenom": "",
        "societe": "Blue Water Inc",
        "adresse1": "9 allee de la poiscaille",
        "adresse2": "",
        "adresse3": "",
        "cp": "13012",
        "ville": "Marseille",
        "pays": "FRANCE",
        "ref_courrier": "987456-456123789",
        "mode_envoi": "normal",
        "tracking_number": null,
        "id_envoi": "987",
        "statut_courrier": "imprime",
        "statut_description": "14/02/2020 : Courrier imprimé par Merci facteur",
        "ref_interne": "client_123"
    }]
}
```

### Exemple — événement `are`, avec l'accusé de réception

```json
{
    "event": {
        "name_event": "are",
        "id_user": 17460,
        "date_event": 1581674000
    },
    "detail": [{
        "societe": "Green Flower Corp",
        "nom": "Dupont",
        "prenom": "Michel",
        "civilite": "M.",
        "adresse1": "3 rue des fleurs",
        "adresse2": "",
        "adresse3": "",
        "cp": "75015",
        "ville": "Paris",
        "pays": "FRANCE",
        "ref_courrier": "123456-789123456",
        "mode_envoi": "lrare",
        "tracking_number": "2C123456789",
        "id_envoi": "123",
        "are_base64_jpeg": "JVBERi0xLjQj4+ [...] g0Kc3RhcUlRU9GDQo=",
        "statut_courrier": "retour_are",
        "statut_description": "14/02/2020 : Retour de l'accusé de réception signé",
        "ref_interne": "client_123"
    }]
}
```

---

## 5. Les statuts

### Courriers papier, dans l'ordre chronologique

| Événement | `statut_courrier` | Signification |
|---|---|---|
| `new` | `wait` | En attente d'impression |
| `printed` | `imprime` | Imprimé par Merci Facteur, posté dans la foulée |
| `new-state` | `pris_en_charge` | Pris en charge par La Poste |
| `new-state` | `prix_en_charge_pays_destinataire` | Pris en charge par le service postal du pays destinataire (international) |
| `new-state` | `traitement` | En cours de traitement chez La Poste |
| `new-state` | `traitement_pays_destination` | En traitement dans le pays destinataire (international) |
| `new-state` | `transit_pays_intermediaire` | En traitement dans un pays de transit (international) |
| `new-state` | `entree_douane` | Entré en douane (international) |
| `new-state` | `sortie_douane` | Sorti de douane (international) |
| `new-state` | `retenu_douane` | Retenu en douane (international) |
| `new-state` | `attente_presentation` | En attente de distribution |
| `new-state` | `distribution_en_cours` | En cours de distribution |
| `new-state` | `attente_au_guichet` | À retirer au bureau de poste indiqué sur l'avis de passage |
| `new-state` | `probleme_resolu` | La Poste a résolu un problème rencontré |
| `error` | `probleme_en_cours` | La Poste rencontre un problème |
| `error` | `non_distribuable` | Courrier non distribuable |
| `error` | `retour_expediteur` | Retourné à l'expéditeur |
| `delivered` | `distribue` | Distribué au destinataire — fin d'acheminement |
| `pnd` | `distribue_expediteur` | Distribué à l'expéditeur en retour — fin d'acheminement, c'est le NPAI |
| `pnd` | `archivage_pnd` | En LRARE, le PND est revenu chez Merci Facteur et y est archivé 3 ans |
| `are` | `retour_are` | En LRARE, l'accusé de réception numérisé est disponible |
| `pdd` | `scan_pdd` | Pour les envois suivis et recommandés, la preuve de dépôt PDF est disponible |

Attention à l'orthographe de `prix_en_charge_pays_destinataire` : c'est bien la valeur émise par l'API, malgré le « prix ». Ne la corrige pas en `pris_`.

### Courriers électroniques, dans l'ordre chronologique

| Événement | `statut_courrier` | Signification |
|---|---|---|
| `new` | `wait` | Recommandé électronique en attente d'envoi |
| `sended` | `envoye` | Envoyé au destinataire par Merci Facteur |
| `new-state` | `accept_wait` | En attente d'acceptation dans la boîte de réception du destinataire |
| `error` | `accept_lock` | Bloqué 3 heures après 6 échecs d'identification OTP |
| `error` | `accept_notification_error` | Notification impossible : email erroné ou boîte pleine — fin d'acheminement |
| `delivered` | `accepted` | Le destinataire a accepté la lettre et signé l'accusé de réception |
| `delivered` | `accepted_downloaded` | Accepté, signé, et document téléchargé |
| `pnd` | `expired` | Non accepté dans le délai imparti |
| `pnd` | `refused` | Refusé par le destinataire |

### Ce qui compte pour une application

Trois statuts sont des **fins d'acheminement** : `distribue`, `distribue_expediteur` et, pour l'électronique, `accepted` / `accepted_downloaded`, `expired`, `refused`, `accept_notification_error`. Après eux, plus aucun événement n'arrivera pour ce courrier.

`distribue_expediteur` est le NPAI — le courrier est revenu. C'est l'information que la plupart des intégrations oublient de traiter, et c'est celle qui coûte le plus cher : une adresse fausse qui reste fausse.

Ne suppose aucun ordre d'arrivée et n'écris pas de machine à états qui exige une séquence : conserve le dernier statut reçu en le datant avec `date_event`, et ignore un événement plus ancien que celui déjà enregistré.

---

## 6. Implémentations de référence

### JavaScript / TypeScript

Un gestionnaire agnostique du framework : il reçoit le corps brut et les en-têtes, et renvoie le code HTTP à retourner.

```js
const WEBHOOK_SECRET = process.env.MF_WEBHOOK_SECRET;

// Comparaison a temps constant, sans dependance.
function safeEqual(a = "", b = "") {
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}

/**
 * @param {string} rawBody  corps brut de la requete (form-urlencoded)
 * @param {Headers|object} headers
 */
async function handleMfWebhook(rawBody, headers) {
  const get = (k) => (typeof headers.get === "function" ? headers.get(k) : headers[k]);

  // 1. Origine : les IP varient, seule la cle secrete fait foi.
  if (!safeEqual(get("x-mf-webhook-secret-key") ?? "", WEBHOOK_SECRET)) {
    return { status: 403, body: "" }; // pas 200 : ce n'est pas Merci Facteur
  }

  // 2. Le corps est un FORMULAIRE dont deux champs sont des chaines JSON.
  //    Ce n'est pas un corps JSON : req.json() echouerait.
  const form = new URLSearchParams(rawBody);
  let event, detail;
  try {
    event = JSON.parse(form.get("event") ?? "{}");
    detail = JSON.parse(form.get("detail") ?? "[]");
  } catch {
    return { status: 400, body: "" };
  }

  // 3. Repondre 200 vite. Le traitement long (base64 des AR/PDD) se fait
  //    de preference apres la reponse, ou dans une tache de fond.
  for (const courrier of detail) {
    await applyEvent(event.name_event, event.date_event, courrier);
  }

  return { status: 200, body: "ok" }; // moins de 500 caracteres
}

async function applyEvent(nameEvent, dateEvent, c) {
  // Idempotence : jusqu'a 3 livraisons du meme evenement (relances a +60 min et +24 h).
  // La cle naturelle est (nameEvent, statut_courrier, ref_courrier).
  if (await dejaTraite(nameEvent, c.statut_courrier, c.ref_courrier)) return;

  // ref_interne = TA reference, posee dans "reference" de l'adresse au sendCourrier.
  // ref_courrier identifie LE COURRIER ; id_envoi est commun a tout l'envoi.
  await majCourrier({
    refInterne: c.ref_interne,
    refCourrier: c.ref_courrier,
    idEnvoi: c.id_envoi,
    statut: c.statut_courrier,          // code stable, pour la logique
    description: c.statut_description,  // texte francais, pour l'affichage uniquement
    tracking: c.tracking_number ?? null,
    dateEvent: dateEvent,               // ignorer un evenement plus ancien que l'enregistre
  });

  // Pieces jointes : vers un stockage de fichiers, jamais dans un log.
  if (c.are_base64_jpeg) await stockerFichier(c.ref_courrier, "are.jpg", c.are_base64_jpeg);
  if (c.pdd_base64_pdf) await stockerFichier(c.ref_courrier, "pdd.pdf", c.pdd_base64_pdf);
}
```

Branchement selon le contexte :

```js
// Express — urlencoded, surtout pas express.json()
app.post("/webhooks/merci-facteur",
  express.urlencoded({ extended: false, limit: "25mb" }), // les AR/PDD en base64 sont volumineux
  async (req, res) => {
    const raw = new URLSearchParams(req.body).toString();
    const { status, body } = await handleMfWebhook(raw, req.headers);
    res.status(status).send(body);
  });

// Deno / Edge Function — pas de verification de JWT sur cette route
Deno.serve(async (req) => {
  const { status, body } = await handleMfWebhook(await req.text(), req.headers);
  return new Response(body, { status });
});
```

### Python

```python
import base64, hmac, json, os

WEBHOOK_SECRET = os.environ["MF_WEBHOOK_SECRET"]


def handle_mf_webhook(form: dict, headers: dict):
    """form : le corps deja parse en formulaire. Retourne (status, body)."""

    # 1. Origine : les IP varient, seule la cle secrete fait foi.
    received = headers.get("X-Mf-Webhook-Secret-Key", "")
    if not hmac.compare_digest(received, WEBHOOK_SECRET):
        return 403, ""  # pas 200 : ce n'est pas Merci Facteur

    # 2. Deux champs de formulaire, chacun une CHAINE JSON.
    try:
        event = json.loads(form.get("event", "{}"))
        detail = json.loads(form.get("detail", "[]"))
    except json.JSONDecodeError:
        return 400, ""

    for courrier in detail:
        apply_event(event.get("name_event"), event.get("date_event"), courrier)

    return 200, "ok"  # moins de 500 caracteres


def apply_event(name_event, date_event, c):
    # Idempotence : jusqu'a 3 livraisons (relances a +60 min et +24 h).
    if deja_traite(name_event, c["statut_courrier"], c["ref_courrier"]):
        return

    maj_courrier(
        ref_interne=c.get("ref_interne"),      # TA reference
        ref_courrier=c["ref_courrier"],        # identifie LE COURRIER
        id_envoi=c["id_envoi"],                # commun a tout l'envoi
        statut=c["statut_courrier"],           # code stable, pour la logique
        description=c["statut_description"],   # texte, pour l'affichage seulement
        tracking=c.get("tracking_number"),
        date_event=date_event,
    )

    if c.get("are_base64_jpeg"):
        stocker_fichier(c["ref_courrier"], "are.jpg", base64.b64decode(c["are_base64_jpeg"]))
    if c.get("pdd_base64_pdf"):
        stocker_fichier(c["ref_courrier"], "pdd.pdf", base64.b64decode(c["pdd_base64_pdf"]))
```

Branchement Flask — `request.form`, jamais `request.get_json()` :

```python
@app.post("/webhooks/merci-facteur")
def mf_webhook():
    status, body = handle_mf_webhook(request.form, request.headers)
    return body, status
```

### PHP

```php
<?php
// Les deux champs arrivent dans $_POST, chacun sous forme de chaine JSON.
$secret = getenv('MF_WEBHOOK_SECRET');
$recu = $_SERVER['HTTP_X_MF_WEBHOOK_SECRET_KEY'] ?? '';

if (!hash_equals($secret, $recu)) {
    http_response_code(403); // pas 200 : ce n'est pas Merci Facteur
    exit;
}

$event = json_decode($_POST['event'] ?? '{}', true);
$detail = json_decode($_POST['detail'] ?? '[]', true);

foreach ($detail as $courrier) {
    applyEvent($event['name_event'], $event['date_event'], $courrier);
}

http_response_code(200);
echo 'ok';
```

---

## 7. Avant de mettre en production

- L'URL de l'endpoint est déclarée dans l'onglet « API » de l'interface Merci Facteur Pro, et doit être **accessible publiquement en HTTPS**.
- La même page permet de **déclencher chaque événement en test** : passe-les tous, en particulier `pnd` et `are`, les deux que les intégrations traitent le plus mal.
- La même page conserve **l'historique de tous les webhooks envoyés** : c'est là qu'on diagnostique un endpoint qui ne répond pas 200.
- Vérifie que la route **n'est pas protégée par l'authentification de l'application**, sinon chaque webhook reçoit une redirection et n'est jamais traité.
- Vérifie que ton serveur accepte un corps de plusieurs mégaoctets : les événements `are` et `pdd` transportent un fichier en base64.
