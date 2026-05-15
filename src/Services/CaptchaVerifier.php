<?php
declare(strict_types=1);

namespace App\Services;

class CaptchaVerifier
{
    public function verify(bool $enabled, string $provider, string $secretKey, string $responseToken, string $remoteIp): bool
    {
        if (!$enabled) {
            return true;
        }

        if ($responseToken === '' || $secretKey === '') {
            return false;
        }

        $endpoint = match ($provider) {
            'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            default => 'https://www.google.com/recaptcha/api/siteverify',
        };

        $payload = http_build_query([
            'secret'   => $secretKey,
            'response' => $responseToken,
            'remoteip' => $remoteIp,
        ]);

        $json = $this->post($endpoint, $payload);
        if ($json === null) {
            return false;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) && ($decoded['success'] ?? false) === true;
    }

    protected function post(string $endpoint, string $payload): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                    . "Content-Length: " . strlen($payload) . "\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        $response = @file_get_contents($endpoint, false, $context);
        return $response === false ? null : $response;
    }
}
