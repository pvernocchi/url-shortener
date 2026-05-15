<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable|array $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'regex'   => $this->buildRegex($pattern),
            'params'  => $this->extractParamNames($pattern),
        ];
    }

    private function buildRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '#');
        $regex   = preg_replace('#\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\}#', '([^/]+)', $escaped);
        return '#^' . $regex . '$#';
    }

    private function extractParamNames(string $pattern): array
    {
        preg_match_all('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', $pattern, $matches);
        return $matches[1];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $path   = rtrim($request->getPath(), '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $routePath = rtrim($route['pattern'], '/') ?: '/';
            $regex = $this->buildRegex($routePath);

            if (!preg_match($regex, $path, $matches)) {
                continue;
            }

            // Build params array
            $params = [];
            array_shift($matches); // remove full match
            foreach ($route['params'] as $i => $name) {
                $params[$name] = $matches[$i] ?? '';
            }

            $this->callHandler($route['handler'], $request, new Response(), $params);
            return;
        }

        // No route matched
        $response = new Response();
        $response->notFound();
    }

    private function callHandler(callable|array $handler, Request $request, Response $response, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();
            $controller->$method($request, $response, $params);
        } else {
            $handler($request, $response, $params);
        }
    }
}
