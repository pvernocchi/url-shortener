<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Csrf;
use App\Core\Upgrade;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\AuditEvent;
use App\Models\ClickEvent;
use App\Models\Link;
use App\Models\Setting;
use App\Services\Mailer;

class AdminController
{
    public function dashboard(Request $req, Response $res): void
    {
        App::requireAuth();
        if (!App::isAdmin()) {
            $res->redirect('/admin/links/create');
        }

        $linkModel  = new Link();
        $clickModel = new ClickEvent();

        $totalLinks  = $linkModel->countAll();
        $totalClicks = $linkModel->totalClicks();
        $activeLinks = $linkModel->countActive();
        $recentLinks = $linkModel->recent(5);

        $html = View::renderWithLayout('admin/dashboard', [
            'title'       => 'Dashboard',
            'totalLinks'  => $totalLinks,
            'totalClicks' => $totalClicks,
            'activeLinks' => $activeLinks,
            'recentLinks' => $recentLinks,
        ]);
        $res->html($html);
    }

    public function settings(Request $req, Response $res): void
    {
        App::requireAuth();
        App::requireAdmin();

        $settingModel = new Setting();
        $settings     = $settingModel->all();

        $html = View::renderWithLayout('admin/settings', [
            'title'    => 'Settings',
            'settings' => $settings,
        ]);
        $res->html($html);
    }

    public function updateSettings(Request $req, Response $res): void
    {
        App::requireAuth();
        App::requireAdmin();

        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/admin/settings');
        }

        $settingModel = new Setting();
        $currentSettings = $settingModel->all();
        $audit = new AuditEvent();
        $fields       = [
            'site_name',
            'allow_registration',
            'default_redirect_type',
            'captcha_site_key',
            'captcha_secret_key',
        ];

        foreach ($fields as $field) {
            $value = $req->post($field);
            if ($value !== null) {
                $newValue = (string)$value;
                $oldValue = isset($currentSettings[$field]) ? (string)$currentSettings[$field] : null;
                $settingModel->set($field, $newValue);
                $audit->recordSettingChange($req, 'system', $field, $oldValue, $newValue);
            }
        }

        $mfaPolicy = (string)$req->post('mfa_policy', 'optional');
        if (!in_array($mfaPolicy, ['optional', 'required'], true)) {
            $mfaPolicy = 'optional';
        }
        $oldMfaPolicy = isset($currentSettings['mfa_policy']) ? (string)$currentSettings['mfa_policy'] : null;
        $settingModel->set('mfa_policy', $mfaPolicy);
        $audit->recordSettingChange($req, 'system', 'mfa_policy', $oldMfaPolicy, $mfaPolicy);

        $captchaProvider = (string)$req->post('captcha_provider', 'recaptcha');
        if (!in_array($captchaProvider, ['recaptcha', 'turnstile'], true)) {
            $captchaProvider = 'recaptcha';
        }
        $oldCaptchaProvider = isset($currentSettings['captcha_provider']) ? (string)$currentSettings['captcha_provider'] : null;
        $settingModel->set('captcha_provider', $captchaProvider);
        $audit->recordSettingChange($req, 'system', 'captcha_provider', $oldCaptchaProvider, $captchaProvider);

        $mfaAllowTotp = $req->post('mfa_allow_totp') === '1' ? '1' : '0';
        $mfaAllowWebauthnPlatform = $req->post('mfa_allow_webauthn_platform') === '1' ? '1' : '0';
        $mfaAllowWebauthnSecurityKey = $req->post('mfa_allow_webauthn_security_key') === '1' ? '1' : '0';
        $captchaEnabled = $req->post('captcha_enabled') === '1' ? '1' : '0';

        $settingModel->set('mfa_allow_totp', $mfaAllowTotp);
        $audit->recordSettingChange($req, 'system', 'mfa_allow_totp', isset($currentSettings['mfa_allow_totp']) ? (string)$currentSettings['mfa_allow_totp'] : null, $mfaAllowTotp);

        $settingModel->set('mfa_allow_webauthn_platform', $mfaAllowWebauthnPlatform);
        $audit->recordSettingChange($req, 'system', 'mfa_allow_webauthn_platform', isset($currentSettings['mfa_allow_webauthn_platform']) ? (string)$currentSettings['mfa_allow_webauthn_platform'] : null, $mfaAllowWebauthnPlatform);

        $settingModel->set('mfa_allow_webauthn_security_key', $mfaAllowWebauthnSecurityKey);
        $audit->recordSettingChange($req, 'system', 'mfa_allow_webauthn_security_key', isset($currentSettings['mfa_allow_webauthn_security_key']) ? (string)$currentSettings['mfa_allow_webauthn_security_key'] : null, $mfaAllowWebauthnSecurityKey);

        $settingModel->set('captcha_enabled', $captchaEnabled);
        $audit->recordSettingChange($req, 'system', 'captcha_enabled', isset($currentSettings['captcha_enabled']) ? (string)$currentSettings['captcha_enabled'] : null, $captchaEnabled);

        Setting::clearCache();
        Session::flash('success', 'Settings updated successfully.');
        $res->redirect('/admin/settings');
    }

    public function backup(Request $req, Response $res): void
    {
        App::requireAuth();
        App::requireAdmin();

        $linkModel = new Link();
        $links     = $linkModel->all(1, 100000);

        $csv  = "id,short_code,original_url,title,redirect_type,click_count,is_active,expires_at,created_at\n";
        foreach ($links as $link) {
            $csv .= implode(',', [
                $link['id'],
                '"' . str_replace('"', '""', $link['short_code']) . '"',
                '"' . str_replace('"', '""', $link['original_url']) . '"',
                '"' . str_replace('"', '""', $link['title'] ?? '') . '"',
                $link['redirect_type'],
                $link['click_count'],
                $link['is_active'],
                $link['expires_at'] ?? '',
                $link['created_at'],
            ]) . "\n";
        }

        $res->download($csv, 'links-backup-' . date('Y-m-d') . '.csv', 'text/csv');
    }

    public function diagnostics(Request $req, Response $res): void
    {
        App::requireAuth();
        App::requireAdmin();

        $dirs = [
            'config/'         => ROOT_PATH . '/config',
            'storage/logs/'   => ROOT_PATH . '/storage/logs',
            'storage/cache/'  => ROOT_PATH . '/storage/cache',
        ];

        $writableDirs = [];
        foreach ($dirs as $label => $path) {
            $writableDirs[$label] = is_writable($path);
        }

        $codeVersion   = Upgrade::getCodeVersion();
        $dbVersion     = Upgrade::getStoredVersion();
        $lastMigration = Upgrade::getLastMigrationFilename();
        $versionsMatch = !Upgrade::needsVersionUpdate($codeVersion, $dbVersion);

        $html = View::renderWithLayout('admin/diagnostics', [
            'title'        => 'Diagnostics',
            'phpVersion'   => PHP_VERSION,
            'extensions'   => get_loaded_extensions(),
            'writableDirs' => $writableDirs,
            'codeVersion'  => $codeVersion,
            'dbVersion'    => $dbVersion,
            'lastMigration'=> $lastMigration,
            'versionsMatch'=> $versionsMatch,
        ]);
        $res->html($html);
    }

    public function emailSettings(Request $req, Response $res): void
    {
        App::requireAuth();
        App::requireAdmin();

        $settingModel = new Setting();
        $settings     = $settingModel->all();

        $html = View::renderWithLayout('admin/email_settings', [
            'title'    => 'Email Settings',
            'settings' => $settings,
        ]);
        $res->html($html);
    }

    public function updateEmailSettings(Request $req, Response $res): void
    {
        App::requireAuth();
        App::requireAdmin();

        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/admin/settings/email');
        }

        $settingModel = new Setting();
        $currentSettings = $settingModel->all();
        $audit = new AuditEvent();

        $fields = [
            'smtp_host',
            'smtp_port',
            'smtp_encryption',
            'smtp_username',
            'smtp_from_address',
            'smtp_from_name',
        ];

        foreach ($fields as $field) {
            $value = $req->post($field);
            if ($value !== null) {
                $newValue = (string)$value;
                $oldValue = isset($currentSettings[$field]) ? (string)$currentSettings[$field] : null;
                $settingModel->set($field, $newValue);
                $audit->recordSettingChange($req, 'system', $field, $oldValue, $newValue);
            }
        }

        // Password: only update when a non-empty value is submitted
        $password = $req->post('smtp_password');
        if ($password !== null && $password !== '') {
            $hadPassword = ($currentSettings['smtp_password'] ?? '') !== '';
            $oldMasked = $hadPassword ? '[set]' : null;
            $newMasked = $hadPassword ? '[changed]' : '[set]';
            $settingModel->set('smtp_password', $password);
            $audit->recordSettingChange($req, 'system', 'smtp_password', $oldMasked, $newMasked);
        }

        // Checkbox → '1' when checked, '0' otherwise
        $smtpLogging = $req->post('smtp_logging') === '1' ? '1' : '0';
        $settingModel->set('smtp_logging', $smtpLogging);
        $audit->recordSettingChange($req, 'system', 'smtp_logging', isset($currentSettings['smtp_logging']) ? (string)$currentSettings['smtp_logging'] : null, $smtpLogging);

        Setting::clearCache();
        Session::flash('success', 'Email settings updated successfully.');
        $res->redirect('/admin/settings/email');
    }

    public function testEmail(Request $req, Response $res): void
    {
        App::requireAuth();
        App::requireAdmin();

        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/admin/settings/email');
        }

        $to = trim((string)$req->post('test_email_to'));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please enter a valid recipient e-mail address.');
            $res->redirect('/admin/settings/email');
        }

        try {
            $mailer = Mailer::fromSettings(new Setting());
            $mailer->sendTest($to);
            Session::flash('success', "Test e-mail sent successfully to {$to}.");
        } catch (\Throwable $e) {
            Session::flash('error', 'Failed to send test e-mail: ' . $e->getMessage());
        }

        $res->redirect('/admin/settings/email');
    }

    public function runUpgrade(Request $req, Response $res): void
    {
        App::requireAuth();
        App::requireAdmin();

        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/admin/diagnostics');
        }

        try {
            $executed = Upgrade::runPendingMigrations();
            if ($executed === []) {
                Session::flash('success', 'Already up to date.');
            } else {
                Session::flash('success', 'Executed migrations: ' . implode(', ', $executed));
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'Upgrade failed: ' . $e->getMessage());
        }

        $res->redirect('/admin/diagnostics');
    }
}
