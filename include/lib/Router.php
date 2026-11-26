<?php
declare(strict_types=1);

/**
 * シンプルなルーター。パターン中の {name} が名前付き引数としてハンドラに渡されます。
 * POST は自動で CSRF トークンを検証します。
 *
 * ハンドラは [クラス名, メソッド名]（呼ばれたときにインスタンス化）か Closure（プラグイン用）。
 */
final class Router
{
    /** @var list<array{0:string,1:string,2:array|Closure}> */
    private array $routes = [];

    public function get(string $pattern, array|Closure $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array|Closure $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function add(string $method, string $pattern, array|Closure $handler): void
    {
        // {name} 以外の部分は正規表現として解釈されないようにエスケープする
        $parts = preg_split('#(\{\w+\})#', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $regex = '';
        foreach ($parts as $part) {
            $regex .= preg_match('#^\{(\w+)\}$#', $part, $m)
                ? '(?P<' . $m[1] . '>[^/]+)'
                : preg_quote($part, '#');
        }
        $this->routes[] = [strtoupper($method), '#^' . $regex . '$#u', $handler];
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
            if ($handler instanceof Closure) {
                $handler(...$params);
            } else {
                [$class, $action] = $handler;
                call_user_func_array([new $class(), $action], $params);
            }
            return;
        }
        abort($methodMismatch ? 405 : 404);
    }
}
