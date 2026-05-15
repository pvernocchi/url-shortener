<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Simple view renderer. Templates live in src/Views/.
 * Call View::render('subdir/template', $data) to get HTML string.
 */
class View
{
    private static string $viewsPath = '';

    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = $path;
    }

    private static function getViewsPath(): string
    {
        if (self::$viewsPath === '') {
            return ROOT_PATH . '/src/Views';
        }
        return self::$viewsPath;
    }

    public static function render(string $template, array $data = []): string
    {
        $templateFile = self::getViewsPath() . '/' . $template . '.php';
        if (!file_exists($templateFile)) {
            throw new \RuntimeException("View template not found: $templateFile");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $templateFile;
        return ob_get_clean() ?: '';
    }

    public static function renderWithLayout(
        string $template,
        array  $data = [],
        string $layout = 'layout/base'
    ): string {
        $content = self::render($template, $data);
        $title   = $data['title'] ?? 'URL Shortener';
        return self::render($layout, array_merge($data, [
            'content' => $content,
            'title'   => $title,
        ]));
    }
}

/**
 * Global HTML escape helper for use in templates.
 */
if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/**
 * Global asset URL helper.
 */
if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $base = rtrim(\App\Core\Config::get('app.url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

/**
 * Global URL helper.
 */
if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim(\App\Core\Config::get('app.url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}
