<?php
declare(strict_types=1);

namespace App\Services;

class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $secret = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, $max)];
        }
        return $secret;
    }

    public function verifyCode(string $secret, string $code, int $window = 1, int $digits = 6, int $timeStep = 30): bool
    {
        $normalizedCode = preg_replace('/\D+/', '', $code) ?? '';
        if ($normalizedCode === '' || strlen($normalizedCode) !== $digits) {
            return false;
        }

        $secret = strtoupper(trim($secret));
        if ($secret === '') {
            return false;
        }

        $counter = intdiv(time(), $timeStep);
        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidate = $this->generateCode($secret, $counter + $offset, $digits);
            if (hash_equals($candidate, $normalizedCode)) {
                return true;
            }
        }
        return false;
    }

    public function provisioningUri(string $issuer, string $accountName, string $secret): string
    {
        $label = rawurlencode($issuer . ':' . $accountName);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ]);
        return "otpauth://totp/{$label}?{$query}";
    }

    public function generateCode(string $secret, int $counter, int $digits = 6): string
    {
        $binarySecret = $this->decodeBase32($secret);
        if ($binarySecret === '') {
            return '';
        }

        $counterBytes = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $counterBytes, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $segment = substr($hash, $offset, 4);
        $value = unpack('N', $segment)[1] & 0x7FFFFFFF;
        $mod = 10 ** $digits;
        return str_pad((string)($value % $mod), $digits, '0', STR_PAD_LEFT);
    }

    private function decodeBase32(string $value): string
    {
        $value = strtoupper(preg_replace('/[^A-Z2-7]/', '', $value) ?? '');
        if ($value === '') {
            return '';
        }

        $buffer = 0;
        $bitsLeft = 0;
        $output = '';
        $alphabetMap = array_flip(str_split(self::ALPHABET));

        foreach (str_split($value) as $char) {
            if (!isset($alphabetMap[$char])) {
                return '';
            }

            $buffer = ($buffer << 5) | $alphabetMap[$char];
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
