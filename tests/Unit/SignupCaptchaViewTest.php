<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\View;
use PHPUnit\Framework\TestCase;

class SignupCaptchaViewTest extends TestCase
{
    protected function setUp(): void
    {
        View::setViewsPath(__DIR__ . '/../../src/Views');
    }

    public function testRendersRecaptchaWhenEnabled(): void
    {
        $html = View::render('auth/signup_request', [
            'captchaEnabled' => true,
            'captchaProvider' => 'recaptcha',
            'captchaSiteKey' => 'test-site-key',
        ]);

        $this->assertStringContainsString('class="g-recaptcha" data-sitekey="test-site-key"', $html);
        $this->assertStringContainsString('https://www.google.com/recaptcha/api.js', $html);
    }

    public function testRendersTurnstileWhenConfigured(): void
    {
        $html = View::render('auth/signup_request', [
            'captchaEnabled' => true,
            'captchaProvider' => 'turnstile',
            'captchaSiteKey' => 'test-site-key',
        ]);

        $this->assertStringContainsString('class="cf-turnstile" data-sitekey="test-site-key"', $html);
        $this->assertStringContainsString('https://challenges.cloudflare.com/turnstile/v0/api.js', $html);
    }

    public function testDoesNotRenderCaptchaWhenDisabled(): void
    {
        $html = View::render('auth/signup_request', [
            'captchaEnabled' => false,
            'captchaProvider' => 'recaptcha',
            'captchaSiteKey' => 'test-site-key',
        ]);

        $this->assertStringNotContainsString('g-recaptcha', $html);
        $this->assertStringNotContainsString('api.js', $html);
    }
}
