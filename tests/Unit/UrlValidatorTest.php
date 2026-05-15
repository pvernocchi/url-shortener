<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\UrlValidator;
use PHPUnit\Framework\TestCase;

class UrlValidatorTest extends TestCase
{
    private UrlValidator $validator;
    private array $defaultConfig;

    protected function setUp(): void
    {
        $this->validator = new UrlValidator();
        $this->defaultConfig = [
            'max_url_length'    => 2048,
            'allowed_protocols' => ['http', 'https'],
            'blocked_domains'   => [],
        ];
    }

    public function testRejectsHttpWhenOnlyHttpsAllowed(): void
    {
        $config = array_merge($this->defaultConfig, ['allowed_protocols' => ['https']]);
        $result = $this->validator->validate('http://example.com', $config);
        $this->assertIsString($result);
        $this->assertStringContainsString('protocol', strtolower($result));
    }

    public function testAcceptsHttpsWhenOnlyHttpsAllowed(): void
    {
        $config = array_merge($this->defaultConfig, ['allowed_protocols' => ['https']]);
        $result = $this->validator->validate('https://example.com', $config);
        $this->assertTrue($result);
    }

    public function testRejectsLocalhost(): void
    {
        $result = $this->validator->validate('http://localhost/path', $this->defaultConfig);
        $this->assertIsString($result);
    }

    public function testRejectsLocalhostHttps(): void
    {
        $result = $this->validator->validate('https://localhost', $this->defaultConfig);
        $this->assertIsString($result);
    }

    public function testRejectsPrivateIp(): void
    {
        $result = $this->validator->validate('http://192.168.1.1/page', $this->defaultConfig);
        $this->assertIsString($result);
    }

    public function testRejectsLoopbackIp(): void
    {
        $result = $this->validator->validate('http://127.0.0.1/', $this->defaultConfig);
        $this->assertIsString($result);
    }

    public function testRejectsBlockedDomain(): void
    {
        $config = array_merge($this->defaultConfig, ['blocked_domains' => ['evil.com']]);
        $result = $this->validator->validate('https://evil.com/path', $config);
        $this->assertIsString($result);
        $this->assertStringContainsString('blocked', strtolower($result));
    }

    public function testAllowsNonBlockedDomain(): void
    {
        $config = array_merge($this->defaultConfig, ['blocked_domains' => ['evil.com']]);
        $result = $this->validator->validate('https://good.com/path', $config);
        $this->assertTrue($result);
    }

    public function testAcceptsValidHttpsUrl(): void
    {
        $result = $this->validator->validate('https://www.example.com/some/path?query=1', $this->defaultConfig);
        $this->assertTrue($result);
    }

    public function testAcceptsValidHttpUrl(): void
    {
        $result = $this->validator->validate('http://example.com/page', $this->defaultConfig);
        $this->assertTrue($result);
    }

    public function testRejectsUrlExceedingMaxLength(): void
    {
        $longUrl = 'https://example.com/' . str_repeat('a', 2048);
        $result  = $this->validator->validate($longUrl, $this->defaultConfig);
        $this->assertIsString($result);
        $this->assertStringContainsString('length', strtolower($result));
    }

    public function testRejectsInvalidUrl(): void
    {
        $result = $this->validator->validate('not-a-url', $this->defaultConfig);
        $this->assertIsString($result);
    }

    public function testRejectsFtpProtocol(): void
    {
        $result = $this->validator->validate('ftp://files.example.com', $this->defaultConfig);
        $this->assertIsString($result);
    }

    public function testNormalizeLowercasesScheme(): void
    {
        $normalized = $this->validator->normalize('HTTPS://EXAMPLE.COM/Path');
        $this->assertStringStartsWith('https://', $normalized);
    }

    public function testNormalizeLowercasesHost(): void
    {
        $normalized = $this->validator->normalize('https://EXAMPLE.COM/path');
        $this->assertStringContainsString('example.com', $normalized);
    }

    public function testNormalizePreservesPath(): void
    {
        $normalized = $this->validator->normalize('https://example.com/My/Path?query=Value');
        $this->assertStringContainsString('/My/Path', $normalized);
        $this->assertStringContainsString('query=Value', $normalized);
    }
}
