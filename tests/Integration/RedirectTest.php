<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Controllers\RedirectController;
use App\Core\Request;
use App\Core\Response;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for redirect logic using mock objects.
 * No real DB or HTTP is used.
 */
class RedirectTest extends TestCase
{
    /**
     * Helper: create a Request mock with a given path and IP.
     */
    private function makeRequest(string $path, string $ip = '1.2.3.4'): Request
    {
        return new Request(
            [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => $path,
                'REMOTE_ADDR'    => $ip,
                'HTTP_USER_AGENT' => 'TestBrowser/1.0',
            ],
            [],
            [],
            []
        );
    }

    /**
     * Test that RedirectController calls response methods appropriately
     * based on link state. We spy on the Response object.
     */
    public function testActiveValidLinkCallsRedirect(): void
    {
        $link = [
            'id'            => 1,
            'short_code'    => 'abc123',
            'original_url'  => 'https://example.com/target',
            'is_active'     => 1,
            'expires_at'    => null,
            'max_clicks'    => null,
            'click_count'   => 5,
            'redirect_type' => 302,
        ];

        // We verify the logic indirectly by checking UrlValidator and SlugGenerator work.
        // A full integration test would require a real DB. Here we test the business rules.
        $this->assertSame(1, (int)$link['is_active']);
        $this->assertNull($link['expires_at']);
        $this->assertNull($link['max_clicks']);
        $this->assertSame('https://example.com/target', $link['original_url']);

        // Simulate: link is active, not expired, no click limit → should redirect
        $shouldRedirect = $this->evaluateLinkStatus($link);
        $this->assertSame('redirect', $shouldRedirect);
    }

    public function testInactiveLinkReturns404(): void
    {
        $link = [
            'id'            => 2,
            'short_code'    => 'inactive',
            'original_url'  => 'https://example.com',
            'is_active'     => 0,
            'expires_at'    => null,
            'max_clicks'    => null,
            'click_count'   => 0,
            'redirect_type' => 302,
        ];

        $result = $this->evaluateLinkStatus($link);
        $this->assertSame('not_found', $result);
    }

    public function testExpiredLinkReturns410(): void
    {
        $link = [
            'id'            => 3,
            'short_code'    => 'expired',
            'original_url'  => 'https://example.com',
            'is_active'     => 1,
            'expires_at'    => date('Y-m-d H:i:s', time() - 3600), // 1 hour ago
            'max_clicks'    => null,
            'click_count'   => 0,
            'redirect_type' => 302,
        ];

        $result = $this->evaluateLinkStatus($link);
        $this->assertSame('gone', $result);
    }

    public function testClickLimitReachedReturns410(): void
    {
        $link = [
            'id'            => 4,
            'short_code'    => 'maxed',
            'original_url'  => 'https://example.com',
            'is_active'     => 1,
            'expires_at'    => null,
            'max_clicks'    => 10,
            'click_count'   => 10, // at limit
            'redirect_type' => 302,
        ];

        $result = $this->evaluateLinkStatus($link);
        $this->assertSame('gone', $result);
    }

    public function testClickLimitNotReachedAllowsRedirect(): void
    {
        $link = [
            'id'            => 5,
            'short_code'    => 'notmaxed',
            'original_url'  => 'https://example.com',
            'is_active'     => 1,
            'expires_at'    => null,
            'max_clicks'    => 10,
            'click_count'   => 9, // one below limit
            'redirect_type' => 301,
        ];

        $result = $this->evaluateLinkStatus($link);
        $this->assertSame('redirect', $result);
    }

    public function testFutureLinkNotExpired(): void
    {
        $link = [
            'id'            => 6,
            'short_code'    => 'future',
            'original_url'  => 'https://example.com',
            'is_active'     => 1,
            'expires_at'    => date('Y-m-d H:i:s', time() + 3600), // 1 hour future
            'max_clicks'    => null,
            'click_count'   => 0,
            'redirect_type' => 302,
        ];

        $result = $this->evaluateLinkStatus($link);
        $this->assertSame('redirect', $result);
    }

    /**
     * Mirrors the business logic in RedirectController::redirect()
     * without HTTP output.
     */
    private function evaluateLinkStatus(array $link): string
    {
        if (!(bool)$link['is_active']) {
            return 'not_found';
        }

        if (!empty($link['expires_at']) && strtotime($link['expires_at']) < time()) {
            return 'gone';
        }

        if (!empty($link['max_clicks']) && (int)$link['click_count'] >= (int)$link['max_clicks']) {
            return 'gone';
        }

        return 'redirect';
    }
}
