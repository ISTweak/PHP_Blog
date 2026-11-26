<?php
/**
 * アプリケーションの初期化。public/index.php から読み込まれます。
 */
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('INC', __DIR__);
define('DATA', __DIR__ . '/data');   // DB・バックアップ・アップロード・セッション・ログ

if (PHP_VERSION_ID < 80300) {
    http_response_code(500);
    exit('PHP 8.3 以上が必要です。');
}

// 必須の PHP 拡張が有効か確認（足りなければ、どの php.ini を直せばよいか表示する）
$missing = array_values(array_filter(
    ['pdo_sqlite', 'mbstring', 'fileinfo'],
    static fn(string $ext): bool => !extension_loaded($ext)
));
if ($missing) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("PHP の拡張機能が有効になっていません: " . implode(', ', $missing) . "\n"
        . "読み込まれている php.ini: " . (php_ini_loaded_file() ?: '（なし）') . "\n"
        . "php.ini で extension=" . implode(' / extension=', $missing) . " を有効にし、Apache を再起動してください。\n");
}

$configFile = INC . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('include/config.php が見つかりません。include/config.sample.php をコピーして作成してください。');
}
$config = require $configFile;

date_default_timezone_set((string) ($config['timezone'] ?? 'Asia/Tokyo'));
mb_internal_encoding('UTF-8');

spl_autoload_register(static function (string $class): void {
    foreach (['lib', 'controllers'] as $dir) {
        $file = INC . '/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require INC . '/lib/helpers.php';

App::init($config);
start_session();
