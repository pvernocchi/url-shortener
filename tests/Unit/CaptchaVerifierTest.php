<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CaptchaVerifier;
use PHPUnit\Framework\TestCase;

class CaptchaVerifierTest extends TestCase
{
    public function testDisabledCaptchaAlwaysPasses(): void
    {
        $verifier = new class extends CaptchaVerifier {
            protected function post(string $endpoint, string $payload): ?string
            {
                return null;
            }
        };

        $this->assertTrue($verifier->verify(false, 'recaptcha', '', '', '127.0.0.1'));
    }

    public function testEnabledCaptchaFailsWithoutSecretOrToken(): void
    {
        $verifier = new CaptchaVerifier();

        $this->assertFalse($verifier->verify(true, 'recaptcha', '', 'token', '127.0.0.1'));
        $this->assertFalse($verifier->verify(true, 'recaptcha', 'secret', '', '127.0.0.1'));
    }

    public function testEnabledCaptchaPassesWhenProviderReturnsSuccess(): void
    {
        $verifier = new class extends CaptchaVerifier {
            protected function post(string $endpoint, string $payload): ?string
            {
                return json_encode(['success' => true]);
            }
        };

        $this->assertTrue($verifier->verify(true, 'turnstile', 'secret', 'token', '127.0.0.1'));
    }

    public function testEnabledCaptchaFailsWhenProviderReturnsError(): void
    {
        $verifier = new class extends CaptchaVerifier {
            protected function post(string $endpoint, string $payload): ?string
            {
                return json_encode(['success' => false]);
            }
        };

        $this->assertFalse($verifier->verify(true, 'recaptcha', 'secret', 'token', '127.0.0.1'));
    }
}
