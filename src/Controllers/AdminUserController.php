<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\AuditEvent;
use App\Models\Setting;
use App\Models\SignupInvitation;
use App\Models\User;
use App\Services\Mailer;

class AdminUserController
{
    private const MIN_PASSWORD_LENGTH = 8;
    private const INVITATION_TTL = 86400;
    private const ROLES = ['admin', 'user'];
    private const STATUSES = ['active', 'suspended'];

    public function index(Request $req, Response $res): void
    {
        App::requireAuth();
        App::requireAdmin();

        $users = (new User())->all();

        $html = View::renderWithLayout('admin/users/index', [
            'title'         => 'Users',
            'users'         => $users,
            'currentUserId' => App::currentUserId(),
        ]);
        $res->html($html);
    }

    public function edit(Request $req, Response $res, array $params): void
    {
        App::requireAuth();
        App::requireAdmin();

        $user = $this->loadUser($params, $res);

        $html = View::renderWithLayout('admin/users/edit', [
            'title'         => 'Edit User',
            'user'          => $user,
            'currentUserId' => App::currentUserId(),
        ]);
        $res->html($html);
    }

    public function update(Request $req, Response $res, array $params): void
    {
        App::requireAuth();
        App::requireAdmin();
        $this->verifyCsrf($req, $res);

        $user = $this->loadUser($params, $res);
        $id   = (int)$user['id'];
        $back = '/admin/users/' . $id . '/edit';

        $name   = trim((string)$req->post('name', ''));
        $email  = trim((string)$req->post('email', ''));
        $role   = (string)$req->post('role', $user['role']);
        $status = (string)$req->post('status', $user['status']);

        if ($name === '' || $email === '') {
            Session::flash('error', 'Name and email are required.');
            $res->redirect($back);
        }
        if (mb_strlen($name) > 100) {
            Session::flash('error', 'Name must be at most 100 characters.');
            $res->redirect($back);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            Session::flash('error', 'Please provide a valid email address.');
            $res->redirect($back);
        }
        if (!in_array($role, self::ROLES, true) || !in_array($status, self::STATUSES, true)) {
            Session::flash('error', 'Invalid role or status.');
            $res->redirect($back);
        }

        $userModel = new User();
        $existing  = $userModel->findByEmail($email);
        if ($existing && (int)$existing['id'] !== $id) {
            Session::flash('error', 'Another account already uses this email.');
            $res->redirect($back);
        }

        $isSelf = $id === App::currentUserId();
        if ($isSelf && ($role !== $user['role'] || $status !== $user['status'])) {
            Session::flash('error', 'You cannot change your own role or status.');
            $res->redirect($back);
        }
        if ($user['role'] === 'admin' && $role !== 'admin' && $userModel->countAdmins() <= 1) {
            Session::flash('error', 'Cannot demote the last admin.');
            $res->redirect($back);
        }

        $userModel->update($id, [
            'name'   => $name,
            'email'  => $email,
            'role'   => $role,
            'status' => $status,
        ]);

        $audit = new AuditEvent();
        foreach (['name' => $name, 'email' => $email, 'role' => $role, 'status' => $status] as $key => $value) {
            $audit->recordSettingChange($req, 'admin_user', $key, (string)$user[$key], $value, $id);
        }

        if ($isSelf) {
            Session::set('user_name', $name);
            Session::set('user_email', $email);
        }

        Session::flash('success', 'User updated.');
        $res->redirect($back);
    }

    public function resetPassword(Request $req, Response $res, array $params): void
    {
        App::requireAuth();
        App::requireAdmin();
        $this->verifyCsrf($req, $res);

        $user = $this->loadUser($params, $res);
        $id   = (int)$user['id'];
        $back = '/admin/users/' . $id . '/edit';

        $password = (string)$req->post('password', '');
        $confirm  = (string)$req->post('password_confirm', '');

        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            Session::flash('error', 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.');
            $res->redirect($back);
        }
        if ($password !== $confirm) {
            Session::flash('error', 'Passwords do not match.');
            $res->redirect($back);
        }

        $userModel = new User();
        $userModel->update($id, ['password' => $password]);
        $userModel->resetLoginAttempts($id);
        (new AuditEvent())->recordSettingChange($req, 'admin_user', 'password', null, '[reset]', $id);

        Session::flash('success', 'Password reset.');
        $res->redirect($back);
    }

    public function delete(Request $req, Response $res, array $params): void
    {
        App::requireAuth();
        App::requireAdmin();
        $this->verifyCsrf($req, $res);

        $user = $this->loadUser($params, $res);
        $id   = (int)$user['id'];

        if ($id === App::currentUserId()) {
            Session::flash('error', 'You cannot delete your own account.');
            $res->redirect('/admin/users');
        }

        $userModel = new User();
        if ($user['role'] === 'admin' && $userModel->countAdmins() <= 1) {
            Session::flash('error', 'Cannot delete the last admin.');
            $res->redirect('/admin/users');
        }

        (new AuditEvent())->recordSettingChange($req, 'admin_user', 'deleted', (string)$user['email'], '[deleted]');
        $userModel->delete($id);

        Session::flash('success', 'User deleted.');
        $res->redirect('/admin/users');
    }

    public function invite(Request $req, Response $res): void
    {
        App::requireAuth();
        App::requireAdmin();
        $this->verifyCsrf($req, $res);

        $name  = trim((string)$req->post('name', ''));
        $email = trim((string)$req->post('email', ''));

        if ($name === '' || $email === '' || mb_strlen($name) > 100) {
            Session::flash('error', 'A name (max 100 characters) and email are required.');
            $res->redirect('/admin/users');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            Session::flash('error', 'Please provide a valid email address.');
            $res->redirect('/admin/users');
        }
        if ((new User())->findByEmail($email)) {
            Session::flash('error', 'An account with this email already exists.');
            $res->redirect('/admin/users');
        }

        $token = bin2hex(random_bytes(32));
        (new SignupInvitation())->create([
            'name'       => $name,
            'email'      => $email,
            'token'      => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + self::INVITATION_TTL),
        ]);

        $baseUrl  = rtrim((string)Config::get('app.url', ''), '/');
        $link     = $baseUrl . '/signup/complete?token=' . urlencode($token);
        $safeName = trim(preg_replace('/[\r\n\t]+/', ' ', $name) ?? '');

        try {
            Mailer::fromSettings(new Setting())->send(
                $email,
                'You have been invited',
                "Hello {$safeName},\r\n\r\n"
                . "You have been invited to create an account. Use this link to complete your setup:\r\n{$link}\r\n\r\n"
                . "This invitation expires in 24 hours."
            );
        } catch (\Throwable $e) {
            Session::flash('error', 'Could not send invitation email: ' . $e->getMessage());
            $res->redirect('/admin/users');
        }

        Session::flash('success', 'Invitation sent to ' . $email . '.');
        $res->redirect('/admin/users');
    }

    public function promote(Request $req, Response $res, array $params): void
    {
        App::requireAuth();
        App::requireAdmin();
        $this->verifyCsrf($req, $res);

        $user = $this->loadUser($params, $res);

        if (($user['role'] ?? 'user') === 'admin') {
            Session::flash('error', 'User is already an admin.');
            $res->redirect('/admin/users');
        }

        (new User())->update((int)$user['id'], ['role' => 'admin']);
        Session::flash('success', 'User promoted to admin.');
        $res->redirect('/admin/users');
    }

    private function verifyCsrf(Request $req, Response $res): void
    {
        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/admin/users');
        }
    }

    private function loadUser(array $params, Response $res): array
    {
        $id   = (int)($params['id'] ?? 0);
        $user = $id > 0 ? (new User())->findById($id) : null;
        if (!$user) {
            Session::flash('error', 'User not found.');
            $res->redirect('/admin/users');
        }
        return $user;
    }
}
