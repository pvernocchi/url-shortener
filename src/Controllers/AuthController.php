<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;
use App\Models\SignupInvitation;
use App\Models\User;
use App\Services\Mailer;

class AuthController
{
    private const SIGNUP_INVITATION_TTL_SECONDS = 86400;

    public function showLogin(Request $req, Response $res): void
    {
        if (Session::has('user_id')) {
            $res->redirect('/admin');
        }
        $html = View::renderWithLayout('auth/login', ['title' => 'Login']);
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
        Session::regenerate();
        Session::set('user_id', (int)$user['id']);
        Session::set('user_email', $user['email']);
        Session::set('user_name', $user['name']);
        Session::set('user_role', $user['role']);

        $res->redirect('/admin');
    }

    public function handleLogout(Request $req, Response $res): void
    {
        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            $res->redirect('/login');
        }
        Session::destroy();
        $res->redirect('/login');
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
        $expiresAt  = date('Y-m-d H:i:s', time() + self::SIGNUP_INVITATION_TTL_SECONDS);
        $inviteModel = new SignupInvitation();
        $inviteModel->create([
            'name'       => $name,
            'email'      => $email,
            'token'      => $token,
            'expires_at' => $expiresAt,
        ]);

        $baseUrl = rtrim((string)Config::get('app.url', ''), '/');
        $link    = $baseUrl . '/signup/complete?token=' . urlencode($token);

        try {
            $mailer = Mailer::fromSettings(new Setting());
            $mailer->send(
                $email,
                'Complete your account setup',
                "Hello {$name},\r\n\r\n"
                . "Use this link to complete your account setup:\r\n{$link}\r\n\r\n"
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
        if (strlen($password) < 8) {
            Session::flash('error', 'Password must be at least 8 characters.');
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

        $userModel->create([
            'name'     => $invitation['name'],
            'email'    => $invitation['email'],
            'password' => $password,
            'role'     => 'user',
            'status'   => 'active',
        ]);
        $inviteModel->markUsed((int)$invitation['id']);

        Session::flash('success', 'Account created successfully. You can now sign in.');
        $res->redirect('/login');
    }
}
