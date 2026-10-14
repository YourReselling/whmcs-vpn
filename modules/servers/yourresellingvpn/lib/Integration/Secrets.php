<?php

declare(strict_types=1);

namespace YourReselling\Whmcs\Vpn\Integration;

/** Protect one-time product credentials in the durable journal with the panel's application key. */
final class Secrets
{
    /** @return list<array{label:string,value:string}> */
    public static function extract(array $data, string $prefix = '', bool $sensitive = false): array
    {
        $rows = [];
        foreach ($data as $key => $value) {
            $label = $prefix.(string) $key;
            $secret = $sensitive || (bool) preg_match('/password|secret|token|private.?key|access.?key|credential|kubeconfig|wireguard.?config|connection.?string/i', (string) $key);
            if (is_array($value)) {
                $rows = array_merge($rows, self::extract($value, $label.' · ', $secret));
            } elseif ($secret && is_scalar($value) && (string) $value !== '') {
                $rows[] = ['label' => str_replace('_', ' ', $label), 'value' => (string) $value];
            }
        }

        return $rows;
    }

    public static function seal(string $plaintext, string $applicationKey): string
    {
        if ($applicationKey === '') {
            throw new PanelException('Der Anwendungsschlüssel für Zugangsdaten fehlt.');
        }
        $nonce = random_bytes(12);
        $encrypted = openssl_encrypt($plaintext, 'aes-256-gcm', hash('sha256', 'your-reselling-v3:'.$applicationKey, true), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($encrypted === false) {
            throw new PanelException('Zugangsdaten konnten nicht verschlüsselt werden.');
        }

        return base64_encode($nonce.$tag.$encrypted);
    }

    public static function open(string $sealed, string $applicationKey): array
    {
        $bytes = base64_decode($sealed, true);
        if ($bytes === false || strlen($bytes) < 28 || $applicationKey === '') {
            throw new PanelException('Ungültige geschützte Zugangsdaten.');
        }
        $plaintext = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', hash('sha256', 'your-reselling-v3:'.$applicationKey, true), OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16));
        if ($plaintext === false) {
            throw new PanelException('Zugangsdaten konnten nicht entschlüsselt werden.');
        }

        return json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
    }
}
