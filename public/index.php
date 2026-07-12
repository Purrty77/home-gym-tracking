<?php

declare(strict_types=1);

use App\Core\Router;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/app.php';
date_default_timezone_set($config['timezone']);
set_exception_handler(function (Throwable $exception) use ($config): void {
    $line=sprintf("[%s] %s in %s:%d\n%s\n",date('c'),$exception->getMessage(),$exception->getFile(),$exception->getLine(),$exception->getTraceAsString());
    error_log($line,3,dirname(__DIR__).'/storage/logs/app.log');
    http_response_code(500);
    if($config['debug']){echo '<pre>'.htmlspecialchars($line,ENT_QUOTES,'UTF-8').'</pre>';return;}
    \App\Core\View::render('errors/500');
});
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

$router = new Router();
require dirname(__DIR__) . '/routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
