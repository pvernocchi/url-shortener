<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\User;

class AdminUserController
{
    public function index(Request $req, Response $res): void
    {
        App::requireAuth();
        App::requireAdmin();

        $users = (new User())->all();

        $html = View::renderWithLayout('admin/users/index', [
            'title' => 'Users',
            'users' => $users,
        ]);
        $res->html($html);
    }

    public function promote(Request $req, Response $res, array $params): void
    {
        App::requireAuth();
        App::requireAdmin();

        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/admin/users');
        }

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'Invalid user.');
            $res->redirect('/admin/users');
        }

        $userModel = new User();
        $user      = $userModel->findById($id);
        if (!$user) {
            Session::flash('error', 'User not found.');
            $res->redirect('/admin/users');
        }

        if (($user['role'] ?? 'user') === 'admin') {
            Session::flash('success', 'User is already an admin.');
            $res->redirect('/admin/users');
        }

        $userModel->update($id, ['role' => 'admin']);
        Session::flash('success', 'User promoted to admin.');
        $res->redirect('/admin/users');
    }
}
