<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\App;
use PHPUnit\Framework\TestCase;

class AppRoutesTest extends TestCase
{
    public function testRegistersSignupAndAdminUserRoutes(): void
    {
        $app = new App();

        $registerRoutes = new \ReflectionMethod($app, 'registerRoutes');
        $registerRoutes->setAccessible(true);
        $registerRoutes->invoke($app);

        $routerProperty = new \ReflectionProperty($app, 'router');
        $routerProperty->setAccessible(true);
        $router = $routerProperty->getValue($app);

        $routesProperty = new \ReflectionProperty($router, 'routes');
        $routesProperty->setAccessible(true);
        $routes = $routesProperty->getValue($router);

        $routeMap = [];
        foreach ($routes as $route) {
            $routeMap[$route['method'] . ' ' . $route['pattern']] = true;
        }

        $this->assertArrayHasKey('GET /signup', $routeMap);
        $this->assertArrayHasKey('POST /signup', $routeMap);
        $this->assertArrayHasKey('GET /signup/complete', $routeMap);
        $this->assertArrayHasKey('POST /signup/complete', $routeMap);
        $this->assertArrayHasKey('GET /login/mfa', $routeMap);
        $this->assertArrayHasKey('POST /login/mfa', $routeMap);
        $this->assertArrayHasKey('GET /login/mfa/setup', $routeMap);
        $this->assertArrayHasKey('POST /login/mfa/setup', $routeMap);
        $this->assertArrayHasKey('GET /admin/security', $routeMap);
        $this->assertArrayHasKey('POST /admin/security', $routeMap);
        $this->assertArrayHasKey('GET /admin/profile', $routeMap);
        $this->assertArrayHasKey('POST /admin/profile', $routeMap);
        $this->assertArrayHasKey('GET /admin/users', $routeMap);
        $this->assertArrayHasKey('POST /admin/users/{id}/promote', $routeMap);
    }
}
