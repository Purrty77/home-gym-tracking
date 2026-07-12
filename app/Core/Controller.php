<?php

namespace App\Core;

abstract class Controller
{
    protected function view(string $name, array $data = []): void
    {
        View::render($name, $data);
    }

    protected function redirect(string $path, ?string $message = null): never
    {
        if ($message) $_SESSION['_flash']['success'] = $message;
        header('Location: ' . url($path), true, 303);
        exit;
    }
}

