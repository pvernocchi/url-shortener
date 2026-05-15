<?php
declare(strict_types=1);

namespace App\Core;

class Response
{
    public function redirect(string $url, int $code = 302): never
    {
        http_response_code($code);
        header('Location: ' . $url);
        exit;
    }

    public function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function html(string $content, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }

    public function notFound(): never
    {
        $content = View::render('errors/404', ['title' => '404 Not Found']);
        $this->html($content, 404);
    }

    public function gone(): never
    {
        $content = View::render('errors/410', ['title' => 'Link Expired']);
        $this->html($content, 410);
    }

    public function download(string $content, string $filename, string $mimeType = 'text/plain'): never
    {
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
}
