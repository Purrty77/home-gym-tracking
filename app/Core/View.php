<?php

namespace App\Core;

use RuntimeException;

final class View
{
    public static function render(string $name, array $data = []): void
    {
        $file = dirname(__DIR__, 2) . '/resources/views/' . $name . '.php';
        if (!is_file($file)) throw new RuntimeException("Vue introuvable : {$name}");
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        $content = ob_get_clean();
        require dirname(__DIR__, 2) . '/resources/views/layouts/app.php';
        unset($_SESSION['_old']);
    }
}

