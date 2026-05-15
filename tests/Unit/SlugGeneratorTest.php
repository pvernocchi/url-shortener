<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Link;
use App\Services\SlugGenerator;
use PHPUnit\Framework\TestCase;

class SlugGeneratorTest extends TestCase
{
    private SlugGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SlugGenerator();
    }

    public function testGenerateReturnsCorrectLength(): void
    {
        for ($i = 4; $i <= 10; $i++) {
            $slug = $this->generator->generate($i);
            $this->assertSame($i, strlen($slug), "generate($i) should return $i chars");
        }
    }

    public function testGenerateDefaultLengthIsSix(): void
    {
        $slug = $this->generator->generate();
        $this->assertSame(6, strlen($slug));
    }

    public function testGenerateOnlyAlphanumericChars(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $slug = $this->generator->generate(12);
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $slug);
        }
    }

    public function testIsReservedReturnsTrueForAdmin(): void
    {
        $this->assertTrue($this->generator->isReserved('admin'));
    }

    public function testIsReservedReturnsTrueForInstall(): void
    {
        $this->assertTrue($this->generator->isReserved('install'));
    }

    public function testIsReservedReturnsTrueForApi(): void
    {
        $this->assertTrue($this->generator->isReserved('api'));
    }

    public function testIsReservedReturnsTrueForLogin(): void
    {
        $this->assertTrue($this->generator->isReserved('login'));
    }

    public function testIsReservedReturnsFalseForNormalSlug(): void
    {
        $this->assertFalse($this->generator->isReserved('my-link'));
        $this->assertFalse($this->generator->isReserved('abcdef'));
    }

    public function testIsValidReturnsFalseForTooShortSlugs(): void
    {
        $this->assertFalse($this->generator->isValid('ab'));
        $this->assertFalse($this->generator->isValid('a'));
        $this->assertFalse($this->generator->isValid(''));
    }

    public function testIsValidReturnsFalseForInvalidChars(): void
    {
        $this->assertFalse($this->generator->isValid('has space'));
        $this->assertFalse($this->generator->isValid('has/slash'));
        $this->assertFalse($this->generator->isValid('has@at'));
        $this->assertFalse($this->generator->isValid('has#hash'));
    }

    public function testIsValidReturnsTrueForValidSlugs(): void
    {
        $this->assertTrue($this->generator->isValid('abc'));
        $this->assertTrue($this->generator->isValid('my-link'));
        $this->assertTrue($this->generator->isValid('my_link'));
        $this->assertTrue($this->generator->isValid('ABC123'));
        $this->assertTrue($this->generator->isValid('a1b2c3d4'));
    }

    public function testIsValidReturnsFalseForTooLongSlug(): void
    {
        $longSlug = str_repeat('a', 65);
        $this->assertFalse($this->generator->isValid($longSlug));
    }

    public function testIsValidReturnsTrueForMaxLengthSlug(): void
    {
        $maxSlug = str_repeat('a', 64);
        $this->assertTrue($this->generator->isValid($maxSlug));
    }

    public function testGenerateUniqueReturnsNonReservedCode(): void
    {
        $linkModel = $this->createMock(Link::class);
        $linkModel->method('codeExists')->willReturn(false);

        $code = $this->generator->generateUnique($linkModel);

        $this->assertFalse($this->generator->isReserved($code));
        $this->assertTrue($this->generator->isValid($code));
    }

    public function testGenerateUniqueAvoidsCollisions(): void
    {
        $linkModel = $this->createMock(Link::class);
        // Simulate first 5 calls returning collision, then no collision
        $linkModel->method('codeExists')
            ->willReturnOnConsecutiveCalls(true, true, true, true, true, false);

        $code = $this->generator->generateUnique($linkModel);
        $this->assertNotEmpty($code);
    }
}
