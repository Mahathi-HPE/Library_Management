<?php
declare(strict_types=1);

session_start();

spl_autoload_register(function (string $class): void {
    $paths = [
        __DIR__ . '/core/' . $class . '.php',
        __DIR__ . '/controllers/' . $class . '.php',
        __DIR__ . '/models/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$controllerName = ucfirst(strtolower($_GET['c'] ?? 'auth')) . 'Controller';
$action = $_GET['a'] ?? 'login';

if (!class_exists($controllerName) || !method_exists($controllerName, $action)) {
    http_response_code(404);
    die('Page not found.');
}

(new $controllerName())->$action();
