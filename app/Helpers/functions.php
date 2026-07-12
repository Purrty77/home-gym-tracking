<?php

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function route(string $name,array $parameters=[]): string
{
    return \App\Core\Router::urlFor($name,$parameters);
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(\App\Core\Csrf::token()) . '">';
}

function flash(string $key): ?string
{
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}
