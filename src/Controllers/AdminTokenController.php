<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\ApiTokenIssuer;

class AdminTokenController
{
    public function index(Request $req, Response $res): void
    {
        App::requireAuth();
        App::requireAdmin();

        $tokenModel = new ApiToken();
        $tokens     = $tokenModel->allWithUsers();

        $html = View::renderWithLayout('admin/tokens/index', [
            'title'  => 'API Tokens',
            'tokens' => $tokens,
        ]);
        $res->html($html);
    }

    public function create(Request $req, Response $res): void
    {
        App::requireAuth();
        App::requireAdmin();

        $userModel = new User();
        $users     = $userModel->all();

        $html = View::renderWithLayout('admin/tokens/create', [
            'title'         => 'Create API Token',
            'users'         => $users,
            'currentUserId' => App::currentUserId(),
        ]);
        $res->html($html);
    }

    public function store(Request $req, Response $res): void
    {
        App::requireAuth();
        App::requireAdmin();

        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $this->redirectBack($req, $res, '/admin/tokens/create');
            return;
        }

        $name = trim((string)$req->post('name', ''));
        if ($name === '' || mb_strlen($name) > 100) {
            Session::flash('error', 'Token name is required and must be 1-100 characters.');
            $res->redirect('/admin/tokens/create');
            return;
        }

        $scopes = ApiTokenIssuer::normalizeScopes((array)$req->post('scopes', []));
        $userId = $this->resolveUserId($req);

        $issuer       = new ApiTokenIssuer();
        $issued       = $issuer->issue();
        $tokenModel   = new ApiToken();
        $tokenModel->createHashed($userId, $name, $issued['hash'], $scopes);

        Session::flash('success', 'API token created successfully.');
        Session::flash('new_token_plaintext', $issued['raw']);
        $res->redirect('/admin/tokens');
    }

    public function revoke(Request $req, Response $res, array $params): void
    {
        App::requireAuth();
        App::requireAdmin();

        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $this->redirectBack($req, $res, '/admin/tokens');
            return;
        }

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'Invalid token id.');
            $res->redirect('/admin/tokens');
            return;
        }

        $tokenModel = new ApiToken();
        $tokenModel->revoke($id);
        Session::flash('success', 'Token revoked.');
        $res->redirect('/admin/tokens');
    }

    public function delete(Request $req, Response $res, array $params): void
    {
        App::requireAuth();
        App::requireAdmin();

        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $this->redirectBack($req, $res, '/admin/tokens');
            return;
        }

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'Invalid token id.');
            $res->redirect('/admin/tokens');
            return;
        }

        $tokenModel = new ApiToken();
        $tokenModel->deleteById($id);
        Session::flash('success', 'Token deleted.');
        $res->redirect('/admin/tokens');
    }

    private function resolveUserId(Request $req): int
    {
        $currentUserId = (int)(App::currentUserId() ?? 0);
        $candidate     = (int)$req->post('user_id', 0);
        if ($candidate <= 0) {
            return $currentUserId;
        }

        $userModel = new User();
        $user      = $userModel->findById($candidate);

        return $user ? (int)$user['id'] : $currentUserId;
    }

    private function redirectBack(Request $req, Response $res, string $fallback): void
    {
        $referer = $req->referer();
        if ($referer !== '') {
            $path = parse_url($referer, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $res->redirect($path);
                return;
            }
        }
        $res->redirect($fallback);
        return;
    }
}
