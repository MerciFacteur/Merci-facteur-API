<?php

declare(strict_types=1);

/**
 * Endpoint de réception des webhooks Merci Facteur.
 *
 * URL à déclarer dans l'onglet « API » de l'interface Merci Facteur Pro, ou
 * avec $mf->setWebhookEndpoint('https://…').
 *
 * Trois règles : vérifier l'origine, répondre 200 vite, traiter ensuite de
 * façon idempotente (Merci Facteur réessaie 2 fois en cas d'échec).
 */

require __DIR__ . '/../vendor/autoload.php';

use MerciFacteur\Api\Client;

// 1. Origine. Le filtrage par IP ne fonctionne pas : les webhooks partent de
//    plusieurs adresses. La clé se trouve dans l'onglet « API » du compte.
if (!Client::webhookEstAuthentique(
    (string) getenv('MF_WEBHOOK_SECRET'),
    $_SERVER['HTTP_X_MF_WEBHOOK_SECRET_KEY'] ?? null,
)) {
    http_response_code(403);
    exit;
}

$event = json_decode($_POST['event'] ?? '[]', true);
$detail = json_decode($_POST['detail'] ?? '[]', true);

// 2. Répondre immédiatement : un traitement long provoque un timeout, donc une
//    relance, donc un doublon. Le corps doit faire moins de 500 caractères.
http_response_code(200);
echo 'ok';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// 3. Traiter. Dédupliquez sur (name_event, ref_courrier) : le même événement
//    peut arriver jusqu'à trois fois.
$nomEvenement = (string) ($event['name_event'] ?? '');

foreach ((array) $detail as $courrier) {
    $refCourrier = (string) ($courrier['ref_courrier'] ?? '');
    $refInterne = (string) ($courrier['ref_interne'] ?? ''); // votre propre référence
    $suivi = $courrier['tracking_number'] ?? null;           // null en mode normal

    switch ($nomEvenement) {
        case 'printed':
            // Le tracking_number apparaît ici, et nulle part avant. Sans lui,
            // aucune preuve ne pourra être récupérée par API plus tard.
            // monApp()->enregistreSuivi($refInterne, $suivi);
            break;

        case 'delivered':
            // Fin d'acheminement.
            break;

        case 'pnd':
            // Pli non distribué : suspendre la relance, marquer l'adresse.
            break;

        case 'are':
            // Accusé de réception numérisé (mode lrare), déjà en base64 ici :
            // aucun appel à getProof n'est nécessaire.
            // file_put_contents("ar-$refInterne.jpeg", base64_decode($courrier['are_base64_jpeg']));
            break;

        case 'pdd':
            // Preuve de dépôt, également transportée en base64.
            // file_put_contents("pdd-$refInterne.pdf", base64_decode($courrier['pdd_base64_pdf']));
            break;

        default:
            // new, sended, new-state, error : statut dans $courrier['statut_courrier'].
            // Testez le code, jamais statut_description, qui est du texte libre.
    }
}
