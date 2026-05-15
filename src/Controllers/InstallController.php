<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Migration;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Upgrade;
use App\Core\View;
use App\Models\Setting;
use App\Models\User;

class InstallController
{
    public function showStep1(Request $req, Response $res): void
    {
        // If already installed, redirect
        if ($this->isInstalled()) {
            $res->redirect('/admin');
        }

        $checks = $this->runEnvChecks();
        $html   = View::renderWithLayout('install/step1', [
            'title'  => 'Install – Environment Check',
            'checks' => $checks,
        ]);
        $res->html($html);
    }

    public function handleStep1(Request $req, Response $res): void
    {
        if ($this->isInstalled()) {
            $res->redirect('/admin');
        }

        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/install');
        }

        $checks = $this->runEnvChecks();
        $allPass = !in_array(false, array_column($checks, 'pass'), true);

        if ($allPass) {
            $res->redirect('/install/step2');
        } else {
            Session::flash('error', 'Please fix the environment issues before continuing.');
            $res->redirect('/install');
        }
    }

    public function showStep2(Request $req, Response $res): void
    {
        if ($this->isInstalled()) {
            $res->redirect('/admin');
        }

        $appSecret = bin2hex(random_bytes(16));
        $html = View::renderWithLayout('install/step2', [
            'title'     => 'Install – Configuration',
            'appSecret' => $appSecret,
        ]);
        $res->html($html);
    }

    public function handleStep2(Request $req, Response $res): void
    {
        if ($this->isInstalled()) {
            $res->redirect('/admin');
        }

        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/install/step2');
        }

        // Collect form data
        $appName    = trim((string)$req->post('app_name', 'URL Shortener'));
        $appUrl     = rtrim(trim((string)$req->post('app_url', '')), '/');
        $appSecret  = trim((string)$req->post('app_secret', bin2hex(random_bytes(16))));
        $dbHost     = trim((string)$req->post('db_host', 'localhost'));
        $dbPort     = (int)$req->post('db_port', 3306);
        $dbName     = trim((string)$req->post('db_name', ''));
        $dbUser     = trim((string)$req->post('db_user', ''));
        $dbPass     = (string)$req->post('db_pass', '');
        $dbPrefix   = trim((string)$req->post('db_prefix', 'us_'));
        $adminName  = trim((string)$req->post('admin_name', 'Admin'));
        $adminEmail = trim((string)$req->post('admin_email', ''));
        $adminPass  = (string)$req->post('admin_pass', '');
        $adminPass2 = (string)$req->post('admin_pass2', '');

        // Validate
        $errors = [];
        if (empty($appUrl) || !filter_var($appUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'App URL is invalid.';
        }
        if (empty($dbName)) {
            $errors[] = 'Database name is required.';
        }
        if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Admin email is invalid.';
        }
        if (strlen($adminPass) < 8) {
            $errors[] = 'Admin password must be at least 8 characters.';
        }
        if ($adminPass !== $adminPass2) {
            $errors[] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $res->redirect('/install/step2');
        }

        // Test DB connection
        try {
            $dbConfig = [
                'host'    => $dbHost,
                'port'    => $dbPort,
                'name'    => $dbName,
                'user'    => $dbUser,
                'pass'    => $dbPass,
                'charset' => 'utf8mb4',
                'prefix'  => $dbPrefix,
            ];
            $db = Database::getInstance($dbConfig);
        } catch (\RuntimeException $e) {
            Session::flash('errors', ['Database connection failed: ' . $e->getMessage()]);
            $res->redirect('/install/step2');
        }

        // Build config array
        $config = [
            'app' => [
                'name'      => $appName,
                'url'       => $appUrl,
                'secret'    => $appSecret,
                'debug'     => false,
                'timezone'  => 'UTC',
                'auto_migrate' => true,
                'installed' => true,
            ],
            'db' => [
                'host'    => $dbHost,
                'port'    => $dbPort,
                'name'    => $dbName,
                'user'    => $dbUser,
                'pass'    => $dbPass,
                'charset' => 'utf8mb4',
                'prefix'  => $dbPrefix,
            ],
            'security' => [
                'rate_limit_create'   => 10,
                'rate_limit_redirect' => 300,
                'max_url_length'      => 2048,
                'allowed_protocols'   => ['http', 'https'],
                'blocked_domains'     => [],
                'login_max_attempts'  => 5,
                'login_lockout_mins'  => 15,
            ],
            'analytics' => [
                'enabled'        => true,
                'anonymize_ip'   => true,
                'retention_days' => 365,
            ],
            'api' => [
                'enabled' => true,
            ],
            'cron' => [
                'key' => bin2hex(random_bytes(16)),
            ],
        ];

        // Run migrations
        try {
            Config::setInstallMode(false);
            foreach ($config as $key => $val) {
                Config::set($key, $val);
            }
            $migration = new Migration();
            $migration->run($db, ROOT_PATH . '/migrations');
        } catch (\Throwable $e) {
            Session::flash('errors', ['Migration failed: ' . $e->getMessage()]);
            $res->redirect('/install/step2');
        }

        // Create admin user
        try {
            $userModel = new User();
            $userModel->create([
                'email'         => $adminEmail,
                'password_hash' => password_hash($adminPass, PASSWORD_BCRYPT),
                'name'          => $adminName,
                'role'          => 'admin',
                'status'        => 'active',
            ]);
        } catch (\Throwable $e) {
            Session::flash('errors', ['Failed to create admin user: ' . $e->getMessage()]);
            $res->redirect('/install/step2');
        }

        // Seed default settings
        try {
            $settingModel = new Setting();
            $settingModel->set('site_name', $appName);
            $settingModel->set('allow_registration', '0');
            $settingModel->set('default_redirect_type', '302');
            $settingModel->set('app_version', Upgrade::getCodeVersion());
        } catch (\Throwable $e) {
            // Non-fatal
        }

        // Write config file
        $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
        $configPath    = ROOT_PATH . '/config/config.php';

        if (file_put_contents($configPath, $configContent) === false) {
            Session::flash('errors', ['Could not write config file. Check permissions on /config directory.']);
            $res->redirect('/install/step2');
        }

        $res->redirect('/install/complete');
    }

    public function showComplete(Request $req, Response $res): void
    {
        $html = View::renderWithLayout('install/complete', ['title' => 'Installation Complete']);
        $res->html($html);
    }

    private function isInstalled(): bool
    {
        $configFile = ROOT_PATH . '/config/config.php';
        if (!file_exists($configFile)) {
            return false;
        }
        Config::reload();
        return Config::get('app.installed', false) === true;
    }

    private function runEnvChecks(): array
    {
        $checks = [];

        $checks[] = [
            'label' => 'PHP version >= 8.1',
            'pass'  => version_compare(PHP_VERSION, '8.1.0', '>='),
            'value' => PHP_VERSION,
        ];

        foreach (['pdo', 'pdo_mysql', 'json', 'mbstring'] as $ext) {
            $checks[] = [
                'label' => "Extension: $ext",
                'pass'  => extension_loaded($ext),
                'value' => extension_loaded($ext) ? 'Loaded' : 'Missing',
            ];
        }

        $configDir = ROOT_PATH . '/config';
        $checks[] = [
            'label' => 'config/ directory writable',
            'pass'  => is_writable($configDir),
            'value' => is_writable($configDir) ? 'Writable' : 'Not writable',
        ];

        $logsDir = ROOT_PATH . '/storage/logs';
        $checks[] = [
            'label' => 'storage/logs/ writable',
            'pass'  => is_writable($logsDir),
            'value' => is_writable($logsDir) ? 'Writable' : 'Not writable',
        ];

        $cacheDir = ROOT_PATH . '/storage/cache';
        $checks[] = [
            'label' => 'storage/cache/ writable',
            'pass'  => is_writable($cacheDir),
            'value' => is_writable($cacheDir) ? 'Writable' : 'Not writable',
        ];

        return $checks;
    }
}
