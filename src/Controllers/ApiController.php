<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Models\ApiToken;
use App\Models\Link;
use App\Services\SlugGenerator;
use App\Services\UrlValidator;

class ApiController
{
    private function authenticate(Request $req, Response $res, string $requiredScope = 'read'): array
    {
        if (!Config::get('api.enabled', true)) {
            $res->json(['error' => 'API is disabled.'], 403);
        }

        $authHeader = $req->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            $res->json(['error' => 'Missing or invalid Authorization header.'], 401);
        }

        $rawToken  = substr($authHeader, 7);
        $tokenModel = new ApiToken();
        $token     = $tokenModel->findByToken($rawToken);

        if (!$token) {
            $res->json(['error' => 'Invalid or revoked token.'], 401);
        }

        $scopes = explode(',', $token['scopes'] ?? '');
        if (!in_array($requiredScope, $scopes, true)) {
            $res->json(['error' => 'Insufficient scope.'], 403);
        }

        return $token;
    }

    public function listLinks(Request $req, Response $res): void
    {
        $token     = $this->authenticate($req, $res, 'read');
        $linkModel = new Link();
        $page      = max(1, (int)$req->get('page', 1));
        $links     = $linkModel->allByOwner((int)$token['user_id'], $page, 20);
        $total     = $linkModel->countByOwner((int)$token['user_id']);

        $res->json([
            'data'  => $links,
            'meta'  => [
                'page'  => $page,
                'total' => $total,
            ],
        ]);
    }

    public function createLink(Request $req, Response $res): void
    {
        $token = $this->authenticate($req, $res, 'write');

        $input = $req->json();
        $url   = trim((string)($input['url'] ?? ''));

        if (empty($url)) {
            $res->json(['error' => 'url is required.'], 422);
        }

        $validator = new UrlValidator();
        $result    = $validator->validate($url, Config::all()['security'] ?? []);
        if ($result !== true) {
            $res->json(['error' => $result], 422);
        }

        $url       = $validator->normalize($url);
        $linkModel = new Link();
        $slugGen   = new SlugGenerator();

        $customCode = trim((string)($input['code'] ?? ''));
        if (!empty($customCode)) {
            if (!$slugGen->isValid($customCode) || $slugGen->isReserved($customCode)) {
                $res->json(['error' => 'Invalid or reserved code.'], 422);
            }
            if ($linkModel->codeExists($customCode)) {
                $res->json(['error' => 'Code already taken.'], 409);
            }
            $shortCode = $customCode;
        } else {
            $shortCode = $slugGen->generateUnique($linkModel);
        }

        $redirectType = (int)($input['redirect_type'] ?? 302);
        if (!in_array($redirectType, [301, 302], true)) {
            $redirectType = 302;
        }

        $data = [
            'owner_id'      => (int)$token['user_id'],
            'short_code'    => $shortCode,
            'original_url'  => $url,
            'title'         => isset($input['title']) ? substr((string)$input['title'], 0, 255) : null,
            'redirect_type' => $redirectType,
            'is_active'     => 1,
        ];

        if (!empty($input['expires_at'])) {
            $data['expires_at'] = date('Y-m-d H:i:s', strtotime((string)$input['expires_at']));
        }

        $id   = $linkModel->create($data);
        $link = $linkModel->findById($id);

        $res->json(['data' => $link], 201);
    }

    public function getLink(Request $req, Response $res, array $params): void
    {
        $token = $this->authenticate($req, $res, 'read');
        $code  = $params['code'] ?? '';

        $linkModel = new Link();
        $link      = $linkModel->findByCode($code);

        if (!$link) {
            $res->json(['error' => 'Link not found.'], 404);
        }

        // Only owner or admin can see it
        if ((int)$link['owner_id'] !== (int)$token['user_id']) {
            $res->json(['error' => 'Not authorized.'], 403);
        }

        $res->json(['data' => $link]);
    }

    public function deleteLink(Request $req, Response $res, array $params): void
    {
        $token = $this->authenticate($req, $res, 'write');
        $id    = (int)($params['id'] ?? 0);

        $linkModel = new Link();
        $link      = $linkModel->findById($id);

        if (!$link) {
            $res->json(['error' => 'Link not found.'], 404);
        }

        if ((int)$link['owner_id'] !== (int)$token['user_id']) {
            $res->json(['error' => 'Not authorized.'], 403);
        }

        $linkModel->delete($id);
        $res->json(['message' => 'Link deleted.']);
    }
}
