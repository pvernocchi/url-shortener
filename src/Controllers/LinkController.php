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
use App\Models\ClickEvent;
use App\Models\Link;
use App\Models\Setting;
use App\Services\Mailer;
use App\Services\SlugGenerator;
use App\Services\UrlValidator;

class LinkController
{
    public function index(Request $req, Response $res): void
    {
        App::requireAuth();
        $this->ensureAdminCanManageExistingLinks($res);

        $linkModel = new Link();
        $page      = max(1, (int)$req->get('page', 1));
        $perPage   = 20;

        $links = $linkModel->all($page, $perPage);
        $total = $linkModel->countAll();

        $totalPages = (int)ceil($total / $perPage);

        $html = View::renderWithLayout('admin/links/index', [
            'title'      => 'My Links',
            'links'      => $links,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
        $res->html($html);
    }

    public function create(Request $req, Response $res): void
    {
        App::requireAuth();
        $html = View::renderWithLayout('admin/links/create', ['title' => 'Create Link']);
        $res->html($html);
    }

    public function store(Request $req, Response $res): void
    {
        App::requireAuth();

        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/admin/links/create');
        }

        // Rate limit
        $rateLimiter = new RateLimiter();
        $rateLimit   = (int)Config::get('security.rate_limit_create', 10);
        if (!$rateLimiter->check('create_link', $req->ip(), $rateLimit, 3600)) {
            Session::flash('error', 'Rate limit exceeded. Please wait before creating more links.');
            $res->redirect('/admin/links/create');
        }

        $originalUrl  = trim((string)$req->post('original_url', ''));
        $customCode   = trim((string)$req->post('custom_code', ''));
        $title        = trim((string)$req->post('title', ''));
        $redirectType = (int)$req->post('redirect_type', 302);
        $expiresAt    = trim((string)$req->post('expires_at', ''));
        $maxClicks    = $req->post('max_clicks') !== null && $req->post('max_clicks') !== ''
            ? (int)$req->post('max_clicks')
            : null;

        // Validate URL
        $validator = new UrlValidator();
        $result    = $validator->validate($originalUrl, Config::all()['security'] ?? []);
        if ($result !== true) {
            Session::flash('error', $result);
            $res->redirect('/admin/links/create');
        }

        $originalUrl = $validator->normalize($originalUrl);

        // Resolve short code
        $slugGenerator = new SlugGenerator();
        $linkModel     = new Link();

        if (!empty($customCode)) {
            if (!$slugGenerator->isValid($customCode)) {
                Session::flash('error', 'Custom alias must be 3-64 alphanumeric characters (hyphens/underscores allowed).');
                $res->redirect('/admin/links/create');
            }
            if ($slugGenerator->isReserved($customCode)) {
                Session::flash('error', 'That alias is reserved.');
                $res->redirect('/admin/links/create');
            }
            if ($linkModel->codeExists($customCode)) {
                Session::flash('error', 'That alias is already taken.');
                $res->redirect('/admin/links/create');
            }
            $shortCode = $customCode;
        } else {
            $shortCode = $slugGenerator->generateUnique($linkModel);
        }

        $data = [
            'owner_id'      => App::currentUserId(),
            'short_code'    => $shortCode,
            'original_url'  => $originalUrl,
            'title'         => $title ?: null,
            'redirect_type' => in_array($redirectType, [301, 302], true) ? $redirectType : 302,
            'is_active'     => 1,
        ];

        if (!empty($expiresAt)) {
            $data['expires_at'] = date('Y-m-d H:i:s', strtotime($expiresAt));
        }
        if ($maxClicks !== null) {
            $data['max_clicks'] = $maxClicks;
        }

        $linkModel->create($data);
        $rateLimiter->increment('create_link', $req->ip());

        $baseUrl = rtrim(Config::get('app.url', ''), '/');
        $shortUrl = "$baseUrl/$shortCode";
        Session::flash('success', "Link created! Short URL: $shortUrl");

        $userEmail = (string)Session::get('user_email', '');
        if ($userEmail !== '') {
            try {
                $mailer = Mailer::fromSettings(new Setting());
                $mailer->send(
                    $userEmail,
                    'Your new shortened URL',
                    "Your shortened URL is ready:\r\n\r\n"
                    . "Short URL: {$shortUrl}\r\n"
                    . "Original URL: {$originalUrl}\r\n"
                );
            } catch (\Throwable $e) {
                Session::flash('error', 'Link created, but confirmation email could not be sent: ' . $e->getMessage());
            }
        }

        $res->redirect('/admin/links');
    }

    public function edit(Request $req, Response $res, array $params): void
    {
        App::requireAuth();
        $this->ensureAdminCanManageExistingLinks($res);
        $link = $this->findOwnedLink((int)($params['id'] ?? 0));

        if (!$link) {
            $res->notFound();
        }

        $html = View::renderWithLayout('admin/links/edit', [
            'title' => 'Edit Link',
            'link'  => $link,
        ]);
        $res->html($html);
    }

    public function update(Request $req, Response $res, array $params): void
    {
        App::requireAuth();
        $this->ensureAdminCanManageExistingLinks($res);

        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/admin/links');
        }

        $link = $this->findOwnedLink((int)($params['id'] ?? 0));
        if (!$link) {
            $res->notFound();
        }

        $originalUrl  = trim((string)$req->post('original_url', ''));
        $title        = trim((string)$req->post('title', ''));
        $redirectType = (int)$req->post('redirect_type', 302);
        $expiresAt    = trim((string)$req->post('expires_at', ''));
        $maxClicks    = $req->post('max_clicks') !== null && $req->post('max_clicks') !== ''
            ? (int)$req->post('max_clicks')
            : null;
        $isActive     = $req->post('is_active') !== null ? 1 : 0;

        if (!empty($originalUrl)) {
            $validator = new UrlValidator();
            $result    = $validator->validate($originalUrl, Config::all()['security'] ?? []);
            if ($result !== true) {
                Session::flash('error', $result);
                $res->redirect('/admin/links/' . $link['id'] . '/edit');
            }
            $originalUrl = $validator->normalize($originalUrl);
        }

        $linkModel = new Link();
        $data      = [
            'original_url'  => $originalUrl ?: $link['original_url'],
            'title'         => $title ?: null,
            'redirect_type' => in_array($redirectType, [301, 302], true) ? $redirectType : 302,
            'is_active'     => $isActive,
            'expires_at'    => !empty($expiresAt) ? date('Y-m-d H:i:s', strtotime($expiresAt)) : null,
            'max_clicks'    => $maxClicks,
        ];

        $linkModel->update((int)$link['id'], $data);
        Session::flash('success', 'Link updated successfully.');
        $res->redirect('/admin/links');
    }

    public function delete(Request $req, Response $res, array $params): void
    {
        App::requireAuth();
        $this->ensureAdminCanManageExistingLinks($res);

        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            Session::flash('error', 'Invalid CSRF token.');
            $res->redirect('/admin/links');
        }

        $link = $this->findOwnedLink((int)($params['id'] ?? 0));
        if (!$link) {
            $res->notFound();
        }

        $linkModel = new Link();
        $linkModel->delete((int)$link['id']);

        Session::flash('success', 'Link deleted.');
        $res->redirect('/admin/links');
    }

    public function toggle(Request $req, Response $res, array $params): void
    {
        App::requireAuth();
        $this->ensureAdminCanManageExistingLinks($res);

        if (!Csrf::verify((string)$req->post('_csrf_token'))) {
            $res->redirect('/admin/links');
        }

        $link = $this->findOwnedLink((int)($params['id'] ?? 0));
        if (!$link) {
            $res->notFound();
        }

        $linkModel = new Link();
        $linkModel->update((int)$link['id'], ['is_active' => $link['is_active'] ? 0 : 1]);

        Session::flash('success', 'Link status toggled.');
        $res->redirect('/admin/links');
    }

    public function analytics(Request $req, Response $res, array $params): void
    {
        App::requireAuth();
        $this->ensureAdminCanManageExistingLinks($res);

        $link = $this->findOwnedLink((int)($params['id'] ?? 0));
        if (!$link) {
            $res->notFound();
        }

        $clickModel  = new ClickEvent();
        $totalClicks = $clickModel->countByLink((int)$link['id']);
        $recentClicks = $clickModel->recentByLink((int)$link['id'], 50);
        $referrers   = $clickModel->referrerSummary((int)$link['id']);
        $daily       = $clickModel->dailyCountsByLink((int)$link['id'], 30);

        $html = View::renderWithLayout('admin/analytics', [
            'title'       => 'Analytics: ' . $link['short_code'],
            'link'        => $link,
            'totalClicks' => $totalClicks,
            'recentClicks' => $recentClicks,
            'referrers'   => $referrers,
            'daily'       => $daily,
        ]);
        $res->html($html);
    }

    private function findOwnedLink(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $linkModel = new Link();
        $link      = $linkModel->findById($id);

        if (!$link) {
            return null;
        }

        // Admin can access all links
        if (App::isAdmin()) {
            return $link;
        }

        // User can only access their own links
        if ((int)$link['owner_id'] === App::currentUserId()) {
            return $link;
        }

        return null;
    }

    private function ensureAdminCanManageExistingLinks(Response $res): void
    {
        if (!App::isAdmin()) {
            Session::flash('error', 'Access denied: Only administrators can manage existing links. You can create new links instead.');
            $res->redirect('/admin/links/create');
        }
    }
}
