<?php
declare(strict_types=1);

/**
 * シンプルなルーター。パターン中の {name} が名前付き引数としてハンドラに渡されます。
 * POST は自動で CSRF トークンを検証します。
 */
final class Router
{
    /** @var array<int, array{0:string,1:string,2:array{0:class-string,1:string}}> */
    private array $routes = [];

    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, array $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#u';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $methodMismatch = false;
        foreach ($this->routes as [$m, $regex, $handler]) {
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            if ($m !== $method) {
                $methodMismatch = true;
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            if ($method === 'POST') {
                verify_csrf();
            }
            [$class, $action] = $handler;
            call_user_func_array([new $class(), $action], $params);
            return;
        }
        abort($methodMismatch ? 405 : 404);
    }
}
