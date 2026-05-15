<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Server-side WebAuthn helper for credential registration and assertion.
 *
 * Supports ES256 (EC P-256) and RS256 (RSA) COSE key types.
 * Attestation format-agnostic: always extracts the credential from authData
 * without verifying the attestation statement (suitable for most deployments).
 */
class WebAuthn
{
    /** Relying-party ID = origin hostname (e.g. "example.com") */
    private string $rpId;
    /** Full origin (e.g. "https://example.com") */
    private string $origin;
    /** Human-readable RP name */
    private string $rpName;

    private const ALG_ES256 = -7;
    private const ALG_RS256 = -257;
    private const KTY_EC2   = 2;
    private const KTY_RSA   = 3;

    public function __construct()
    {
        $appUrl   = rtrim((string)Config::get('app.url', ''), '/');
        $parsed   = parse_url($appUrl);
        $this->origin = $appUrl;
        $this->rpId   = (string)($parsed['host'] ?? 'localhost');
        $this->rpName = (string)Config::get('app.name', 'URL Shortener');
    }

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    /**
     * Build PublicKeyCredentialCreationOptions for navigator.credentials.create().
     *
     * @param int    $userId   Database user ID
     * @param string $email    User e-mail (used as WebAuthn user.name)
     * @param string $name     Display name
     * @param string $type     'platform' or 'security_key'
     * @param array  $existing Already-registered credential IDs (base64url strings)
     *
     * @return array Options array (JSON-encode before sending to browser)
     */
    public function buildCreationOptions(
        int    $userId,
        string $email,
        string $name,
        string $type,
        array  $existing = []
    ): array {
        $challenge = random_bytes(32);

        $options = [
            'challenge'        => $this->base64url($challenge),
            'rp'               => ['id' => $this->rpId, 'name' => $this->rpName],
            'user'             => [
                'id'          => $this->base64url(pack('N', $userId)),
                'name'        => $email,
                'displayName' => $name,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => self::ALG_ES256],
                ['type' => 'public-key', 'alg' => self::ALG_RS256],
            ],
            'timeout'          => 60000,
            'attestation'      => 'none',
            'authenticatorSelection' => [
                'authenticatorAttachment' => ($type === 'platform') ? 'platform' : 'cross-platform',
                'residentKey'             => 'discouraged',
                'userVerification'        => 'preferred',
            ],
            'excludeCredentials' => array_map(
                fn($id) => ['type' => 'public-key', 'id' => $id, 'transports' => []],
                $existing
            ),
        ];

        return ['options' => $options, 'challenge' => $this->base64url($challenge)];
    }

    /**
     * Verify a PublicKeyCredential response (registration).
     *
     * @param array  $credential  Decoded JSON from the browser
     * @param string $challenge   Base64url challenge stored in session
     *
     * @return array{credentialId:string, publicKeyPem:string, signCount:int, aaguid:string, type:string}
     * @throws \RuntimeException on verification failure
     */
    public function verifyRegistration(array $credential, string $challenge): array
    {
        // 1. Decode clientDataJSON
        $clientData = json_decode(
            $this->base64urlDecode((string)($credential['response']['clientDataJSON'] ?? '')),
            true
        );
        if (!is_array($clientData)) {
            throw new \RuntimeException('Invalid clientDataJSON');
        }

        // 2. Verify type
        if (($clientData['type'] ?? '') !== 'webauthn.create') {
            throw new \RuntimeException('Unexpected clientData type');
        }

        // 3. Verify challenge
        $receivedChallenge = $clientData['challenge'] ?? '';
        if (!hash_equals($challenge, $receivedChallenge)) {
            throw new \RuntimeException('Challenge mismatch');
        }

        // 4. Verify origin
        $receivedOrigin = rtrim((string)($clientData['origin'] ?? ''), '/');
        if (!hash_equals($this->origin, $receivedOrigin)) {
            throw new \RuntimeException("Origin mismatch: expected {$this->origin}, got {$receivedOrigin}");
        }

        // 5. Decode attestationObject
        $attObjBytes = $this->base64urlDecode((string)($credential['response']['attestationObject'] ?? ''));
        $attObj      = (new Cbor())->decode($attObjBytes);
        if (!is_array($attObj) || !isset($attObj['authData'])) {
            throw new \RuntimeException('Invalid attestationObject');
        }

        // 6. Parse authData
        $authData = $this->parseAuthData((string)$attObj['authData']);

        // 7. Verify rpIdHash
        if (!hash_equals(hash('sha256', $this->rpId, true), $authData['rpIdHash'])) {
            throw new \RuntimeException('rpIdHash mismatch');
        }

        // 8. Check UP flag (user present)
        if (!($authData['flags'] & 0x01)) {
            throw new \RuntimeException('User-present flag not set');
        }

        // 9. Extract credential
        $credId    = $authData['credentialId'] ?? '';
        $coseKey   = $authData['cosePublicKey'] ?? '';
        $signCount = $authData['signCount'] ?? 0;
        $aaguid    = $authData['aaguid'] ?? '';

        if ($credId === '' || $coseKey === '') {
            throw new \RuntimeException('Missing credential data in authData');
        }

        $pem = $this->coseKeyToPem($coseKey);

        return [
            'credentialId' => $this->base64url($credId),
            'publicKeyPem' => $pem,
            'signCount'    => $signCount,
            'aaguid'       => bin2hex($aaguid),
        ];
    }

    // -------------------------------------------------------------------------
    // Authentication (assertion)
    // -------------------------------------------------------------------------

    /**
     * Build PublicKeyCredentialRequestOptions for navigator.credentials.get().
     *
     * @param array $credentialIds Base64url credential IDs allowed for this user
     *
     * @return array{options: array, challenge: string}
     */
    public function buildAssertionOptions(array $credentialIds): array
    {
        $challenge = random_bytes(32);
        $options   = [
            'challenge'        => $this->base64url($challenge),
            'rpId'             => $this->rpId,
            'timeout'          => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => array_map(
                fn($id) => ['type' => 'public-key', 'id' => $id, 'transports' => []],
                $credentialIds
            ),
        ];
        return ['options' => $options, 'challenge' => $this->base64url($challenge)];
    }

    /**
     * Verify a PublicKeyCredential response (authentication/assertion).
     *
     * @param array  $credential  Decoded JSON from the browser
     * @param string $challenge   Base64url challenge stored in session
     * @param string $publicKeyPem PEM-encoded public key from storage
     * @param int    $storedSignCount Previously stored sign count
     *
     * @return int New sign count (must be stored)
     * @throws \RuntimeException on failure
     */
    public function verifyAssertion(
        array  $credential,
        string $challenge,
        string $publicKeyPem,
        int    $storedSignCount
    ): int {
        // 1. Decode clientDataJSON
        $clientData = json_decode(
            $this->base64urlDecode((string)($credential['response']['clientDataJSON'] ?? '')),
            true
        );
        if (!is_array($clientData)) {
            throw new \RuntimeException('Invalid clientDataJSON');
        }

        if (($clientData['type'] ?? '') !== 'webauthn.get') {
            throw new \RuntimeException('Unexpected clientData type');
        }

        $receivedChallenge = $clientData['challenge'] ?? '';
        if (!hash_equals($challenge, $receivedChallenge)) {
            throw new \RuntimeException('Challenge mismatch');
        }

        $receivedOrigin = rtrim((string)($clientData['origin'] ?? ''), '/');
        if (!hash_equals($this->origin, $receivedOrigin)) {
            throw new \RuntimeException("Origin mismatch: expected {$this->origin}, got {$receivedOrigin}");
        }

        // 2. Parse authenticatorData
        $authDataBytes = $this->base64urlDecode((string)($credential['response']['authenticatorData'] ?? ''));
        $authData      = $this->parseAuthData($authDataBytes, false);

        if (!hash_equals(hash('sha256', $this->rpId, true), $authData['rpIdHash'])) {
            throw new \RuntimeException('rpIdHash mismatch');
        }

        if (!($authData['flags'] & 0x01)) {
            throw new \RuntimeException('User-present flag not set');
        }

        // 3. Verify sign count
        $newSignCount = $authData['signCount'];
        if ($storedSignCount !== 0 && $newSignCount !== 0 && $newSignCount <= $storedSignCount) {
            throw new \RuntimeException('Sign count did not increase (possible cloning)');
        }

        // 4. Verify signature
        $sigBytes        = $this->base64urlDecode((string)($credential['response']['signature'] ?? ''));
        $clientDataHash  = hash('sha256', $this->base64urlDecode((string)($credential['response']['clientDataJSON'] ?? '')), true);
        $verifyData      = $authDataBytes . $clientDataHash;

        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false) {
            throw new \RuntimeException('Could not load public key');
        }

        $alg = $this->detectAlgorithmFromPem($publicKeyPem);
        $result = openssl_verify($verifyData, $sigBytes, $key, $alg);

        if ($result !== 1) {
            throw new \RuntimeException('Signature verification failed');
        }

        return $newSignCount;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Parse WebAuthn authenticatorData binary blob.
     *
     * @param string $data        Raw binary authenticatorData
     * @param bool   $hasCredData Whether to expect attested credential data (registration)
     */
    private function parseAuthData(string $data, bool $hasCredData = true): array
    {
        if (strlen($data) < 37) {
            throw new \RuntimeException('authData too short');
        }

        $pos       = 0;
        $rpIdHash  = substr($data, $pos, 32);
        $pos      += 32;
        $flags     = ord($data[$pos++]);
        $signCount = unpack('N', substr($data, $pos, 4))[1];
        $pos      += 4;

        $result = [
            'rpIdHash'  => $rpIdHash,
            'flags'     => $flags,
            'signCount' => $signCount,
        ];

        // AT flag = 0x40: attested credential data included
        if ($flags & 0x40) {
            if (strlen($data) < $pos + 18) {
                throw new \RuntimeException('authData truncated before credential data');
            }
            $aaguid  = substr($data, $pos, 16);
            $pos    += 16;
            $credIdLen = unpack('n', substr($data, $pos, 2))[1];
            $pos   += 2;
            $credId = substr($data, $pos, $credIdLen);
            $pos   += $credIdLen;

            $result['aaguid']       = $aaguid;
            $result['credentialId'] = $credId;
            $result['cosePublicKey'] = substr($data, $pos);
        }

        return $result;
    }

    /**
     * Convert a CBOR-encoded COSE public key to PEM.
     *
     * @throws \RuntimeException if the key type is unsupported.
     */
    private function coseKeyToPem(string $coseKeyBytes): string
    {
        $coseKey = (new Cbor())->decode($coseKeyBytes);
        if (!is_array($coseKey)) {
            throw new \RuntimeException('COSE key is not a map');
        }

        $kty = (int)($coseKey[1] ?? 0);

        if ($kty === self::KTY_EC2) {
            return $this->ecCoseKeyToPem($coseKey);
        }
        if ($kty === self::KTY_RSA) {
            return $this->rsaCoseKeyToPem($coseKey);
        }

        throw new \RuntimeException("Unsupported COSE kty: {$kty}");
    }

    /** Convert EC P-256 COSE key to PEM. */
    private function ecCoseKeyToPem(array $coseKey): string
    {
        $x = (string)($coseKey[-2] ?? '');
        $y = (string)($coseKey[-3] ?? '');

        if (strlen($x) !== 32 || strlen($y) !== 32) {
            throw new \RuntimeException('Invalid EC P-256 key coordinates');
        }

        // ASN.1 SubjectPublicKeyInfo for EC P-256
        $oid = "\x30\x13"                     // SEQUENCE (AlgorithmIdentifier)
             . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"  // OID ecPublicKey
             . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // OID P-256

        $point   = "\x04" . $x . $y;          // uncompressed EC point
        $bitStr  = "\x00" . $point;            // BIT STRING padding byte
        $pubBits = "\x03" . $this->derLen(strlen($bitStr)) . $bitStr;

        $spki = "\x30" . $this->derLen(strlen($oid) + strlen($pubBits)) . $oid . $pubBits;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    /** Convert RSA COSE key to PEM. */
    private function rsaCoseKeyToPem(array $coseKey): string
    {
        $n = (string)($coseKey[-1] ?? '');
        $e = (string)($coseKey[-2] ?? '');

        if ($n === '' || $e === '') {
            throw new \RuntimeException('Invalid RSA COSE key (missing n or e)');
        }

        // Encode n and e as DER integers (add 0x00 prefix if high bit set)
        $nDer = $this->derInt($n);
        $eDer = $this->derInt($e);
        $seq  = "\x02" . $this->derLen(strlen($nDer)) . $nDer
              . "\x02" . $this->derLen(strlen($eDer)) . $eDer;
        $rsaKey  = "\x30" . $this->derLen(strlen($seq)) . $seq;
        $bitStr  = "\x00" . $rsaKey;
        $pubBits = "\x03" . $this->derLen(strlen($bitStr)) . $bitStr;

        // OID rsaEncryption: 1.2.840.113549.1.1.1
        $oid  = "\x30\x0d"
              . "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01"
              . "\x05\x00";  // NULL
        $spki = "\x30" . $this->derLen(strlen($oid) + strlen($pubBits)) . $oid . $pubBits;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    /** Encode a big-endian integer for DER (ASN.1 INTEGER). */
    private function derInt(string $bytes): string
    {
        // Remove leading zero bytes (but keep at least one)
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        }
        // Prefix with 0x00 if the high bit is set (to keep it positive)
        if (ord($bytes[0]) >= 0x80) {
            $bytes = "\x00" . $bytes;
        }
        return $bytes;
    }

    /** Encode a DER length (short or long form). */
    private function derLen(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }
        if ($len <= 0xff) {
            return "\x81" . chr($len);
        }
        return "\x82" . chr(($len >> 8) & 0xff) . chr($len & 0xff);
    }

    /** Detect OpenSSL signature algorithm constant from PEM. */
    private function detectAlgorithmFromPem(string $pem): int
    {
        $info = openssl_pkey_get_details(openssl_pkey_get_public($pem));
        if (is_array($info) && isset($info['type'])) {
            return $info['type'] === OPENSSL_KEYTYPE_RSA
                ? OPENSSL_ALGO_SHA256
                : OPENSSL_ALGO_SHA256;
        }
        return OPENSSL_ALGO_SHA256;
    }

    // -------------------------------------------------------------------------
    // Base64url helpers
    // -------------------------------------------------------------------------

    public function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public function base64urlDecode(string $str): string
    {
        $pad = strlen($str) % 4;
        if ($pad === 2) {
            $str .= '==';
        } elseif ($pad === 3) {
            $str .= '=';
        }
        $decoded = base64_decode(strtr($str, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64url input');
        }
        return $decoded;
    }
}
