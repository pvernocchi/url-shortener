<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\AuditEvent;
use App\Models\Setting;
use App\Models\SignupInvitation;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\CaptchaVerifier;
use App\Services\Mailer;
use App\Services\Totp;

class AuthController
{
    private const INVITATION_TTL = 86400;
    private const MIN_PASSWORD_LENGTH = 8;

    public function showLogin(Request $req, Response $res): void
    {
        if (Session::has('user_id')) {
            $res->redirect('/admin');
        }

        $settingModel = new Setting();
        $captchaProvider = strtolower((string)$settingModel->get('captcha_provider', (string)Config::get('captcha.provider', 'recaptcha')));
        if (!in_array($captchaProvider, ['recaptcha', 'turnstile'], true)) {
            $captchaProvider = 'recaptcha';
        }

        $html = View::renderWithLayout('auth/login', [
            'title'           => 'Login',
            'captchaEnabled'  => $settingModel->get('captcha_enabled', Config::get('captcha.enabled', false) ? '1' : '0') === '1',
            'captchaProvider' => $captchaProvider,
            'captchaSiteKey'  => (string)$settingModel->get('captcha_site_key', (string)Config::get('captcha.site_key', '')),
        ]);
        $res->html($html);
    }

    public function handleLogin(Request $req, Response $res): void
    {
        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/login');
        }

        $email    = trim((string)$req->post('email', ''));
        $password = (string)$req->post('password', '');
        $settingModel = new Setting();

        $captchaProvider = strtolower((string)$settingModel->get('captcha_provider', (string)Config::get('captcha.provider', 'recaptcha')));
        if (!in_array($captchaProvider, ['recaptcha', 'turnstile'], true)) {
            $captchaProvider = 'recaptcha';
        }
        $captchaEnabled = $settingModel->get('captcha_enabled', Config::get('captcha.enabled', false) ? '1' : '0') === '1';
        $captchaSecret = (string)$settingModel->get('captcha_secret_key', (string)Config::get('captcha.secret_key', ''));
        $captchaToken = $captchaProvider === 'turnstile'
            ? (string)$req->post('cf-turnstile-response', '')
            : (string)$req->post('g-recaptcha-response', '');

        // Rate limiting
        $rateLimiter = new RateLimiter();
        $maxAttempts = Config::get('security.login_max_attempts', 5);
        if (!$rateLimiter->check('login', $req->ip(), (int)$maxAttempts, 900)) {
            Session::flash('error', 'Too many login attempts. Please try again later.');
            $res->redirect('/login');
        }

        if (empty($email) || empty($password)) {
            Session::flash('error', 'Email and password are required.');
            $res->redirect('/login');
        }

        $captchaVerifier = new CaptchaVerifier();
        if (!$captchaVerifier->verify($captchaEnabled, $captchaProvider, $captchaSecret, $captchaToken, $req->ip())) {
            Session::flash('error', 'CAPTCHA validation failed. Please try again.');
            $res->redirect('/login');
        }

        $userModel = new User();
        $user      = $userModel->findByEmail($email);

        if (!$user) {
            $rateLimiter->increment('login', $req->ip());
            Session::flash('error', 'Invalid email or password.');
            $res->redirect('/login');
        }

        // Check if account is locked
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
            Session::flash('error', "Account locked. Try again in $mins minute(s).");
            $res->redirect('/login');
        }

        // Check status
        if (($user['status'] ?? 'active') !== 'active') {
            Session::flash('error', 'Your account has been suspended.');
            $res->redirect('/login');
        }

        if (!$userModel->verifyPassword($password, $user['password_hash'])) {
            $rateLimiter->increment('login', $req->ip());
            $userModel->incrementLoginAttempts((int)$user['id']);

            $loginMaxAttempts = (int)Config::get('security.login_max_attempts', 5);
            $newAttempts      = ($user['login_attempts'] ?? 0) + 1;
            if ($newAttempts >= $loginMaxAttempts) {
                $lockoutMins = (int)Config::get('security.login_lockout_mins', 15);
                $userModel->lockUser((int)$user['id'], $lockoutMins);
                Session::flash('error', "Too many failed attempts. Account locked for $lockoutMins minutes.");
            } else {
                Session::flash('error', 'Invalid email or password.');
            }
            $res->redirect('/login');
        }

        // Success
        $userModel->resetLoginAttempts((int)$user['id']);
        $this->clearPendingAuth();

        $mfaPolicy = strtolower((string)$settingModel->get('mfa_policy', (string)Config::get('security.mfa_policy', 'optional')));
        if (!in_array($mfaPolicy, ['optional', 'required'], true)) {
            $mfaPolicy = 'optional';
        }

        $totpEnabled = $settingModel->get('mfa_allow_totp', '1') === '1';
        $totpSecret  = (string)($user['mfa_totp_secret'] ?? '');
        $userSettingModel = new UserSetting();
        $userMfaEnabled = $userSettingModel->isEnabled((int)$user['id'], 'mfa_totp_enabled', false);

        if ($totpEnabled && $userMfaEnabled && $totpSecret !== '') {
            $this->storePendingUser($user, $mfaPolicy === 'required');
            Session::set('pending_mfa_type', 'totp');
            $res->redirect('/login/mfa');
        }

        if ($mfaPolicy === 'required') {
            if (!$totpEnabled) {
                Session::flash('error', 'MFA is required but no supported MFA method is enabled.');
                $res->redirect('/login');
            }

            $this->storePendingUser($user, true);
            $res->redirect('/login/mfa/setup');
        }

        $this->finalizeLogin($user, $res);
    }

    public function handleLogout(Request $req, Response $res): void
    {
        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            $res->redirect('/login');
        }
        Session::destroy();
        $res->redirect('/login');
    }

    public function showSecuritySettings(Request $req, Response $res): void
    {
        App::requireAuth();

        $settingModel = new Setting();
        $totpAllowed = $settingModel->get('mfa_allow_totp', '1') === '1';
        $userSettingModel = new UserSetting();
        $userModel = new User();
        $user = $userModel->findById((int)Session::get('user_id'));
        if (!$user) {
            Session::destroy();
            $res->redirect('/login');
        }

        $userMfaEnabled = $userSettingModel->isEnabled((int)$user['id'], 'mfa_totp_enabled', false);
        $totpConfigured = !empty($user['mfa_totp_secret']);
        $totpSecret = '';
        if ($totpAllowed && (!$userMfaEnabled || !$totpConfigured)) {
            $totpSecret = (string)Session::get('account_totp_secret', '');
            if ($totpSecret === '') {
                $totpSecret = (new Totp())->generateSecret();
                Session::set('account_totp_secret', $totpSecret);
            }
        }

        $issuer = (string)$settingModel->get('site_name', Config::get('app.name', 'URL Shortener'));
        $html = View::renderWithLayout('auth/security_settings', [
            'title'               => 'Security Settings',
            'totpAllowed'         => $totpAllowed,
            'totpEnabled'         => $userMfaEnabled && $totpConfigured,
            'totpConfigured'      => $totpConfigured,
            'totpSecret'          => $totpSecret,
            'totpProvisioningUri' => $totpSecret !== '' ? (new Totp())->provisioningUri($issuer, (string)$user['email'], $totpSecret) : '',
        ]);
        $res->html($html);
    }

    public function updateSecuritySettings(Request $req, Response $res): void
    {
        App::requireAuth();
        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/admin/security');
        }

        $action = (string)$req->post('action', '');
        $userId = (int)Session::get('user_id');
        $userModel = new User();
        $userSettingModel = new UserSetting();
        $user = $userModel->findById($userId);
        if (!$user) {
            Session::destroy();
            $res->redirect('/login');
        }

        $oldMfaEnabled = $userSettingModel->isEnabled($userId, 'mfa_totp_enabled', false);

        if ($action === 'disable_totp') {
            $userModel->clearTotpSecret($userId);
            Session::remove('account_totp_secret');
            $userSettingModel->set($userId, 'mfa_totp_enabled', '0');
            (new AuditEvent())->recordSettingChange($req, 'profile', 'mfa_totp_enabled', $oldMfaEnabled ? '1' : '0', '0', $userId);
            Session::flash('success', 'TOTP has been disabled.');
            $res->redirect('/admin/security');
        }

        if ($action === 'enable_totp') {
            $secret = (string)Session::get('account_totp_secret', '');
            $code = (string)$req->post('mfa_code', '');
            if ($secret === '' || !(new Totp())->verifyCode($secret, $code)) {
                Session::flash('error', 'Invalid authentication code.');
                $res->redirect('/admin/security');
            }

            $userModel->setTotpSecret($userId, $secret);
            $userSettingModel->set($userId, 'mfa_totp_enabled', '1');
            Session::remove('account_totp_secret');
            (new AuditEvent())->recordSettingChange($req, 'profile', 'mfa_totp_enabled', $oldMfaEnabled ? '1' : '0', '1', $userId);
            Session::flash('success', 'TOTP has been enabled.');
            $res->redirect('/admin/security');
        }

        Session::flash('error', 'Unsupported security action.');
        $res->redirect('/admin/security');
    }

    public function showProfileSettings(Request $req, Response $res): void
    {
        App::requireAuth();

        $userId = (int)Session::get('user_id');
        if (!(new UserSetting())->isEnabled($userId, 'profile_edit_enabled', false)) {
            Session::flash('error', 'Profile editing is disabled for your account.');
            $res->redirect('/admin');
        }

        $user = (new User())->findById($userId);
        if (!$user) {
            Session::destroy();
            $res->redirect('/login');
        }

        $html = View::renderWithLayout('auth/profile_settings', [
            'title'             => 'Profile Settings',
            'user'              => $user,
            'minPasswordLength' => self::MIN_PASSWORD_LENGTH,
        ]);
        $res->html($html);
    }

    public function updateProfileSettings(Request $req, Response $res): void
    {
        App::requireAuth();
        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/admin/profile');
        }

        $userId = (int)Session::get('user_id');
        if (!(new UserSetting())->isEnabled($userId, 'profile_edit_enabled', false)) {
            Session::flash('error', 'Profile editing is disabled for your account.');
            $res->redirect('/admin');
        }

        $userModel = new User();
        $user = $userModel->findById($userId);
        if (!$user) {
            Session::destroy();
            $res->redirect('/login');
        }

        $name = trim((string)$req->post('name', ''));
        if ($name === '' || mb_strlen($name) > 100) {
            Session::flash('error', 'Name is required and must be at most 100 characters.');
            $res->redirect('/admin/profile');
        }

        $password = (string)$req->post('password', '');
        $confirmPassword = (string)$req->post('password_confirm', '');
        if ($password !== '' || $confirmPassword !== '') {
            if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
                Session::flash('error', 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.');
                $res->redirect('/admin/profile');
            }
            if ($password !== $confirmPassword) {
                Session::flash('error', 'Passwords do not match.');
                $res->redirect('/admin/profile');
            }
        }

        $updates = [];
        if ($name !== (string)$user['name']) {
            $updates['name'] = $name;
        }
        if ($password !== '') {
            $updates['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }

        if ($updates === []) {
            Session::flash('success', 'No profile changes were made.');
            $res->redirect('/admin/profile');
        }

        $userModel->update($userId, $updates);

        $audit = new AuditEvent();
        if (isset($updates['name'])) {
            $audit->recordSettingChange($req, 'profile', 'name', (string)$user['name'], $name, $userId);
            Session::set('user_name', $name);
        }
        if (isset($updates['password_hash'])) {
            $audit->recordSettingChange($req, 'profile', 'password', null, '[changed]', $userId);
        }

        Session::flash('success', 'Profile updated successfully.');
        $res->redirect('/admin/profile');
    }

    public function showMfaChallenge(Request $req, Response $res): void
    {
        if (Session::has('user_id')) {
            $res->redirect('/admin');
        }

        if (Session::get('pending_mfa_type') !== 'totp' || !$this->hasPendingUser()) {
            $res->redirect('/login');
        }

        $html = View::renderWithLayout('auth/mfa_challenge', [
            'title'       => 'Multi-factor authentication',
            'pendingEmail' => (string)Session::get('pending_user_email', ''),
        ]);
        $res->html($html);
    }

    public function handleMfaChallenge(Request $req, Response $res): void
    {
        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/login');
        }

        if (Session::get('pending_mfa_type') !== 'totp' || !$this->hasPendingUser()) {
            $res->redirect('/login');
        }

        $userId = (int)Session::get('pending_user_id');
        $user   = (new User())->findById($userId);
        if (!$user || ($user['status'] ?? 'active') !== 'active') {
            $this->clearPendingAuth();
            Session::flash('error', 'Your account is no longer available.');
            $res->redirect('/login');
        }

        $secret = (string)($user['mfa_totp_secret'] ?? '');
        $code   = (string)$req->post('mfa_code', '');
        if ($secret === '' || !(new Totp())->verifyCode($secret, $code)) {
            Session::flash('error', 'Invalid authentication code.');
            $res->redirect('/login/mfa');
        }

        $this->finalizeLogin($user, $res);
    }

    public function showMfaSetup(Request $req, Response $res): void
    {
        if (Session::has('user_id')) {
            $res->redirect('/admin');
        }

        if (!$this->hasPendingUser()) {
            $res->redirect('/login');
        }

        $settingModel = new Setting();

        $totpEnabled = $settingModel->get('mfa_allow_totp', '1') === '1';
        if (!$totpEnabled) {
            Session::flash('error', 'TOTP setup is currently disabled by your administrator.');
            $res->redirect('/login');
        }

        $totp = new Totp();
        $secret = (string)Session::get('pending_mfa_secret', '');
        if ($secret === '') {
            $secret = $totp->generateSecret();
            Session::set('pending_mfa_secret', $secret);
        }

        $issuer = (string)$settingModel->get('site_name', Config::get('app.name', 'URL Shortener'));
        $email  = (string)Session::get('pending_user_email', '');

        $html = View::renderWithLayout('auth/mfa_setup', [
            'title'                  => 'Set up MFA',
            'totpSecret'             => $secret,
            'totpProvisioningUri'    => $totp->provisioningUri($issuer, $email, $secret),
            'mfaRequired'            => Session::get('pending_mfa_required') === true,
            'allowWebauthnPlatform'  => $settingModel->get('mfa_allow_webauthn_platform', '1') === '1',
            'allowWebauthnYubikey'   => $settingModel->get('mfa_allow_webauthn_security_key', '1') === '1',
        ]);
        $res->html($html);
    }

    public function handleMfaSetup(Request $req, Response $res): void
    {
        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/login');
        }

        if (!$this->hasPendingUser()) {
            $res->redirect('/login');
        }

        $action = (string)$req->post('action', 'enable_totp');
        if ($action === 'skip' && Session::get('pending_mfa_required') !== true) {
            $user = (new User())->findById((int)Session::get('pending_user_id'));
            if (!$user) {
                $this->clearPendingAuth();
                Session::flash('error', 'Your account is no longer available.');
                $res->redirect('/login');
            }
            $this->finalizeLogin($user, $res);
        }

        $secret = (string)Session::get('pending_mfa_secret', '');
        $code   = (string)$req->post('mfa_code', '');
        if ($secret === '' || !(new Totp())->verifyCode($secret, $code)) {
            Session::flash('error', 'Invalid code. Please try again.');
            $res->redirect('/login/mfa/setup');
        }

        $userId = (int)Session::get('pending_user_id');
        $userModel = new User();
        $userModel->setTotpSecret($userId, $secret);
        (new UserSetting())->set($userId, 'mfa_totp_enabled', '1');
        $user = $userModel->findById($userId);
        if (!$user) {
            $this->clearPendingAuth();
            Session::flash('error', 'Your account is no longer available.');
            $res->redirect('/login');
        }

        $this->finalizeLogin($user, $res);
    }

    public function showSignupRequest(Request $req, Response $res): void
    {
        if (Session::has('user_id')) {
            $res->redirect('/admin');
        }

        $html = View::renderWithLayout('auth/signup_request', ['title' => 'Create Account']);
        $res->html($html);
    }

    public function handleSignupRequest(Request $req, Response $res): void
    {
        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/signup');
        }

        $name  = trim((string)$req->post('name', ''));
        $email = trim((string)$req->post('email', ''));

        if ($name === '' || $email === '') {
            Session::flash('error', 'Name and email are required.');
            $res->redirect('/signup');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please provide a valid email address.');
            $res->redirect('/signup');
        }

        $userModel = new User();
        if ($userModel->findByEmail($email)) {
            Session::flash('error', 'An account with this email already exists.');
            $res->redirect('/login');
        }

        $token      = bin2hex(random_bytes(32));
        $expiresAt  = date('Y-m-d H:i:s', time() + self::INVITATION_TTL);
        $inviteModel = new SignupInvitation();
        $inviteModel->create([
            'name'       => $name,
            'email'      => $email,
            'token'      => $token,
            'expires_at' => $expiresAt,
        ]);

        $baseUrl = rtrim((string)Config::get('app.url', ''), '/');
        $link    = $baseUrl . '/signup/complete?token=' . urlencode($token);
        $safeName = $this->sanitizeForEmailBody($name);
        $safeLink = $this->sanitizeForEmailBody($link);

        try {
            $mailer = Mailer::fromSettings(new Setting());
            $mailer->send(
                $email,
                'Complete your account setup',
                "Hello {$safeName},\r\n\r\n"
                . "Use this link to complete your account setup:\r\n{$safeLink}\r\n\r\n"
                . "This invitation expires in 24 hours."
            );
        } catch (\Throwable $e) {
            Session::flash('error', 'Could not send invitation email: ' . $e->getMessage());
            $res->redirect('/signup');
        }

        Session::flash('success', 'Invitation sent. Check your email to complete account creation.');
        $res->redirect('/login');
    }

    public function showSignupComplete(Request $req, Response $res): void
    {
        if (Session::has('user_id')) {
            $res->redirect('/admin');
        }

        $token = trim((string)$req->get('token', ''));
        if ($token === '') {
            Session::flash('error', 'Invalid invitation link.');
            $res->redirect('/signup');
        }

        $invitation = (new SignupInvitation())->findValidByToken($token);
        if (!$invitation) {
            Session::flash('error', 'Invitation is invalid or expired.');
            $res->redirect('/signup');
        }

        $html = View::renderWithLayout('auth/signup_complete', [
            'title'      => 'Set Your Password',
            'token'      => $token,
            'name'       => $invitation['name'],
            'email'      => $invitation['email'],
        ]);
        $res->html($html);
    }

    public function handleSignupComplete(Request $req, Response $res): void
    {
        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/signup');
        }

        $token           = trim((string)$req->post('token', ''));
        $password        = (string)$req->post('password', '');
        $confirmPassword = (string)$req->post('password_confirm', '');

        if ($token === '') {
            Session::flash('error', 'Invalid invitation.');
            $res->redirect('/signup');
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            Session::flash('error', 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.');
            $res->redirect('/signup/complete?token=' . urlencode($token));
        }
        if ($password !== $confirmPassword) {
            Session::flash('error', 'Passwords do not match.');
            $res->redirect('/signup/complete?token=' . urlencode($token));
        }

        $inviteModel = new SignupInvitation();
        $invitation  = $inviteModel->findValidByToken($token);
        if (!$invitation) {
            Session::flash('error', 'Invitation is invalid or expired.');
            $res->redirect('/signup');
        }

        $userModel = new User();
        if ($userModel->findByEmail((string)$invitation['email'])) {
            Session::flash('error', 'An account with this email already exists.');
            $res->redirect('/login');
        }

        $newUserId = $userModel->create([
            'name'     => $invitation['name'],
            'email'    => $invitation['email'],
            'password' => $password,
            'role'     => 'user',
            'status'   => 'active',
        ]);
        (new UserSetting())->ensureDefaultsForUser($newUserId);
        $inviteModel->markUsed((int)$invitation['id']);

        Session::flash('success', 'Account created successfully. You can now sign in.');
        $res->redirect('/login');
    }

    private function sanitizeForEmailBody(string $value): string
    {
        $value = preg_replace('/[\r\n\t]+/', ' ', $value) ?? '';
        return trim($value);
    }

    private function finalizeLogin(array $user, Response $res): void
    {
        $this->clearPendingAuth();
        Session::regenerate();
        Session::set('user_id', (int)$user['id']);
        Session::set('user_email', $user['email']);
        Session::set('user_name', $user['name']);
        Session::set('user_role', $user['role']);
        $res->redirect('/admin');
    }

    private function hasPendingUser(): bool
    {
        return Session::get('pending_user_id') !== null;
    }

    private function storePendingUser(array $user, bool $required): void
    {
        Session::set('pending_user_id', (int)$user['id']);
        Session::set('pending_user_email', $user['email']);
        Session::set('pending_user_name', $user['name']);
        Session::set('pending_user_role', $user['role']);
        Session::set('pending_mfa_required', $required);
    }

    private function clearPendingAuth(): void
    {
        foreach ([
            'pending_user_id',
            'pending_user_email',
            'pending_user_name',
            'pending_user_role',
            'pending_mfa_required',
            'pending_mfa_type',
            'pending_mfa_secret',
        ] as $key) {
            Session::remove($key);
        }
    }
}
