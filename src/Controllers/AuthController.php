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
use App\Models\User;

class AuthController
{
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
}
