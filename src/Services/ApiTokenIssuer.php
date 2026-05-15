<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ApiToken;

class ApiTokenIssuer
{
    /**
     * @return array{raw:string,hash:string}
     */
    public function issue(): array
    {
        $rawToken = $this->generateRawToken();
        return [
            'raw'  => $rawToken,
            'hash' => ApiToken::hashToken($rawToken),
        ];
    }

    public function generateRawToken(): string
    {
        return ApiToken::TOKEN_PREFIX . bin2hex(random_bytes(32));
    }

    public static function normalizeScopes(array $scopes): string
    {
        $allowed = ['read', 'write'];
        $unique  = [];

        foreach ($scopes as $scope) {
            $normalized = strtolower(trim((string)$scope));
            if (in_array($normalized, $allowed, true) && !in_array($normalized, $unique, true)) {
                $unique[] = $normalized;
            }
        }

        if ($unique === []) {
            return 'read';
        }

        usort($unique, static function (string $a, string $b): int {
            $order = ['read' => 0, 'write' => 1];
            return $order[$a] <=> $order[$b];
        });

        return implode(',', $unique);
    }
}

