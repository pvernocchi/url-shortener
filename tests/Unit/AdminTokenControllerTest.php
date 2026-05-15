<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ApiToken;
use App\Services\ApiTokenIssuer;
use PHPUnit\Framework\TestCase;

class AdminTokenControllerTest extends TestCase
{
    public function testTokenGenerationUsesPrefixAndExpectedLength(): void
    {
        $issuer = new ApiTokenIssuer();
        $token  = $issuer->generateRawToken();

        $this->assertStringStartsWith('usk_', $token);
        $this->assertSame(68, strlen($token));
        $this->assertMatchesRegularExpression('/^usk_[a-f0-9]{64}$/', $token);
    }

    public function testIssuedTokenHashMatchesApiTokenHashingScheme(): void
    {
        $issuer  = new ApiTokenIssuer();
        $issued  = $issuer->issue();
        $hash    = ApiToken::hashToken($issued['raw']);

        $this->assertSame($hash, $issued['hash']);
        $this->assertSame(hash('sha256', $issued['raw']), $issued['hash']);
    }

    public function testScopeNormalizationDefaultsToReadWhenEmpty(): void
    {
        $this->assertSame('read', ApiTokenIssuer::normalizeScopes([]));
    }

    public function testScopeNormalizationSupportsReadWriteSubset(): void
    {
        $this->assertSame('read,write', ApiTokenIssuer::normalizeScopes(['read', 'write']));
    }

    public function testScopeNormalizationFiltersUnknownValues(): void
    {
        $this->assertSame('read', ApiTokenIssuer::normalizeScopes(['admin', 'read', '']));
    }
}

