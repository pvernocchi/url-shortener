<?php
declare(strict_types=1);

namespace App\Core;

class Request
{
    private array $server;
    private array $get;
    private array $post;
    private array $files;

    public function __construct(
        array $server = [],
        array $get = [],
        array $post = [],
        array $files = []
    ) {
        $this->server = $server ?: $_SERVER;
        $this->get    = $get ?: $_GET;
        $this->post   = $post ?: $_POST;
        $this->files  = $files ?: $_FILES;
    }

    public function getMethod(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function getPath(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return $path !== false && $path !== null ? $path : '/';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function getAll(): array
    {
        return $this->get;
    }

    public function postAll(): array
    {
        return $this->post;
    }

    public function files(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    public function isDelete(): bool
    {
        return $this->getMethod() === 'DELETE';
    }

    public function ip(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($this->server[$key])) {
                $ip = trim(explode(',', $this->server[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }

    public function referer(): string
    {
        return $this->server['HTTP_REFERER'] ?? '';
    }

    public function isJson(): bool
    {
        $contentType = $this->server['CONTENT_TYPE'] ?? '';
        return str_contains($contentType, 'application/json');
    }

    public function json(): array
    {
        $body = file_get_contents('php://input');
        if (empty($body)) {
            return [];
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }
}
