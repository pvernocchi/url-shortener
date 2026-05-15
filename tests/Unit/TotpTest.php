<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    public function testGenerateSecretUsesExpectedAlphabetAndLength(): void
    {
        $totp = new Totp();
        $secret = $totp->generateSecret(32);

        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testProvisioningUriContainsIssuerAndSecret(): void
    {
        $totp = new Totp();
        $uri = $totp->provisioningUri('URL Shortener', 'admin@example.com', 'JBSWY3DPEHPK3PXP');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('issuer=URL+Shortener', $uri);
    }

    public function testVerifyCodeAcceptsCurrentGeneratedCode(): void
    {
        $totp = new Totp();
        $secret = 'JBSWY3DPEHPK3PXP';
        $counter = intdiv(time(), 30);
        $code = $totp->generateCode($secret, $counter);

        $this->assertTrue($totp->verifyCode($secret, $code, 0));
    }

    public function testVerifyCodeRejectsInvalidCode(): void
    {
        $totp = new Totp();
        $this->assertFalse($totp->verifyCode('JBSWY3DPEHPK3PXP', '000000', 0));
    }
}
