<?php

declare(strict_types=1);

namespace MerciFacteur\Api\Http;

use CurlHandle;
use MerciFacteur\Api\Exception\TransportException;

/**
 * Transport cURL.
 *
 * Deux garde-fous que la classe historique n'avait pas : un timeout de
 * connexion et un timeout total. Sans eux, un incident réseau bloque le
 * processus PHP jusqu'au max_execution_time.
 */
final class CurlTransport
{
    public function __construct(
        private readonly int $connectTimeout = 10,
        private readonly int $timeout = 120,
        private readonly string $userAgent = 'merci-facteur-php/2.0',
    ) {
    }

    /**
     * @param array<string, string>      $headers
     * @param array<string, scalar>|null $form Corps application/x-www-form-urlencoded
     *
     * @throws TransportException
     */
    public function request(
        string $method,
        string $url,
        array $headers,
        ?array $form,
        string $endpoint,
        bool $outcomeUnknownOnFailure = false,
    ): HttpResponse {
        $curl = curl_init();
        if (!$curl instanceof CurlHandle) {
            throw new TransportException('cURL indisponible.', $endpoint, false);
        }

        $entetes = [];
        foreach ($headers as $nom => $valeur) {
            $entetes[] = $nom . ': ' . $valeur;
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $entetes,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ];

        if ($form !== null) {
            $options[CURLOPT_POSTFIELDS] = http_build_query($form);
            $entetes[] = 'Content-Type: application/x-www-form-urlencoded';
            $options[CURLOPT_HTTPHEADER] = $entetes;
        }

        curl_setopt_array($curl, $options);

        $body = curl_exec($curl);
        $erreur = curl_error($curl);
        $numeroErreur = curl_errno($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $numeroErreur !== 0) {
            throw new TransportException(
                sprintf('%s : échec réseau (cURL %d) — %s', $endpoint, $numeroErreur, $erreur),
                $endpoint,
                $outcomeUnknownOnFailure,
            );
        }

        return new HttpResponse($status, (string) $body);
    }
}
