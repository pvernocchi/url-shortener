<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\CronController;
use App\Controllers\InstallController;
use App\Controllers\LinkController;
use App\Controllers\RedirectController;

class App
{
    private Router $router;
    private bool $autoMigrationChecked = false;

    public function __construct()
    {
        $this->router = new Router();
    }

    public function run(): void
    {
        $this->bootstrap();
        $this->registerRoutes();

        $request  = new Request();
        $response = new Response();

        // If not installed, only allow install routes
        if (!$this->isInstalled()) {
            $path = $request->getPath();
            if (!str_starts_with($path, '/install')) {
                $response->redirect('/install');
            }
        }

        $this->router->dispatch($request);
    }

    private function bootstrap(): void
    {
        // Determine if we're in install mode
        $isInstallMode = !file_exists(ROOT_PATH . '/config/config.php');
        Config::setInstallMode($isInstallMode);

        // Try to load config (won't throw in install mode)
        if (!$isInstallMode) {
            Config::load();
            $this->defineAppVersionConstant();
            $timezone = Config::get('app.timezone', 'UTC');
            date_default_timezone_set($timezone);
        } else {
            $this->defineAppVersionConstant();
            date_default_timezone_set('UTC');
        }

        // Error handling
        $debug = Config::get('app.debug', false);
        if ($debug) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            error_reporting(E_ALL);
        }

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);

        // Start session
        Session::start();

        $this->runAutoMigrationsIfNeeded($isInstallMode);
    }

    private function isInstalled(): bool
    {
        $configFile = ROOT_PATH . '/config/config.php';
        if (!file_exists($configFile)) {
            return false;
        }
        return Config::get('app.installed', false) === true;
    }

    private function registerRoutes(): void
    {
        $r = $this->router;

        // Install
        $r->add('GET',  '/install',          [InstallController::class, 'showStep1']);
        $r->add('POST', '/install/step1',    [InstallController::class, 'handleStep1']);
        $r->add('GET',  '/install/step2',    [InstallController::class, 'showStep2']);
        $r->add('POST', '/install/step2',    [InstallController::class, 'handleStep2']);
        $r->add('GET',  '/install/complete', [InstallController::class, 'showComplete']);

        // Auth
        $r->add('GET',  '/login',  [AuthController::class, 'showLogin']);
        $r->add('POST', '/login',  [AuthController::class, 'handleLogin']);
        $r->add('POST', '/logout', [AuthController::class, 'handleLogout']);

        // Admin
        $r->add('GET',  '/admin',              [AdminController::class, 'dashboard']);
        $r->add('GET',  '/admin/settings',     [AdminController::class, 'settings']);
        $r->add('POST', '/admin/settings',     [AdminController::class, 'updateSettings']);
        $r->add('GET',  '/admin/backup',       [AdminController::class, 'backup']);
        $r->add('GET',  '/admin/diagnostics',  [AdminController::class, 'diagnostics']);
        $r->add('POST', '/admin/upgrade',      [AdminController::class, 'runUpgrade']);

        // Links
        $r->add('GET',  '/admin/links',                [LinkController::class, 'index']);
        $r->add('GET',  '/admin/links/create',         [LinkController::class, 'create']);
        $r->add('POST', '/admin/links',                [LinkController::class, 'store']);
        $r->add('GET',  '/admin/links/{id}/edit',      [LinkController::class, 'edit']);
        $r->add('POST', '/admin/links/{id}',           [LinkController::class, 'update']);
        $r->add('POST', '/admin/links/{id}/delete',    [LinkController::class, 'delete']);
        $r->add('POST', '/admin/links/{id}/toggle',    [LinkController::class, 'toggle']);
        $r->add('GET',  '/admin/links/{id}/analytics', [LinkController::class, 'analytics']);

        // API
        $r->add('GET',    '/api/v1/links',        [ApiController::class, 'listLinks']);
        $r->add('POST',   '/api/v1/links',        [ApiController::class, 'createLink']);
        $r->add('GET',    '/api/v1/links/{code}', [ApiController::class, 'getLink']);
        $r->add('DELETE', '/api/v1/links/{id}',   [ApiController::class, 'deleteLink']);

        // Cron
        $r->add('GET', '/cron/cleanup', [CronController::class, 'cleanup']);
        $r->add('GET', '/cron/upgrade', [CronController::class, 'upgrade']);

        // Redirect (must be last)
        $r->add('GET', '/{code}', [RedirectController::class, 'redirect']);
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        $this->logError("PHP Error [$errno]: $errstr in $errfile:$errline");
        return true;
    }

    public function handleException(\Throwable $e): void
    {
        $this->logError(
            sprintf(
                "Uncaught %s: %s in %s:%d\nStack trace:\n%s",
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            )
        );

        $debug = Config::get('app.debug', false);
        if ($debug) {
            echo '<pre>' . e(get_class($e) . ': ' . $e->getMessage() . "\n\n" . $e->getTraceAsString()) . '</pre>';
        } else {
            http_response_code(500);
            echo View::render('errors/404', ['title' => 'Server Error']);
        }
    }

    private function logError(string $message): void
    {
        $this->ensureLogDirectoryExists();

        $logFile = ROOT_PATH . '/storage/logs/error.log';
        $line    = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private function ensureLogDirectoryExists(): void
    {
        $logDir = ROOT_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
    }

    private function defineAppVersionConstant(): void
    {
        if (defined('APP_VERSION')) {
            return;
        }

        $versionFile = ROOT_PATH . '/VERSION';
        $version = '0.0.0';
        if (file_exists($versionFile)) {
            $fileVersion = trim((string)file_get_contents($versionFile));
            if ($fileVersion !== '') {
                $version = $fileVersion;
            }
        }

        define('APP_VERSION', $version);
    }

    private function runAutoMigrationsIfNeeded(bool $isInstallMode): void
    {
        if ($this->autoMigrationChecked || $isInstallMode || !$this->isInstalled()) {
            return;
        }

        $this->autoMigrationChecked = true;

        $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (str_starts_with($path, '/install')) {
            return;
        }

        if (Config::get('app.auto_migrate', true) !== true) {
            return;
        }

        try {
            $db       = Database::getInstance();
            $executed = (new Migration())->run($db, ROOT_PATH . '/migrations');
            Upgrade::syncVersion();
        } catch (\Throwable $e) {
            $this->logError('Auto-migration failed: ' . $e->getMessage());
            if (Config::get('app.debug', false) === true) {
                throw $e;
            }
            http_response_code(503);
            echo View::render('errors/upgrade', ['title' => 'Service Unavailable']);
            exit;
        }
    }

    public static function requireAuth(): void
    {
        Session::start();
        if (!Session::has('user_id')) {
            $response = new Response();
            $response->redirect('/login');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireAuth();
        if (Session::get('user_role') !== 'admin') {
            $response = new Response();
            $response->redirect('/admin');
        }
    }

    public static function currentUserId(): ?int
    {
        $id = Session::get('user_id');
        return $id !== null ? (int)$id : null;
    }

    public static function currentUserRole(): ?string
    {
        return Session::get('user_role');
    }

    public static function isAdmin(): bool
    {
        return Session::get('user_role') === 'admin';
    }
}
