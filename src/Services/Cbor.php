<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Minimal CBOR (RFC 7049) decoder sufficient for parsing WebAuthn attestation data.
 *
 * Supported major types:
 *   0 – unsigned integer
 *   1 – negative integer
 *   2 – byte string
 *   3 – text string
 *   4 – array
 *   5 – map
 *
 * Integer keys in maps are returned as PHP int; text keys as string.
 * Map values are decoded recursively.
 */
class Cbor
{
    private string $buf;
    private int $pos;

    /**
     * Decode a CBOR-encoded binary string.
     *
     * @return mixed Decoded PHP value (int, string, array, or associative array for maps).
     * @throws \RuntimeException on unsupported or malformed input.
     */
    public function decode(string $data): mixed
    {
        $this->buf = $data;
        $this->pos = 0;
        return $this->readItem();
    }

    private function readItem(): mixed
    {
        $byte = $this->readByte();
        $majorType = ($byte >> 5) & 0x07;
        $info      = $byte & 0x1f;

        return match ($majorType) {
            0 => $this->readUint($info),
            1 => -1 - $this->readUint($info),
            2 => $this->readRaw($this->readUint($info)),
            3 => $this->readRaw($this->readUint($info)),
            4 => $this->readArray($this->readUint($info)),
            5 => $this->readMap($this->readUint($info)),
            default => throw new \RuntimeException("Unsupported CBOR major type: {$majorType}"),
        };
    }

    private function readByte(): int
    {
        if ($this->pos >= strlen($this->buf)) {
            throw new \RuntimeException('CBOR buffer underflow');
        }
        return ord($this->buf[$this->pos++]);
    }

    private function readUint(int $info): int
    {
        if ($info <= 23) {
            return $info;
        }
        if ($info === 24) {
            return $this->readByte();
        }
        if ($info === 25) {
            $v = unpack('n', substr($this->buf, $this->pos, 2));
            $this->pos += 2;
            return (int)$v[1];
        }
        if ($info === 26) {
            $v = unpack('N', substr($this->buf, $this->pos, 4));
            $this->pos += 4;
            return (int)$v[1];
        }
        if ($info === 27) {
            // 64-bit; use two 32-bit reads to avoid sign issues
            $hi = unpack('N', substr($this->buf, $this->pos, 4))[1];
            $lo = unpack('N', substr($this->buf, $this->pos + 4, 4))[1];
            $this->pos += 8;
            // Truncate to PHP_INT_MAX for practical CBOR lengths
            return ($hi << 32) | $lo;
        }
        throw new \RuntimeException("Unsupported CBOR additional info: {$info}");
    }

    private function readRaw(int $len): string
    {
        $s = substr($this->buf, $this->pos, $len);
        $this->pos += $len;
        return $s;
    }

    private function readArray(int $len): array
    {
        $arr = [];
        for ($i = 0; $i < $len; $i++) {
            $arr[] = $this->readItem();
        }
        return $arr;
    }

    private function readMap(int $len): array
    {
        $map = [];
        for ($i = 0; $i < $len; $i++) {
            $key   = $this->readItem();
            $value = $this->readItem();
            // Store int keys as-is (PHP allows mixed array keys that are int or string)
            $map[$key] = $value;
        }
        return $map;
    }
}
